<?php
session_start();
require_once '../../db.php';

if (!isset($_GET['payment_id']) || empty($_GET['payment_id'])) {
    echo '<div class="alert alert-danger">Invalid payment ID</div>';
    exit;
}

$payment_id = intval($_GET['payment_id']);

// Fetch payment details for receipt
$query = "
    SELECT 
        p.*,
        stl.lease_number,
        stl.lease_year,
        stl.lease_amount,
        stl.payment_due_date,
        lr.address as land_address,
        lr.deed_number,
        CONCAT(b.first_name, ' ', COALESCE(b.last_name, '')) as beneficiary_name,
        b.nic_number,
        b.mobile,
        lup.purpose_name,
        gn.gn_name,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM short_term_lease_payments p
    INNER JOIN short_term_leases stl ON p.st_lease_id = stl.st_lease_id
    INNER JOIN land_registration lr ON stl.land_id = lr.land_id
    INNER JOIN beneficiaries b ON stl.beneficiary_id = b.ben_id
    INNER JOIN land_usage_purposes lup ON stl.purpose_id = lup.purpose_id
    LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
    LEFT JOIN user_license u ON p.created_by = u.usr_id
    WHERE p.payment_id = ?
";

$stmt = $con->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    echo '<div class="alert alert-danger">Payment not found</div>';
    exit;
}

// Get client/location name
$location_query = "SELECT client_name FROM client WHERE location_id = ?";
$stmt = $con->prepare($location_query);
$stmt->bind_param("i", $payment['location_id']);
$stmt->execute();
$location_result = $stmt->get_result();
$location = $location_result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Receipt - <?php echo $payment['receipt_number'] ?: $payment['payment_id']; ?></title>
    <link href="../../assets/bootstrap4/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; }
        .receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
        .receipt-title { font-size: 24px; font-weight: bold; }
        .receipt-subtitle { font-size: 16px; color: #666; }
        .receipt-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .receipt-total { background-color: #f8f9fa; font-weight: bold; }
        .receipt-footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="receipt-header">
            <div class="receipt-title">PAYMENT RECEIPT</div>
            <div class="receipt-subtitle">Short-Term Lease Payment</div>
            <div class="receipt-subtitle"><?php echo htmlspecialchars($location['client_name'] ?? 'Land Administration Office'); ?></div>
        </div>
        
        <div class="row mb-3">
            <div class="col-6">
                <strong>Receipt No:</strong> <?php echo $payment['receipt_number'] ?: 'PAY-' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT); ?><br>
                <strong>Payment Date:</strong> <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?><br>
                <strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
            </div>
            <div class="col-6 text-right">
                <strong>Lease No:</strong> <?php echo htmlspecialchars($payment['lease_number']); ?><br>
                <strong>Year:</strong> <?php echo $payment['lease_year']; ?><br>
                <strong>Due Date:</strong> <?php echo date('d/m/Y', strtotime($payment['payment_due_date'])); ?>
            </div>
        </div>
        
        <h6>Beneficiary Information</h6>
        <table class="table table-sm receipt-table">
            <tr>
                <td width="20%"><strong>Name:</strong></td>
                <td><?php echo htmlspecialchars($payment['beneficiary_name']); ?></td>
            </tr>
            <tr>
                <td><strong>NIC:</strong></td>
                <td><?php echo htmlspecialchars($payment['nic_number']); ?></td>
            </tr>
            <tr>
                <td><strong>Mobile:</strong></td>
                <td><?php echo htmlspecialchars($payment['mobile']); ?></td>
            </tr>
        </table>
        
        <h6>Land Information</h6>
        <table class="table table-sm receipt-table">
            <tr>
                <td width="20%"><strong>Address:</strong></td>
                <td><?php echo htmlspecialchars($payment['land_address']); ?></td>
            </tr>
            <tr>
                <td><strong>Deed No:</strong></td>
                <td><?php echo htmlspecialchars($payment['deed_number']); ?></td>
            </tr>
            <tr>
                <td><strong>Purpose:</strong></td>
                <td><?php echo htmlspecialchars($payment['purpose_name']); ?></td>
            </tr>
            <tr>
                <td><strong>GN Division:</strong></td>
                <td><?php echo htmlspecialchars($payment['gn_name'] ?? 'N/A'); ?></td>
            </tr>
        </table>
        
        <h6>Payment Details</h6>
        <table class="table table-sm receipt-table">
            <tr>
                <td width="20%"><strong>Lease Amount:</strong></td>
                <td class="text-right">LKR <?php echo number_format($payment['lease_amount_paid'], 2); ?></td>
            </tr>
            <tr>
                <td><strong>Penalty Amount:</strong></td>
                <td class="text-right">LKR <?php echo number_format($payment['penalty_amount_paid'], 2); ?></td>
            </tr>
            <tr class="receipt-total">
                <td><strong>Total Amount Paid:</strong></td>
                <td class="text-right"><strong>LKR <?php echo number_format($payment['total_amount'], 2); ?></strong></td>
            </tr>
        </table>
        
        <?php if (!empty($payment['reference_number'])): ?>
        <p><strong>Reference Number:</strong> <?php echo htmlspecialchars($payment['reference_number']); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($payment['bank_details'])): ?>
        <p><strong>Bank Details:</strong> <?php echo htmlspecialchars($payment['bank_details']); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($payment['payment_notes'])): ?>
        <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($payment['payment_notes'])); ?></p>
        <?php endif; ?>
        
        <div class="receipt-footer">
            <p>Received by: <?php echo htmlspecialchars($payment['created_by_name']); ?></p>
            <p>Date & Time: <?php echo date('d/m/Y g:i A', strtotime($payment['created_on'])); ?></p>
            <p><small>This is a computer generated receipt. For any queries, please contact the office.</small></p>
        </div>
        
        <div class="no-print text-center mt-4">
            <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
    </div>
    
    <script>
        // Auto print when page loads
        window.onload = function() {
            // window.print();
        }
    </script>
</body>
</html>