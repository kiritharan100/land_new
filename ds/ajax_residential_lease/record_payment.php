<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
require_once __DIR__ . '/rl_payment_allocator.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$response = ['success' => false, 'message' => ''];

try {
    $lease_id = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $payment_date = trim($_POST['payment_date'] ?? '');
    $receipt_number = trim($_POST['receipt_number'] ?? '');
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $notes = trim($_POST['notes'] ?? '');

    if ($lease_id <= 0) {
        throw new Exception('Invalid lease ID');
    }
    if ($amount <= 0) {
        throw new Exception('Amount must be positive');
    }
    if (!$payment_date) {
        throw new Exception('Payment date is required');
    }
    if (!$receipt_number) {
        throw new Exception('Receipt number is required');
    }

    // Get lease and location
    $stmt = mysqli_prepare($con, 'SELECT location_id, discount_rate, beneficiary_id FROM rl_lease WHERE rl_lease_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $lease_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lease = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$lease) {
        throw new Exception('Lease not found');
    }

    $location_id = (int)$lease['location_id'];
    $ben_id_log = isset($lease['beneficiary_id']) ? (int)$lease['beneficiary_id'] : 0;
    // Use the allocator function to get proper discount rate (converted to decimal)
    $discount_rate = fetchRLLeaseDiscountRate($con, $lease_id);
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // Load schedules for allocation
    $schedule_state = loadRLLeaseSchedulesForPayment($con, $lease_id);
    
    if (empty($schedule_state)) {
        throw new Exception('No schedules available for this lease');
    }
    
    // Allocate the payment
    $allocation = allocateRLLeasePayment($schedule_state, $payment_date, $amount, $discount_rate);
    $allocations = $allocation['allocations'];
    $totals = $allocation['totals'];
    $currentScheduleId = $allocation['current_schedule_id'];
    
    if (empty($allocations)) {
        // No allocations needed (payment applied but nothing owed)
        $currentScheduleId = 0;
    }

    $con->begin_transaction();

    // Insert payment record
    $sql = "INSERT INTO rl_lease_payments 
            (location_id, lease_id, schedule_id, payment_date, amount, rent_paid, panalty_paid, premium_paid, 
             discount_apply, current_year_payment, receipt_number, payment_method, notes, status, created_by, created_on)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'iiisddddddsssi', 
        $location_id, 
        $lease_id, 
        $currentScheduleId, 
        $payment_date, 
        $amount,
        $totals['rent'], 
        $totals['penalty'], 
        $totals['premium'],
        $totals['discount'],
        $totals['current_year_payment'],
        $receipt_number, 
        $payment_method, 
        $notes,
        $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to save payment: ' . mysqli_error($con));
    }
    $payment_id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    // Update schedules and insert payment details
    $updateScheduleSql = "UPDATE rl_lease_schedules SET 
            paid_rent = paid_rent + ?,
            panalty_paid = panalty_paid + ?,
            premium_paid = premium_paid + ?,
            total_paid = total_paid + ?,
            discount_apply = discount_apply + ?
         WHERE schedule_id = ?";
    $updateScheduleStmt = mysqli_prepare($con, $updateScheduleSql);

    $insertDetailSql = "INSERT INTO rl_lease_payments_detail 
            (payment_id, schedule_id, rent_paid, penalty_paid, premium_paid, discount_apply, current_year_payment, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    $insertDetailStmt = mysqli_prepare($con, $insertDetailSql);

    foreach ($allocations as $sid => $alloc) {
        $rentInc = $alloc['rent'];
        $penInc = $alloc['penalty'];
        $premInc = $alloc['premium'];
        $discInc = $alloc['discount'];
        $curYearInc = $alloc['current_year_payment'];
        $totalPaidSchedule = $alloc['total_paid'];
        $scheduleId = (int)$sid;

        mysqli_stmt_bind_param($updateScheduleStmt, 'dddddi',
            $rentInc, $penInc, $premInc, $totalPaidSchedule, $discInc, $scheduleId);
        if (!mysqli_stmt_execute($updateScheduleStmt)) {
            throw new Exception('Failed to update schedule: ' . mysqli_error($con));
        }

        $hasDetail = ($rentInc > 0) || ($penInc > 0) || ($premInc > 0) || ($discInc > 0);
        if ($hasDetail) {
            mysqli_stmt_bind_param($insertDetailStmt, 'iiddddd',
                $payment_id, $scheduleId, $rentInc, $penInc, $premInc, $discInc, $curYearInc);
            if (!mysqli_stmt_execute($insertDetailStmt)) {
                throw new Exception('Failed to insert payment detail: ' . mysqli_error($con));
            }
        }
    }

    mysqli_stmt_close($updateScheduleStmt);
    mysqli_stmt_close($insertDetailStmt);

    $con->commit();

    // Recalculate penalties after payment
    try {
        $_REQUEST['lease_id'] = $lease_id;
        ob_start();
        include __DIR__ . '/rl_cal_penalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // Non-fatal
    }

    if (function_exists('UserLog')) {
        $detail = sprintf(
            'Recorded payment: ID=%d | Lease=%d | Receipt=%s | Amount=%.2f',
            $payment_id,
            $lease_id,
            $receipt_number,
            $amount
        );
        UserLog(2, 'RL Record Payment', $detail, $ben_id_log, 'RL');
    }

    $response['success'] = true;
    $response['message'] = 'Payment recorded successfully';
    $response['payment_id'] = $payment_id;

} catch (Exception $e) {
    if ($con->in_transaction) {
        $con->rollback();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
