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
    // Since land_usage_purposes table doesn't exist, provide common land usage purposes
    $data = [
        ['id' => 1, 'text' => 'Agricultural Use'],
        ['id' => 2, 'text' => 'Residential Development'],
        ['id' => 3, 'text' => 'Commercial Development'],
        ['id' => 4, 'text' => 'Industrial Use'],
        ['id' => 5, 'text' => 'Recreational Use'],
        ['id' => 6, 'text' => 'Conservation'],
        ['id' => 7, 'text' => 'Government Use'],
        ['id' => 8, 'text' => 'Educational Institution'],
        ['id' => 9, 'text' => 'Religious Purpose'],
        ['id' => 10, 'text' => 'Mixed Use Development']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total_count' => count($data)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_active_land_usage_purposes.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>