<?php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in again.']);
    exit;
}

$location_id = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 1;

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    if (empty($action)) {
        throw new Exception('Action parameter is required');
    }
    
    if ($location_id <= 0) {
        throw new Exception('Valid location ID is required');
    }
    
    switch ($action) {
        case 'add':
            $penalty_type = trim($_POST['penalty_type'] ?? '');
            $penalty_rate = floatval($_POST['penalty_rate'] ?? 0);
            $effective_from = $_POST['effective_from'] ?? '';
            $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
            $description = trim($_POST['description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            // Validation
            if (empty($penalty_type) || $penalty_rate < 0 || empty($effective_from)) {
                throw new Exception('Please fill in all required fields');
            }
            
            if ($penalty_rate > 100) {
                throw new Exception('Penalty rate cannot exceed 100%');
            }
            
            // Insert new penalty rate
            $insert_query = "INSERT INTO short_term_penalty_settings (location_id, penalty_type, penalty_rate, 
                            effective_from, effective_to, description, is_active, created_by, created_on) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare insert query: ' . $conn->error);
            }
            
            $stmt->bind_param("isdsssii", $location_id, $penalty_type, $penalty_rate, $effective_from, 
                             $effective_to, $description, $is_active, $user_id);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to add penalty rate: ' . $error);
            }
            
            $new_id = $conn->insert_id;
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Penalty rate added successfully', 'rate_id' => $new_id]);
            break;
            
        case 'edit':
            $rate_id = intval($_POST['rate_id'] ?? 0);
            $penalty_type = trim($_POST['penalty_type'] ?? '');
            $penalty_rate = floatval($_POST['penalty_rate'] ?? 0);
            $effective_from = $_POST['effective_from'] ?? '';
            $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
            $description = trim($_POST['description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            // Debug logging
            error_log("Edit penalty rate debug: rate_id=$rate_id, penalty_type=$penalty_type, penalty_rate=$penalty_rate, effective_from=$effective_from, effective_to=$effective_to, is_active=$is_active, location_id=$location_id");
            
            // Validation
            if (empty($penalty_type) || $penalty_rate < 0 || empty($effective_from) || $rate_id <= 0) {
                throw new Exception('Please fill in all required fields');
            }
            
            if ($penalty_rate > 100) {
                throw new Exception('Penalty rate cannot exceed 100%');
            }
            
            // Update penalty rate
            $update_query = "UPDATE short_term_penalty_settings SET penalty_type = ?, penalty_rate = ?, effective_from = ?, 
                            effective_to = ?, description = ?, is_active = ?, updated_by = ?, updated_on = NOW()
                            WHERE setting_id = ? AND location_id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare update query: ' . $conn->error);
            }
            
            $stmt->bind_param("sdsssiiii", $penalty_type, $penalty_rate, $effective_from, $effective_to, 
                             $description, $is_active, $user_id, $rate_id, $location_id);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to update penalty rate: ' . $error);
            }
            
            if ($stmt->affected_rows === 0) {
                $stmt->close();
                // Add more specific debugging for this case
                error_log("No rows affected. Debug: rate_id=$rate_id, location_id=$location_id");
                throw new Exception('No records were updated. Rate may not exist or belong to this location.');
            }
            
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Penalty rate updated successfully']);
            break;
            
        case 'delete':
            $rate_id = intval($_POST['rate_id'] ?? 0);
            
            if ($rate_id <= 0) {
                throw new Exception('Invalid penalty rate ID');
            }
            
            // Delete penalty rate
            $delete_query = "DELETE FROM short_term_penalty_settings WHERE setting_id = ? AND location_id = ?";
            $stmt = $conn->prepare($delete_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare delete query: ' . $conn->error);
            }
            
            $stmt->bind_param("ii", $rate_id, $location_id);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to delete penalty rate: ' . $error);
            }
            
            if ($stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception('No records were deleted. Rate may not exist or belong to this location.');
            }
            
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Penalty rate deleted successfully']);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Error in manage_penalty_rates.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'action' => $action ?? 'unknown',
            'location_id' => $location_id
        ]
    ]);
}
?>