<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$lease_id = intval($_POST['lease_id'] ?? 0);
$location_id = intval($_POST['location_id'] ?? 0);
$payment_amount = floatval($_POST['payment_amount'] ?? 0);
$payment_date = $_POST['payment_date'] ?? '';
$reference_number = $_POST['reference_number'] ?? '';
$payment_notes = $_POST['payment_notes'] ?? '';
$created_by = intval($_SESSION['user_id'] ?? $_SESSION['username'] ?? 1);

if ($lease_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid lease ID is required']);
    exit;
}

if ($payment_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than zero']);
    exit;
}

if (empty($payment_date)) {
    echo json_encode(['success' => false, 'message' => 'Payment date is required']);
    exit;
}

if (empty($reference_number)) {
    echo json_encode(['success' => false, 'message' => 'Reference number is required']);
    exit;
}

try {
    // Check database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Connection object not found'));
    }
    
    $conn->begin_transaction();
    
    // Get current lease details (include payment_due_date for on-time check)
    $lease_query = "SELECT 
        st_lease_id,
        lease_number,
        lease_amount,
        payment_due_date,
        COALESCE(amount_paid, 0) as amount_paid,
        COALESCE(penalty_amount, 0) as penalty_amount,
        COALESCE(penalty_paid, 0) as penalty_paid,
        COALESCE(total_paid, 0) as total_paid
        FROM short_term_leases 
        WHERE st_lease_id = ? AND location_id = ?
        AND (is_deleted IS NULL OR is_deleted = 0)";
    
    $stmt = $conn->prepare($lease_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare lease query: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $lease_id, $location_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute lease query: ' . $stmt->error);
    }
    
    $lease_result = $stmt->get_result();
    
    if (!$lease_row = $lease_result->fetch_assoc()) {
        throw new Exception('Lease not found or no permission to access');
    }
    
    // Calculate outstanding amounts
    $rent_outstanding = $lease_row['lease_amount'] - $lease_row['amount_paid'];
    $penalty_outstanding = $lease_row['penalty_amount'] - $lease_row['penalty_paid'];
    $total_outstanding = $rent_outstanding + $penalty_outstanding;
    
    // Validate payment amount doesn't exceed outstanding
    if ($payment_amount > $total_outstanding) {
        throw new Exception('Payment amount cannot exceed total outstanding amount');
    }
    
    // Allocate payment to RENT first, then penalty (updated business rule)
    $rent_payment = min($payment_amount, $rent_outstanding);
    $penalty_payment = $payment_amount - $rent_payment;
    
    // Insert payment record
    $payment_query = "INSERT INTO short_term_lease_payments 
        (location_id, st_lease_id, payment_date, lease_amount_paid, penalty_amount_paid, 
         total_amount, payment_method, receipt_number, reference_number, bank_details, 
         payment_notes, created_by, created_on) 
        VALUES (?, ?, ?, ?, ?, ?, 'cash', '', ?, '', ?, ?, NOW())";
    
    $stmt = $conn->prepare($payment_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare payment query: ' . $conn->error);
    }
    
    // Parameters order: location_id, st_lease_id, payment_date, lease_amount_paid, penalty_amount_paid, total_amount, reference_number, payment_notes, created_by
    $stmt->bind_param("iisdddssi", 
        $location_id, 
        $lease_id, 
        $payment_date, 
        $rent_payment, 
        $penalty_payment, 
        $payment_amount,
        $reference_number,
        $payment_notes,
        $created_by
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert payment record: ' . $stmt->error);
    }
    
    $payment_id = $conn->insert_id;
    
    // Update lease amounts
    $new_amount_paid = $lease_row['amount_paid'] + $rent_payment;
    $new_penalty_paid = $lease_row['penalty_paid'] + $penalty_payment;
    $new_total_paid = $lease_row['total_paid'] + $payment_amount;
    
    // Determine payment status (initial, may be overridden by on-time rule below)
    $remaining_balance = ($lease_row['lease_amount'] + $lease_row['penalty_amount']) - $new_total_paid;
    if ($remaining_balance <= 0) {
        $payment_status = 'Paid';
    } elseif ($new_total_paid > 0) {
        $payment_status = 'Partially Paid';
    } else {
        $payment_status = 'Unpaid';
    }
    
    // Update lease record
    $update_query = "UPDATE short_term_leases 
        SET amount_paid = ?, 
            penalty_paid = ?, 
            total_paid = ?,
            payment_status = ?,
            updated_by = ?, 
            updated_on = NOW() 
        WHERE st_lease_id = ?";
    
    $stmt = $conn->prepare($update_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare update query: ' . $conn->error);
    }
    
    $stmt->bind_param("dddssi", 
        $new_amount_paid, 
        $new_penalty_paid, 
        $new_total_paid,
        $payment_status,
        $created_by, 
        $lease_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update lease record: ' . $stmt->error);
    }

    // Business rule: If lease rent is fully paid on/before due date, zero penalties
    $onTimeClearsPenalty = false;
    $dueDate = $lease_row['payment_due_date'] ?? null;
    if (!empty($dueDate)) {
        // Sum rent payments made on/before due date
        $sumSql = "SELECT COALESCE(SUM(lease_amount_paid),0) AS rent_paid_to_due
                   FROM short_term_lease_payments
                   WHERE st_lease_id = ? AND total_amount > 0 AND payment_date <= ?";
        $sumStmt = $conn->prepare($sumSql);
        if ($sumStmt) {
            $sumStmt->bind_param('is', $lease_id, $dueDate);
            if ($sumStmt->execute()) {
                $sumRes = $sumStmt->get_result();
                $sumRow = $sumRes->fetch_assoc();
                $rentPaidToDue = (float)($sumRow['rent_paid_to_due'] ?? 0);
                if ($rentPaidToDue >= (float)$lease_row['lease_amount']) {
                    $onTimeClearsPenalty = true;
                }
            }
            $sumStmt->close();
        }
    }

    if ($onTimeClearsPenalty) {
        // Zero out penalty_amount; keep totals consistent and recompute status/balance
        $zeroStmt = $conn->prepare("UPDATE short_term_leases
                                    SET penalty_amount = 0.00,
                                        updated_by = ?, updated_on = NOW()
                                    WHERE st_lease_id = ?");
        if ($zeroStmt) {
            $zeroStmt->bind_param('ii', $created_by, $lease_id);
            $zeroStmt->execute();
            $zeroStmt->close();
        }
        // Recompute remaining balance and status based on rent only
        $remaining_balance = max(0.0, ($lease_row['lease_amount'] - $new_amount_paid));
        $payment_status = ($remaining_balance <= 0.0) ? 'Paid' : (($new_total_paid > 0) ? 'Partially Paid' : 'Unpaid');

        // Persist possibly updated status
        $statusStmt = $conn->prepare("UPDATE short_term_leases
                                      SET payment_status = ?, updated_by = ?, updated_on = NOW()
                                      WHERE st_lease_id = ?");
        if ($statusStmt) {
            $statusStmt->bind_param('sii', $payment_status, $created_by, $lease_id);
            $statusStmt->execute();
            $statusStmt->close();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully',
        'data' => [
            'payment_id' => $payment_id,
            'lease_payment' => $rent_payment,
            'penalty_payment' => $penalty_payment,
            'total_payment' => $payment_amount,
            'remaining_balance' => $remaining_balance,
            'payment_status' => $payment_status,
            'penalty_cleared_on_time' => $onTimeClearsPenalty
        ]
    ]);
    
} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    error_log("Error in record_lease_payment.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>