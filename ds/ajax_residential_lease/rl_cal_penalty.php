<?php
/**
 * Residential Lease Penalty Calculation
 * Similar to LTL but uses rl_lease_schedules table
 */
require_once dirname(__DIR__, 2) . '/db.php';
date_default_timezone_set('Asia/Colombo');

$today = date('Y-m-d');
$lease_id = isset($_REQUEST['lease_id']) ? intval($_REQUEST['lease_id']) : null;

// Check if called directly or included
$called_directly = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'rl_cal_penalty.php');

if (!$lease_id) {
    if ($called_directly) echo "No lease_id provided";
    return;
}

// Get lease details
$leaseQuery = "
    SELECT rl_lease_id, valuvation_date, start_date, end_date, penalty_rate
    FROM rl_lease
    WHERE rl_lease_id = ?
";
$stmt = mysqli_prepare($con, $leaseQuery);
mysqli_stmt_bind_param($stmt, 'i', $lease_id);
mysqli_stmt_execute($stmt);
$leaseResult = mysqli_stmt_get_result($stmt);
$lease = mysqli_fetch_assoc($leaseResult);
mysqli_stmt_close($stmt);

if (!$lease) {
    if ($called_directly) echo "Lease not found";
    return;
}

$valuation_date = $lease['valuvation_date'];
$penalty_rate = (float)$lease['penalty_rate'];

// Skip penalty calculation if valuation_date is empty or '0000-00-00'
if (empty($valuation_date) || $valuation_date == '0000-00-00') {
    $resetNoValuation = "
        UPDATE rl_lease_schedules
        SET panalty = 0,
            penalty_last_calc = NULL,
            penalty_remarks = 'No valuation date'
        WHERE lease_id = ?
    ";
    $stmt = mysqli_prepare($con, $resetNoValuation);
    mysqli_stmt_bind_param($stmt, 'i', $lease_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return;
}

// If penalty rate is zero, reset to 0
if ($penalty_rate == 0) {
    $resetZeroPenalty = "
        UPDATE rl_lease_schedules
        SET panalty = 0,
            penalty_last_calc = NULL,
            penalty_remarks = '0% penalty rate'
        WHERE lease_id = ?
    ";
    $stmt = mysqli_prepare($con, $resetZeroPenalty);
    mysqli_stmt_bind_param($stmt, 'i', $lease_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return;
}

// Reset all penalties first
$resetQuery = "UPDATE rl_lease_schedules SET panalty = 0 WHERE lease_id = ?";
$stmt = mysqli_prepare($con, $resetQuery);
mysqli_stmt_bind_param($stmt, 'i', $lease_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Calculate cumulative outstanding and penalties
$scheduleQuery = "
    SELECT 
        ls.schedule_id,
        ls.start_date,
        ls.end_date,
        ls.premium,
        ls.annual_amount,
        ls.panalty,
        IFNULL(SUM(lp.rent_paid), 0) AS rent_paid,
        IFNULL(SUM(lp.panalty_paid), 0) AS panalty_paid,
        IFNULL(SUM(lp.premium_paid), 0) AS premium_paid,
        IFNULL(SUM(lp.discount_apply), 0) AS discount_apply,
        IFNULL(SUM(w.write_off_amount), 0) AS write_off_amount
    FROM rl_lease_schedules ls
    LEFT JOIN rl_lease_payments lp 
        ON ls.schedule_id = lp.schedule_id AND lp.status = 1
    LEFT JOIN rl_write_off w
        ON ls.schedule_id = w.schedule_id AND w.status = 1
    WHERE ls.lease_id = ?
      AND DATE_ADD(ls.start_date, INTERVAL 30 DAY) < ?
    GROUP BY ls.schedule_id
    ORDER BY ls.schedule_year
";

$stmt = mysqli_prepare($con, $scheduleQuery);
mysqli_stmt_bind_param($stmt, 'is', $lease_id, $today);
mysqli_stmt_execute($stmt);
$scheduleResult = mysqli_stmt_get_result($stmt);

$cumulative_outstanding = 0;
$penalty_year = 0;

while ($schedule = mysqli_fetch_assoc($scheduleResult)) {
    $cumulative_outstanding_last_schedule = max(0, $cumulative_outstanding);
    $cumulative_outstanding += ($schedule['annual_amount'] + $schedule['premium'] 
        - $schedule['rent_paid'] - $schedule['premium_paid'] - $schedule['discount_apply']);
    
    if ($schedule['end_date'] > $valuation_date) {
        if ($penalty_year > 0) {
            $write_off_amount = $schedule['write_off_amount'];
            $penalty_amount = ($cumulative_outstanding_last_schedule * ($penalty_rate / 100)) - $write_off_amount;
            $penalty_amount = max(0, $penalty_amount);
            
            $updatePenaltyQuery = "
                UPDATE rl_lease_schedules
                SET panalty = ?,
                    penalty_last_calc = ?,
                    penalty_remarks = 'Calculated'
                WHERE schedule_id = ?
            ";
            $stmtUpdate = mysqli_prepare($con, $updatePenaltyQuery);
            mysqli_stmt_bind_param($stmtUpdate, 'dsi', $penalty_amount, $today, $schedule['schedule_id']);
            mysqli_stmt_execute($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);
        }
        $penalty_year++;
    }
}

mysqli_stmt_close($stmt);

if ($called_directly) {
    echo "Penalty calculation completed for lease $lease_id";
}
