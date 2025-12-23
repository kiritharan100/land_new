<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$lease_id = intval($_GET['lease_id'] ?? 0);

try {
    if ($lease_id <= 0) {
        throw new Exception('Invalid lease ID');
    }
    
        $query = "SELECT stl.*, 
                lr.address as land_address,
                lr.lcg_plan_no, 
                lr.val_plan_no, 
                lr.survey_plan_no,
                lr.lcg_hectares,
                b.name as beneficiary_name, 
                b.nic_reg_no,
                gn.gn_name
            FROM short_term_leases stl
            LEFT JOIN short_term_land_registration lr ON stl.land_id = lr.land_id
            LEFT JOIN short_term_beneficiaries b ON stl.beneficiary_id = b.ben_id
            LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
            WHERE stl.st_lease_id = ?";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $lease_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $lease = $result->fetch_assoc();
        
        // Helper function to format date
        function formatDate($dateStr) {
            if (!$dateStr || $dateStr == '0000-00-00' || $dateStr == '00-00-0000') {
                return '';
            }
            $timestamp = strtotime($dateStr);
            return $timestamp ? date('Y-m-d', $timestamp) : '';
        }
        
        // Format the response to match the form field names
        $response_data = [
            'lease_id' => $lease['st_lease_id'],
            'land_registration_id' => $lease['land_id'],
            'beneficiary_id' => $lease['beneficiary_id'],
            'land_usage_purpose_id' => $lease['purpose_id'],
            'start_date' => formatDate($lease['start_date']),
            'annual_fee' => $lease['lease_amount'],
            'payment_due_date' => formatDate($lease['payment_due_date']),
            'end_date' => formatDate($lease['end_date']),
            'special_conditions' => isset($lease['remarks']) ? $lease['remarks'] : '',
            'auto_renewal_enabled' => isset($lease['auto_renew']) ? intval($lease['auto_renew']) : 0,
            'is_active' => (isset($lease['status']) && strtolower($lease['status']) == 'active' && (!isset($lease['is_deleted']) || $lease['is_deleted'] == 0)) ? 1 : 0,
            
            // Debug info
            'debug_status' => isset($lease['status']) ? $lease['status'] : 'NO_STATUS',
            'debug_is_deleted' => isset($lease['is_deleted']) ? $lease['is_deleted'] : 'NO_IS_DELETED',
            'debug_start_date' => isset($lease['start_date']) ? $lease['start_date'] : 'NO_START_DATE',
            'debug_payment_due_date' => isset($lease['payment_due_date']) ? $lease['payment_due_date'] : 'NO_PAYMENT_DUE_DATE',
            
            // Additional display info
            'land_address' => $lease['land_address'],
            'plan_no' => $lease['lcg_plan_no'] ?: $lease['val_plan_no'] ?: $lease['survey_plan_no'],
            'hectares' => $lease['lcg_hectares'],
            'beneficiary_name' => $lease['beneficiary_name'],
            'nic_reg_no' => $lease['nic_reg_no'],
            'gn_name' => $lease['gn_name']
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $response_data
        ]);
    } else {
        throw new Exception('Lease not found');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in get_short_term_lease.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>