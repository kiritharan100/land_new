<?php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$location_id = $_GET['location_id'] ?? '';

if (empty($location_id)) {
    echo json_encode(['success' => false, 'message' => 'Location ID is required']);
    exit();
}

try {
        $query = "SELECT ben_id as id, CONCAT(name, ' - ', nic_reg_no) as text,
                name as beneficiary_name, nic_reg_no as nic_no, telephone as mobile_no
            FROM short_term_beneficiaries 
            WHERE location_id = ? AND status = 1
            ORDER BY name";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $location_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'text' => $row['text'],
            'beneficiary_name' => $row['beneficiary_name'],
            'nic_no' => $row['nic_no'],
            'mobile_no' => $row['mobile_no']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total_count' => count($data)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_beneficiaries.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>