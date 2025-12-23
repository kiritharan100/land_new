<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$location_id = intval($_GET['location_id'] ?? 0);
$show_deleted = intval($_GET['show_deleted'] ?? 0);
$year_filter = $_GET['year_filter'] ?? null; // Use null to distinguish from empty string
$payment_status_filter = $_GET['payment_status_filter'] ?? '';

// Debug log to track filters
error_log("Year filter value: " . ($year_filter !== null ? ($year_filter ?: 'empty_string') : 'null'));
error_log("Payment status filter value: " . ($payment_status_filter ?: 'empty'));

// If no year filter provided (null means initial load, empty string means "All Years" selected)
if ($year_filter === null) {
    try {
        $recent_year_query = "SELECT YEAR(start_date) as year 
                             FROM short_term_leases 
                             WHERE location_id = ? 
                             AND start_date IS NOT NULL
                             AND (is_deleted IS NULL OR is_deleted = 0)
                             ORDER BY year DESC
                             LIMIT 1";
        $stmt = $conn->prepare($recent_year_query);
        if ($stmt) {
            $stmt->bind_param("i", $location_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $year_filter = $row['year'];
                error_log("Auto-selected year filter: " . $year_filter);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error getting recent year: " . $e->getMessage());
    }
} elseif ($year_filter === '') {
    error_log("All Years selected - no year filter will be applied");
}

if ($location_id <= 0) {
    echo json_encode(['error' => 'Valid location ID is required']);
    exit;
}

try {
    // Check if is_deleted column exists, if not add it
    $check_column_query = "SHOW COLUMNS FROM short_term_leases LIKE 'is_deleted'";
    $column_result = $conn->query($check_column_query);
    
    if ($column_result->num_rows === 0) {
        // Add is_deleted column
        $add_column_query = "ALTER TABLE short_term_leases ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status";
        $conn->query($add_column_query);
    }
    
    // Check if payment tracking columns exist, if not add them
    $payment_columns = ['amount_paid', 'penalty_amount', 'penalty_paid'];
    foreach ($payment_columns as $column) {
        $check_payment_column_query = "SHOW COLUMNS FROM short_term_leases LIKE '$column'";
        $payment_column_result = $conn->query($check_payment_column_query);
        
        if ($payment_column_result->num_rows === 0) {
            // Add payment tracking column
            $add_payment_column_query = "ALTER TABLE short_term_leases ADD COLUMN $column DECIMAL(15,2) DEFAULT 0.00 AFTER lease_amount";
            $conn->query($add_payment_column_query);
            error_log("Added column: $column to short_term_leases table");
        }
    }
    
    // DataTable parameters
    $draw = intval($_GET['draw'] ?? 1);
    $start = intval($_GET['start'] ?? 0);
    $length = intval($_GET['length'] ?? 10);
    $search_value = $_GET['search']['value'] ?? '';
    $order_column = intval($_GET['order'][0]['column'] ?? 8);
    $order_dir = $_GET['order'][0]['dir'] ?? 'desc';
    
    // Column mapping for ordering
    $columns = [
        0 => 'stl.st_lease_id',
        1 => 'lr.address',
        2 => 'b.name',
        3 => 'lup.purpose_name',
        4 => 'gn.gn_name',
        5 => 'stl.start_date',
        6 => 'stl.lease_amount',
        7 => 'lease_paid',
        8 => 'penalty_amount',
        9 => 'penalty_paid',
        10 => 'payment_status',
        11 => 'stl.auto_renew',
        12 => 'stl.status'
    ];
    
    $order_by = $columns[$order_column] ?? 'stl.created_on';
    
    // Base query with all necessary joins
    $base_query = "FROM short_term_leases stl
                   LEFT JOIN land_registration lr ON stl.land_id = lr.land_id 
                   LEFT JOIN beneficiaries b ON stl.beneficiary_id = b.ben_id
                   LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
                   LEFT JOIN land_usage_purposes lup ON stl.purpose_id = lup.purpose_id
                   WHERE stl.location_id = ?";
    
    $params = [$location_id];
    $param_types = "i";
    
    // Filter deleted leases based on show_deleted parameter
    if ($show_deleted == 0) {
        $base_query .= " AND (stl.is_deleted IS NULL OR stl.is_deleted = 0)";
    }
    
    // Add year filter if specified (not empty string which means "All Years")
    if (!empty($year_filter)) {
        $base_query .= " AND YEAR(stl.start_date) = ?";
        $params[] = $year_filter;
        $param_types .= "i";
        error_log("Applying year filter: " . $year_filter);
    } else {
        error_log("No year filter applied - showing all years");
    }
    
    // Add payment status filter if specified
    if (!empty($payment_status_filter)) {
        switch ($payment_status_filter) {
            case 'paid':
                $base_query .= " AND (stl.lease_amount <= COALESCE(stl.amount_paid, 0) AND COALESCE(stl.penalty_amount, 0) <= COALESCE(stl.penalty_paid, 0))";
                break;
            case 'partial':
                $base_query .= " AND ((stl.lease_amount > COALESCE(stl.amount_paid, 0) AND COALESCE(stl.amount_paid, 0) > 0) OR (COALESCE(stl.penalty_amount, 0) > COALESCE(stl.penalty_paid, 0) AND COALESCE(stl.penalty_paid, 0) > 0))";
                break;
            case 'unpaid':
                $base_query .= " AND (COALESCE(stl.amount_paid, 0) = 0 OR stl.lease_amount > COALESCE(stl.amount_paid, 0) OR COALESCE(stl.penalty_amount, 0) > COALESCE(stl.penalty_paid, 0))";
                break;
        }
        error_log("Applying payment status filter: " . $payment_status_filter);
    }
    
    // Add search filter
    if (!empty($search_value)) {
        $base_query .= " AND (
            lr.address LIKE ? OR
            lr.lcg_plan_no LIKE ? OR
            lr.val_plan_no LIKE ? OR
            lr.survey_plan_no LIKE ? OR
            b.name LIKE ? OR
            gn.gn_name LIKE ? OR
            stl.lease_number LIKE ? OR
            stl.status LIKE ?
        )";
        
        $search_param = "%{$search_value}%";
        $params = array_merge($params, array_fill(0, 8, $search_param));
        $param_types .= "ssssssss";
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total " . $base_query;
    $stmt = $conn->prepare($count_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare count query: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Get filtered count (same as total if no search)
    $filtered_records = $total_records;
    
    // Get data with pagination
    $data_query = "SELECT 
        stl.st_lease_id,
        stl.land_id,
        stl.beneficiary_id,
        stl.purpose_id,
        stl.lease_number,
        stl.lease_year,
        stl.start_date,
        stl.lease_amount,
        stl.payment_due_date,
        stl.auto_renew,
        stl.payment_status,
        stl.status,
        stl.is_deleted,
        stl.remarks,
        COALESCE(stl.amount_paid, 0) as amount_paid,
        COALESCE(stl.penalty_amount, 0) as penalty_amount,
        COALESCE(stl.penalty_paid, 0) as penalty_paid,
        lr.address,
        lr.lcg_plan_no,
        lr.val_plan_no,
        lr.survey_plan_no,
        lr.hectares,
        b.name as beneficiary_name,
        gn.gn_name,
        lup.purpose_name
        " . $base_query . "
        ORDER BY {$order_by} {$order_dir}
        LIMIT {$start}, {$length}";
    
    $stmt = $conn->prepare($data_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare data query: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Check if lease is deleted
        $is_deleted = isset($row['is_deleted']) && $row['is_deleted'] == 1;
        
        // Format land registration display
        $land_display = $row['address'] ?: 'N/A';
        if ($row['lcg_plan_no']) {
            $land_display .= " (Plan: {$row['lcg_plan_no']})";
        } elseif ($row['val_plan_no']) {
            $land_display .= " (Plan: {$row['val_plan_no']})";
        } elseif ($row['survey_plan_no']) {
            $land_display .= " (Plan: {$row['survey_plan_no']})";
        }
       
        
        // Apply deleted styling
        if ($is_deleted) {
            $land_display = '<span style="color: #dc3545; text-decoration: line-through;">' . $land_display . '</span>';
        }
        
        // Format currency
        $lease_amount = number_format($row['lease_amount'], 2);
        if ($is_deleted) {
            $lease_amount = '<span style="color: #dc3545; text-decoration: line-through;">' . $lease_amount . '</span>';
        } else {
            $lease_amount = $lease_amount;
        }
        
        // Format lease paid amount
        $lease_paid = number_format($row['amount_paid'], 2);
        if ($is_deleted) {
            $lease_paid = '<span style="color: #dc3545; text-decoration: line-through;">' . $lease_paid . '</span>';
        } else {
            $lease_paid = $lease_paid;
        }
        
        // Format penalty amount
        $penalty_amount = number_format($row['penalty_amount'], 2);
        if ($is_deleted) {
            $penalty_amount = '<span style="color: #dc3545; text-decoration: line-through;">' . $penalty_amount . '</span>';
        } else {
            $penalty_amount = $penalty_amount;
        }
        
        // Format penalty paid amount
        $penalty_paid = number_format($row['penalty_paid'], 2);
        if ($is_deleted) {
            $penalty_paid = '<span style="color: #dc3545; text-decoration: line-through;">' . $penalty_paid . '</span>';
        } else {
            $penalty_paid = $penalty_paid;
        }
        
        // Calculate payment status
        $total_due = $row['lease_amount'] + $row['penalty_amount'];
        $total_paid = $row['amount_paid'] + $row['penalty_paid'];
        
        if ($total_due <= $total_paid) {
            $payment_status = '<span class="badge badge-success">Paid</span>';
        } elseif ($total_paid > 0) {
            $payment_status = '<span class="badge badge-warning">Partially Paid</span>';
        } else {
            $payment_status = '<span class="badge badge-danger">Unpaid</span>';
        }
        
        if ($is_deleted) {
            $payment_status = '<span style="color: #dc3545; text-decoration: line-through;">' . $payment_status . '</span>';
        }
        
        // Format auto renewal
        $auto_renew = $row['auto_renew'] ? 
            '<span class="badge badge-info"><i class="fa fa-sync-alt"></i> Auto</span>' : 
            '<span class="badge badge-secondary"><i class="fa fa-times"></i> Disabled</span>';
        
        if ($is_deleted) {
            $auto_renew = '<span style="color: #dc3545; text-decoration: line-through;">' . $auto_renew . '</span>';
        }
        
        // Format status with deleted consideration
        $status_class = '';
        $status_text = $row['status'];
        
        if ($is_deleted) {
            $status_class = 'badge-danger';
            $status_text = 'DELETED';
        } else {
            switch (strtoupper($row['status'])) {
                case 'ACTIVE':
                    $status_class = 'badge-success';
                    break;
                case 'INACTIVE':
                    $status_class = 'badge-secondary';
                    break;
                case 'EXPIRED':
                    $status_class = 'badge-warning';
                    break;
                case 'CANCELLED':
                    $status_class = 'badge-danger';
                    break;
                default:
                    $status_class = 'badge-info';
            }
        }
        $status_display = '<span class="badge ' . $status_class . '">' . ucfirst($status_text) . '</span>';
        
        // Format beneficiary name
        $beneficiary_name = htmlspecialchars($row['beneficiary_name'] ?: 'N/A');
        if ($is_deleted) {
            $beneficiary_name = '<span style="color: #dc3545; text-decoration: line-through;">' . $beneficiary_name . '</span>';
        }
        
        // Format Purpose Name
        $purpose_name = htmlspecialchars($row['purpose_name'] ?: 'N/A');
        if ($is_deleted) {
            $purpose_name = '<span style="color: #dc3545; text-decoration: line-through;">' . $purpose_name . '</span>';
        }
        
        // Format GN Division
        $gn_name = htmlspecialchars($row['gn_name'] ?: 'N/A');
        if ($is_deleted) {
            $gn_name = '<span style="color: #dc3545; text-decoration: line-through;">' . $gn_name . '</span>';
        }
        
        // Format lease number
        $lease_number = $row['lease_number'];
        if ($is_deleted) {
            $lease_number = '<span style="color: #dc3545; text-decoration: line-through;">' . $lease_number . '</span>';
        }
        
        // Format start date
        $start_date = date('Y-m-d', strtotime($row['start_date']));
        if ($is_deleted) {
            $start_date = '<span style="color: #dc3545; text-decoration: line-through;">' . $start_date . '</span>';
        }
        
        // Action dropdown
        if ($is_deleted) {
            $actions = '
                <select class="form-control form-control-sm action-select" data-lease-id="' . $row['st_lease_id'] . '" style="width: 120px;">
                    <option value="">Select</option>
                    <option value="view">View Details</option>
                    <option value="restore">Restore Lease</option>
                </select>';
        } else {
            $actions = '
                <select class="form-control form-control-sm action-select" data-lease-id="' . $row['st_lease_id'] . '" style="width: 120px;">
                    <option value="">Select</option>
                    <option value="view">View Details</option>
                    <option value="edit">Edit Lease</option>
                    <option value="payment">Record Payment</option>
                    <option value="delete">Delete Lease</option>
                </select>';
        }
        // Format lease number
        $lease_number = $row['lease_number'];
        if ($is_deleted) {
            $lease_number = '<span style="color: #dc3545; text-decoration: line-through;">' . $lease_number . '</span>';
        }
        
        // Format start date
        $start_date = date('Y-m-d', strtotime($row['start_date']));
        if ($is_deleted) {
            $start_date = '<span style="color: #dc3545; text-decoration: line-through;">' . $start_date . '</span>';
        }
        
        // Format beneficiary name
        $beneficiary_name = htmlspecialchars($row['beneficiary_name'] ?: 'N/A');
        if ($is_deleted) {
            $beneficiary_name = '<span style="color: #dc3545; text-decoration: line-through;">' . $beneficiary_name . '</span>';
        }
        
        // Action dropdown
        if ($is_deleted) {
            $actions = '
                <select class="form-control form-control-sm action-select" data-lease-id="' . $row['st_lease_id'] . '" style="width: 120px;">
                    <option value="">Select</option>
                    <option value="view">View Details</option>
                    <option value="restore">Restore Lease</option>
                </select>';
        } else {
            $actions = '
                <select class="form-control form-control-sm action-select" data-lease-id="' . $row['st_lease_id'] . '" style="width: 120px;">
                    <option value="">Select</option>
                    <option value="view">View Details</option>
                    <option value="edit">Edit Lease</option>
                    <option value="payment">Record Payment</option>
                    <option value="delete">Delete Lease</option>
                </select>';
        }
        
        $data[] = [
            $lease_number,
            $land_display,
            $beneficiary_name,
            $purpose_name,
            $gn_name,
            $start_date,
            $lease_amount,
            $lease_paid,
            $penalty_amount,
            $penalty_paid,
            $payment_status,
            $auto_renew,
            $status_display,
            $actions
        ];
    }
    
    $stmt->close();
    
    // Return DataTable format
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $total_records,
        'recordsFiltered' => $filtered_records,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log("Error in fetch_short_term_leases.php: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage(),
        'draw' => intval($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
}
?>