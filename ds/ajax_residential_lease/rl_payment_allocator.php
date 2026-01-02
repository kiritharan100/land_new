<?php
/**
 * Payment Allocator for Residential Lease (RL)
 * Handles payment allocation across rl_lease_schedules
 */

declare(strict_types=1);

const RL_PAYMENT_EPSILON = 0.005;

/**
 * Fetch discount rate from rl_lease table
 */
function fetchRLLeaseDiscountRate(mysqli $con, int $leaseId): float
{
    $sql = "SELECT discount_rate FROM rl_lease WHERE rl_lease_id = ? LIMIT 1";
    if ($stmt = $con->prepare($sql)) {
        $stmt->bind_param('i', $leaseId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['discount_rate'])) {
            return max(0.0, floatval($row['discount_rate']) / 100.0);
        }
    }
    return 0.0;
}

/**
 * Load all schedules for a residential lease
 */
function loadRLLeaseSchedulesForPayment(mysqli $con, int $leaseId): array
{
    $sql = "SELECT schedule_id, lease_id, schedule_year, start_date, end_date,
                   annual_amount, panalty, panalty_paid, premium, premium_paid,
                   paid_rent, discount_apply
            FROM rl_lease_schedules
            WHERE lease_id = ?  
            ORDER BY start_date ASC, schedule_id ASC";

    if (!$stmt = $con->prepare($sql)) {
        throw new RuntimeException('Failed to prepare RL schedule query');
    }

    $stmt->bind_param('i', $leaseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    return $schedules;
}

/**
 * Determine which schedule corresponds to a payment date
 */
function determineRLCurrentScheduleId(array $schedules, int $paymentTs): ?int
{
    foreach ($schedules as $schedule) {
        $startTs = $schedule['start_date'] ? strtotime($schedule['start_date']) : false;
        $endTs = $schedule['end_date'] ? strtotime($schedule['end_date']) : false;
        if ($startTs !== false && $endTs !== false && $paymentTs >= $startTs && $paymentTs <= $endTs) {
            return intval($schedule['schedule_id']);
        }
    }

    foreach ($schedules as $schedule) {
        $startTs = $schedule['start_date'] ? strtotime($schedule['start_date']) : false;
        if ($startTs !== false && $paymentTs < $startTs) {
            return intval($schedule['schedule_id']);
        }
    }

    if (!empty($schedules)) {
        $last = end($schedules);
        return intval($last['schedule_id']);
    }

    return null;
}

/**
 * Allocate a payment across RL schedules
 * Priority: Penalty -> Premium -> Rent (with discount rules)
 */
function allocateRLLeasePayment(array $schedules, string $paymentDate, float $amount, float $discountRate): array
{
    $paymentTs = strtotime($paymentDate);
    if ($paymentTs === false) {
        throw new InvalidArgumentException('Invalid payment date');
    }

    $updatedSchedules = [];
    foreach ($schedules as $schedule) {
        $updatedSchedules[] = [
            'schedule_id'        => intval($schedule['schedule_id']),
            'start_date'         => $schedule['start_date'],
            'end_date'           => $schedule['end_date'],
            'start_ts'           => $schedule['start_date'] ? strtotime($schedule['start_date']) : null,
            'annual_amount'      => floatval($schedule['annual_amount'] ?? 0),
            'panalty'            => floatval($schedule['panalty'] ?? 0),
            'panalty_paid'       => floatval($schedule['panalty_paid'] ?? 0),
            'premium'            => floatval($schedule['premium'] ?? 0),
            'premium_paid'       => floatval($schedule['premium_paid'] ?? 0),
            'paid_rent'          => floatval($schedule['paid_rent'] ?? 0),
            'discount_apply'     => floatval($schedule['discount_apply'] ?? 0),
        ];
    }

    $currentScheduleId = determineRLCurrentScheduleId($updatedSchedules, $paymentTs);
    if ($currentScheduleId === null) {
        throw new RuntimeException('No schedule found for payment');
    }

    $remaining = $amount;
    $allocations = [];
    $totals = [
        'rent' => 0.0,
        'penalty' => 0.0,
        'premium' => 0.0,
        'discount' => 0.0,
        'current_year_payment' => 0.0,
    ];

    foreach ($updatedSchedules as &$schedule) {
        $dueTs = $schedule['start_ts'];
        $penaltyAllowed = ($dueTs !== null) && ($paymentTs > $dueTs);
        $schedule['pen_out'] = $penaltyAllowed
            ? max(0.0, $schedule['panalty'] - $schedule['panalty_paid'])
            : 0.0;

        $schedule['prem_out'] = max(0.0, $schedule['premium'] - $schedule['premium_paid']);
        $schedule['rent_out'] = max(0.0, $schedule['annual_amount'] - $schedule['paid_rent'] - $schedule['discount_apply']);
        $schedule['discount_cap'] = max(0.0, $schedule['annual_amount'] * $discountRate);
        $schedule['discount_remaining'] = max(0.0, $schedule['discount_cap'] - $schedule['discount_apply']);
        $schedule['within_window'] = $schedule['start_ts'] !== null && $paymentTs <= ($schedule['start_ts'] + 30 * 86400);
    }
    unset($schedule);

    // Phase 1: clear penalties across all schedules (global priority)
    foreach ($updatedSchedules as &$schedule) {
        if ($remaining <= RL_PAYMENT_EPSILON || $schedule['pen_out'] <= RL_PAYMENT_EPSILON) {
            continue;
        }
        $sid = $schedule['schedule_id'];
        if (!isset($allocations[$sid])) {
            $allocations[$sid] = [
                'rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0,
                'discount' => 0.0, 'current_year_payment' => 0.0, 'total_paid' => 0.0,
            ];
        }
        $pay = min($remaining, $schedule['pen_out']);
        $schedule['pen_out'] -= $pay;
        $schedule['panalty_paid'] += $pay;
        $allocations[$sid]['penalty'] += $pay;
        $allocations[$sid]['total_paid'] += $pay;
        $totals['penalty'] += $pay;
        $remaining -= $pay;
    }
    unset($schedule);

    // Phase 2: clear premiums across all schedules (after penalties)
    foreach ($updatedSchedules as &$schedule) {
        if ($remaining <= RL_PAYMENT_EPSILON || $schedule['prem_out'] <= RL_PAYMENT_EPSILON) {
            continue;
        }
        $sid = $schedule['schedule_id'];
        if (!isset($allocations[$sid])) {
            $allocations[$sid] = [
                'rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0,
                'discount' => 0.0, 'current_year_payment' => 0.0, 'total_paid' => 0.0,
            ];
        }
        $pay = min($remaining, $schedule['prem_out']);
        $schedule['prem_out'] -= $pay;
        $schedule['premium_paid'] += $pay;
        $allocations[$sid]['premium'] += $pay;
        $allocations[$sid]['total_paid'] += $pay;
        $totals['premium'] += $pay;
        $remaining -= $pay;
    }
    unset($schedule);

    // Phase 3: settle rent (with discount rules)
    $priorOutstanding = 0.0;
    foreach ($updatedSchedules as &$schedule) {
        if ($remaining <= RL_PAYMENT_EPSILON) {
            $priorOutstanding += $schedule['pen_out'] + $schedule['prem_out'] + $schedule['rent_out'];
            continue;
        }
        if ($schedule['pen_out'] > RL_PAYMENT_EPSILON || $schedule['prem_out'] > RL_PAYMENT_EPSILON) {
            $priorOutstanding += $schedule['pen_out'] + $schedule['prem_out'] + $schedule['rent_out'];
            continue;
        }

        $sid = $schedule['schedule_id'];
        if (!isset($allocations[$sid])) {
            $allocations[$sid] = [
                'rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0,
                'discount' => 0.0, 'current_year_payment' => 0.0, 'total_paid' => 0.0,
            ];
        }
        $alloc = $allocations[$sid];
        $noOutstandingBefore = ($priorOutstanding <= RL_PAYMENT_EPSILON);
        $rentOutstandingAtStart = $schedule['rent_out'];

        $canApplyDiscount = $discountRate > 0.0
            && $rentOutstandingAtStart > RL_PAYMENT_EPSILON
            && $schedule['discount_remaining'] > RL_PAYMENT_EPSILON
            && $schedule['within_window']
            && $noOutstandingBefore
            && $schedule['pen_out'] <= RL_PAYMENT_EPSILON
            && $schedule['prem_out'] <= RL_PAYMENT_EPSILON
            && $schedule['discount_apply'] <= RL_PAYMENT_EPSILON;

        $discountAttempted = false;

        while ($remaining > RL_PAYMENT_EPSILON && $schedule['rent_out'] > RL_PAYMENT_EPSILON) {
            if (!$discountAttempted && $canApplyDiscount) {
                $discountAttempted = true;

                $maxAllowedDiscount = min($schedule['discount_remaining'], $rentOutstandingAtStart * $discountRate);
                $paymentNeededForFull = $rentOutstandingAtStart - $maxAllowedDiscount;
                $minPayWithRate = $rentOutstandingAtStart * (1.0 - $discountRate);

                if ($maxAllowedDiscount <= RL_PAYMENT_EPSILON || $paymentNeededForFull <= RL_PAYMENT_EPSILON) {
                    $canApplyDiscount = false;
                    continue;
                }

                if ($remaining + RL_PAYMENT_EPSILON < $minPayWithRate || $remaining + RL_PAYMENT_EPSILON < $paymentNeededForFull) {
                    $canApplyDiscount = false;
                    continue;
                }

                $outstandingRounded = round($rentOutstandingAtStart, 2);
                $payRounded = round($paymentNeededForFull, 2);
                $discountRounded = round($maxAllowedDiscount, 2);
                if (round($payRounded + $discountRounded, 2) !== $outstandingRounded) {
                    $canApplyDiscount = false;
                    continue;
                }

                $paymentPortion = $paymentNeededForFull;
                if ($paymentPortion > $remaining) {
                    $paymentPortion = $remaining;
                }

                if ($paymentPortion <= RL_PAYMENT_EPSILON) {
                    $canApplyDiscount = false;
                    continue;
                }

                $remaining -= $paymentPortion;
                $schedule['rent_out'] -= ($paymentPortion + $maxAllowedDiscount);
                if ($schedule['rent_out'] < 0.0) {
                    $schedule['rent_out'] = 0.0;
                }
                $schedule['paid_rent'] += $paymentPortion;
                $schedule['discount_apply'] += $maxAllowedDiscount;
                $schedule['discount_remaining'] = max(0.0, $schedule['discount_remaining'] - $maxAllowedDiscount);

                $alloc['rent'] += $paymentPortion;
                $alloc['discount'] += $maxAllowedDiscount;
                $alloc['total_paid'] += $paymentPortion;
                $totals['rent'] += $paymentPortion;
                $totals['discount'] += $maxAllowedDiscount;

                if ($sid === $currentScheduleId) {
                    $alloc['current_year_payment'] += $paymentPortion;
                    $totals['current_year_payment'] += $paymentPortion;
                }

                break;
            }

            $pay = min($remaining, $schedule['rent_out']);
            if ($pay <= RL_PAYMENT_EPSILON) {
                break;
            }

            $remaining -= $pay;
            $schedule['rent_out'] -= $pay;
            if ($schedule['rent_out'] < 0.0) {
                $schedule['rent_out'] = 0.0;
            }
            $schedule['paid_rent'] += $pay;

            $alloc['rent'] += $pay;
            $alloc['total_paid'] += $pay;
            $totals['rent'] += $pay;

            if ($sid === $currentScheduleId) {
                $alloc['current_year_payment'] += $pay;
                $totals['current_year_payment'] += $pay;
            }

            $canApplyDiscount = false;
            $discountAttempted = true;
        }

        $allocations[$sid] = $alloc;
        $priorOutstanding = $schedule['pen_out'] + $schedule['prem_out'] + $schedule['rent_out'];
    }
    unset($schedule);

    foreach ($allocations as $sid => $alloc) {
        $allocations[$sid] = [
            'rent' => round($alloc['rent'], 2),
            'penalty' => round($alloc['penalty'], 2),
            'premium' => round($alloc['premium'], 2),
            'discount' => round($alloc['discount'], 2),
            'current_year_payment' => round($alloc['current_year_payment'], 2),
            'total_paid' => round($alloc['total_paid'], 2),
        ];
    }

    $totals = [
        'rent' => round($totals['rent'], 2),
        'penalty' => round($totals['penalty'], 2),
        'premium' => round($totals['premium'], 2),
        'discount' => round($totals['discount'], 2),
        'current_year_payment' => round($totals['current_year_payment'], 2),
    ];

    return [
        'allocations' => $allocations,
        'totals' => $totals,
        'schedules' => $updatedSchedules,
        'current_schedule_id' => $currentScheduleId,
        'remaining' => $remaining,
    ];
}

/**
 * Reapply all ACTIVE payments on existing RL schedules (no regeneration).
 * Resets schedule totals and replays payments in chronological order.
 */
function reapplyRLPaymentsOnExistingSchedules(mysqli $con, int $lease_id): bool
{
    // Load active payments in order
    $payments = [];
    if ($st = mysqli_prepare(
        $con,
        "SELECT * FROM rl_lease_payments 
         WHERE lease_id=? AND status=1 
         ORDER BY payment_date ASC, payment_id ASC"
    )){
        mysqli_stmt_bind_param($st, 'i', $lease_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($row = mysqli_fetch_assoc($rs)) {
            $payments[] = $row;
        }
        mysqli_stmt_close($st);
    }

    $con->begin_transaction();

    // Reset paid/discount totals on schedules before replay (ALWAYS - even if no payments)
    if ($stReset = mysqli_prepare($con, "UPDATE rl_lease_schedules SET paid_rent=0, panalty_paid=0, premium_paid=0, total_paid=0, discount_apply=0 WHERE lease_id=?")) {
        mysqli_stmt_bind_param($stReset, 'i', $lease_id);
        if (!mysqli_stmt_execute($stReset)) {
            $con->rollback();
            mysqli_stmt_close($stReset);
            return false;
        }
        mysqli_stmt_close($stReset);
    } else {
        $con->rollback();
        return false;
    }

    // If no active payments remain, commit the reset and return success
    if (empty($payments)) {
        $con->commit();
        return true;
    }

    // Reset payment summaries (use 0 for schedule_id since it's NOT NULL in rl_lease_payments)
    if ($stResetPay = mysqli_prepare($con, "UPDATE rl_lease_payments SET schedule_id=0, rent_paid=0, panalty_paid=0, premium_paid=0, discount_apply=0, current_year_payment=0 WHERE lease_id=? AND status=1")) {
        mysqli_stmt_bind_param($stResetPay, 'i', $lease_id);
        if (!mysqli_stmt_execute($stResetPay)) {
            $con->rollback();
            mysqli_stmt_close($stResetPay);
            return false;
        }
        mysqli_stmt_close($stResetPay);
    } else {
        $con->rollback();
        return false;
    }

    // Clear all payment details for this lease before replay
    if ($stDelDetailAll = mysqli_prepare($con, "DELETE FROM rl_lease_payments_detail WHERE payment_id IN (SELECT payment_id FROM rl_lease_payments WHERE lease_id=? )")) {
        mysqli_stmt_bind_param($stDelDetailAll, 'i', $lease_id);
        if (!mysqli_stmt_execute($stDelDetailAll)) {
            $con->rollback();
            mysqli_stmt_close($stDelDetailAll);
            return false;
        }
        mysqli_stmt_close($stDelDetailAll);
    } else {
        // Table might not exist yet, continue
    }

    $discountRate  = fetchRLLeaseDiscountRate($con, $lease_id);
    $scheduleState = loadRLLeaseSchedulesForPayment($con, $lease_id);

    $updatePaymentSql = "UPDATE rl_lease_payments SET 
            schedule_id=?,
            rent_paid=?,
            panalty_paid=?,
            premium_paid=?,
            discount_apply=?,
            current_year_payment=?,
            payment_type=?
         WHERE payment_id=?";
    $updatePaymentStmt = $con->prepare($updatePaymentSql);
    if (!$updatePaymentStmt) {
        $con->rollback();
        return false;
    }

    $updateScheduleSql = "UPDATE rl_lease_schedules SET 
            paid_rent = paid_rent + ?,
            panalty_paid = panalty_paid + ?,
            premium_paid = premium_paid + ?,
            total_paid = total_paid + ?,
            discount_apply = discount_apply + ?
         WHERE schedule_id = ?";
    $updateScheduleStmt = $con->prepare($updateScheduleSql);
    if (!$updateScheduleStmt) {
        $updatePaymentStmt->close();
        $con->rollback();
        return false;
    }

    foreach ($payments as $pay) {
        $paymentId   = intval($pay['payment_id']);
        $amount      = floatval($pay['amount'] ?? 0);
        $paymentDate = $pay['payment_date'];

        if ($amount <= 0) {
            continue;
        }

        $allocation        = allocateRLLeasePayment($scheduleState, $paymentDate, $amount, $discountRate);
        $allocations       = $allocation['allocations'];
        $totals            = $allocation['totals'];
        $currentScheduleId = $allocation['current_schedule_id'];
        $remainingAfter    = $allocation['remaining'];

        if ($remainingAfter > 0.01) {
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            $con->rollback();
            return false;
        }

        if (empty($allocations)) {
            $scheduleState = $allocation['schedules'];
            continue;
        }

        $totalActual = $totals['rent'] + $totals['penalty'] + $totals['premium'];
        if (abs($totalActual - $amount) > 0.01) {
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            $con->rollback();
            return false;
        }

        $paymentType    = 'mixed';
        $newRent        = $totals['rent'];
        $newPenalty     = $totals['penalty'];
        $newPremium     = $totals['premium'];
        $newDiscount    = $totals['discount'];
        $newCurrentYear = $totals['current_year_payment'];

        $updatePaymentStmt->bind_param(
            'idddddsi',
            $currentScheduleId,
            $newRent,
            $newPenalty,
            $newPremium,
            $newDiscount,
            $newCurrentYear,
            $paymentType,
            $paymentId
        );
        if (!$updatePaymentStmt->execute()) {
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            $con->rollback();
            return false;
        }

        foreach ($allocations as $sid => $alloc) {
            $scheduleId        = intval($sid);
            $rentInc           = $alloc['rent'];
            $penInc            = $alloc['penalty'];
            $premInc           = $alloc['premium'];
            $discInc           = $alloc['discount'];
            $totalPaidSchedule = $alloc['total_paid'];

            $updateScheduleStmt->bind_param(
                'dddddi',
                $rentInc,
                $penInc,
                $premInc,
                $totalPaidSchedule,
                $discInc,
                $scheduleId
            );
            if (!$updateScheduleStmt->execute()) {
                $updateScheduleStmt->close();
                $updatePaymentStmt->close();
                $con->rollback();
                return false;
            }
        }

        $scheduleState = $allocation['schedules'];
    }

    $updateScheduleStmt->close();
    $updatePaymentStmt->close();

    $con->commit();

    return true;
}

