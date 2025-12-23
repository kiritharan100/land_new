<?php
require('../../db.php');
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$lease_id = intval($_GET['lease_id']);

try {
    if ($lease_id <= 0) {
        throw new Exception('Invalid lease ID');
    }
    
    // Get lease information
    $lease_query = "SELECT stl.lease_number, stl.lease_year, stl.annual_fee, 
                           stl.total_paid, stl.penalty_amount, stl.penalty_paid,
                           CONCAT(lr.reg_no, ' - ', lr.district, ', ', lr.ds_division) as land_info,
                           b.beneficiary_name
                    FROM short_term_leases stl
                    LEFT JOIN land_registration lr ON stl.land_registration_id = lr.id
                    LEFT JOIN beneficiaries b ON stl.beneficiary_id = b.id
                    WHERE stl.lease_id = ?";
    $stmt = $con->prepare($lease_query);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $lease_result = $stmt->get_result();
    $lease = $lease_result->fetch_assoc();
    
    if (!$lease) {
        throw new Exception('Lease not found');
    }
    
    // Get payment history
    $payments_query = "SELECT payment_date, lease_amount_paid, penalty_amount_paid, 
                              total_amount, payment_method, receipt_number, reference_number,
                              bank_details, payment_notes, created_on
                       FROM short_term_lease_payments 
                       WHERE st_lease_id = ?
                       ORDER BY payment_date DESC, created_on DESC";
    $stmt = $con->prepare($payments_query);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $payments_result = $stmt->get_result();
    
    ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><strong>Lease Information</strong></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Lease Number:</strong> <?php echo htmlspecialchars($lease['lease_number']); ?></p>
                            <p><strong>Land:</strong> <?php echo htmlspecialchars($lease['land_info']); ?></p>
                            <p><strong>Beneficiary:</strong> <?php echo htmlspecialchars($lease['beneficiary_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Year:</strong> <?php echo $lease['lease_year']; ?></p>
                            <p><strong>Annual Fee:</strong> LKR <?php echo number_format($lease['annual_fee'], 2); ?></p>
                            <p><strong>Total Paid:</strong> LKR <?php echo number_format($lease['total_paid'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <h6><strong>Payment History</strong></h6>
            <?php if ($payments_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Lease Amount</th>
                                <th>Penalty Amount</th>
                                <th>Total Amount</th>
                                <th>Method</th>
                                <th>Receipt No.</th>
                                <th>Reference</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                    <td class="text-right">LKR <?php echo number_format($payment['lease_amount_paid'], 2); ?></td>
                                    <td class="text-right">LKR <?php echo number_format($payment['penalty_amount_paid'], 2); ?></td>
                                    <td class="text-right"><strong>LKR <?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['receipt_number'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_notes'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td><strong>Total Payments:</strong></td>
                                <td class="text-right"><strong>LKR <?php echo number_format($lease['total_paid'], 2); ?></strong></td>
                                <td class="text-right"><strong>LKR <?php echo number_format($lease['penalty_paid'], 2); ?></strong></td>
                                <td class="text-right"><strong>LKR <?php echo number_format($lease['total_paid'] + $lease['penalty_paid'], 2); ?></strong></td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No payments have been recorded for this lease yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

$con->close();
?>