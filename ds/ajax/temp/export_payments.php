<?php
session_start();
require_once '../../db.php';

// Get filter parameters
$location_id = $_SESSION['location_id'] ?? 0;
$filter_year = $_GET['filter_year'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$purpose_filter = $_GET['purpose_filter'] ?? '';

// Build WHERE conditions
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($location_id)) {
    $where_conditions[] = "stl.location_id = ?";
    $params[] = $location_id;
    $types .= "i";
}

if (!empty($filter_year)) {
    $where_conditions[] = "stl.lease_year = ?";
    $params[] = $filter_year;
    $types .= "s";
}

if (!empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "p.payment_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

if (!empty($payment_status)) {
    $where_conditions[] = "stl.payment_status = ?";
    $params[] = $payment_status;
    $types .= "s";
}

if (!empty($purpose_filter)) {
    $where_conditions[] = "stl.purpose_id = ?";
    $params[] = $purpose_filter;
    $types .= "i";
}

// Fetch data for export
$export_query = "
    SELECT 
        p.payment_date as 'Payment Date',
        p.receipt_number as 'Receipt Number',
        stl.lease_number as 'Lease Number',
        stl.lease_year as 'Lease Year',
        CONCAT(b.first_name, ' ', COALESCE(b.last_name, '')) as 'Beneficiary Name',
        b.nic_number as 'NIC Number',
        lr.address as 'Land Address',
        lr.deed_number as 'Deed Number',
        lup.purpose_name as 'Purpose',
        gn.gn_name as 'GN Division',
        p.lease_amount_paid as 'Lease Amount Paid',
        p.penalty_amount_paid as 'Penalty Amount Paid',
        p.total_amount as 'Total Amount',
        p.payment_method as 'Payment Method',
        p.reference_number as 'Reference Number',
        p.bank_details as 'Bank Details',
        stl.payment_status as 'Payment Status',
        stl.payment_due_date as 'Due Date',
        p.payment_notes as 'Notes',
        CONCAT(u.first_name, ' ', u.last_name) as 'Created By',
        p.created_on as 'Created On'
    FROM short_term_lease_payments p
    INNER JOIN short_term_leases stl ON p.st_lease_id = stl.st_lease_id
    INNER JOIN land_registration lr ON stl.land_id = lr.land_id
    INNER JOIN beneficiaries b ON stl.beneficiary_id = b.ben_id
    INNER JOIN land_usage_purposes lup ON stl.purpose_id = lup.purpose_id
    LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
    LEFT JOIN user_license u ON p.created_by = u.usr_id
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY p.payment_date DESC, p.payment_id DESC
";

$payments = [];
if (!empty($params)) {
    $stmt = $con->prepare($export_query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $con->query($export_query);
    $payments = $result->fetch_all(MYSQLI_ASSOC);
}

// Set headers for Excel download
$filename = 'short_term_lease_payments_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
if (!empty($payments)) {
    fputcsv($output, array_keys($payments[0]));
    
    // Write data rows
    foreach ($payments as $payment) {
        // Format dates
        if (!empty($payment['Payment Date'])) {
            $payment['Payment Date'] = date('d/m/Y', strtotime($payment['Payment Date']));
        }
        if (!empty($payment['Due Date'])) {
            $payment['Due Date'] = date('d/m/Y', strtotime($payment['Due Date']));
        }
        if (!empty($payment['Created On'])) {
            $payment['Created On'] = date('d/m/Y H:i:s', strtotime($payment['Created On']));
        }
        
        // Format amounts
        $payment['Lease Amount Paid'] = number_format($payment['Lease Amount Paid'], 2);
        $payment['Penalty Amount Paid'] = number_format($payment['Penalty Amount Paid'], 2);
        $payment['Total Amount'] = number_format($payment['Total Amount'], 2);
        
        // Clean up payment method
        $payment['Payment Method'] = ucfirst(str_replace('_', ' ', $payment['Payment Method']));
        
        fputcsv($output, $payment);
    }
} else {
    // Write empty header
    fputcsv($output, ['No payment records found for the selected criteria']);
}

fclose($output);
exit;
?>