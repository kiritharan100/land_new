<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__) . '/payment_allocator.php';

set_time_limit(0);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "Starting lease payment detail backfill...\n";

define('BACKFILL_EPSILON', 0.01);

function fetchActiveLeaseIds(mysqli $con): array
{
    $ids = [];
    $sql = "SELECT DISTINCT lease_id FROM lease_payments WHERE status = 1 ORDER BY lease_id";
    if ($res = mysqli_query($con, $sql)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $ids[] = (int)$row['lease_id'];
        }
        mysqli_free_result($res);
    }
    return $ids;
}

function reapplyLeasePayments(mysqli $con, int $leaseId, float $discountRate): bool
{
    $payments = [];
    if ($stmt = $con->prepare(
        "SELECT payment_id, amount, payment_date FROM lease_payments WHERE lease_id = ? AND status = 1 ORDER BY payment_date ASC, payment_id ASC"
    )) {
        $stmt->bind_param('i', $leaseId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();
    } else {
        return false;
    }

    if (empty($payments)) {
        return true;
    }

    $scheduleState = loadLeaseSchedulesForPayment($con, $leaseId);

    $updatePaymentSql = "UPDATE lease_payments SET 
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
        return false;
    }

    $updateScheduleSql = "UPDATE lease_schedules SET 
            paid_rent = paid_rent + ?,
            panalty_paid = panalty_paid + ?,
            premium_paid = premium_paid + ?,
            total_paid = total_paid + ?,
            discount_apply = discount_apply + ?
         WHERE schedule_id = ?";
    $updateScheduleStmt = $con->prepare($updateScheduleSql);
    if (!$updateScheduleStmt) {
        $updatePaymentStmt->close();
        return false;
    }

    $insertDetailSql = "INSERT INTO lease_payments_detail (
            payment_id, schedule_id, rent_paid, penalty_paid, premium_paid,
            discount_apply, current_year_payment, status
        ) VALUES (?,?,?,?,?,?,?,?)";
    $insertDetailStmt = $con->prepare($insertDetailSql);
    if (!$insertDetailStmt) {
        $updateScheduleStmt->close();
        $updatePaymentStmt->close();
        return false;
    }

    $deleteDetailStmt = $con->prepare("DELETE FROM lease_payments_detail WHERE payment_id = ?");
    if (!$deleteDetailStmt) {
        $insertDetailStmt->close();
        $updateScheduleStmt->close();
        $updatePaymentStmt->close();
        return false;
    }

    foreach ($payments as $pay) {
        $paymentId = (int)$pay['payment_id'];
        $amount = floatval($pay['amount'] ?? 0);
        $paymentDate = $pay['payment_date'];

        if ($amount <= 0) {
            continue;
        }

        $allocation = allocateLeasePayment($scheduleState, $paymentDate, $amount, $discountRate);
        $allocations = $allocation['allocations'];
        $totals = $allocation['totals'];
        $currentScheduleId = $allocation['current_schedule_id'];
        $remainingAfter = $allocation['remaining'];

        if ($remainingAfter > BACKFILL_EPSILON) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        if (empty($allocations)) {
            $scheduleState = $allocation['schedules'];
            continue;
        }

        $totalActual = $totals['rent'] + $totals['penalty'] + $totals['premium'];
        if (abs($totalActual - $amount) > BACKFILL_EPSILON) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        $paymentType = 'mixed';
        $newRent = $totals['rent'];
        $newPenalty = $totals['penalty'];
        $newPremium = $totals['premium'];
        $newDiscount = $totals['discount'];
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
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        $deleteDetailStmt->bind_param('i', $paymentId);
        $deleteDetailStmt->execute();

        foreach ($allocations as $sid => $alloc) {
            $scheduleId = (int)$sid;
            $rentInc = $alloc['rent'];
            $penInc = $alloc['penalty'];
            $premInc = $alloc['premium'];
            $discInc = $alloc['discount'];
            $curYearInc = $alloc['current_year_payment'];
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
                $deleteDetailStmt->close();
                $insertDetailStmt->close();
                $updateScheduleStmt->close();
                $updatePaymentStmt->close();
                return false;
            }

            $hasDetail = ($rentInc > 0) || ($penInc > 0) || ($premInc > 0) || ($discInc > 0);
            if ($hasDetail) {
                $status = 1;
                $insertDetailStmt->bind_param(
                    'iidddddi',
                    $paymentId,
                    $scheduleId,
                    $rentInc,
                    $penInc,
                    $premInc,
                    $discInc,
                    $curYearInc,
                    $status
                );
                if (!$insertDetailStmt->execute()) {
                    $deleteDetailStmt->close();
                    $insertDetailStmt->close();
                    $updateScheduleStmt->close();
                    $updatePaymentStmt->close();
                    return false;
                }
            }
        }

        $scheduleState = $allocation['schedules'];
    }

    $deleteDetailStmt->close();
    $insertDetailStmt->close();
    $updateScheduleStmt->close();
    $updatePaymentStmt->close();

    return true;
}

$leaseIds = fetchActiveLeaseIds($con);
$totalLeases = count($leaseIds);
$processed = 0;
$failures = [];

echo "Found {$totalLeases} lease(s) with active payments.\n";

foreach ($leaseIds as $leaseId) {
    $processed++;
    echo "[{$processed}/{$totalLeases}] Processing lease {$leaseId}...";

    try {
        $con->begin_transaction();

        $resetSql = "UPDATE lease_schedules SET paid_rent=0, panalty_paid=0, premium_paid=0, total_paid=0, discount_apply=0 WHERE lease_id = ?";
        if ($stmtReset = $con->prepare($resetSql)) {
            $stmtReset->bind_param('i', $leaseId);
            if (!$stmtReset->execute()) {
                throw new Exception('Failed to reset schedules');
            }
            $stmtReset->close();
        } else {
            throw new Exception('Failed to prepare schedule reset');
        }

        if ($stmtDelDetails = $con->prepare("DELETE FROM lease_payments_detail WHERE payment_id IN (SELECT payment_id FROM lease_payments WHERE lease_id = ?)")) {
            $stmtDelDetails->bind_param('i', $leaseId);
            $stmtDelDetails->execute();
            $stmtDelDetails->close();
        }

        $discountRate = fetchLeaseDiscountRate($con, null, $leaseId);
        if (!reapplyLeasePayments($con, $leaseId, $discountRate)) {
            throw new Exception('Failed during payment replay');
        }

        $con->commit();
        echo " done.\n";
    } catch (Exception $e) {
        if ($con->in_transaction) {
            $con->rollback();
        }
        $failures[] = ['lease_id' => $leaseId, 'error' => $e->getMessage()];
        echo " failed: " . $e->getMessage() . "\n";
    }
}

if (empty($failures)) {
    echo "Backfill completed successfully.\n";
} else {
    echo "Backfill completed with ".count($failures)." failure(s):\n";
    foreach ($failures as $failure) {
        echo " - Lease {$failure['lease_id']}: {$failure['error']}\n";
    }
}

?>