<?php
session_start();
require_once '../../db.php';

if (!isset($_GET['payment_id']) || empty($_GET['payment_id'])) {
    echo '<div class="alert alert-danger">Invalid payment ID</div>';
    exit;
}

$payment_id = intval($_GET['payment_id']);

// Fetch payment details
$query = "
    SELECT 
        p.*,
        stl.lease_number,
        stl.lease_year,
        stl.lease_amount,
        stl.payment_due_date,
        stl.payment_status,
        lr.address as land_address,
        lr.deed_number,
        lr.land_area,
        CONCAT(b.first_name, ' ', COALESCE(b.last_name, '')) as beneficiary_name,
        b.nic_number,
        b.mobile,
        lup.purpose_name,
        gn.gn_name,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
        u.mobile as creator_mobile
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
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary"><i class="fa fa-info-circle"></i> Payment Information</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Payment ID:</strong></td>
                    <td><?php echo $payment['payment_id']; ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Date:</strong></td>
                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Receipt Number:</strong></td>
                    <td>
                        <?php if (!empty($payment['receipt_number'])): ?>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($payment['receipt_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">Not provided</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Reference Number:</strong></td>
                    <td><?php echo !empty($payment['reference_number']) ? htmlspecialchars($payment['reference_number']) : '<span class="text-muted">Not provided</span>'; ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td><span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span></td>
                </tr>
                <tr>
                    <td><strong>Bank Details:</strong></td>
                    <td><?php echo !empty($payment['bank_details']) ? htmlspecialchars($payment['bank_details']) : '<span class="text-muted">Not provided</span>'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-success"><i class="fa fa-money-bill"></i> Amount Breakdown</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Lease Amount Paid:</strong></td>
                    <td class="text-right"><strong>LKR <?php echo number_format($payment['lease_amount_paid'], 2); ?></strong></td>
                </tr>
                <tr>
                    <td><strong>Penalty Amount Paid:</strong></td>
                    <td class="text-right"><strong>LKR <?php echo number_format($payment['penalty_amount_paid'], 2); ?></strong></td>
                </tr>
                <tr class="table-success">
                    <td><strong>Total Amount:</strong></td>
                    <td class="text-right"><strong>LKR <?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                </tr>
            </table>
            
            <h6 class="text-info mt-3"><i class="fa fa-file-contract"></i> Lease Information</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Lease Number:</strong></td>
                    <td><?php echo htmlspecialchars($payment['lease_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Lease Year:</strong></td>
                    <td><?php echo $payment['lease_year']; ?></td>
                </tr>
                <tr>
                    <td><strong>Total Lease Amount:</strong></td>
                    <td>LKR <?php echo number_format($payment['lease_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td><strong>Due Date:</strong></td>
                    <td><?php echo date('d/m/Y', strtotime($payment['payment_due_date'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Status:</strong></td>
                    <td>
                        <span class="badge badge-<?php 
                            echo ($payment['payment_status'] == 'paid') ? 'success' : 
                                (($payment['payment_status'] == 'partial') ? 'warning' : 
                                (($payment['payment_status'] == 'overdue') ? 'danger' : 'secondary')); 
                        ?>">
                            <?php echo ucfirst($payment['payment_status']); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <h6 class="text-warning"><i class="fa fa-user"></i> Beneficiary Details</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Name:</strong></td>
                    <td><?php echo htmlspecialchars($payment['beneficiary_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>NIC Number:</strong></td>
                    <td><?php echo htmlspecialchars($payment['nic_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Mobile:</strong></td>
                    <td><?php echo htmlspecialchars($payment['mobile']); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="text-dark"><i class="fa fa-map-marker"></i> Land Details</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Address:</strong></td>
                    <td><?php echo htmlspecialchars($payment['land_address']); ?></td>
                </tr>
                <tr>
                    <td><strong>Deed Number:</strong></td>
                    <td><?php echo htmlspecialchars($payment['deed_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Land Area:</strong></td>
                    <td><?php echo htmlspecialchars($payment['land_area']); ?></td>
                </tr>
                <tr>
                    <td><strong>GN Division:</strong></td>
                    <td><?php echo htmlspecialchars($payment['gn_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td><strong>Purpose:</strong></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($payment['purpose_name']); ?></span></td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php if (!empty($payment['payment_notes'])): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-secondary"><i class="fa fa-sticky-note"></i> Payment Notes</h6>
            <div class="alert alert-light">
                <?php echo nl2br(htmlspecialchars($payment['payment_notes'])); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-muted"><i class="fa fa-clock"></i> Record Information</h6>
            <small class="text-muted">
                Created by: <strong><?php echo htmlspecialchars($payment['created_by_name']); ?></strong> 
                on <?php echo date('d/m/Y g:i A', strtotime($payment['created_on'])); ?>
                <?php if (!empty($payment['creator_mobile'])): ?>
                    | Contact: <?php echo htmlspecialchars($payment['creator_mobile']); ?>
                <?php endif; ?>
            </small>
        </div>
    </div>
</div>