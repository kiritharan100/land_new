<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    $rl_lease_id = isset($_POST['rl_lease_id']) ? (int)$_POST['rl_lease_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $receipt_number = isset($_POST['receipt_number']) ? trim($_POST['receipt_number']) : '';
    $mode_payment = isset($_POST['mode_payment']) ? trim($_POST['mode_payment']) : 'Cash';
    $memo = isset($_POST['memo']) ? trim($_POST['memo']) : '';
    $location_id = isset($_POST['location_id']) ? trim($_POST['location_id']) : '';
    
    if ($rl_lease_id <= 0) {
        throw new Exception('Invalid lease ID');
    }
    
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than zero');
    }
    
    if ($receipt_number === '') {
        throw new Exception('Receipt number is required');
    }
    
    // Verify lease exists
    $sql_check = "SELECT rl_lease_id, lease_number FROM rl_lease WHERE rl_lease_id = ? LIMIT 1";
    $lease = null;
    if ($st = mysqli_prepare($con, $sql_check)) {
        mysqli_stmt_bind_param($st, 'i', $rl_lease_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        $lease = mysqli_fetch_assoc($rs);
        mysqli_stmt_close($st);
    }
    
    if (!$lease) {
        throw new Exception('Lease not found');
    }
    
    // Insert payment into rl_valuvation_paid
    $sql_insert = "INSERT INTO rl_valuvation_paid (rl_lease_id, amount, location_id, mode_payment, receipt_number, memo, status) 
                   VALUES (?, ?, ?, ?, ?, ?, 1)";
    
    if ($stInsert = mysqli_prepare($con, $sql_insert)) {
        mysqli_stmt_bind_param($stInsert, 'idssss', $rl_lease_id, $amount, $location_id, $mode_payment, $receipt_number, $memo);
        
        if (mysqli_stmt_execute($stInsert)) {
            $paid_id = mysqli_insert_id($con);
            
            // Log the action
            if (function_exists('UserLog')) {
                UserLog(
                    "Valuation Payment Added - Lease: " . ($lease['lease_number'] ?? $rl_lease_id) . 
                    ", Receipt: " . $receipt_number . 
                    ", Amount: Rs. " . number_format($amount, 2),
                    'rl_valuvation_paid',
                    $paid_id
                );
            }
            
            $response['success'] = true;
            $response['message'] = 'Valuation payment recorded successfully';
            $response['paid_id'] = $paid_id;
        } else {
            throw new Exception('Database error: ' . mysqli_error($con));
        }
        mysqli_stmt_close($stInsert);
    } else {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($con));
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

