<?php
include '../../db.php';

$payment_id = intval($_GET['payment_id'] ?? 0);

if ($payment_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid payment ID</div>";
    exit;
}

// Get payment details with related information
$payment_sql = "SELECT 
    lp.*, 
    l.lease_number, l.start_date as lease_start, l.end_date as lease_end,
    ben.name as beneficiary_name,
    ben.address as beneficiary_address,
     
    ben.email as beneficiary_email,
    land.address as land_address,
    land.hectares as land_hectares,
    ls.schedule_year, ls.annual_amount, ls.paid_rent, ls.panalty_paid, ls.total_paid,
    u.i_name as created_by_user
FROM lease_payments lp
LEFT JOIN leases l ON lp.lease_id = l.lease_id
LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
LEFT JOIN land_registration land ON l.land_id = land.land_id
LEFT JOIN lease_schedules ls ON lp.schedule_id = ls.schedule_id
LEFT JOIN user_license u ON lp.created_by = u.usr_id
WHERE lp.payment_id = ?";

$stmt = $con->prepare($payment_sql);
if (!$stmt) {
    echo "<div class='alert alert-danger'>Database error: " . $con->error . "</div>";
    exit;
}

$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    echo "<div class='alert alert-danger'>Payment not found</div>";
    exit;
}
?>

<div class="container-fluid">
    <!-- Payment Summary -->
    <div class="row">
        <div class="col-md-6">
            <h6>Payment Information</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Receipt Number</strong></td>
                    <td><?= htmlspecialchars($payment['receipt_number']) ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Date</strong></td>
                    <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                </tr>
                <tr>
                    <td><strong>Amount</strong></td>
                    <td><strong>LKR <?= number_format($payment['amount'], 2) ?></strong></td>
                </tr>
                <tr>
                    <td><strong>Payment Type</strong></td>
                    <td>
                        <span class="badge badge-<?= $payment['payment_type'] == 'rent' ? 'primary' : 'warning' ?>">
                            <?= ucfirst($payment['payment_type']) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Payment Method</strong></td>
                    <td><?= ucfirst($payment['payment_method']) ?></td>
                </tr>
                <?php if ($payment['reference_number']): ?>
                <tr>
                    <td><strong>Reference Number</strong></td>
                    <td><?= htmlspecialchars($payment['reference_number']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Created On</strong></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($payment['created_on'])) ?></td>
                </tr>
                <tr>
                    <td><strong>Created By</strong></td>
                    <td><?= htmlspecialchars($payment['created_by_user'] ?? 'System') ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6>Lease Information</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Lease Number</strong></td>
                    <td><?= htmlspecialchars($payment['lease_number'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td><strong>Lease Period</strong></td>
                    <td>
                        <?= $payment['lease_start'] ? date('d/m/Y', strtotime($payment['lease_start'])) : 'N/A' ?> 
                        to 
                        <?= $payment['lease_end'] ? date('d/m/Y', strtotime($payment['lease_end'])) : 'N/A' ?>
                    </td>
                </tr>
                <?php if ($payment['schedule_year']): ?>
                <tr>
                    <td><strong>Schedule Year</strong></td>
                    <td><?= $payment['schedule_year'] ?></td>
                </tr>
                <tr>
                    <td><strong>Annual Amount</strong></td>
                    <td>LKR <?= number_format($payment['annual_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <hr>
    
    <!-- Beneficiary Information -->
    <div class="row">
        <div class="col-md-6">
            <h6>Beneficiary Information</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Name</strong></td>
                    <td><?= htmlspecialchars($payment['beneficiary_name'] ?? 'N/A') ?></td>
                </tr>
                <?php if ($payment['beneficiary_address']): ?>
                <tr>
                    <td><strong>Address</strong></td>
                    <td><?= htmlspecialchars($payment['beneficiary_address']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['beneficiary_phone']): ?>
                <tr>
                    <td><strong>Phone</strong></td>
                    <td><?= htmlspecialchars($payment['beneficiary_phone']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['beneficiary_email']): ?>
                <tr>
                    <td><strong>Email</strong></td>
                    <td><?= htmlspecialchars($payment['beneficiary_email']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6>Land Information</h6>
            <table class="table table-sm table-bordered">
                <?php if ($payment['land_address']): ?>
                <tr>
                    <td><strong>Address</strong></td>
                    <td><?= htmlspecialchars($payment['land_address']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['land_hectares']): ?>
                <tr>
                    <td><strong>Area</strong></td>
                    <td><?= number_format($payment['land_hectares'], 2) ?> Hectares</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <?php if ($payment['schedule_year']): ?>
    <hr>
    
    <!-- Schedule Status After Payment -->
    <div class="row">
        <div class="col-12">
            <h6>Schedule Status (After This Payment)</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <td><strong>Annual Amount</strong></td>
                    <td>LKR <?= number_format($payment['annual_amount'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Rent Paid</strong></td>
                    <td>LKR <?= number_format($payment['paid_rent'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Penalty Paid</strong></td>
                    <td>LKR <?= number_format($payment['panalty_paid'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Total Paid</strong></td>
                    <td><strong>LKR <?= number_format($payment['total_paid'], 2) ?></strong></td>
                </tr>
                <tr>
                    <td><strong>Balance Rent</strong></td>
                    <td>
                        <?php 
                        $balance = $payment['annual_amount'] - $payment['paid_rent'];
                        $class = $balance <= 0 ? 'text-success' : 'text-danger';
                        ?>
                        <span class="<?= $class ?>">
                            LKR <?= number_format($balance, 2) ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($payment['notes']): ?>
    <hr>
    
    <!-- Notes -->
    <div class="row">
        <div class="col-12">
            <h6>Notes</h6>
            <div class="alert alert-info">
                <?= nl2br(htmlspecialchars($payment['notes'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>