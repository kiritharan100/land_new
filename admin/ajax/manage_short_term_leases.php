<?php
require('../db.php');
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$location_id = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);
$action = $_POST['action'] ?? '';

$response = array();

try {
    if ($action == 'add') {
        $land_registration_id = intval($_POST['land_registration_id']);
        $beneficiary_id = intval($_POST['beneficiary_id']);
        $land_usage_purpose_id = intval($_POST['land_usage_purpose_id']);
        $lease_year = intval($_POST['lease_year']);
        $annual_fee = floatval($_POST['annual_fee']);
        $payment_due_date = $_POST['payment_due_date'];
        $special_conditions = trim($_POST['special_conditions']);
        $auto_renewal_enabled = isset($_POST['auto_renewal_enabled']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $created_by = $_SESSION['user_id'];
        
        // Validation
        if ($land_registration_id <= 0 || $beneficiary_id <= 0 || $land_usage_purpose_id <= 0 || 
            $lease_year <= 0 || $annual_fee <= 0 || empty($payment_due_date)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Check if lease already exists for this land registration and year
        $check_query = "SELECT COUNT(*) as count FROM short_term_leases 
                       WHERE location_id = ? AND land_registration_id = ? AND lease_year = ?";
        $stmt = $con->prepare($check_query);
        $stmt->bind_param("iii", $location_id, $land_registration_id, $lease_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            throw new Exception('A lease already exists for this land registration in the selected year');
        }
        
        // Start transaction
        $con->begin_transaction();
        
        // Insert new lease
        $insert_query = "INSERT INTO short_term_leases (location_id, land_registration_id, beneficiary_id, 
                        land_usage_purpose_id, lease_year, start_date, end_date, annual_fee, payment_due_date, 
                        special_conditions, auto_renewal_enabled, status, is_active, created_by, created_on) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, NOW())";
        
        $start_date = $lease_year . '-01-01';
        $end_date = $lease_year . '-12-31';
        
        $stmt = $con->prepare($insert_query);
        $stmt->bind_param("iiiisssdsiiis", $location_id, $land_registration_id, $beneficiary_id, 
                         $land_usage_purpose_id, $lease_year, $start_date, $end_date, $annual_fee, 
                         $payment_due_date, $special_conditions, $auto_renewal_enabled, $is_active, $created_by);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create lease');
        }
        
        $lease_id = $con->insert_id;
        
        // Create initial payment record
        $payment_query = "INSERT INTO short_term_lease_payments (lease_id, payment_year, amount_due, 
                         due_date, payment_status, created_on) VALUES (?, ?, ?, ?, 'PENDING', NOW())";
        $stmt = $con->prepare($payment_query);
        $stmt->bind_param("iids", $lease_id, $lease_year, $annual_fee, $payment_due_date);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create payment record');
        }
        
        $con->commit();
        $response['success'] = true;
        $response['message'] = 'Short-term lease created successfully';
        
    } else if ($action == 'edit') {
        $lease_id = intval($_POST['lease_id']);
        $land_registration_id = intval($_POST['land_registration_id']);
        $beneficiary_id = intval($_POST['beneficiary_id']);
        $land_usage_purpose_id = intval($_POST['land_usage_purpose_id']);
        $lease_year = intval($_POST['lease_year']);
        $annual_fee = floatval($_POST['annual_fee']);
        $payment_due_date = $_POST['payment_due_date'];
        $special_conditions = trim($_POST['special_conditions']);
        $auto_renewal_enabled = isset($_POST['auto_renewal_enabled']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if ($lease_id <= 0 || $land_registration_id <= 0 || $beneficiary_id <= 0 || 
            $land_usage_purpose_id <= 0 || $lease_year <= 0 || $annual_fee <= 0 || empty($payment_due_date)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Check if lease exists for different land registration and year (excluding current)
        $check_query = "SELECT COUNT(*) as count FROM short_term_leases 
                       WHERE location_id = ? AND land_registration_id = ? AND lease_year = ? AND lease_id != ?";
        $stmt = $con->prepare($check_query);
        $stmt->bind_param("iiii", $location_id, $land_registration_id, $lease_year, $lease_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            throw new Exception('A lease already exists for this land registration in the selected year');
        }
        
        // Start transaction
        $con->begin_transaction();
        
        // Update lease
        $update_query = "UPDATE short_term_leases SET land_registration_id = ?, beneficiary_id = ?, 
                        land_usage_purpose_id = ?, lease_year = ?, start_date = ?, end_date = ?, 
                        annual_fee = ?, payment_due_date = ?, special_conditions = ?, 
                        auto_renewal_enabled = ?, is_active = ?, updated_by = ?, updated_on = NOW()
                        WHERE lease_id = ? AND location_id = ?";
        
        $start_date = $lease_year . '-01-01';
        $end_date = $lease_year . '-12-31';
        
        $stmt = $con->prepare($update_query);
        $stmt->bind_param("iiiissdsiiiii", $land_registration_id, $beneficiary_id, $land_usage_purpose_id, 
                         $lease_year, $start_date, $end_date, $annual_fee, $payment_due_date, 
                         $special_conditions, $auto_renewal_enabled, $is_active, $_SESSION['user_id'], 
                         $lease_id, $location_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update lease');
        }
        
        // Update payment record if exists
        $payment_update_query = "UPDATE short_term_lease_payments SET amount_due = ?, due_date = ? 
                               WHERE lease_id = ? AND payment_year = ? AND payment_status = 'PENDING'";
        $stmt = $con->prepare($payment_update_query);
        $stmt->bind_param("dsii", $annual_fee, $payment_due_date, $lease_id, $lease_year);
        $stmt->execute();
        
        $con->commit();
        $response['success'] = true;
        $response['message'] = 'Short-term lease updated successfully';
        
    } else if ($action == 'delete') {
        $lease_id = intval($_POST['lease_id']);
        
        if ($lease_id <= 0) {
            throw new Exception('Invalid lease ID');
        }
        
        // Check if lease has payments
        $payment_check_query = "SELECT COUNT(*) as count FROM short_term_lease_payments 
                              WHERE lease_id = ? AND payment_status != 'PENDING'";
        $stmt = $con->prepare($payment_check_query);
        $stmt->bind_param("i", $lease_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment_count = $result->fetch_assoc()['count'];
        
        if ($payment_count > 0) {
            throw new Exception('Cannot delete lease with existing payments');
        }
        
        // Start transaction
        $con->begin_transaction();
        
        // Delete payment records first
        $delete_payments_query = "DELETE FROM short_term_lease_payments WHERE lease_id = ?";
        $stmt = $con->prepare($delete_payments_query);
        $stmt->bind_param("i", $lease_id);
        $stmt->execute();
        
        // Delete lease
        $delete_query = "DELETE FROM short_term_leases WHERE lease_id = ? AND location_id = ?";
        $stmt = $con->prepare($delete_query);
        $stmt->bind_param("ii", $lease_id, $location_id);
        
        if ($stmt->execute()) {
            $con->commit();
            $response['success'] = true;
            $response['message'] = 'Short-term lease deleted successfully';
        } else {
            throw new Exception('Failed to delete lease');
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($con->autocommit === false) {
        $con->rollback();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
$con->close();
?>