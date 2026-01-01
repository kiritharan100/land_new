<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';
require_once __DIR__ . '/rl_payment_allocator.php';

header('Content-Type: application/json');

if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $payment_id = intval($_POST['payment_id']);
        
        if ($payment_id <= 0) {
            throw new Exception("Invalid payment ID");
        }
        
        // Get payment details before cancellation
        $payment_sql = "SELECT lp.*, l.lease_number, l.file_number, l.beneficiary_id
                       FROM rl_lease_payments lp 
                       LEFT JOIN rl_lease l ON lp.lease_id = l.rl_lease_id 
                       WHERE lp.payment_id = ?";
        $stmt = $con->prepare($payment_sql);
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        if (isset($payment['status']) && (int)$payment['status'] === 0) {
            throw new Exception("Payment is already cancelled");
        }

        // Allow cancellation only for the most recent active payment on the lease.
        $latest_sql = "SELECT payment_id 
                       FROM rl_lease_payments 
                       WHERE lease_id = ? AND status = 1 
                       ORDER BY payment_date DESC, payment_id DESC 
                       LIMIT 1";
        $stmt_latest = $con->prepare($latest_sql);
        $stmt_latest->bind_param("i", $payment['lease_id']);
        $stmt_latest->execute();
        $latest_row = $stmt_latest->get_result()->fetch_assoc();
        $stmt_latest->close();

        if (!$latest_row || (int)$latest_row['payment_id'] !== $payment_id) {
            throw new Exception("Only the latest active payment can be cancelled.");
        }

        // Start transaction
        $con->begin_transaction();

        // 1) Mark the payment as cancelled (status = 0)
        $delete_sql = "UPDATE rl_lease_payments SET status = 0 WHERE payment_id = ?";
        $stmt_delete = $con->prepare($delete_sql);
        $stmt_delete->bind_param("i", $payment_id);
        if (!$stmt_delete->execute()) {
            throw new Exception("Failed to cancel payment: " . $stmt_delete->error);
        }

        // 1b) Mark detail rows as inactive
        $cancelDetailSql = "UPDATE rl_lease_payments_detail SET status = 0 WHERE payment_id = ?";
        $stmt_cancel_detail = $con->prepare($cancelDetailSql);
        $stmt_cancel_detail->bind_param("i", $payment_id);
        if (!$stmt_cancel_detail->execute()) {
            throw new Exception("Failed to cancel payment detail rows: " . $stmt_cancel_detail->error);
        }

        // 2) Reapply all remaining active payments using the allocator
        if (!reapplyRLPaymentsOnExistingSchedules($con, intval($payment['lease_id']))) {
            throw new Exception('Failed to rebuild payment allocations');
        }

        // Commit transaction
        $con->commit();
        
        // Log the action
        if (function_exists('UserLog')) {
            $ben_id = intval($payment['beneficiary_id'] ?? 0);
            UserLog(
                '2', 
                'RL Cancel Payment', 
                "Cancelled payment: {$payment['receipt_number']}, Amount: {$payment['amount']}, Lease_file: {$payment['file_number']}",
                $ben_id
            );
        }

        // Trigger penalty recalculation
        try {
            $_REQUEST['lease_id'] = $payment['lease_id'];
            ob_start();
            include __DIR__ . '/rl_cal_penalty.php';
            ob_end_clean();
        } catch (Exception $e) {
            // Non-fatal
        }

        $response['success'] = true;
        $response['message'] = "Payment has been cancelled successfully and allocations rebuilt.";
        
    } catch (Exception $e) {
        if ($con->in_transaction) {
            $con->rollback();
        }
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

