<?php
session_start();
header('Content-Type: application/json');

require_once '../../db.php';

if (empty($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$location_id = intval($_GET['location_id']);

// DataTables parameters
$draw = intval($_GET['draw']);
$start = intval($_GET['start']);
$length = intval($_GET['length']);
$search_value = $_GET['search']['value'];
$order_column = $_GET['order'][0]['column'];
$order_dir = $_GET['order'][0]['dir'];

// Column mapping
$columns = ['purpose_id', 'purpose_name', 'purpose_description', 'is_active', 'created_by', 'created_on', 'actions'];
$order_column = $columns[$order_column];

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Base query - updated column names to match schema
    $base_query = "FROM land_usage_purposes lup
                   LEFT JOIN user_license ul ON lup.created_by = ul.usr_id
                   WHERE lup.location_id = ?";
    
    // Search condition
    $search_condition = "";
    if (!empty($search_value)) {
        $search_condition = " AND (lup.purpose_name LIKE ? OR lup.purpose_description LIKE ?)";
    }
    
    // Count total records
    $total_query = "SELECT COUNT(*) as total " . $base_query;
    $stmt = $conn->prepare($total_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare total count query: ' . $conn->error);
    }
    $stmt->bind_param('i', $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_records = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Count filtered records
    $filtered_query = "SELECT COUNT(*) as total " . $base_query . $search_condition;
    $stmt = $conn->prepare($filtered_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare filtered count query: ' . $conn->error);
    }
    
    if (!empty($search_value)) {
        $search_param = "%{$search_value}%";
        $stmt->bind_param('iss', $location_id, $search_param, $search_param);
    } else {
        $stmt->bind_param('i', $location_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $filtered_records = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Get data
    $data_query = "SELECT lup.purpose_id, lup.purpose_name, lup.purpose_description, 
                          lup.is_active, ul.i_name as created_by_name, lup.created_on
                   " . $base_query . $search_condition . "
                   ORDER BY lup.{$order_column} {$order_dir}
                   LIMIT ?, ?";
    
    $stmt = $conn->prepare($data_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare data query: ' . $conn->error);
    }
    
    if (!empty($search_value)) {
        $search_param = "%{$search_value}%";
        $stmt->bind_param('issii', $location_id, $search_param, $search_param, $start, $length);
    } else {
        $stmt->bind_param('iii', $location_id, $start, $length);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $status_badge = $row['is_active'] ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-secondary">Inactive</span>';
        
        $actions = '
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-info" onclick="editPurpose(' . $row['purpose_id'] . ')" title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="deletePurpose(' . $row['purpose_id'] . ')" title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>';
        
        $data[] = [
            $row['purpose_id'],
            htmlspecialchars($row['purpose_name']),
            htmlspecialchars($row['purpose_description'] ?: '-'),
            $status_badge,
            htmlspecialchars($row['created_by_name'] ?: 'System'),
            date('Y-m-d H:i', strtotime($row['created_on'])),
            $actions
        ];
    }
    $stmt->close();
    
    $response = [
        'draw' => $draw,
        'recordsTotal' => $total_records,
        'recordsFiltered' => $filtered_records,
        'data' => $data
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in fetch_land_usage_purposes.php: " . $e->getMessage());
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}
?>