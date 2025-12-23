<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include '../../db.php';

// Set content type for JSON response
header('Content-Type: application/json');

if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        if (empty($_POST['lease_id']) || empty($_POST['payment_date']) || empty($_POST['amount'])) {
            throw new Exception("Missing required fields: lease_id, payment_date, or amount");
        }
        
        $lease_id = intval($_POST['lease_id']);
        $payment_date = $_POST['payment_date'];
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $reference_number = $_POST['reference_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($amount <= 0) {
            throw new Exception("Payment amount must be greater than zero");
        }
        
        // Generate receipt number
        $receipt_number = "RCPT-" . date('Ymd-His') . "-" . rand(100, 999);
        
        // Get lease info for penalty rate
        $lease_sql = "SELECT annual_rent_percentage FROM leases WHERE lease_id = ?";
        $lease_stmt = $con->prepare($lease_sql);
        $lease_stmt->bind_param("i", $lease_id);
        $lease_stmt->execute();
        $lease_info = $lease_stmt->get_result()->fetch_assoc();
        $penalty_rate = 0.001; // Default 0.1% per day
        
        // Get outstanding schedules
        $schedule_sql = "SELECT 
                            ls.schedule_id, ls.due_date, ls.annual_amount,
                            COALESCE(SUM(CASE WHEN lp.payment_type = 'rent' THEN lp.amount ELSE 0 END), 0) as paid_rent,
                            (ls.annual_amount - COALESCE(SUM(CASE WHEN lp.payment_type = 'rent' THEN lp.amount ELSE 0 END), 0)) as balance_amount
                         FROM lease_schedules ls 
                         LEFT JOIN lease_payments lp ON ls.schedule_id = lp.schedule_id
                         WHERE ls.lease_id = ? AND ls.due_date <= ? 
                         GROUP BY ls.schedule_id, ls.due_date, ls.annual_amount
                         HAVING balance_amount > 0
                         ORDER BY ls.due_date";
        
        $stmt = $con->prepare($schedule_sql);
        if (!$stmt) {
            throw new Exception("Error preparing schedule query: " . $con->error);
        }
        $stmt->bind_param("is", $lease_id, $payment_date);
        $stmt->execute();
        $schedules = $stmt->get_result();
        
        $remaining_amount = $amount;
        $payments_recorded = [];
        
        // Process payment for each schedule
        while (($schedule = $schedules->fetch_assoc()) && $remaining_amount > 0) {
            if ($schedule['balance_amount'] > 0) {
                // Calculate penalty for this schedule
                $due_date = new DateTime($schedule['due_date']);
                $current_date = new DateTime($payment_date);
                
                if ($current_date > $due_date) {
                    $interval = $due_date->diff($current_date);
                    $days_overdue = $interval->days;
                } else {
                    $days_overdue = 0;
                }
                
                $penalty_amount = 0;
                if ($days_overdue > 0) {
                    $penalty_amount = $schedule['balance_amount'] * $penalty_rate * $days_overdue;
                    $max_penalty = $schedule['balance_amount'] * 0.9;
                    $penalty_amount = min($penalty_amount, $max_penalty);
                }
                
                $total_due = $schedule['balance_amount'] + $penalty_amount;
                $payment_for_schedule = min($total_due, $remaining_amount);
                
                if ($payment_for_schedule > 0) {
                    // Split payment between penalty and rent
                    $penalty_payment = min($penalty_amount, $payment_for_schedule);
                    $rent_payment = $payment_for_schedule - $penalty_payment;
                    
                    // Insert payment record for rent
                    if ($rent_payment > 0) {
                        $payment_sql = "INSERT INTO lease_payments (
                            lease_id, schedule_id, payment_date, amount, payment_type,
                            receipt_number, payment_method, reference_number, notes, created_by
                        ) VALUES (?, ?, ?, ?, 'rent', ?, ?, ?, ?, ?)";
                        
                        $stmt_payment = $con->prepare($payment_sql);
                        if (!$stmt_payment) {
                            throw new Exception("Error preparing rent payment statement: " . $con->error);
                        }
                        
                        $user_id = $_SESSION['user_id'] ?? 1;
                        $stmt_payment->bind_param("iisdssssi", 
                            $lease_id, $schedule['schedule_id'], $payment_date, $rent_payment,
                            $receipt_number, $payment_method, $reference_number, $notes, $user_id
                        );
                        
                        if (!$stmt_payment->execute()) {
                            throw new Exception("Error recording rent payment: " . $stmt_payment->error);
                        }
                        
                        $payments_recorded[] = "Rent: LKR " . number_format($rent_payment, 2);
                    }
                    
                    // Insert payment record for penalty
                    if ($penalty_payment > 0) {
                        $payment_sql = "INSERT INTO lease_payments (
                            lease_id, schedule_id, payment_date, amount, payment_type,
                            receipt_number, payment_method, reference_number, notes, created_by
                        ) VALUES (?, ?, ?, ?, 'penalty', ?, ?, ?, ?, ?)";
                        
                        $stmt_penalty = $con->prepare($payment_sql);
                        if (!$stmt_penalty) {
                            throw new Exception("Error preparing penalty payment statement: " . $con->error);
                        }
                        
                        $user_id = $_SESSION['user_id'] ?? 1;
                        $stmt_penalty->bind_param("iisdssssi", 
                            $lease_id, $schedule['schedule_id'], $payment_date, $penalty_payment,
                            $receipt_number, $payment_method, $reference_number, $notes, $user_id
                        );
                        
                        if (!$stmt_penalty->execute()) {
                            throw new Exception("Error recording penalty payment: " . $stmt_penalty->error);
                        }
                        
                        $payments_recorded[] = "Penalty: LKR " . number_format($penalty_payment, 2);
                    }
                    
                    // Update schedule status if rent is fully paid
                    $new_paid = $schedule['paid_rent'] + $rent_payment;
                    if ($new_paid >= $schedule['annual_amount']) {
                        $update_sql = "UPDATE lease_schedules SET status = 'paid' WHERE schedule_id = ?";
                        $stmt_update = $con->prepare($update_sql);
                        if ($stmt_update) {
                            $stmt_update->bind_param("i", $schedule['schedule_id']);
                            $stmt_update->execute();
                        }
                    }
                    
                    $remaining_amount -= $payment_for_schedule;
                }
            }
        }
        
        UserLog('Lease Management', 'Record Payment', "Recorded payment for lease ID: $lease_id, Receipt: $receipt_number");
        
        $payment_breakdown = implode(', ', $payments_recorded);
        $response['success'] = true;
        $response['message'] = "Payment recorded successfully! Receipt: $receipt_number\nBreakdown: $payment_breakdown";
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
}
?>