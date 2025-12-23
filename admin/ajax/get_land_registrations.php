<?php
require('../db.php');
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$location_id = intval($_GET['location_id']);
$response = array();

try {
    $query = "SELECT id, CONCAT(reg_no, ' - ', district, ', ', ds_division, ', ', gn_division) as text
              FROM land_registration 
              WHERE location_id = ? AND is_active = 1
              ORDER BY reg_no";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'text' => $row['text']
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $data;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
$con->close();
?>