<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';

header('Content-Type: text/html');

if (isset($_GET['lease_id'])) {
    $lease_id = (int)$_GET['lease_id'];

    // Get lease details
    $lease_sql = "SELECT l.*, ben.name as beneficiary_name, cr.payment_sms, ben.language, ben.telephone, ben.rl_ben_id
                  FROM rl_lease l
                  LEFT JOIN client_registration cr ON cr.c_id = l.location_id
                  LEFT JOIN rl_beneficiaries ben ON l.beneficiary_id = ben.rl_ben_id
                  WHERE l.rl_lease_id = ?";
    $stmt = $con->prepare($lease_sql);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $lease = $stmt->get_result()->fetch_assoc();

    // Determine the most recent active payment date to enforce as the minimum selectable date.
    $lastPaymentDate = null;
    $paymentDateMin = '';
    $defaultPaymentDate = date('Y-m-d');

    $lastPaymentSql = "SELECT payment_date 
                       FROM rl_lease_payments 
                       WHERE lease_id = ? AND (status IS NULL OR status = 1) 
                       ORDER BY payment_date DESC, payment_id DESC 
                       LIMIT 1";
    if ($lpStmt = $con->prepare($lastPaymentSql)) {
        $lpStmt->bind_param("i", $lease_id);
        $lpStmt->execute();
        $lpRes = $lpStmt->get_result();
        if ($lpRes && ($lpRow = $lpRes->fetch_assoc())) {
            $lastPaymentDate = $lpRow['payment_date'];
            $paymentDateMin = $lastPaymentDate;
        }
        $lpStmt->close();
    }

    if ($lastPaymentDate && $lastPaymentDate > $defaultPaymentDate) {
        $defaultPaymentDate = $lastPaymentDate;
    }
?>
<form id="rlPaymentForm" method="post" action="ajax_residential_lease/record_payment.php">
    <input type="hidden" name="lease_id" value="<?= $lease_id ?>">
    <input type="hidden" name="payment_sms" value="<?= htmlspecialchars($lease['payment_sms'] ?? '') ?>">
    <input type="hidden" name="sms_language" value="<?= htmlspecialchars($lease['language'] ?? '') ?>">
    <input type="hidden" name="telephone" value="<?= htmlspecialchars($lease['telephone'] ?? '') ?>">
    <input type="hidden" name="ben_id" value="<?= htmlspecialchars($lease['rl_ben_id'] ?? '') ?>">
        
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number'] ?? '') ?> - <?= htmlspecialchars($lease['beneficiary_name'] ?? '') ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="rl_payment_date">Payment Date *</label>
                <input type="date" class="form-control" id="rl_payment_date" name="payment_date" 
                       value="<?= htmlspecialchars($defaultPaymentDate) ?>"
                       <?= $paymentDateMin ? 'min="'.htmlspecialchars($paymentDateMin).'"' : '' ?>
                       required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="rl_payment_method">Payment Method *</label>
                <select class="form-control" id="rl_payment_method" name="payment_method" required>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_deposit">Bank Deposit</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="rl_reference_number">Receipt Number *</label>
                <input type="text" class="form-control" id="rl_reference_number" name="receipt_number" 
                       placeholder="Enter receipt number" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="rl_amount">Amount (LKR) *</label>
                <input type="number" step="0.01" class="form-control" id="rl_amount" name="amount" 
                       required placeholder="Enter payment amount">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label for="rl_notes">Notes</label>
                <textarea class="form-control" id="rl_notes" name="notes" rows="3" 
                          placeholder="Any additional notes..."></textarea>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 text-right">
            <button type="submit" class="btn btn-success">
                <i class="fa fa-save"></i> Record Payment
            </button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
    </div>
</form>
<?php } ?>

