<?php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$rate_id = intval($_GET['rate_id']);

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    if (empty($rate_id)) {
        throw new Exception('Rate ID is required');
    }
    
    $sql = "SELECT * FROM short_term_penalty_settings WHERE setting_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $rate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rate = $result->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $rate]);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Penalty rate not found']);
    }
    
} catch (Exception $e) {
    error_log("Error in get_penalty_rate.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>