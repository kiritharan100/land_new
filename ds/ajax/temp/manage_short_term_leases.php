<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
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
    if ($action == 'add') {
        $land_id = intval($_POST['land_registration_id']);
        $beneficiary_id = intval($_POST['beneficiary_id']);
        $purpose_id = intval($_POST['land_usage_purpose_id']);
        $lease_year = intval($_POST['lease_year']);
        $lease_amount = floatval($_POST['annual_fee']);
        $payment_due_date = $_POST['payment_due_date'];
        $start_date = $_POST['start_date'];
        $remarks = trim($_POST['special_conditions']);
        $auto_renew = isset($_POST['auto_renewal_enabled']) ? 1 : 0;
        $status = 'active'; // Always set new leases to active

        // Derive lease_year from start_date if missing or invalid
        if (($lease_year <= 0 || $lease_year < 1900) && !empty($start_date)) {
            $ts = strtotime($start_date);
            if ($ts !== false) {
                $lease_year = (int)date('Y', $ts);
            }
        }
        
        // Validation
        if ($land_id <= 0 || $beneficiary_id <= 0 || $purpose_id <= 0 || 
            $lease_year <= 0 || $lease_amount <= 0 || empty($payment_due_date) || empty($start_date)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Prevent duplicates only when an existing lease is not cancelled
        $check_query = "SELECT COUNT(*) AS cnt FROM short_term_leases 
                         WHERE location_id = ? AND land_id = ? AND lease_year = ? 
                           AND (status <> 'cancelled' OR status IS NULL)";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            throw new Exception('Failed to prepare duplicate check: ' . $conn->error);
        }
        $stmt->bind_param('iii', $location_id, $land_id, $lease_year);
        if (!$stmt->execute()) {
            $err = $stmt->error; $stmt->close();
            throw new Exception('Failed to execute duplicate check: ' . $err);
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (intval($row['cnt']) > 0) {
            throw new Exception('A non-cancelled lease already exists for this land and year');
        }
        
        // Insert new lease (temporarily set auto_renew to 0 to avoid trigger issues)
        // Determine end_date: use provided value if valid, else default to end of start year
        $end_date = null;
        if (!empty($_POST['end_date'])) {
            $end_date_try = $_POST['end_date'];
            $tsEnd = strtotime($end_date_try);
            if ($tsEnd !== false) {
                $end_date = date('Y-m-d', $tsEnd);
            }
        }
        if (!$end_date) {
            $tsStart = strtotime($start_date);
            $year = $tsStart ? (int)date('Y', $tsStart) : $lease_year;
            $end_date = $year . '-12-31';
        }
        $lease_number = 'STL-' . $location_id . '-' . $lease_year . '-' . str_pad($land_id, 4, '0', STR_PAD_LEFT);
        
        $insert_query = "INSERT INTO short_term_leases (location_id, land_id, beneficiary_id, 
                        purpose_id, lease_number, lease_year, start_date, end_date, lease_amount, 
                        payment_due_date, auto_renew, payment_status, total_paid, penalty_amount, 
                        penalty_paid, last_penalty_calc, status, remarks, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'unpaid', 0.00, 0.00, 0.00, NULL, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception('Failed to prepare insert query: ' . $conn->error);
        }
        
        $stmt->bind_param("iiiisissdssii", $location_id, $land_id, $beneficiary_id, 
                         $purpose_id, $lease_number, $lease_year, $start_date, $end_date, 
                         $lease_amount, $payment_due_date, $status, $remarks, $user_id);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Failed to create lease: ' . $error);
        }
        
        $lease_id = $conn->insert_id;
        $stmt->close();
        
        // Now update auto_renew separately to avoid trigger issues
        if ($auto_renew == 1) {
            $update_auto_renew = "UPDATE short_term_leases SET auto_renew = 1 WHERE st_lease_id = ?";
            $update_stmt = $conn->prepare($update_auto_renew);
            if ($update_stmt) {
                $update_stmt->bind_param("i", $lease_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }

        // Trigger penalty calculation script for this lease (best-effort, non-blocking where possible)
        try {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            // manage_short_term_leases.php is in /ds/ajax; go up one to /ds for the script
            $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');        // e.g., /land/ds/ajax
            $dsDir = rtrim(dirname($baseDir), '/\\');                         // e.g., /land/ds
            $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dsDir . '/cal_penalty_short_lease.php?st_lease_id=' . $lease_id;

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10); // fast timeout
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_exec($ch);
                curl_close($ch);
            } else {
                // Fallback; ignore response
                @file_get_contents($url);
            }
        } catch (Throwable $e) {
            // Best-effort: don't block or fail the main operation
            error_log('Penalty trigger failed for lease ' . $lease_id . ': ' . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Short-term lease created successfully', 'lease_id' => $lease_id]);
        
    } else if ($action == 'edit') {
        $lease_id = intval($_POST['lease_id']);
        $land_id = intval($_POST['land_registration_id']);
        $beneficiary_id = intval($_POST['beneficiary_id']);
        $purpose_id = intval($_POST['land_usage_purpose_id']);
        $lease_year = intval($_POST['lease_year']);
        $lease_amount = floatval($_POST['annual_fee']);
        $payment_due_date = $_POST['payment_due_date'];
        $start_date = $_POST['start_date'];
        $remarks = trim($_POST['special_conditions']);
        $auto_renew = isset($_POST['auto_renewal_enabled']) ? 1 : 0;

        // Derive lease_year from start_date if missing or invalid
        if (($lease_year <= 0 || $lease_year < 1900) && !empty($start_date)) {
            $ts = strtotime($start_date);
            if ($ts !== false) {
                $lease_year = (int)date('Y', $ts);
            }
        }
        
        // Get current status to preserve it if is_active checkbox is unchecked
        $current_status_query = "SELECT status FROM short_term_leases WHERE st_lease_id = ?";
        $current_stmt = $conn->prepare($current_status_query);
        $current_stmt->bind_param("i", $lease_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current_lease = $current_result->fetch_assoc();
        $current_stmt->close();
        
        // Only change to active if checkbox is checked, otherwise preserve current status
        if (isset($_POST['is_active']) && $_POST['is_active']) {
            $status = 'active';
        } else {
            $status = $current_lease['status'];
        }
        
        // Debug: Log the date values being received
        error_log("EDIT LEASE DEBUG - Lease ID: $lease_id");
        error_log("EDIT LEASE DEBUG - Start Date received: " . $start_date);
        error_log("EDIT LEASE DEBUG - Payment Due Date received: " . $payment_due_date);
        error_log("EDIT LEASE DEBUG - is_active POST value: " . (isset($_POST['is_active']) ? $_POST['is_active'] : 'NOT_SET'));
        error_log("EDIT LEASE DEBUG - auto_renewal_enabled POST value: " . (isset($_POST['auto_renewal_enabled']) ? $_POST['auto_renewal_enabled'] : 'NOT_SET'));
        error_log("EDIT LEASE DEBUG - Current status from DB: " . $current_lease['status']);
        error_log("EDIT LEASE DEBUG - Final status to save: " . $status);
        error_log("EDIT LEASE DEBUG - Auto Renew: " . $auto_renew);
        
        // Validation
        if ($lease_id <= 0 || $land_id <= 0 || $beneficiary_id <= 0 || 
            $purpose_id <= 0 || $lease_year <= 0 || $lease_amount <= 0 || 
            empty($payment_due_date) || empty($start_date)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Prevent duplicates only when an existing lease is not cancelled (excluding this record)
        $check_query = "SELECT COUNT(*) AS cnt FROM short_term_leases 
                         WHERE location_id = ? AND land_id = ? AND lease_year = ? 
                           AND (status <> 'cancelled' OR status IS NULL)
                           AND st_lease_id <> ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            throw new Exception('Failed to prepare duplicate check: ' . $conn->error);
        }
        $stmt->bind_param('iiii', $location_id, $land_id, $lease_year, $lease_id);
        if (!$stmt->execute()) {
            $err = $stmt->error; $stmt->close();
            throw new Exception('Failed to execute duplicate check: ' . $err);
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (intval($row['cnt']) > 0) {
            throw new Exception('A non-cancelled lease already exists for this land and year');
        }
        
        // Update lease
        // Determine end_date: use provided value if valid, else default to end of start year
        $end_date = null;
        if (!empty($_POST['end_date'])) {
            $end_date_try = $_POST['end_date'];
            $tsEnd = strtotime($end_date_try);
            if ($tsEnd !== false) {
                $end_date = date('Y-m-d', $tsEnd);
            }
        }
        if (!$end_date) {
            $tsStart = strtotime($start_date);
            $year = $tsStart ? (int)date('Y', $tsStart) : $lease_year;
            $end_date = $year . '-12-31';
        }
        $lease_number = 'STL-' . $location_id . '-' . $lease_year . '-' . str_pad($land_id, 4, '0', STR_PAD_LEFT);
        
        $update_query = "UPDATE short_term_leases SET land_id = ?, beneficiary_id = ?, 
                        purpose_id = ?, lease_number = ?, lease_year = ?, start_date = ?, end_date = ?, 
                        lease_amount = ?, payment_due_date = ?, auto_renew = ?, status = ?, remarks = ?, 
                        updated_by = ?, updated_on = NOW()
                        WHERE st_lease_id = ? AND location_id = ?";
        
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception('Failed to prepare update query: ' . $conn->error);
        }
        
        // Debug: Log the parameters being bound
        error_log("UPDATE PARAMS - land_id: $land_id, beneficiary_id: $beneficiary_id, purpose_id: $purpose_id");
        error_log("UPDATE PARAMS - lease_number: $lease_number, lease_year: $lease_year");
        error_log("UPDATE PARAMS - start_date: $start_date, end_date: $end_date");
        error_log("UPDATE PARAMS - lease_amount: $lease_amount, payment_due_date: $payment_due_date");
        error_log("UPDATE PARAMS - auto_renew: $auto_renew, status: $status, remarks: $remarks");
        error_log("UPDATE PARAMS - user_id: $user_id, lease_id: $lease_id, location_id: $location_id");
        
        // Count parameters for debugging
        $params = [$land_id, $beneficiary_id, $purpose_id, $lease_number, $lease_year, 
                  $start_date, $end_date, $lease_amount, $payment_due_date, $auto_renew, 
                  $status, $remarks, $user_id, $lease_id, $location_id];
        error_log("PARAM COUNT DEBUG - Expected: 15, Actual: " . count($params));
        error_log("PARAM TYPES DEBUG - Binding string: iiisisdsissiii (length: " . strlen("iiisisdsissiii") . ")");
        
        $stmt->bind_param("iiisisdssissiii", $land_id, $beneficiary_id, $purpose_id, 
                         $lease_number, $lease_year, $start_date, $end_date, $lease_amount, 
                         $payment_due_date, $auto_renew, $status, $remarks, $user_id, 
                         $lease_id, $location_id);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Failed to update lease: ' . $error);
        }
        
        $affected_rows = $stmt->affected_rows;
        error_log("UPDATE RESULT - Affected rows: $affected_rows");
        
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new Exception('No records were updated. Lease may not exist or belong to this location.');
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Short-term lease updated successfully']);
        
    } else if ($action == 'delete') {
        $lease_id = intval($_POST['lease_id']);
        
        if ($lease_id <= 0) {
            throw new Exception('Invalid lease ID');
        }

        // Prevent deletion if there are active (non-cancelled) payments
        $payCheckSql = "SELECT COUNT(*) AS cnt FROM short_term_lease_payments WHERE st_lease_id = ? AND total_amount > 0";
        $payStmt = $conn->prepare($payCheckSql);
        if (!$payStmt) {
            throw new Exception('Failed to prepare payment check: ' . $conn->error);
        }
        $payStmt->bind_param('i', $lease_id);
        if (!$payStmt->execute()) {
            $err = $payStmt->error; $payStmt->close();
            throw new Exception('Failed to execute payment check: ' . $err);
        }
        $payRes = $payStmt->get_result();
        $payRow = $payRes->fetch_assoc();
        $payStmt->close();
        if (intval($payRow['cnt']) > 0) {
            throw new Exception('Lease cannot be deleted: active payments exist. Cancel payments first.');
        }
        
        // Check if is_deleted column exists, if not add it
        $check_column_query = "SHOW COLUMNS FROM short_term_leases LIKE 'is_deleted'";
        $column_result = $conn->query($check_column_query);
        
        if ($column_result->num_rows === 0) {
            // Add is_deleted column
            $add_column_query = "ALTER TABLE short_term_leases ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status";
            if (!$conn->query($add_column_query)) {
                throw new Exception('Failed to add is_deleted column: ' . $conn->error);
            }
        }
        
        // Soft delete lease (set is_deleted = 1)
        $delete_query = "UPDATE short_term_leases SET is_deleted = 1, status = 'cancelled', updated_by = ?, updated_on = NOW() WHERE st_lease_id = ? AND location_id = ?";
        $stmt = $conn->prepare($delete_query);
        if (!$stmt) {
            throw new Exception('Failed to prepare delete query: ' . $conn->error);
        }
        
        $stmt->bind_param("iii", $user_id, $lease_id, $location_id);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Failed to delete lease: ' . $error);
        }
        
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new Exception('No records were deleted. Lease may not exist or belong to this location.');
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Short-term lease deleted successfully']);
        
    } else if ($action == 'restore') {
        $lease_id = intval($_POST['lease_id']);
        
        if ($lease_id <= 0) {
            throw new Exception('Invalid lease ID');
        }
        
        // Restore lease (set is_deleted = 0)
        $restore_query = "UPDATE short_term_leases SET is_deleted = 0, status = 'active', updated_by = ?, updated_on = NOW() WHERE st_lease_id = ? AND location_id = ?";
        $stmt = $conn->prepare($restore_query);
        if (!$stmt) {
            throw new Exception('Failed to prepare restore query: ' . $conn->error);
        }
        
        $stmt->bind_param("iii", $user_id, $lease_id, $location_id);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Failed to restore lease: ' . $error);
        }
        
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new Exception('No records were restored. Lease may not exist or belong to this location.');
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Short-term lease restored successfully']);
        
    } else {
        throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Error in manage_short_term_leases.php: " . $e->getMessage());
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