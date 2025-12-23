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
    l.lease_number,
    ben.name as beneficiary_name,
    ben.address as beneficiary_address,
    
    land.address as land_address,
    land.hectares as land_hectares
FROM lease_payments lp
LEFT JOIN leases l ON lp.lease_id = l.lease_id
LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
LEFT JOIN land_registration land ON l.land_id = land.land_id
WHERE lp.payment_id = ?";

$stmt = $con->prepare($payment_sql);
if (!$stmt) {
    echo "<div class='alert alert-danger'>Database error: " . $con->error . "</div>";
    exit;
}

$stmt->bind_param("i", $payment_id);
if (!$stmt->execute()) {
    echo "<div class='alert alert-danger'>Query execution error: " . $stmt->error . "</div>";
    exit;
}
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    echo "<div class='alert alert-danger'>Payment not found</div>";
    exit;
}

// Get company/client information
$client_sql = "SELECT * FROM client_registration WHERE c_id = ?";
$stmt_client = $con->prepare($client_sql);
if (!$stmt_client) {
    echo "<div class='alert alert-danger'>Client query error: " . $con->error . "</div>";
    exit;
}
$location_id = $payment['location_id'] ?? 1;
$stmt_client->bind_param("i", $location_id);
$stmt_client->execute();
$client = $stmt_client->get_result()->fetch_assoc();
?>

<div class="receipt-container" style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
    <!-- Header -->
    <div class="text-center mb-4">
        <h3 class="mb-1"><?= htmlspecialchars($client['client_name'] ?? 'Land Management System') ?></h3>
        <p class="mb-1"><?= htmlspecialchars($client['address'] ?? '') ?></p>
        <p class="mb-1">Phone: <?= htmlspecialchars($client['phone'] ?? '') ?></p>
        <hr>
        <h4 class="text-primary">PAYMENT RECEIPT</h4>
    </div>
    
    <!-- Receipt Details -->
    <div class="row mb-3">
        <div class="col-6">
            <strong>Receipt No:</strong> <?= htmlspecialchars($payment['receipt_number']) ?><br>
            <strong>Payment Date:</strong> <?= date('d/m/Y', strtotime($payment['payment_date'])) ?><br>
            <strong>Payment Method:</strong> <?= ucfirst($payment['payment_method']) ?>
        </div>
        <div class="col-6 text-right">
            <strong>Lease No:</strong> <?= htmlspecialchars($payment['lease_number'] ?? 'N/A') ?><br>
            <strong>Created On:</strong> <?= date('d/m/Y H:i', strtotime($payment['created_on'])) ?><br>
            <?php if ($payment['reference_number']): ?>
                <strong>Reference:</strong> <?= htmlspecialchars($payment['reference_number']) ?>
            <?php endif; ?>
        </div>
    </div>
    
    <hr>
    
    <!-- Payer Information -->
    <div class="mb-3">
        <h6>Received From:</h6>
        <p class="mb-1"><strong><?= htmlspecialchars($payment['beneficiary_name'] ?? 'N/A') ?></strong></p>
        <?php if ($payment['beneficiary_address']): ?>
            <p class="mb-1"><?= htmlspecialchars($payment['beneficiary_address']) ?></p>
        <?php endif; ?>
        <?php if ($payment['beneficiary_phone']): ?>
            <p class="mb-1">Phone: <?= htmlspecialchars($payment['beneficiary_phone']) ?></p>
        <?php endif; ?>
    </div>
    
    <hr>
    
    <!-- Land Information -->
    <?php if ($payment['land_address']): ?>
    <div class="mb-3">
        <h6>Land Details:</h6>
        <p class="mb-1"><?= htmlspecialchars($payment['land_address']) ?></p>
        <?php if ($payment['land_hectares']): ?>
            <p class="mb-1">Area: <?= number_format($payment['land_hectares'], 2) ?> Hectares</p>
        <?php endif; ?>
    </div>
    <hr>
    <?php endif; ?>
    
    <!-- Payment Details -->
    <div class="mb-4">
        <h6>Payment Details:</h6>
        <table class="table table-bordered">
            <tr>
                <td><strong>Payment Type</strong></td>
                <td><?= ucfirst($payment['payment_type']) ?></td>
            </tr>
            <tr>
                <td><strong>Amount Paid</strong></td>
                <td class="text-right"><strong>LKR <?= number_format($payment['amount'], 2) ?></strong></td>
            </tr>
        </table>
        
        <?php if ($payment['notes']): ?>
        <div class="mt-2">
            <strong>Notes:</strong> <?= htmlspecialchars($payment['notes']) ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Amount in Words -->
    <div class="mb-4">
        <strong>Amount in Words:</strong> 
        <span id="amountInWords"><?= convertNumberToWords($payment['amount']) ?> Rupees Only</span>
    </div>
    
    <!-- Footer -->
    <div class="row mt-5">
        <div class="col-6">
            <div class="text-center">
                <br><br>
                ___________________<br>
                <small>Received By</small>
            </div>
        </div>
        <div class="col-6">
            <div class="text-center">
                <br><br>
                ___________________<br>
                <small>Authorized Signature</small>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <small class="text-muted">
            This is a computer generated receipt.<br>
            Generated on <?= date('d/m/Y H:i:s') ?>
        </small>
    </div>
</div>

<?php
// Function to convert number to words
function convertNumberToWords($number) {
    $words = array(
        '0' => '', '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four', '5' => 'Five',
        '6' => 'Six', '7' => 'Seven', '8' => 'Eight', '9' => 'Nine', '10' => 'Ten',
        '11' => 'Eleven', '12' => 'Twelve', '13' => 'Thirteen', '14' => 'Fourteen', '15' => 'Fifteen',
        '16' => 'Sixteen', '17' => 'Seventeen', '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty',
        '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty', '60' => 'Sixty', '70' => 'Seventy',
        '80' => 'Eighty', '90' => 'Ninety'
    );
    
    if ($number < 21) {
        return $words[$number];
    } else if ($number < 100) {
        $tens = intval($number / 10) * 10;
        $units = $number % 10;
        return $words[$tens] . ($units ? ' ' . $words[$units] : '');
    } else if ($number < 1000) {
        $hundreds = intval($number / 100);
        $remainder = $number % 100;
        return $words[$hundreds] . ' Hundred' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    } else if ($number < 100000) {
        $thousands = intval($number / 1000);
        $remainder = $number % 1000;
        return convertNumberToWords($thousands) . ' Thousand' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    } else if ($number < 10000000) {
        $lakhs = intval($number / 100000);
        $remainder = $number % 100000;
        return convertNumberToWords($lakhs) . ' Lakh' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    } else {
        $crores = intval($number / 10000000);
        $remainder = $number % 10000000;
        return convertNumberToWords($crores) . ' Crore' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    }
}
?>