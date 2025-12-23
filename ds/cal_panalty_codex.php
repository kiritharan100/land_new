<?php
include '../db.php';
date_default_timezone_set('Asia/Colombo');

$today = date('Y-m-d');
$todayTs = strtotime($today);
$futureLimitDate = date('Y-m-d', strtotime('+365 days'));
$futureLimitTs = strtotime($futureLimitDate);
const PENALTY_EPSILON = 0.01;

$penaltyLogPath = __DIR__ . '/penalty_log.txt';
if (!file_exists($penaltyLogPath)) {
    @touch($penaltyLogPath);
}

function appendPenaltyLog(string $path, array $messages): void
{
    if (empty($messages)) {
        return;
    }

    $content = implode(PHP_EOL, $messages) . PHP_EOL;
    file_put_contents($path, $content, FILE_APPEND);
}

function buildCumulativeSeries(array $events): array
{
    if (empty($events)) {
        return ['timestamps' => [], 'prefix' => []];
    }

    usort($events, static function (array $a, array $b): int {
        return ($a['ts'] ?? 0) <=> ($b['ts'] ?? 0);
    });

    $timestamps = [];
    $prefix = [];
    $runningTotal = 0.0;

    foreach ($events as $event) {
        if (!isset($event['ts']) || $event['ts'] === null) {
            continue;
        }

        $runningTotal += $event['amount'];
        $timestamps[] = $event['ts'];
        $prefix[] = round($runningTotal, 2);
    }

    return ['timestamps' => $timestamps, 'prefix' => $prefix];
}

function cumulativeSumUpTo(array $series, int $timestamp): float
{
    if (empty($series['timestamps'])) {
        return 0.0;
    }

    $low = 0;
    $high = count($series['timestamps']) - 1;
    $resultIndex = -1;

    while ($low <= $high) {
        $mid = intdiv($low + $high, 2);
        if ($series['timestamps'][$mid] <= $timestamp) {
            $resultIndex = $mid;
            $low = $mid + 1;
        } else {
            $high = $mid - 1;
        }
    }

    return $resultIndex >= 0 ? $series['prefix'][$resultIndex] : 0.0;
}

function isActiveScheduleStatus($status): bool
{
    if ($status === null) {
        return false;
    }

    if (is_numeric($status)) {
        return intval($status) === 1;
    }

    $normalized = strtolower(trim((string) $status));
    return in_array($normalized, ['pending', 'overdue', 'active'], true);
}

$specificLeaseId = isset($_REQUEST['lease_id']) ? intval($_REQUEST['lease_id']) : null;

$leaseSql = $specificLeaseId
    ? "SELECT lease_id, valuation_date FROM leases WHERE status = 1 AND lease_id = {$specificLeaseId}"
    : "SELECT lease_id, valuation_date FROM leases WHERE status = 1";

$leaseResult = mysqli_query($con, $leaseSql);

if (!$leaseResult) {
    echo "Failed to load leases for penalty calculation.";
    return;
}

$hasFromDate = false;
$colCheck = mysqli_query(
    $con,
    "SELECT COUNT(*) AS cnt
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'lease_schedules'
       AND column_name = 'from_date'"
);
if ($colCheck) {
    $colInfo = mysqli_fetch_assoc($colCheck);
    if ($colInfo && intval($colInfo['cnt']) > 0) {
        $hasFromDate = true;
    }
    mysqli_free_result($colCheck);
}

$updatePenaltyStmt = $con->prepare(
    "UPDATE lease_schedules
        SET panalty = ?,
            penalty_last_calc = ?,
            penalty_remarks = ?
      WHERE schedule_id = ?
        AND (status = 1 OR status = '1' OR status = 'pending' OR status = 'overdue')"
);
if (!$updatePenaltyStmt) {
    mysqli_free_result($leaseResult);
    echo "Failed to prepare penalty update statement.";
    return;
}

$penaltyAmount = 0.0;
$penaltyDateValue = $today;
$penaltyRemark = '';
$penaltyScheduleId = 0;
$updatePenaltyStmt->bind_param('dssi', $penaltyAmount, $penaltyDateValue, $penaltyRemark, $penaltyScheduleId);

$leasesProcessed = 0;
$warningsDetected = false;

