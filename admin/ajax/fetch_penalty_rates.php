<?php
require('../db.php');
session_start();

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
$columns = ['rate_id', 'rate_type', 'rate_percentage', 'effective_date', 'end_date', 'is_active', 'created_by', 'created_on', 'actions'];
$order_column = $columns[$order_column];

try {
    // Base query
    $base_query = "FROM penalty_rates pr
                   LEFT JOIN user_license ul ON pr.created_by = ul.usr_id
                   WHERE pr.location_id = ?";
    
    // Search condition
    $search_condition = "";
    if (!empty($search_value)) {
        $search_condition = " AND (pr.rate_type LIKE ? OR pr.description LIKE ?)";
    }
    
    // Count total records
    $total_query = "SELECT COUNT(*) as total " . $base_query;
    $stmt = $con->prepare($total_query);
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    
    // Count filtered records
    $filtered_query = "SELECT COUNT(*) as total " . $base_query . $search_condition;
    if (!empty($search_value)) {
        $search_param = "%{$search_value}%";
        $stmt = $con->prepare($filtered_query);
        $stmt->bind_param("iss", $location_id, $search_param, $search_param);
    } else {
        $stmt = $con->prepare($filtered_query);
        $stmt->bind_param("i", $location_id);
    }
    $stmt->execute();
    $filtered_records = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get data
    $data_query = "SELECT pr.rate_id, pr.rate_type, pr.rate_percentage, pr.effective_date, 
                          pr.end_date, pr.is_active, ul.name as created_by_name, pr.created_on
                   " . $base_query . $search_condition . "
                   ORDER BY pr.{$order_column} {$order_dir}
                   LIMIT ?, ?";
    
    if (!empty($search_value)) {
        $stmt = $con->prepare($data_query);
        $stmt->bind_param("issii", $location_id, $search_param, $search_param, $start, $length);
    } else {
        $stmt = $con->prepare($data_query);
        $stmt->bind_param("iii", $location_id, $start, $length);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Format rate type
        $rate_type_formatted = ucwords(str_replace('_', ' ', $row['rate_type']));
        
        // Format rate percentage
        $rate_formatted = number_format($row['rate_percentage'], 2) . '%';
        
        // Format dates
        $effective_date = date('Y-m-d', strtotime($row['effective_date']));
        $end_date = $row['end_date'] ? date('Y-m-d', strtotime($row['end_date'])) : 'Ongoing';
        
        // Status badge
        $status_badge = $row['is_active'] ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-secondary">Inactive</span>';
        
        // Actions
        $actions = '
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-info" onclick="editPenaltyRate(' . $row['rate_id'] . ')" title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="deletePenaltyRate(' . $row['rate_id'] . ')" title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>';
        
        $data[] = [
            $row['rate_id'],
            $rate_type_formatted,
            $rate_formatted,
            $effective_date,
            $end_date,
            $status_badge,
            htmlspecialchars($row['created_by_name'] ?: 'System'),
            date('Y-m-d H:i', strtotime($row['created_on'])),
            $actions
        ];
    }
    
    $response = [
        'draw' => $draw,
        'recordsTotal' => $total_records,
        'recordsFiltered' => $filtered_records,
        'data' => $data
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}

$con->close();
?>