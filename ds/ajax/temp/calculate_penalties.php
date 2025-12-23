<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$location_id = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);
$st_lease_id = intval($_POST['st_lease_id'] ?? $_GET['st_lease_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 1;

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Build the WHERE clause based on parameters
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    if ($st_lease_id > 0) {
        // Calculate for specific lease
        $where_conditions[] = "stl.st_lease_id = ?";
        $params[] = $st_lease_id;
        $param_types .= "i";
    } elseif ($location_id > 0) {
        // Calculate for specific location
        $where_conditions[] = "stl.location_id = ?";
        $params[] = $location_id;
        $param_types .= "i";
    }
    
    // Only calculate for leases where payment is due and not fully paid
    $where_conditions[] = "stl.payment_due_date < CURDATE()";
    $where_conditions[] = "stl.status = 'active'";
    $where_conditions[] = "(stl.is_deleted IS NULL OR stl.is_deleted = 0)";
    $where_conditions[] = "(stl.lease_amount - stl.total_paid) > 0";
    
    $where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get leases that need penalty calculation
    $query = "SELECT stl.st_lease_id, stl.location_id, stl.start_date, stl.payment_due_date,
                     stl.lease_amount, stl.total_paid, stl.penalty_amount, stl.penalty_paid,
                     stl.last_penalty_calc,
                     (stl.lease_amount - stl.total_paid) as total_due,
                     DATEDIFF(CURDATE(), stl.payment_due_date) as days_overdue
              FROM short_term_leases stl
              $where_clause
              ORDER BY stl.payment_due_date ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $leases_processed = 0;
    $total_penalties_calculated = 0;
    
    while ($lease = $result->fetch_assoc()) {
        // Get applicable penalty rate for this lease
        $penalty_query = "SELECT penalty_type, penalty_rate 
                         FROM short_term_penalty_settings 
                         WHERE effective_from <= ? 
                         AND is_active = 1
                         ORDER BY effective_from DESC 
                         LIMIT 1";
        
        $penalty_stmt = $conn->prepare($penalty_query);
        $penalty_stmt->bind_param("s", $lease['start_date']);
        $penalty_stmt->execute();
        $penalty_result = $penalty_stmt->get_result();
        
        if ($penalty_result->num_rows > 0) {
            $penalty_setting = $penalty_result->fetch_assoc();
            $penalty_type = $penalty_setting['penalty_type'];
            $penalty_rate = floatval($penalty_setting['penalty_rate']);
            
            // Calculate penalty amount
            $total_due = floatval($lease['total_due']);
            $days_overdue = intval($lease['days_overdue']);
            
            if ($days_overdue > 0 && $total_due > 0) {
                $penalty_amount = 0;
                
                if ($penalty_type === 'annual') {
                    // Annual penalty rate: calculate based on full year
                    $penalty_amount = ($total_due * $penalty_rate / 100);
                } elseif ($penalty_type === 'monthly') {
                    // Monthly penalty rate: calculate based on months overdue
                    $months_overdue = ceil($days_overdue / 30);
                    $penalty_amount = ($total_due * $penalty_rate / 100) * $months_overdue;
                }
                
                // Only update if penalty amount has changed or this is first calculation
                $current_penalty = floatval($lease['penalty_amount']);
                
                if ($penalty_amount != $current_penalty) {
                    // Update penalty amount in database
                    $update_query = "UPDATE short_term_leases 
                                   SET penalty_amount = ?, 
                                       last_penalty_calc = CURDATE(),
                                       updated_by = ?,
                                       updated_on = NOW()
                                   WHERE st_lease_id = ?";
                    
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("dii", $penalty_amount, $user_id, $lease['st_lease_id']);
                    
                    if ($update_stmt->execute()) {
                        $leases_processed++;
                        $total_penalties_calculated += $penalty_amount;
                        
                        // Log the penalty calculation
                        error_log("Penalty calculated for lease {$lease['st_lease_id']}: " .
                                "Total Due: {$total_due}, Days Overdue: {$days_overdue}, " .
                                "Penalty Type: {$penalty_type}, Rate: {$penalty_rate}%, " .
                                "Penalty Amount: {$penalty_amount}");
                    }
                    
                    $update_stmt->close();
                }
            }
        }
        
        $penalty_stmt->close();
    }
    
    $stmt->close();
    
    if ($leases_processed > 0) {
        $message = "Penalties calculated successfully for {$leases_processed} lease(s). " .
                   "Total penalties: LKR " . number_format($total_penalties_calculated, 2);
    } else {
        $message = "No leases found requiring penalty calculation.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'leases_processed' => $leases_processed,
        'total_penalties' => $total_penalties_calculated
    ]);
    
} catch (Exception $e) {
    error_log("Error in calculate_penalties.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>