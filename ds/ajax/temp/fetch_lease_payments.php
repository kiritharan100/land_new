<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$location_id = intval($_GET['location_id'] ?? 0);
$filter_year = !empty($_GET['filter_year']) ? intval($_GET['filter_year']) : null;
$filter_status = !empty($_GET['filter_status']) ? $_GET['filter_status'] : null;
$filter_purpose = !empty($_GET['filter_purpose']) ? intval($_GET['filter_purpose']) : null;

// DataTables parameters
$draw = intval($_GET['draw'] ?? 1);
$start = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$search_value = $_GET['search']['value'] ?? '';
$order_column = intval($_GET['order'][0]['column'] ?? 10);
$order_dir = $_GET['order'][0]['dir'] ?? 'asc';

// Column mapping
$columns = ['stl.st_lease_id', 'land_info', 'beneficiary_info', 'lup.purpose_name', 
           'YEAR(stl.start_date)', 'stl.lease_amount', 'stl.amount_paid', 'stl.penalty_amount',
           'balance_amount', 'stl.payment_status', 'stl.payment_due_date', 'actions'];
$order_column_name = $columns[$order_column] ?? 'stl.payment_due_date';

if ($location_id <= 0) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Valid location ID is required'
    ]);
    exit;
}

try {
    // Base query with correct table structure
    $base_query = "FROM short_term_leases stl
                   LEFT JOIN land_registration lr ON stl.land_registration_id = lr.id
                   LEFT JOIN beneficiaries b ON stl.beneficiary_id = b.id
                   LEFT JOIN land_usage_purposes lup ON stl.land_usage_purpose_id = lup.purpose_id
                   WHERE stl.location_id = ? AND (stl.is_deleted IS NULL OR stl.is_deleted = 0)";
    
    // Filter conditions
    $filter_conditions = "";
    $filter_params = [$location_id];
    $filter_types = "i";
    
    if ($filter_year) {
        $filter_conditions .= " AND YEAR(stl.start_date) = ?";
        $filter_params[] = $filter_year;
        $filter_types .= "i";
    }
    
    if ($filter_status) {
        $filter_conditions .= " AND stl.payment_status = ?";
        $filter_params[] = $filter_status;
        $filter_types .= "s";
    }
    
    if ($filter_purpose) {
        $filter_conditions .= " AND stl.land_usage_purpose_id = ?";
        $filter_params[] = $filter_purpose;
        $filter_types .= "i";
    }
    
    // Search condition
    $search_condition = "";
    $search_params = [];
    if (!empty($search_value)) {
        $search_condition = " AND (lr.reg_no LIKE ? OR lr.district LIKE ? OR lr.ds_division LIKE ? OR 
                             lr.gn_division LIKE ? OR b.beneficiary_name LIKE ? OR 
                             lup.purpose_name LIKE ? OR stl.lease_number LIKE ?)";
        $search_params = array_fill(0, 7, "%{$search_value}%");
    }
    
    // Count total records
    $total_query = "SELECT COUNT(*) as total " . $base_query . $filter_conditions;
    $stmt = $conn->prepare($total_query);
    $stmt->bind_param($filter_types, ...$filter_params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    
    // Count filtered records
    $filtered_query = "SELECT COUNT(*) as total " . $base_query . $filter_conditions . $search_condition;
    if (!empty($search_value)) {
        $all_params = array_merge($filter_params, $search_params);
        $all_types = $filter_types . str_repeat("s", 7);
        $stmt = $conn->prepare($filtered_query);
        $stmt->bind_param($all_types, ...$all_params);
    } else {
        $stmt = $conn->prepare($filtered_query);
        $stmt->bind_param($filter_types, ...$filter_params);
    }
    $stmt->execute();
    $filtered_records = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get data with correct field names
    $data_query = "SELECT stl.st_lease_id, YEAR(stl.start_date) as lease_year, stl.lease_amount, stl.amount_paid, 
                          stl.penalty_paid, stl.payment_status, stl.payment_due_date,
                          (stl.lease_amount - stl.amount_paid) as balance_amount,
                          CONCAT(lr.reg_no, ' - ', lr.district, ', ', lr.ds_division) as land_info,
                          b.beneficiary_name,
                          lup.purpose_name,
                          stl.lease_number
                   " . $base_query . $filter_conditions . $search_condition . "
                   ORDER BY {$order_column} {$order_dir}
                   LIMIT ?, ?";
    
    if (!empty($search_value)) {
        $all_params = array_merge($filter_params, $search_params, [$start, $length]);
        $all_types = $filter_types . str_repeat("s", 7) . "ii";
        $stmt = $conn->prepare($data_query);
        $stmt->bind_param($all_types, ...$all_params);
    } else {
        $all_params = array_merge($filter_params, [$start, $length]);
        $all_types = $filter_types . "ii";
        $stmt = $conn->prepare($data_query);
        $stmt->bind_param($all_types, ...$all_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Format amounts with correct field names
        $lease_amount = 'LKR ' . number_format($row['lease_amount'], 2);
        $paid_amount = 'LKR ' . number_format($row['amount_paid'], 2);
        $penalty_amount = 'LKR ' . number_format($row['penalty_paid'], 2);
        $balance_amount = 'LKR ' . number_format($row['balance_amount'], 2);
        
        // Status badge
        $status_class = '';
        switch ($row['payment_status']) {
            case 'PAID':
                $status_class = 'badge-success';
                break;
            case 'PARTIAL':
                $status_class = 'badge-warning';
                break;
            case 'OVERDUE':
                $status_class = 'badge-danger';
                break;
            case 'PENDING':
            default:
                $status_class = 'badge-secondary';
        }
        $status_badge = '<span class="badge ' . $status_class . '">' . $row['payment_status'] . '</span>';
        
        // Due date with overdue indicator
        $due_date = date('Y-m-d', strtotime($row['payment_due_date']));
        if (strtotime($row['payment_due_date']) < time() && $row['payment_status'] != 'PAID') {
            $due_date = '<span class="text-danger"><strong>' . $due_date . '</strong><br><small>OVERDUE</small></span>';
        }
        
        // Actions with correct field reference
        $actions = '
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-success" onclick="recordPayment(' . $row['st_lease_id'] . ')" title="Record Payment">
                    <i class="fa fa-credit-card"></i>
                </button>
                <button type="button" class="btn btn-sm btn-info" onclick="viewPaymentHistory(' . $row['st_lease_id'] . ')" title="Payment History">
                    <i class="fa fa-history"></i>
                </button>
            </div>';
        
        $data[] = [
            $row['st_lease_id'],
            htmlspecialchars($row['land_info']),
            htmlspecialchars($row['beneficiary_name'] ?: 'Not Assigned'),
            htmlspecialchars($row['purpose_name'] ?: 'Not Specified'),
            $row['lease_year'],
            $lease_amount,
            $paid_amount,
            $penalty_amount,
            $balance_amount,
            $status_badge,
            $due_date,
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

$conn->close();
?>