<?php
// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);

try {
    // Include database connection
    require_once '../../db.php';
    
    // Verify database connection exists (MySQLi connection)
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection not established: ' . ($conn->connect_error ?? 'Unknown error'));
    }
    
    // Check if user is logged in
    if (empty($_SESSION['username'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required. Please log in again.',
            'debug' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'session_vars' => array_keys($_SESSION)
            ]
        ]);
        exit;
    }
    
    // Get parameters
    $user_id = $_SESSION['user_id'] ?? 1;
    $username = $_SESSION['username'];
    $action = $_POST['action'] ?? '';
    $location_id = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);
    
    // Validate required parameters
    if (empty($action)) {
        throw new Exception('Action parameter is required');
    }
    
    if ($location_id <= 0) {
        throw new Exception('Valid location ID is required. Received: ' . $location_id);
    }
    
    // Log the request for debugging
    error_log("Land Usage Purpose Request: User=$username, Action=$action, LocationID=$location_id");
    
    switch ($action) {
        case 'add':
            // Get and validate form data
            $purpose_name = trim($_POST['purpose_name'] ?? '');
            $purpose_description = trim($_POST['purpose_description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            // Validation
            if (empty($purpose_name)) {
                throw new Exception('Purpose name is required and cannot be empty');
            }
            
            if (strlen($purpose_name) > 100) {
                throw new Exception('Purpose name cannot exceed 100 characters');
            }
            
            // Check for duplicate
            $check_sql = "SELECT purpose_id FROM land_usage_purposes 
                         WHERE purpose_name = ? AND location_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception('Failed to prepare duplicate check query: ' . $conn->error);
            }
            
            $check_stmt->bind_param('si', $purpose_name, $location_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_stmt->close();
                throw new Exception('A purpose with this name already exists in this location');
            }
            $check_stmt->close();
            
            // Insert new purpose
            $insert_sql = "INSERT INTO land_usage_purposes 
                          (location_id, purpose_name, purpose_description, is_active, created_by, created_on) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
            
            $insert_stmt = $conn->prepare($insert_sql);
            if (!$insert_stmt) {
                throw new Exception('Failed to prepare insert query: ' . $conn->error);
            }
            
            $insert_stmt->bind_param('issii', $location_id, $purpose_name, $purpose_description, $is_active, $user_id);
            $result = $insert_stmt->execute();
            
            if (!$result) {
                $error = $insert_stmt->error;
                $insert_stmt->close();
                throw new Exception('Database insert failed: ' . $error);
            }
            
            $new_id = $conn->insert_id;
            $insert_stmt->close();
            error_log("Successfully added purpose ID: $new_id");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Land usage purpose added successfully',
                'purpose_id' => $new_id
            ]);
            break;
            
        case 'edit':
            $purpose_id = intval($_POST['purpose_id'] ?? 0);
            $purpose_name = trim($_POST['purpose_name'] ?? '');
            $purpose_description = trim($_POST['purpose_description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            if ($purpose_id <= 0) {
                throw new Exception('Valid purpose ID is required for editing');
            }
            
            if (empty($purpose_name)) {
                throw new Exception('Purpose name is required and cannot be empty');
            }
            
            // Check for duplicate (excluding current record)
            $check_sql = "SELECT purpose_id FROM land_usage_purposes 
                         WHERE purpose_name = ? AND location_id = ? AND purpose_id != ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception('Failed to prepare duplicate check query: ' . $conn->error);
            }
            
            $check_stmt->bind_param('sii', $purpose_name, $location_id, $purpose_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $check_stmt->close();
                throw new Exception('Another purpose with this name already exists');
            }
            $check_stmt->close();
            
            // Update purpose
            $update_sql = "UPDATE land_usage_purposes 
                          SET purpose_name = ?, purpose_description = ?, is_active = ?, 
                              updated_by = ?, updated_on = NOW() 
                          WHERE purpose_id = ? AND location_id = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception('Failed to prepare update query: ' . $conn->error);
            }
            
            $update_stmt->bind_param('ssiiii', $purpose_name, $purpose_description, $is_active, $user_id, $purpose_id, $location_id);
            $result = $update_stmt->execute();
            
            if (!$result) {
                $error = $update_stmt->error;
                $update_stmt->close();
                throw new Exception('Database update failed: ' . $error);
            }
            
            if ($update_stmt->affected_rows === 0) {
                $update_stmt->close();
                throw new Exception('No records were updated. Purpose may not exist or belong to this location.');
            }
            
            $update_stmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Land usage purpose updated successfully'
            ]);
            break;
            
        case 'delete':
            $purpose_id = intval($_POST['purpose_id'] ?? 0);
            
            if ($purpose_id <= 0) {
                throw new Exception('Valid purpose ID is required for deletion');
            }
            
            // Check if purpose is being used
            $usage_check = "SELECT COUNT(*) as count FROM short_term_leases 
                           WHERE land_usage_purpose_id = ? AND location_id = ?";
            $usage_stmt = $conn->prepare($usage_check);
            if ($usage_stmt) {
                $usage_stmt->bind_param('ii', $purpose_id, $location_id);
                $usage_stmt->execute();
                $usage_result = $usage_stmt->get_result();
                $usage_data = $usage_result->fetch_assoc();
                $usage_stmt->close();
                
                if ($usage_data['count'] > 0) {
                    throw new Exception('Cannot delete this purpose as it is currently being used in ' . 
                                      $usage_data['count'] . ' lease(s)');
                }
            }
            
            // Delete purpose
            $delete_sql = "DELETE FROM land_usage_purposes WHERE purpose_id = ? AND location_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            if (!$delete_stmt) {
                throw new Exception('Failed to prepare delete query: ' . $conn->error);
            }
            
            $delete_stmt->bind_param('ii', $purpose_id, $location_id);
            $result = $delete_stmt->execute();
            
            if (!$result) {
                $error = $delete_stmt->error;
                $delete_stmt->close();
                throw new Exception('Database delete failed: ' . $error);
            }
            
            if ($delete_stmt->affected_rows === 0) {
                $delete_stmt->close();
                throw new Exception('No records were deleted. Purpose may not exist or belong to this location.');
            }
            
            $delete_stmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Land usage purpose deleted successfully'
            ]);
            break;
            
        case 'test':
            echo json_encode([
                'success' => true,
                'message' => 'Connection test successful',
                'debug_info' => [
                    'user' => $username,
                    'user_id' => $user_id,
                    'location_id' => $location_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'database' => 'Connected'
                ]
            ]);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Error in manage_land_usage_purposes.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'Exception',
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'action' => $action ?? 'unknown'
        ]
    ]);
} catch (Throwable $e) {
    error_log("Fatal Error in manage_land_usage_purposes.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A fatal error occurred: ' . $e->getMessage(),
        'error_type' => 'Fatal',
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

// Clean up output buffer
ob_end_flush();
?>