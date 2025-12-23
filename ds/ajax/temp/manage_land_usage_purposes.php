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
            $purpose_name = trim($_POST['purpose_name'] ?? '');
            $purpose_description = trim($_POST['purpose_description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            // Validation
            if (empty($purpose_name)) {
                throw new Exception('Purpose name is required');
            }
            
            // Check for duplicate purpose name
            $check_query = "SELECT purpose_id FROM land_usage_purposes WHERE location_id = ? AND purpose_name = ?";
            $check_stmt = $conn->prepare($check_query);
            if (!$check_stmt) {
                throw new Exception('Failed to prepare duplicate check query: ' . $conn->error);
            }
            
            $check_stmt->bind_param('is', $location_id, $purpose_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_stmt->close();
                throw new Exception('A purpose with this name already exists for this location');
            }
            $check_stmt->close();
            
            // Insert new purpose
            $insert_query = "INSERT INTO land_usage_purposes (location_id, purpose_name, purpose_description, is_active, created_by, created_on) 
                            VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare insert query: ' . $conn->error);
            }
            
            $stmt->bind_param('issii', $location_id, $purpose_name, $purpose_description, $is_active, $user_id);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to add land usage purpose: ' . $error);
            }
            
            $new_id = $conn->insert_id;
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Land usage purpose added successfully', 'purpose_id' => $new_id]);
            break;
            
        case 'edit':
            $purpose_id = intval($_POST['purpose_id'] ?? 0);
            $purpose_name = trim($_POST['purpose_name'] ?? '');
            $purpose_description = trim($_POST['purpose_description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            // Validation
            if (empty($purpose_name) || $purpose_id <= 0) {
                throw new Exception('Purpose name and ID are required');
            }
            
            // Check for duplicate purpose name (excluding current record)
            $check_query = "SELECT purpose_id FROM land_usage_purposes WHERE location_id = ? AND purpose_name = ? AND purpose_id != ?";
            $check_stmt = $conn->prepare($check_query);
            if (!$check_stmt) {
                throw new Exception('Failed to prepare duplicate check query: ' . $conn->error);
            }
            
            $check_stmt->bind_param('isi', $location_id, $purpose_name, $purpose_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_stmt->close();
                throw new Exception('A purpose with this name already exists for this location');
            }
            $check_stmt->close();
            
            // Update purpose
            $update_query = "UPDATE land_usage_purposes SET purpose_name = ?, purpose_description = ?, is_active = ?, updated_by = ?, updated_on = NOW()
                            WHERE purpose_id = ? AND location_id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare update query: ' . $conn->error);
            }
            
            $stmt->bind_param('ssiiii', $purpose_name, $purpose_description, $is_active, $user_id, $purpose_id, $location_id);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to update land usage purpose: ' . $error);
            }
            
            if ($stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception('No records were updated. Purpose may not exist or belong to this location.');
            }
            
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Land usage purpose updated successfully']);
            break;
            
        case 'delete':
            $purpose_id = intval($_POST['purpose_id'] ?? 0);
            
            if ($purpose_id <= 0) {
                throw new Exception('Invalid purpose ID');
            }
            
            // Check if purpose is being used in any leases or other records
            $usage_check = "SELECT COUNT(*) as count FROM short_term_leases WHERE land_usage_purpose_id = ?";
            $usage_stmt = $conn->prepare($usage_check);
            if ($usage_stmt) {
                $usage_stmt->bind_param('i', $purpose_id);
                $usage_stmt->execute();
                $usage_result = $usage_stmt->get_result();
                $usage_row = $usage_result->fetch_assoc();
                $usage_stmt->close();
                
                if ($usage_row['count'] > 0) {
                    throw new Exception('Cannot delete this purpose as it is being used in ' . $usage_row['count'] . ' lease record(s)');
                }
            }
            
            // Delete purpose
            $delete_query = "DELETE FROM land_usage_purposes WHERE purpose_id = ? AND location_id = ?";
            $stmt = $conn->prepare($delete_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare delete query: ' . $conn->error);
            }
            
            $stmt->bind_param('ii', $purpose_id, $location_id);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to delete land usage purpose: ' . $error);
            }
            
            if ($stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception('No records were deleted. Purpose may not exist or belong to this location.');
            }
            
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Land usage purpose deleted successfully']);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Error in manage_land_usage_purposes.php: " . $e->getMessage());
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