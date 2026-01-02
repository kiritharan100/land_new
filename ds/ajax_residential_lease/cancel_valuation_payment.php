<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$response = ['success' => false, 'message' => ''];

try {
    // Check permission
    if (!hasPermission(19)) {
        throw new Exception('You do not have permission to cancel payments');
    }
    
    $paid_id = isset($_POST['paid_id']) ? (int)$_POST['paid_id'] : 0;
    
    if ($paid_id <= 0) {
        throw new Exception('Invalid payment ID');
    }
    
    // Get payment details before cancellation
    $sql_get = "SELECT vp.*, l.lease_number 
                FROM rl_valuvation_paid vp 
                LEFT JOIN rl_lease l ON vp.rl_lease_id = l.rl_lease_id 
                WHERE vp.paid_id = ? AND vp.status = 1 
                LIMIT 1";
    
    $payment = null;
    if ($st = mysqli_prepare($con, $sql_get)) {
        mysqli_stmt_bind_param($st, 'i', $paid_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        $payment = mysqli_fetch_assoc($rs);
        mysqli_stmt_close($st);
    }
    
    if (!$payment) {
        throw new Exception('Payment not found or already cancelled');
    }
    
    // Update status to 0 (cancelled)
    $sql_cancel = "UPDATE rl_valuvation_paid SET status = 0 WHERE paid_id = ?";
    
    if ($stCancel = mysqli_prepare($con, $sql_cancel)) {
        mysqli_stmt_bind_param($stCancel, 'i', $paid_id);
        
        if (mysqli_stmt_execute($stCancel)) {
            // Log the action
            if (function_exists('UserLog')) {
                $detail = "Valuation Payment Cancelled - Lease: " . ($payment['lease_number'] ?? $payment['rl_lease_id']) . 
                          ", Receipt: " . ($payment['receipt_number'] ?? '') . 
                          ", Amount: Rs. " . number_format((float)$payment['amount'], 2);
                UserLog(2, 'RL Cancel Valuation Payment', $detail, $payment['beneficiary_id'] ?? null, 'RL');
            }
            
            $response['success'] = true;
            $response['message'] = 'Payment cancelled successfully';
        } else {
            throw new Exception('Database error: ' . mysqli_error($con));
        }
        mysqli_stmt_close($stCancel);
    } else {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($con));
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

