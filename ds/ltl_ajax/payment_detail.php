<?php

echo "<h4>Payment Detail</h4><hr>";
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: text/html');

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$paymentId = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$leaseId   = isset($_GET['lease_id']) ? (int)$_GET['lease_id'] : 0;

if ($paymentId <= 0) {
    echo '<div class="text-danger">Invalid payment reference.</div>';
    exit;
}

$paymentSql = "SELECT lp.*, l.lease_number, l.file_number, ben.name AS beneficiary_name
               FROM lease_payments lp
               INNER JOIN leases l ON lp.lease_id = l.lease_id
               INNER JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
               WHERE lp.payment_id = ? LIMIT 1";

if (!($stmt = $con->prepare($paymentSql))) {
    echo '<div class="text-danger">Failed to load payment.</div>';
    exit;
}
$stmt->bind_param("i", $paymentId);
$stmt->execute();
$resPayment = $stmt->get_result();
$payment = $resPayment ? $resPayment->fetch_assoc() : null;
$stmt->close();

if (!$payment) {
    echo '<div class="text-danger">Payment not found.</div>';
    exit;
}

if ($leaseId > 0 && (int)$payment['lease_id'] !== $leaseId) {
    echo '<div class="text-danger">Payment does not belong to this lease.</div>';
    exit;
}

$detailSql = "SELECT lpd.*, ls.start_date, ls.end_date, ls.annual_amount
              FROM lease_payments_detail lpd
              LEFT JOIN lease_schedules ls ON lpd.schedule_id = ls.schedule_id
              WHERE lpd.payment_id = ?
                AND (lpd.status IS NULL OR lpd.status = 1)
              ORDER BY ls.start_date, lpd.id";

$details = [];
if ($dStmt = $con->prepare($detailSql)) {
    $dStmt->bind_param("i", $paymentId);
    $dStmt->execute();
    $resDetails = $dStmt->get_result();
    if ($resDetails) {
        $details = $resDetails->fetch_all(MYSQLI_ASSOC);
    }
    $dStmt->close();
}

$totals = [
    'premium' => 0.0,
    'rent'    => 0.0,
    'penalty' => 0.0,
    'discount'=> 0.0,
];

foreach ($details as $row) {
    $totals['premium'] += (float)($row['premium_paid'] ?? 0);
    $totals['rent']    += (float)($row['rent_paid'] ?? 0);
    $totals['penalty'] += (float)($row['penalty_paid'] ?? 0);
    $totals['discount']+= (float)($row['discount_apply'] ?? 0);
}

$paymentDate   = $payment['payment_date'] ?? '';
$receiptNumber = $payment['reference_number'] ?? '-';
$amountValue   = isset($payment['amount']) ? number_format((float)$payment['amount'], 2) : '0.00';
?>
<style>
.ltl-payment-view-meta {
    font-size: 0.95rem;
    line-height: 1.4;
}

.ltl-payment-view-meta .label {
    font-weight: 600;
}

.ltl-payment-detail-table th,
.ltl-payment-detail-table td {
    font-size: 0.92rem;
    vertical-align: middle;
}

.ltl-payment-detail-table tfoot td {
    font-weight: 700;
    background: #f8f9fa;
}
</style>

<div class="ltl-payment-view-meta mb-3">
    <div> Lease: <?= h($payment['lease_number'] ?? '-') ?> &nbsp;|&nbsp;
        File: <?= h($payment['file_number'] ?? '-') ?>
    </div>
    <div> Lessee: <?= h($payment['beneficiary_name'] ?? '-') ?></div>
    <div> Payment Date: <?= h($paymentDate) ?> &nbsp;|&nbsp;
        Receipt # <?= h($receiptNumber) ?>
    </div>
    <div> >Payment Amount: Rs. <?= $amountValue ?></div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm ltl-payment-detail-table">
        <thead class="bg-light">
            <tr>
                <th>Start Date</th>
                <th>End Date</th>
                <th class="text-right">Annual Amount</th>
                <th class="text-right">Premium Paid</th>
                <th class="text-right">Rent Paid</th>
                <th class="text-right">Penalty Paid</th>
                <th class="text-right">Discount Applied</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$details): ?>
            <tr>
                <td colspan="7" class="text-center">No payment detail found.</td>
            </tr>
            <?php else: foreach ($details as $row): ?>
            <tr>
                <td><?= h($row['start_date'] ?? '-') ?></td>
                <td><?= h($row['end_date'] ?? '-') ?></td>
                <td class="text-right"><?= number_format((float)($row['annual_amount'] ?? 0), 2) ?></td>
                <td class="text-right"><?= number_format((float)($row['premium_paid'] ?? 0), 2) ?></td>
                <td class="text-right"><?= number_format((float)($row['rent_paid'] ?? 0), 2) ?></td>
                <td class="text-right"><?= number_format((float)($row['penalty_paid'] ?? 0), 2) ?></td>
                <td class="text-right"><?= number_format((float)($row['discount_apply'] ?? 0), 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right">Total</td>
                <td class="text-right"><?= number_format($totals['premium'], 2) ?></td>
                <td class="text-right"><?= number_format($totals['rent'], 2) ?></td>
                <td class="text-right"><?= number_format($totals['penalty'], 2) ?></td>
                <td class="text-right"><?= number_format($totals['discount'], 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>