<?php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$purpose_id = intval($_GET['purpose_id']);

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    if (empty($purpose_id)) {
        throw new Exception('Purpose ID is required');
    }
    
    $sql = "SELECT * FROM land_usage_purposes WHERE purpose_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $purpose_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $purpose = $result->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $purpose]);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Purpose not found']);
    }
    
} catch (Exception $e) {
    error_log("Error in get_land_usage_purpose.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>