<?php
require('../../db.php');
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$location_id = intval($_GET['location_id']);
$filter_year = !empty($_GET['filter_year']) ? intval($_GET['filter_year']) : null;
$filter_status = !empty($_GET['filter_status']) ? $_GET['filter_status'] : null;
$filter_purpose = !empty($_GET['filter_purpose']) ? intval($_GET['filter_purpose']) : null;

$response = array();

try {
    // Base query for summary
    $base_query = "FROM short_term_leases stl
                   WHERE stl.location_id = ? AND stl.status = 'ACTIVE'";
    
    // Filter conditions
    $filter_conditions = "";
    $filter_params = [$location_id];
    $filter_types = "i";
    
    if ($filter_year) {
        $filter_conditions .= " AND stl.lease_year = ?";
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
    
    // Get summary data
    $summary_query = "SELECT 
                        COUNT(*) as total_leases,
                        COALESCE(SUM(stl.total_paid), 0) as total_collected,
                        COALESCE(SUM(stl.annual_fee - stl.total_paid), 0) as total_pending,
                        COALESCE(SUM(stl.penalty_amount), 0) as total_penalties
                      " . $base_query . $filter_conditions;
    
    $stmt = $con->prepare($summary_query);
    $stmt->bind_param($filter_types, ...$filter_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    
    $response['success'] = true;
    $response['data'] = $summary;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
$con->close();
?>