while ($lease = mysqli_fetch_assoc($leaseResult)) {
    $leaseId = intval($lease['lease_id']);
    $valuationDate = $lease['valuation_date'];
    $leaseLogLines = [];
    $penaltiesApplied = 0;
    $warnings = [];

    if (empty($valuationDate) || $valuationDate === '0000-00-00') {
        if (!mysqli_query(
            $con,
            "UPDATE lease_schedules
                SET panalty = 0,
                    penalty_last_calc = NULL,
                    penalty_remarks = 'No valuation date - penalty not applicable'
             WHERE lease_id = {$leaseId}"
        )) {
            $warnings[] = 'Failed to reset penalties for lease without valuation date';
        }
        $leaseLogLines[] = sprintf('[%s] lease_id=%d skipped: valuation date missing', date('Y-m-d H:i:s'), $leaseId);
        appendPenaltyLog($penaltyLogPath, $leaseLogLines);
        continue;
    }

    $firstPenaltyTs = strtotime($valuationDate);
    if ($firstPenaltyTs === false) {
        $leaseLogLines[] = sprintf('[%s] lease_id=%d skipped: invalid valuation date %s', date('Y-m-d H:i:s'), $leaseId, $valuationDate);
        appendPenaltyLog($penaltyLogPath, $leaseLogLines);
        continue;
    }

    if (!mysqli_query(
        $con,
        "UPDATE lease_schedules
            SET panalty = 0,
                penalty_last_calc = NULL,
                penalty_remarks = NULL
         WHERE lease_id = {$leaseId}"
    )) {
        $warnings[] = 'Failed to reset existing penalties before recalculation';
    }

    $selectFields = "schedule_id, schedule_year, start_date, end_date, annual_amount, penalty_rate, premium, premium_paid, status";
    if ($hasFromDate) {
        $selectFields .= ", from_date";
    }

    $scheduleSql = "SELECT {$selectFields}
                     FROM lease_schedules
                     WHERE lease_id = {$leaseId}
                       AND (status = 1 OR status = '1' OR status = 'pending' OR status = 'overdue')
                     ORDER BY start_date ASC, schedule_id ASC";
    $scheduleRes = mysqli_query($con, $scheduleSql);
    if (!$scheduleRes) {
        $warnings[] = 'Failed to load schedules';
        $leaseLogLines[] = sprintf('[%s] lease_id=%d skipped: unable to load schedules', date('Y-m-d H:i:s'), $leaseId);
        appendPenaltyLog($penaltyLogPath, $leaseLogLines);
        continue;
    }

    $schedules = [];
    while ($row = mysqli_fetch_assoc($scheduleRes)) {
        $row['schedule_id'] = intval($row['schedule_id']);
        $row['annual_amount'] = isset($row['annual_amount']) ? floatval($row['annual_amount']) : 0.0;
        $row['penalty_rate'] = isset($row['penalty_rate']) ? floatval($row['penalty_rate']) : 0.0;
        $row['premium'] = isset($row['premium']) ? floatval($row['premium']) : 0.0;
        $row['status'] = $row['status'];
        $row['start_ts'] = !empty($row['start_date']) ? strtotime($row['start_date']) : null;
        $row['end_ts'] = !empty($row['end_date']) ? strtotime($row['end_date']) : null;

        if ($hasFromDate && !empty($row['from_date'])) {
            $row['effective_from_ts'] = strtotime($row['from_date']);
        } else {
            $row['effective_from_ts'] = $row['start_ts'];
        }

        $schedules[] = $row;
    }
    mysqli_free_result($scheduleRes);

    if (empty($schedules)) {
        $leaseLogLines[] = sprintf('[%s] lease_id=%d skipped: no active schedules', date('Y-m-d H:i:s'), $leaseId);
        appendPenaltyLog($penaltyLogPath, $leaseLogLines);
        continue;
    }

    $paymentsMap = [];
    $paymentSql = "SELECT d.schedule_id, p.payment_date, d.rent_paid, d.discount_apply, d.premium_paid
                   FROM lease_payments_detail d
                   INNER JOIN lease_payments p ON p.payment_id = d.payment_id
                   WHERE p.lease_id = {$leaseId}
                     AND p.status = 1
                     AND d.status = 1";
    $paymentRes = mysqli_query($con, $paymentSql);
    if ($paymentRes) {
        while ($paymentRow = mysqli_fetch_assoc($paymentRes)) {
            $sid = intval($paymentRow['schedule_id']);
            if (!isset($paymentsMap[$sid])) {
                $paymentsMap[$sid] = [];
            }
            $paymentsMap[$sid][] = [
                'ts' => !empty($paymentRow['payment_date']) ? strtotime($paymentRow['payment_date']) : null,
                'rent' => isset($paymentRow['rent_paid']) ? floatval($paymentRow['rent_paid']) : 0.0,
                'discount' => isset($paymentRow['discount_apply']) ? floatval($paymentRow['discount_apply']) : 0.0,
                'premium' => isset($paymentRow['premium_paid']) ? floatval($paymentRow['premium_paid']) : 0.0,
            ];
        }
        mysqli_free_result($paymentRes);
    }

    $paymentsSeriesBySchedule = [];
    foreach ($paymentsMap as $sid => $rows) {
        $scheduleEvents = [];
        foreach ($rows as $row) {
            if (!isset($row['ts']) || $row['ts'] === null) {
                continue;
            }
            $totalPaid = max(0.0, ($row['rent'] ?? 0.0) + ($row['premium'] ?? 0.0) + ($row['discount'] ?? 0.0));
            if ($totalPaid > 0) {
                $scheduleEvents[] = ['ts' => $row['ts'], 'amount' => $totalPaid];
            }
        }
        $paymentsSeriesBySchedule[$sid] = buildCumulativeSeries($scheduleEvents);
    }

    $scheduleDueTotals = [];
    foreach ($schedules as $schedule) {
        $sid = $schedule['schedule_id'];
        $scheduleDueTotals[$sid] = round(max(0.0, $schedule['annual_amount'] + $schedule['premium']), 2);
    }

    $writeOffBySchedule = [];
    $writeOffSql = "SELECT schedule_id, SUM(write_off_amount) AS total_write_off
                    FROM ltl_write_off
                    WHERE lease_id = {$leaseId} AND status = 1
                    GROUP BY schedule_id";
    $writeOffRes = mysqli_query($con, $writeOffSql);
    if ($writeOffRes) {
        while ($writeOffRow = mysqli_fetch_assoc($writeOffRes)) {
            $writeOffBySchedule[intval($writeOffRow['schedule_id'])] = round(floatval($writeOffRow['total_write_off']), 2);
        }
        mysqli_free_result($writeOffRes);
    }

    $scheduleCount = count($schedules);
    $lastPenaltyOutstanding = 0.0;

    for ($index = 0; $index < $scheduleCount; $index++) {
        $schedule = $schedules[$index];
        if (!isActiveScheduleStatus($schedule['status'])) {
            continue;
        }

        $endTs = $schedule['end_ts'];
        if ($endTs === null) {
            continue;
        }

        $nextSchedule = null;
        for ($nextIndex = $index + 1; $nextIndex < $scheduleCount; $nextIndex++) {
            $candidate = $schedules[$nextIndex];
            if (!isActiveScheduleStatus($candidate['status'])) {
                continue;
            }
            $candidateFromTs = $candidate['effective_from_ts'];
            if ($candidateFromTs !== null && $candidateFromTs <= $endTs) {
                continue;
            }
            if ($candidate['end_ts'] !== null && $candidate['end_ts'] > $futureLimitTs) {
                continue;
            }
            $nextSchedule = $candidate;
            break;
        }

        if ($nextSchedule === null) {
            continue;
        }

        $evaluationTs = $nextSchedule['effective_from_ts'] ?? $nextSchedule['start_ts'] ?? null;
        if ($evaluationTs === null) {
            continue;
        }

        if ($evaluationTs > $todayTs) {
            $evaluationTs = $todayTs;
        }

        if ($evaluationTs <= $endTs || $evaluationTs <= $firstPenaltyTs) {
            continue;
        }

        $evaluationTsAdjusted = $evaluationTs - 1;
        if ($evaluationTsAdjusted <= 0) {
            $evaluationTsAdjusted = $evaluationTs;
        }

        if ($evaluationTsAdjusted >= $todayTs) {
            continue;
        }

        $penaltyBaseOutstanding = 0.0;
        for ($calcIndex = 0; $calcIndex <= $index; $calcIndex++) {
            $calcSchedule = $schedules[$calcIndex];
            if (!isActiveScheduleStatus($calcSchedule['status'])) {
                continue;
            }
            $calcEndTs = $calcSchedule['end_ts'];
            if ($calcEndTs === null || $calcEndTs > $evaluationTsAdjusted) {
                continue;
            }

            $calcSid = $calcSchedule['schedule_id'];
            $dueTotal = $scheduleDueTotals[$calcSid] ?? 0.0;
            if ($dueTotal <= PENALTY_EPSILON) {
                continue;
            }

            $series = $paymentsSeriesBySchedule[$calcSid] ?? ['timestamps' => [], 'prefix' => []];
            $paidUpToCutoff = cumulativeSumUpTo($series, $evaluationTsAdjusted);
            $outstandingForSchedule = max(0.0, round($dueTotal - $paidUpToCutoff, 2));
            if ($outstandingForSchedule > PENALTY_EPSILON) {
                $penaltyBaseOutstanding = round($penaltyBaseOutstanding + $outstandingForSchedule, 2);
            }
        }

        if ($penaltyBaseOutstanding <= PENALTY_EPSILON) {
            continue;
        }

        if (($penaltyBaseOutstanding - $lastPenaltyOutstanding) <= PENALTY_EPSILON) {
            continue;
        }

        $rate = $schedule['penalty_rate'] > 0 ? $schedule['penalty_rate'] : 10.0;
        $rawPenaltyAmount = round($penaltyBaseOutstanding * ($rate / 100), 2);
        $penaltyScheduleId = intval($nextSchedule['schedule_id']);

        $writeOffApplied = $writeOffBySchedule[$penaltyScheduleId] ?? 0.0;
        $penaltyAmount = $rawPenaltyAmount;
        if ($writeOffApplied > 0) {
            $penaltyAmount = max(0.0, round($penaltyAmount - $writeOffApplied, 2));
        }

        $penaltyRemarkParts = [];
        $penaltyRemarkParts[] = sprintf('Penalty from Year %s - Outstanding: %.2f', $schedule['schedule_year'], $penaltyBaseOutstanding);
        $penaltyRemarkParts[] = sprintf('Rate: %.2f%%', $rate);
        if ($writeOffApplied > 0) {
            $penaltyRemarkParts[] = sprintf('Write-off applied: %.2f', $writeOffApplied);
        }
        $penaltyRemarkParts[] = sprintf('Final penalty: %.2f', $penaltyAmount);
        $penaltyRemark = implode(' | ', $penaltyRemarkParts);

        $penaltyDateValue = $today;

        $updatePenaltyStmt->bind_param('dssi', $penaltyAmount, $penaltyDateValue, $penaltyRemark, $penaltyScheduleId);
        if (!$updatePenaltyStmt->execute()) {
            $error = $updatePenaltyStmt->error;
            $warnings[] = sprintf('Failed to update schedule %d: %s', $penaltyScheduleId, $error);
            error_log("Failed to update penalty for schedule {$penaltyScheduleId}: {$error}");
        } else {
            $penaltiesApplied++;
            $leaseLogLines[] = sprintf(
                '[%s] lease_id=%d schedule_id=%d penalty=%.2f base=%.2f rate=%.2f%% write_off=%.2f',
                date('Y-m-d H:i:s'),
                $leaseId,
                $penaltyScheduleId,
                $penaltyAmount,
                $penaltyBaseOutstanding,
                $rate,
                $writeOffApplied
            );
        }

        $lastPenaltyOutstanding = $penaltyBaseOutstanding;
    }

    if (!empty($warnings)) {
        $warningsDetected = true;
        foreach ($warnings as $warning) {
            $leaseLogLines[] = sprintf('[%s] lease_id=%d warning: %s', date('Y-m-d H:i:s'), $leaseId, $warning);
        }
    }

    if ($penaltiesApplied === 0) {
        $leaseLogLines[] = sprintf('[%s] lease_id=%d penalties recalculated with no updates', date('Y-m-d H:i:s'), $leaseId);
    } else {
        $leaseLogLines[] = sprintf('[%s] lease_id=%d penalties updated: %d schedule(s)', date('Y-m-d H:i:s'), $leaseId, $penaltiesApplied);
    }

    appendPenaltyLog($penaltyLogPath, $leaseLogLines);

    $leasesProcessed++;
}

$updatePenaltyStmt->close();
mysqli_free_result($leaseResult);

$messageSuffix = $warningsDetected ? ' (warnings logged to ds/penalty_log.txt)' : '';
if ($specificLeaseId) {
    echo "Penalty calculation completed for lease ID: {$specificLeaseId} on {$today}{$messageSuffix}.";
} else {
    echo "Penalty calculation completed for {$leasesProcessed} lease(s) on {$today}{$messageSuffix}.";
}