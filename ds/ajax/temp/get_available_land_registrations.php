<?php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$location_id = $_GET['location_id'] ?? '';
$editing_lease_id = $_GET['editing_lease_id'] ?? '';

if (empty($location_id)) {
    echo json_encode(['success' => false, 'message' => 'Location ID is required']);
    exit();
}

try {
    // Query to get land registrations - simplified version without lease exclusions for now
        $sql = "SELECT DISTINCT lr.land_id as id, 
                 CONCAT('LR-', lr.land_id, ' - ', 
                     COALESCE(lr.address, 'No Address'), 
                     ' (Area: ', COALESCE(lr.lcg_hectares, 0), ' hectares)') as text,
                 lr.land_id as reg_no,
                 COALESCE(lr.address, 'No Address') as name,
                 COALESCE(lr.lcg_hectares, 0) as extent,
                 'hectares' as extent_unit,
                 lr.ds_id,
                 lr.gn_id,
                 lr.latitude,
                 lr.longitude
             FROM short_term_land_registration lr 
            WHERE lr.ds_id IN (
                SELECT ds_id FROM client_registration WHERE c_id = ?
            )
            ORDER BY lr.land_id ASC";
    
    $params = [$location_id];
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    // Bind single parameter
    $stmt->bind_param('i', $params[0]);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'text' => $row['text'],
            'reg_no' => $row['reg_no'],
            'name' => $row['name'],
            'extent' => $row['extent'],
            'extent_unit' => $row['extent_unit'],
            'ds_id' => $row['ds_id'],
            'gn_id' => $row['gn_id'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'data' => $data,
        'total_available' => count($data)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_available_land_registrations.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>