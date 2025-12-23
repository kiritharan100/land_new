<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$lease_id = intval($_GET['lease_id'] ?? 0);

if ($lease_id <= 0) {
    echo json_encode(['error' => 'Valid lease ID is required']);
    exit;
}

try {
    // Get lease information with payment details
    $query = "SELECT 
        stl.st_lease_id,
        stl.lease_number,
        stl.lease_year,
        stl.start_date,
        stl.lease_amount,
        COALESCE(stl.amount_paid, 0) as amount_paid,
        COALESCE(stl.penalty_amount, 0) as penalty_amount,
        COALESCE(stl.penalty_paid, 0) as penalty_paid,
        COALESCE(stl.total_paid, 0) as total_paid,
        stl.payment_due_date,
        stl.status,
        lr.address,
        lr.lcg_plan_no,
        lr.val_plan_no,
        lr.survey_plan_no,
        b.name as beneficiary_name,
        lup.purpose_name,
        gn.gn_name
        FROM short_term_leases stl
        LEFT JOIN short_term_land_registration lr ON stl.land_id = lr.land_id
        LEFT JOIN short_term_beneficiaries b ON stl.beneficiary_id = b.ben_id
        LEFT JOIN land_usage_purposes lup ON stl.purpose_id = lup.purpose_id
        LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
        WHERE stl.st_lease_id = ?
        AND (stl.is_deleted IS NULL OR stl.is_deleted = 0)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Format land information
        $land_info = $row['address'] ?: 'N/A';
        if ($row['lcg_plan_no']) {
            $land_info .= " (Plan: {$row['lcg_plan_no']})";
        } elseif ($row['val_plan_no']) {
            $land_info .= " (Plan: {$row['val_plan_no']})";
        } elseif ($row['survey_plan_no']) {
            $land_info .= " (Plan: {$row['survey_plan_no']})";
        }
        
        // Calculate outstanding amounts
        $rent_outstanding = $row['lease_amount'] - $row['amount_paid'];
        $penalty_outstanding = $row['penalty_amount'] - $row['penalty_paid'];
        $total_outstanding = $rent_outstanding + $penalty_outstanding;
        
        $lease_data = [
            'lease_id' => $row['st_lease_id'],
            'lease_number' => $row['lease_number'],
            'lease_year' => $row['lease_year'],
            'lease_amount' => floatval($row['lease_amount']),
            'amount_paid' => floatval($row['amount_paid']),
            'penalty_amount' => floatval($row['penalty_amount']),
            'penalty_paid' => floatval($row['penalty_paid']),
            'total_paid' => floatval($row['total_paid']),
            'rent_outstanding' => $rent_outstanding,
            'penalty_outstanding' => $penalty_outstanding,
            'balance_amount' => $total_outstanding,
            'payment_due_date' => $row['payment_due_date'],
            'status' => $row['status'],
            'land_info' => $land_info,
            'beneficiary_name' => $row['beneficiary_name'],
            'purpose_name' => $row['purpose_name'],
            'gn_name' => $row['gn_name']
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $lease_data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Lease not found or has been deleted'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in get_lease_payment_info.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching lease information: ' . $e->getMessage()
    ]);
}
?>