<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$response = ['success' => false, 'message' => ''];

try {
    $lease_id = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

    if ($lease_id <= 0 || $schedule_id <= 0) {
        throw new Exception('Invalid lease or schedule ID');
    }

    if ($amount < 0) {
        throw new Exception('Amount must be positive');
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // Fetch beneficiary id for logging
    $ben_id_log = null;
    if ($lease_id > 0) {
        if ($stBen = mysqli_prepare($con, 'SELECT beneficiary_id FROM rl_lease WHERE rl_lease_id = ? LIMIT 1')) {
            mysqli_stmt_bind_param($stBen, 'i', $lease_id);
            mysqli_stmt_execute($stBen);
            $resBen = mysqli_stmt_get_result($stBen);
            if ($resBen && ($rowBen = mysqli_fetch_assoc($resBen))) {
                $ben_id_log = isset($rowBen['beneficiary_id']) ? (int)$rowBen['beneficiary_id'] : null;
            }
            mysqli_stmt_close($stBen);
        }
    }

    // Insert write-off record
    $sql = "INSERT INTO rl_write_off (lease_id, schedule_id, write_off_amount, created_by, created_on, status) 
            VALUES (?, ?, ?, ?, NOW(), 1)";
    
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iidi', $lease_id, $schedule_id, $amount, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            
            // Recalculate penalty for the lease
            try {
                $_REQUEST['lease_id'] = $lease_id;
                ob_start();
                include __DIR__ . '/rl_cal_penalty.php';
                ob_end_clean();
            } catch (Exception $e) {
                // non-fatal
            }

            if (function_exists('UserLog')) {
                $detail = sprintf('Recorded penalty write-off: Lease ID=%d | Schedule ID=%d | Amount=%.2f', $lease_id, $schedule_id, $amount);
                UserLog(2, 'RL Write Off Penalty', $detail, $ben_id_log, 'RL');
            }
            
            $response['success'] = true;
            $response['message'] = 'Write-off recorded successfully';
        } else {
            throw new Exception('Failed to record write-off: ' . mysqli_error($con));
        }
    } else {
        throw new Exception('Database error: ' . mysqli_error($con));
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


