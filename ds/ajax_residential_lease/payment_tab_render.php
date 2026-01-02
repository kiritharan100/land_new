<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = null; $land = null; $lease = null; $error = '';

if ($md5 !== ''){
  if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, name FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmt, 's', $md5);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($ben = mysqli_fetch_assoc($res))) {
      $ben_id = (int)$ben['rl_ben_id'];
      if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')){
        mysqli_stmt_bind_param($st2, 'i', $ben_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);
        if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
          $land_id = (int)$land['land_id'];
          if ($st3 = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')){
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

$is_grant_issued = !empty($lease['outright_grants_date']);
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-header-text mb-0">Payments</h5>
        <div>
            <?php if ($lease && !$is_grant_issued): ?>
            <?php if (hasPermission(18)): ?>
            <button type="button" class="btn btn-success btn-sm" id="rl-record-payment-btn"><i class="fa fa-plus"></i>
                Record Payment</button>
            <?php 
            // Recalculate penalties when loading this tab
            $lease_id = $lease['rl_lease_id'] ?? 0;
            if ($lease_id > 0) {
                $prevLeaseIdRequest = $_REQUEST['lease_id'] ?? null;
                $_REQUEST['lease_id'] = $lease_id;
                ob_start();
                include __DIR__ . '/rl_cal_penalty.php';
                ob_end_clean();
                if ($prevLeaseIdRequest === null) {
                    unset($_REQUEST['lease_id']);
                } else {
                    $_REQUEST['lease_id'] = $prevLeaseIdRequest;
                }
            }
            ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-block" style="padding: 1rem;">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
        <div class="row mb-2">
            <div class="col-sm-12">
                <div>
                    <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number'] ?? '-') ?> &nbsp;|
                    <strong>Lessee:</strong> <?= htmlspecialchars($ben['name'] ?? '-') ?> &nbsp;|
                    <strong>Land:</strong>
                    <?= htmlspecialchars($land['land_address'] ?? ('Land #' . (int)$land['land_id'])) ?>
                </div>
            </div>
        </div>

        <?php
      // Compute outstanding totals for Rent, Penalty, Premium
      $rent_outstanding = 0.0; $penalty_outstanding = 0.0; $premium_outstanding = 0.0; $total_outstanding = 0.0;
      if ($lease && isset($lease['rl_lease_id'])) {
        $lid = (int)$lease['rl_lease_id'];
        
        // Rent outstanding
        $sqlRentDue = "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) AS due_rent FROM rl_lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
        $sqlRentPaid = "SELECT COALESCE(SUM(paid_rent),0) AS paid_rent_all FROM rl_lease_schedules WHERE lease_id=?";
        $due_rent = 0.0; $paid_rent_all = 0.0;
        if ($st1 = mysqli_prepare($con,$sqlRentDue)) { mysqli_stmt_bind_param($st1,'i',$lid); mysqli_stmt_execute($st1); $r1 = mysqli_stmt_get_result($st1); if ($r1 && ($rw=mysqli_fetch_assoc($r1))) $due_rent = (float)$rw['due_rent']; mysqli_stmt_close($st1);}        
        if ($st2 = mysqli_prepare($con,$sqlRentPaid)) { mysqli_stmt_bind_param($st2,'i',$lid); mysqli_stmt_execute($st2); $r2 = mysqli_stmt_get_result($st2); if ($r2 && ($rw=mysqli_fetch_assoc($r2))) $paid_rent_all = (float)$rw['paid_rent_all']; mysqli_stmt_close($st2);}        
        $rent_outstanding = max(0, $due_rent - $paid_rent_all);

        // Penalty outstanding
        $sqlPenDue = "SELECT COALESCE(SUM(panalty),0) AS due_penalty FROM rl_lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
        $sqlPenPaid = "SELECT COALESCE(SUM(panalty_paid),0) AS paid_penalty_all FROM rl_lease_schedules WHERE lease_id=?";
        $due_penalty = 0.0; $paid_penalty_all = 0.0;
        if ($st3 = mysqli_prepare($con,$sqlPenDue)) { mysqli_stmt_bind_param($st3,'i',$lid); mysqli_stmt_execute($st3); $r3 = mysqli_stmt_get_result($st3); if ($r3 && ($rw=mysqli_fetch_assoc($r3))) $due_penalty = (float)$rw['due_penalty']; mysqli_stmt_close($st3);}        
        if ($st4 = mysqli_prepare($con,$sqlPenPaid)) { mysqli_stmt_bind_param($st4,'i',$lid); mysqli_stmt_execute($st4); $r4 = mysqli_stmt_get_result($st4); if ($r4 && ($rw=mysqli_fetch_assoc($r4))) $paid_penalty_all = (float)$rw['paid_penalty_all']; mysqli_stmt_close($st4);}        
        $penalty_outstanding = max(0, $due_penalty - $paid_penalty_all);

        // Premium outstanding
        $sqlPremDue = "SELECT COALESCE(SUM(premium),0) AS due_premium FROM rl_lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
        $sqlPremPaid = "SELECT COALESCE(SUM(premium_paid),0) AS paid_premium_all FROM rl_lease_schedules WHERE lease_id=?";
        $due_premium = 0.0; $paid_premium_all = 0.0;
        if ($st5 = mysqli_prepare($con,$sqlPremDue)) { mysqli_stmt_bind_param($st5,'i',$lid); mysqli_stmt_execute($st5); $r5 = mysqli_stmt_get_result($st5); if ($r5 && ($rw=mysqli_fetch_assoc($r5))) $due_premium = (float)$rw['due_premium']; mysqli_stmt_close($st5);}        
        if ($st6 = mysqli_prepare($con,$sqlPremPaid)) { mysqli_stmt_bind_param($st6,'i',$lid); mysqli_stmt_execute($st6); $r6 = mysqli_stmt_get_result($st6); if ($r6 && ($rw=mysqli_fetch_assoc($r6))) $paid_premium_all = (float)$rw['paid_premium_all']; mysqli_stmt_close($st6);}        
        $premium_outstanding = max(0, $due_premium - $paid_premium_all);

        $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;
      }
      ?>
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="mb-0" role="alert"
                    style="background:#ffffff;border:2px solid #dc3545;color:#dc3545;font-size:1.15rem;font-weight:600;padding:14px 16px;border-radius:6px;letter-spacing:0.5px;">
                    <span style="font-weight:700;text-transform:uppercase;">Outstanding:</span>
                    Premium: <?= number_format($premium_outstanding, 2) ?> &nbsp;|
                    Penalty: <?= number_format($penalty_outstanding, 2) ?> &nbsp;|
                    Rent: <?= number_format($rent_outstanding, 2) ?> &nbsp;|
                    <span style="font-weight:800;">Total: <?= number_format($total_outstanding, 2) ?></span>
                </div>
            </div>
        </div>

        <?php
      // Fetch payments with allocated year
      $payments = [];
      if ($stP = mysqli_prepare($con, 'SELECT lp.*, YEAR(lp.payment_date) AS payment_year, ls.schedule_year AS allocated_year FROM rl_lease_payments lp LEFT JOIN rl_lease_schedules ls ON lp.schedule_id = ls.schedule_id WHERE lp.lease_id=? ORDER BY lp.payment_date ASC, lp.payment_id ASC')){
        mysqli_stmt_bind_param($stP, 'i', $lease['rl_lease_id']);
        mysqli_stmt_execute($stP);
        $rp = mysqli_stmt_get_result($stP);
        if ($rp) { $payments = mysqli_fetch_all($rp, MYSQLI_ASSOC); }
        mysqli_stmt_close($stP);
      }
      // Identify the latest active payment for cancel button
      $lastActivePaymentId = null;
      $totals = ['rent'=>0.0,'penalty'=>0.0,'premium'=>0.0,'discount'=>0.0,'amount'=>0.0];
      foreach ($payments as $pRow) {
        $rowCancelled = isset($pRow['status']) && (string)$pRow['status'] === '0';
        if (!$rowCancelled) {
          $lastActivePaymentId = (int)($pRow['payment_id'] ?? $pRow['id'] ?? 0);
          $totals['rent']     += (float)($pRow['rent_paid'] ?? 0);
          $totals['penalty']  += (float)($pRow['panalty_paid'] ?? 0);
          $totals['premium']  += (float)($pRow['premium_paid'] ?? 0);
          $totals['discount'] += (float)($pRow['discount_apply'] ?? 0);
          $totals['amount']   += (float)($pRow['amount'] ?? 0);
        }
      }
      ?>
        <style>
        .rl-payments-table { width: 100%; }
        .rl-payments-table th.col-date, .rl-payments-table td.col-date { width: 9.5rem; }
        .rl-payments-table th.col-amt, .rl-payments-table td.col-amt { width: 10rem; text-align: right; }
        .rl-payments-table tr.cancelled-payment-row { background: #fde2e2; }
        .rl-payments-table tr.cancelled-payment-row td { color: #842029; }
        .cancelled-label { display: inline-block; padding: 2px 8px; background: #dc3545; color: #fff; font-size: 12px; border-radius: 4px; }
        </style>
        <div class="table-responsive">
            <table class="table table-bordered table-sm rl-payments-table">
                <thead class="bg-light">
                    <tr>
                        <th width='120' class="col-date">Date</th>
                        <th width='200'>Receipt No</th>
                        <th width='120'>Method</th>
                        <th width='120' class="col-amt">Rent Paid</th>
                        <th width='120' class="col-amt">Penalty Paid</th>
                        <th width='120' class="col-amt">Premium Paid</th>
                        <th width='120' class="col-amt">Discount</th>
                        <th width='120' class="col-amt">Total Payment</th>
                        <th width='230px'>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$payments): ?>
                    <tr>
                        <td colspan="9" class="text-center">No payments recorded</td>
                    </tr>
                    <?php else: foreach ($payments as $p): ?>
                    <?php
                $paymentId = (int)($p['payment_id'] ?? $p['id'] ?? 0);
                $isCancelled = isset($p['status']) && (string)$p['status'] === '0';
                $canCancel = !$isCancelled && !$is_grant_issued && $paymentId === $lastActivePaymentId;
              ?>
                    <tr class="<?= $isCancelled ? 'cancelled-payment-row' : '' ?>">
                        <td class="col-date"><?= htmlspecialchars($p['payment_date']) ?></td>
                        <td><?= htmlspecialchars($p['receipt_number'] ?? $p['reference_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars(ucfirst($p['payment_method'] ?? 'cash')) ?></td>
                        <td class="col-amt"><?= number_format((float)($p['rent_paid'] ?? 0), 2) ?></td>
                        <td class="col-amt"><?= number_format((float)($p['panalty_paid'] ?? 0), 2) ?></td>
                        <td class="col-amt"><?= number_format((float)($p['premium_paid'] ?? 0), 2) ?></td>
                        <td class="col-amt"><?= number_format((float)($p['discount_apply'] ?? 0), 2) ?></td>
                        <td class="col-amt"><?= number_format((float)$p['amount'], 2) ?></td>
                        <td>
                            <?php if ($isCancelled): ?>
                            <span class="cancelled-label">Cancelled</span>
                            <?php else: ?>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-info rl-view-payment-btn"
                                    data-payment-id="<?= $paymentId ?>">
                                    <i class="fa fa-eye"></i> View
                                </button>
                                <?php if (hasPermission(19) && $canCancel): ?>
                                <button type="button" class="btn btn-outline-danger rl-cancel-payment-btn"
                                    data-payment-id="<?= $paymentId ?>"
                                    data-receipt="<?= htmlspecialchars($p['receipt_number'] ?? '') ?>"
                                    data-date="<?= htmlspecialchars($p['payment_date']) ?>"
                                    data-amount="<?= htmlspecialchars($p['amount']) ?>">
                                    <i class="fa fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    <?php if ($payments): ?>
                    <tr style="font-weight:bold;background:#f6f6f6;">
                        <td colspan="3" class="text-right">Total (Active)</td>
                        <td class="col-amt"><?= number_format($totals['rent'], 2) ?></td>
                        <td class="col-amt"><?= number_format($totals['penalty'], 2) ?></td>
                        <td class="col-amt"><?= number_format($totals['premium'], 2) ?></td>
                        <td class="col-amt"><?= number_format($totals['discount'], 2) ?></td>
                        <td class="col-amt"><?= number_format($totals['amount'], 2) ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Payment Modal -->
        <div class="modal fade" id="rl-payment-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Payment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body" id="rl-payment-modal-body">
                        <div style="text-align:center;padding:16px">
                            <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Payment Modal -->
        <div class="modal fade" id="rl-payment-view-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header align-items-center">
                        <h5 class="modal-title mb-0">Payment Details</h5>
                        <div align='right'>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                    aria-hidden="true">&times;</span></button>
                        </div>
                    </div>
                    <div align='right' style='padding-right:80px; padding-top:5px;'>
                        <button type="button" class="btn btn-success btn-sm mr-2" id="rl-payment-view-print-btn">
                            <i class="fa fa-print"></i> Print
                        </button>
                    </div>
                    <div class="modal-body" id="rl-payment-view-body">
                        <div style="text-align:center;padding:16px">
                            <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var btn = document.getElementById('rl-record-payment-btn');
            if (btn) {
                btn.addEventListener('click', function() {
                    var body = document.getElementById('rl-payment-modal-body');
                    if (body) {
                        body.innerHTML = '<div style="text-align:center;padding:16px"><img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" /></div>';
                    }
                    var url = 'ajax_residential_lease/payment_form.php?lease_id=<?= (int)($lease['rl_lease_id'] ?? 0) ?>&_ts=' + Date.now();
                    fetch(url)
                        .then(function(r) { return r.text(); })
                        .then(function(html) {
                            body.innerHTML = html;
                            try {
                                var form = body.querySelector('#rlPaymentForm');
                                if (form) {
                                    form.addEventListener('submit', function(ev) {
                                        ev.preventDefault();
                                        Swal.fire({ title: 'Recording Payment', text: 'Please wait...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                                        var fd = new URLSearchParams(new FormData(form));
                                        fetch(form.getAttribute('action') || 'ajax_residential_lease/record_payment.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                            body: fd.toString()
                                        })
                                        .then(function(r) { return r.text(); })
                                        .then(function(txt) {
                                            var resp;
                                            try { resp = JSON.parse(txt); } catch(e) {
                                                Swal.close();
                                                Swal.fire({ title: 'Error!', text: 'Server response was not JSON: ' + (txt ? txt.substring(0, 300) : 'Empty response'), icon: 'error' });
                                                return;
                                            }
                                            Swal.close();
                                            if (resp && resp.success) {
                                                Swal.fire({ title: 'Success!', text: resp.message || 'Payment recorded', icon: 'success' }).then(function() {
                                                    if (window.jQuery) { jQuery('#rl-payment-modal').modal('hide'); }
                                                    if (typeof window.dispatchEvent === 'function') { window.dispatchEvent(new Event('rl:payments-updated')); }
                                                });
                                            } else {
                                                Swal.fire({ title: 'Error!', text: (resp && resp.message) || 'Failed to record payment', icon: 'error' });
                                            }
                                        })
                                        .catch(function() { Swal.close(); Swal.fire({ title: 'Error!', text: 'Network error occurred.', icon: 'error' }); });
                                    });
                                }
                            } catch(e) {}
                        })
                        .catch(function() { body.innerHTML = '<div class="text-danger">Failed to load payment form.</div>'; });
                    if (window.jQuery) { jQuery('#rl-payment-modal').modal('show'); }
                });
            }

            // View payment detail
            var viewModal = document.getElementById('rl-payment-view-modal');
            var viewBody = document.getElementById('rl-payment-view-body');
            var viewPrintBtn = document.getElementById('rl-payment-view-print-btn');
            var viewLoaderHtml = '<div style="text-align:center;padding:16px"><img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" /></div>';

            function paymentViewError(msg) {
                if (typeof Swal !== 'undefined' && Swal.fire) { Swal.fire('Error', msg, 'error'); } else { alert(msg); }
            }

            function openPaymentPrint() {
                if (!viewBody) return;
                var bodyHtml = viewBody.innerHTML || '';
                if (!bodyHtml.trim()) { paymentViewError('Nothing to print yet.'); return; }
                var printWin = window.open('', 'print-payment-detail');
                if (!printWin) { paymentViewError('Please allow popups to print.'); return; }
                var styles = '<style>body{font-family:Arial, sans-serif;margin:16px;} table{border-collapse:collapse;width:100%;} table,th,td{border:1px solid #444;} th,td{padding:6px;font-size:12px;vertical-align:middle;} tfoot td{font-weight:700;background:#f8f9fa;}</style>';
                printWin.document.write('<html><head><title>Payment Details</title>' + styles + '</head><body>' + bodyHtml + '</body></html>');
                printWin.document.close();
                printWin.focus();
                printWin.print();
            }

            if (viewPrintBtn) { viewPrintBtn.addEventListener('click', openPaymentPrint); }

            document.querySelectorAll('.rl-view-payment-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    var pid = this.getAttribute('data-payment-id');
                    if (!pid || pid === '0') { paymentViewError('Invalid payment reference'); return; }
                    if (viewBody) { viewBody.innerHTML = viewLoaderHtml; }
                    var url = 'ajax_residential_lease/payment_detail.php?lease_id=<?= (int)($lease['rl_lease_id'] ?? 0) ?>&payment_id=' + encodeURIComponent(pid) + '&_ts=' + Date.now();
                    fetch(url).then(function(r) { return r.text(); }).then(function(html) { if (viewBody) { viewBody.innerHTML = html; } }).catch(function() { if (viewBody) { viewBody.innerHTML = '<div class="text-danger">Failed to load payment details.</div>'; } });
                    if (window.jQuery) { jQuery('#rl-payment-view-modal').modal('show'); } else if (viewModal) { viewModal.style.display = 'block'; }
                });
            });

            // Cancel payment handler
            document.querySelectorAll('.rl-cancel-payment-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    var pid = this.getAttribute('data-payment-id');
                    var receipt = this.getAttribute('data-receipt') || '';
                    var pdate = this.getAttribute('data-date') || '';
                    var amount = this.getAttribute('data-amount') || '';
                    if (!pid || pid === '0') { Swal.fire('Error', 'Invalid payment reference', 'error'); return; }
                    Swal.fire({
                        title: 'Cancel this payment?',
                        html: 'Receipt: <strong>' + receipt + '</strong><br>Date: ' + pdate + '<br>Amount: LKR ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, cancel',
                        cancelButtonText: 'No'
                    }).then(function(res) {
                        if (!res.isConfirmed) return;
                        Swal.fire({ title: 'Cancelling...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        var fd = new URLSearchParams();
                        fd.set('payment_id', pid);
                        fetch('ajax_residential_lease/cancel_payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: fd.toString()
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(resp) {
                            Swal.close();
                            if (resp && resp.success) {
                                Swal.fire('Cancelled', resp.message || 'Payment cancelled and penalties recalculated', 'success').then(function() {
                                    if (typeof window.dispatchEvent === 'function') { window.dispatchEvent(new Event('rl:payments-updated')); }
                                });
                            } else {
                                Swal.fire('Error', (resp && resp.message) || 'Failed to cancel payment', 'error');
                            }
                        })
                        .catch(function() { Swal.close(); Swal.fire('Error', 'Network error', 'error'); });
                    });
                });
            });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>
