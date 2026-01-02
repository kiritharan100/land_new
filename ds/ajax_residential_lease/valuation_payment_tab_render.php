<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = null; $land = null; $lease = null; $error = '';

if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, name FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['rl_ben_id'];
            if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                    $land_id = (int)$land['land_id'];
                    if ($st3 = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);
                        if ($r3) { $lease = mysqli_fetch_assoc($r3); }
                        mysqli_stmt_close($st3);
                    }
                    if (!$lease) { $error = 'No lease found for this land.'; }
                } else { $error = 'No land found. Please complete Land Information.'; }
                mysqli_stmt_close($st2);
            }
        } else { $error = 'Invalid beneficiary'; }
        mysqli_stmt_close($stmt);
    }
} else { $error = 'Missing id'; }

$lease_id = $lease['rl_lease_id'] ?? 0;
$is_grant_issued = !empty($lease['outright_grants_date']);
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-header-text mb-0">Payment for Grant (Outright Grants)</h5>
        <div>
            <?php if ($lease && hasPermission(18) && !$is_grant_issued): ?>
            <button type="button" class="btn btn-success btn-sm" id="rl-add-valuation-payment-btn">
                <i class="fa fa-plus"></i> Add Valuation Payment
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-block" style="padding: 1rem;">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
        
        <?php
        // Calculate Total Valuation
        $total_valuation = (float)($lease['valuation_amount'] ?? 0);
        
        // Calculate Total Rent + Premium paid (discount counts as settled, penalty excluded)
        $total_rent_premium_paid = 0.0;
        $total_discount_applied = 0.0;
        
        if ($lease_id > 0) {
            // Sum of paid_rent + premium_paid + discount_apply from schedules
            $sql_paid = "SELECT 
                COALESCE(SUM(paid_rent), 0) AS total_rent_paid,
                COALESCE(SUM(premium_paid), 0) AS total_premium_paid,
                COALESCE(SUM(discount_apply), 0) AS total_discount
                FROM rl_lease_schedules 
                WHERE lease_id = ?";
            if ($st = mysqli_prepare($con, $sql_paid)) {
                mysqli_stmt_bind_param($st, 'i', $lease_id);
                mysqli_stmt_execute($st);
                $rs = mysqli_stmt_get_result($st);
                if ($row = mysqli_fetch_assoc($rs)) {
                    $total_rent_premium_paid = (float)$row['total_rent_paid'] + (float)$row['total_premium_paid'];
                    $total_discount_applied = (float)$row['total_discount'];
                }
                mysqli_stmt_close($st);
            }
        }
        
        // For valuation payment calculation, discount is considered as "settled" amount
        $total_settled = $total_rent_premium_paid + $total_discount_applied;
        
        // Also add valuation payments already made
        $valuation_payments_total = 0.0;
        if ($lease_id > 0) {
            $sql_val = "SELECT COALESCE(SUM(amount), 0) AS total FROM rl_valuvation_paid WHERE rl_lease_id = ? AND status = 1";
            if ($stv = mysqli_prepare($con, $sql_val)) {
                mysqli_stmt_bind_param($stv, 'i', $lease_id);
                mysqli_stmt_execute($stv);
                $rsv = mysqli_stmt_get_result($stv);
                if ($rowv = mysqli_fetch_assoc($rsv)) {
                    $valuation_payments_total = (float)$rowv['total'];
                }
                mysqli_stmt_close($stv);
            }
        }
        
        // Balance to be paid
        $balance_to_pay = max(0, $total_valuation - $total_settled - $valuation_payments_total);
        ?>

        <div class="row mb-2">
            <div class="col-sm-12">
                <div>
                    <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number'] ?? '-') ?> &nbsp;|
                    <strong>Lessee:</strong> <?= htmlspecialchars($ben['name'] ?? '-') ?> &nbsp;|
                    <strong>Land:</strong> <?= htmlspecialchars($land['land_address'] ?? ('Land #' . (int)$land['land_id'])) ?>
                </div>
            </div>
        </div>

        <!-- Outstanding Summary Alert -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="mb-0" role="alert"
                    style="background:#ffffff;border:2px solid #17a2b8;color:#17a2b8;font-size:1.15rem;font-weight:600;padding:14px 16px;border-radius:6px;letter-spacing:0.5px;">
                    <span style="font-weight:700;text-transform:uppercase;">Valuation Summary:</span><br>
                    Total Valuation: <strong>Rs. <?= number_format($total_valuation, 2) ?></strong> &nbsp;|
                    Rent + Premium + Discount Paid: <strong>Rs. <?= number_format($total_settled, 2) ?></strong> &nbsp;|
                    Valuation Payments: <strong>Rs. <?= number_format($valuation_payments_total, 2) ?></strong><br>
                    <span style="font-weight:800;color:#dc3545;">Balance to be Paid: Rs. <?= number_format($balance_to_pay, 2) ?></span>
                </div>
            </div>
        </div>

        <?php
        // Fetch valuation payments
        $payments = [];
        if ($lease_id > 0) {
            $sql_payments = "SELECT * FROM rl_valuvation_paid WHERE rl_lease_id = ? ORDER BY paid_id DESC";
            if ($stp = mysqli_prepare($con, $sql_payments)) {
                mysqli_stmt_bind_param($stp, 'i', $lease_id);
                mysqli_stmt_execute($stp);
                $rsp = mysqli_stmt_get_result($stp);
                if ($rsp) { $payments = mysqli_fetch_all($rsp, MYSQLI_ASSOC); }
                mysqli_stmt_close($stp);
            }
        }
        
        // Find the last active payment for cancel button logic
        $lastActivePaymentId = null;
        $activeTotal = 0.0;
        foreach ($payments as $p) {
            if ((int)($p['status'] ?? 1) === 1) {
                if ($lastActivePaymentId === null) {
                    $lastActivePaymentId = (int)$p['paid_id'];
                }
                $activeTotal += (float)$p['amount'];
            }
        }
        ?>

        <style>
        .rl-val-payments-table { width: 100%; }
        .rl-val-payments-table tr.cancelled-row { background: #fde2e2; }
        .rl-val-payments-table tr.cancelled-row td { color: #842029; }
        .cancelled-label { display: inline-block; padding: 2px 8px; background: #dc3545; color: #fff; font-size: 12px; border-radius: 4px; }
        </style>

        <h6 class="font-weight-bold mt-4">Valuation Payments</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm rl-val-payments-table">
                <thead class="bg-light">
                    <tr>
                        <th width="80">#</th>
                        <th width="150">Receipt No</th>
                        <th width="150">Amount (Rs.)</th>
                        <th width="120">Payment Mode</th>
                        <th>Memo / Notes</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No valuation payments recorded</td>
                    </tr>
                    <?php else: ?>
                    <?php $sn = 1; foreach ($payments as $p): ?>
                    <?php
                        $paymentId = (int)$p['paid_id'];
                        $isCancelled = (int)($p['status'] ?? 1) === 0;
                        $canCancel = !$isCancelled && !$is_grant_issued && $paymentId === $lastActivePaymentId;
                    ?>
                    <tr class="<?= $isCancelled ? 'cancelled-row' : '' ?>">
                        <td><?= $sn++ ?></td>
                        <td><?= htmlspecialchars($p['receipt_number'] ?? '-') ?></td>
                        <td class="text-right"><?= number_format((float)$p['amount'], 2) ?></td>
                        <td><?= htmlspecialchars(ucfirst($p['mode_payment'] ?? 'Cash')) ?></td>
                        <td><?= htmlspecialchars($p['memo'] ?? '') ?></td>
                        <td>
                            <?php if ($isCancelled): ?>
                            <span class="cancelled-label">Cancelled</span>
                            <?php else: ?>
                            <?php if (hasPermission(19) && $canCancel): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm rl-cancel-val-payment-btn"
                                data-id="<?= $paymentId ?>"
                                data-receipt="<?= htmlspecialchars($p['receipt_number'] ?? '') ?>"
                                data-amount="<?= htmlspecialchars($p['amount']) ?>">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($payments)): ?>
                    <tr style="font-weight:bold;background:#f6f6f6;">
                        <td colspan="2" class="text-right">Total (Active)</td>
                        <td class="text-right"><?= number_format($activeTotal, 2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Valuation Payment Modal -->
        <div class="modal fade" id="rl-valuation-payment-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Valuation Payment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="rlValuationPaymentForm" action="ajax_residential_lease/add_valuation_payment.php" method="post">
                            <input type="hidden" name="rl_lease_id" value="<?= (int)$lease_id ?>">
                            <input type="hidden" name="location_id" value="<?= htmlspecialchars($location_id ?? '') ?>">
                            
                            <div class="form-group">
                                <label>Receipt Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="receipt_number" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Amount (Rs.) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Mode</label>
                                <select class="form-control" name="mode_payment">
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Online">Online</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Memo / Notes</label>
                                <textarea class="form-control" name="memo" rows="2"></textarea>
                            </div>
                            
                            <div class="text-right">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-save"></i> Save Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            // Open Add Payment Modal
            var addBtn = document.getElementById('rl-add-valuation-payment-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    if (window.jQuery) {
                        jQuery('#rl-valuation-payment-modal').modal('show');
                    }
                });
            }

            // Handle form submission
            var form = document.getElementById('rlValuationPaymentForm');
            if (form) {
                form.addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    
                    Swal.fire({
                        title: 'Recording Payment',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: function() { Swal.showLoading(); }
                    });
                    
                    var fd = new URLSearchParams(new FormData(form));
                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: fd.toString()
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        Swal.close();
                        if (resp && resp.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: resp.message || 'Payment recorded',
                                icon: 'success'
                            }).then(function() {
                                if (window.jQuery) { jQuery('#rl-valuation-payment-modal').modal('hide'); }
                                if (typeof window.dispatchEvent === 'function') {
                                    window.dispatchEvent(new Event('rl:valuation-payments-updated'));
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: (resp && resp.message) || 'Failed to record payment',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(function() {
                        Swal.close();
                        Swal.fire({ title: 'Error!', text: 'Network error occurred.', icon: 'error' });
                    });
                });
            }

            // Cancel payment handler
            document.querySelectorAll('.rl-cancel-val-payment-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    var pid = this.getAttribute('data-id');
                    var receipt = this.getAttribute('data-receipt') || '';
                    var amount = this.getAttribute('data-amount') || '';
                    
                    if (!pid || pid === '0') {
                        Swal.fire('Error', 'Invalid payment reference', 'error');
                        return;
                    }
                    
                    Swal.fire({
                        title: 'Cancel this payment?',
                        html: 'Receipt: <strong>' + receipt + '</strong><br>Amount: Rs. ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, cancel',
                        cancelButtonText: 'No'
                    }).then(function(res) {
                        if (!res.isConfirmed) return;
                        
                        Swal.fire({
                            title: 'Cancelling...',
                            text: 'Please wait',
                            allowOutsideClick: false,
                            didOpen: function() { Swal.showLoading(); }
                        });
                        
                        var fd = new URLSearchParams();
                        fd.set('paid_id', pid);
                        
                        fetch('ajax_residential_lease/cancel_valuation_payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: fd.toString()
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(resp) {
                            Swal.close();
                            if (resp && resp.success) {
                                Swal.fire('Cancelled', resp.message || 'Payment cancelled', 'success').then(function() {
                                    if (typeof window.dispatchEvent === 'function') {
                                        window.dispatchEvent(new Event('rl:valuation-payments-updated'));
                                    }
                                });
                            } else {
                                Swal.fire('Error', (resp && resp.message) || 'Failed to cancel payment', 'error');
                            }
                        })
                        .catch(function() {
                            Swal.close();
                            Swal.fire('Error', 'Network error', 'error');
                        });
                    });
                });
            });
        })();
        </script>

        <?php endif; ?>
    </div>
</div>
