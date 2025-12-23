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
$columns = ['stl.lease_id', 'land_info', 'beneficiary_info', 'lup.purpose_name', 
           'stl.lease_year', 'stl.annual_fee', 'stl.auto_renewal_enabled', 
           'stl.status', 'stl.created_on', 'actions'];
$order_column = $columns[$order_column] ?? 'stl.created_on';

try {
    // Base query
    $base_query = "FROM short_term_leases stl
                   LEFT JOIN land_registration lr ON stl.land_registration_id = lr.id
                   LEFT JOIN beneficiaries b ON stl.beneficiary_id = b.id
                   LEFT JOIN land_usage_purposes lup ON stl.land_usage_purpose_id = lup.purpose_id
                   WHERE stl.location_id = ?";
    
    // Search condition
    $search_condition = "";
    if (!empty($search_value)) {
        $search_condition = " AND (lr.reg_no LIKE ? OR lr.district LIKE ? OR lr.ds_division LIKE ? OR 
                             lr.gn_division LIKE ? OR b.beneficiary_name LIKE ? OR 
                             lup.purpose_name LIKE ? OR stl.lease_year LIKE ?)";
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
        $stmt->bind_param("isssssss", $location_id, $search_param, $search_param, $search_param, 
                         $search_param, $search_param, $search_param, $search_param);
    } else {
        $stmt = $con->prepare($filtered_query);
        $stmt->bind_param("i", $location_id);
    }
    $stmt->execute();
    $filtered_records = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get data
    $data_query = "SELECT stl.lease_id, stl.lease_year, stl.annual_fee, stl.auto_renewal_enabled, 
                          stl.status, stl.created_on,
                          CONCAT(lr.reg_no, ' - ', lr.district, ', ', lr.ds_division) as land_info,
                          b.beneficiary_name,
                          lup.purpose_name
                   " . $base_query . $search_condition . "
                   ORDER BY {$order_column} {$order_dir}
                   LIMIT ?, ?";
    
    if (!empty($search_value)) {
        $stmt = $con->prepare($data_query);
        $stmt->bind_param("isssssssii", $location_id, $search_param, $search_param, $search_param, 
                         $search_param, $search_param, $search_param, $search_param, $start, $length);
    } else {
        $stmt = $con->prepare($data_query);
        $stmt->bind_param("iii", $location_id, $start, $length);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Format annual fee
        $annual_fee = 'LKR ' . number_format($row['annual_fee'], 2);
        
        // Auto-renewal badge
        $auto_renewal = $row['auto_renewal_enabled'] ? 
            '<span class="badge badge-success">Enabled</span>' : 
            '<span class="badge badge-secondary">Disabled</span>';
        
        // Status badge
        $status_class = '';
        switch ($row['status']) {
            case 'ACTIVE':
                $status_class = 'badge-success';
                break;
            case 'EXPIRED':
                $status_class = 'badge-warning';
                break;
            case 'TERMINATED':
                $status_class = 'badge-danger';
                break;
            default:
                $status_class = 'badge-secondary';
        }
        $status_badge = '<span class="badge ' . $status_class . '">' . $row['status'] . '</span>';
        
        // Actions
        $actions = '
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-info" onclick="viewLeaseDetails(' . $row['lease_id'] . ')" title="View Details">
                    <i class="fa fa-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-warning" onclick="editShortTermLease(' . $row['lease_id'] . ')" title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="deleteShortTermLease(' . $row['lease_id'] . ')" title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>';
        
        $data[] = [
            $row['lease_id'],
            htmlspecialchars($row['land_info']),
            htmlspecialchars($row['beneficiary_name'] ?: 'Not Assigned'),
            htmlspecialchars($row['purpose_name'] ?: 'Not Specified'),
            $row['lease_year'],
            $annual_fee,
            $auto_renewal,
            $status_badge,
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