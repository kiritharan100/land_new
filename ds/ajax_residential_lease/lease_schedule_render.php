<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

// Permission: write-off indicator (perm id 8)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$canWriteOff = (isset($_SESSION['permissions']) && in_array(8, $_SESSION['permissions']));

$md5 = $_GET['id'] ?? '';
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
                } else {
                    $error = 'No land found. Complete Land Information.';
                }
                mysqli_stmt_close($st2);
            }
        } else {
            $error = 'Invalid beneficiary';
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $error = 'Missing id';
}
?>
<style>
.current-schedule {
    background: #28a745 !important;
    color: white !important;
    font-weight: bold;
}
.current-schedule td {
    color: white !important;
}
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-header-text mb-0">Lease Payment Schedule</h5>
        <div>
            <?php if ($lease): ?>
            <button type='button' class='btn btn-info btn-sm' id='rl-regenerate-penalty-btn'
                data-lease-id='<?= (int)($lease['rl_lease_id'] ?? 0) ?>'> Regenerate Penalty </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-block" style="padding: 1rem;">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>

        <div class="row mb-2">
            <div class="col-sm-12">
                <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number']) ?> |
                <strong>Lessee:</strong> <?= htmlspecialchars($ben['name']) ?> |
                <strong>Land:</strong> <?= htmlspecialchars($land['land_address']) ?><br>
                <strong>Start:</strong> <?= htmlspecialchars($lease['start_date']) ?> |
                <strong>End:</strong> <?= htmlspecialchars($lease['end_date']) ?>
            </div>
        </div>

        <?php
        // Load schedules
        $schedules = [];
        if ($stS = mysqli_prepare($con, 'SELECT * FROM rl_lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
            mysqli_stmt_bind_param($stS, 'i', $lease['rl_lease_id']);
            mysqli_stmt_execute($stS);
            $rs = mysqli_stmt_get_result($stS);
            $schedules = mysqli_fetch_all($rs, MYSQLI_ASSOC);
            mysqli_stmt_close($stS);
        }

        $prev_balance_rent = 0;
        $prev_balance_penalty = 0;
        $prev_premium_balance = 0;
        $count = 1;
        
        // Show premium column only for first lease
        $showPremiumCols = ((int)$lease['is_it_first_ease'] === 1);
        ?>

        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="bg-light">
                    <tr>
                        <th>#</th>
                        <th>Start</th>
                        <th>End</th>
                        <?php if ($showPremiumCols): ?>
                        <th>Premium</th>
                        <th>Premium Paid</th>
                        <th>Premium Bal</th>
                        <?php endif; ?>
                        <th>Annual Lease</th>
                        <th>Paid Rent</th>
                        <th>Rent Bal</th>
                        <th>Penalty</th>
                        <th>Penalty Paid</th>
                        <th>Penalty Bal</th>
                        <th>Total Paid</th>
                        <th>Total Outst</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$schedules): ?>
                    <tr>
                        <td colspan="15" class="text-center">No schedules found</td>
                    </tr>
                    <?php else: foreach ($schedules as $schedule):
                        $paid_rent = (float)$schedule['paid_rent'];
                        $annual = (float)$schedule['annual_amount'];
                        $discount = (float)$schedule['discount_apply'];

                        $effective_due = $annual - $discount;
                        $balance_rent = $prev_balance_rent + ($effective_due - $paid_rent);
                        $prev_balance_rent = $balance_rent;

                        $penalty = (float)$schedule['panalty'];
                        $penalty_paid = (float)$schedule['panalty_paid'];
                        $balance_penalty = $prev_balance_penalty + ($penalty - $penalty_paid);
                        $prev_balance_penalty = $balance_penalty;

                        $premium = (float)$schedule['premium'];
                        $premium_paid = (float)$schedule['premium_paid'];
                        if ($showPremiumCols) {
                            $prev_premium_balance += ($premium - $premium_paid);
                        }

                        $total_payment = $paid_rent + $penalty_paid + ($showPremiumCols ? $premium_paid : 0);
                        $total_outstanding = $balance_rent + $balance_penalty + ($showPremiumCols ? $prev_premium_balance : 0);

                        $today = date('Y-m-d');
                        $isCurrent = ($schedule['start_date'] <= $today && $schedule['end_date'] >= $today);

                        if ($isCurrent) {
                            $status1 = '<span class="badge" style="background:#006400;color:white;">P</span>';
                            $rowClass = 'current-schedule';
                        } else {
                            if ($schedule['end_date'] < $today && $total_outstanding <= 0) {
                                $status1 = '<i class="fa fa-check text-success"></i>';
                                $rowClass = '';
                            } elseif ($schedule['end_date'] < $today && $total_outstanding > 0) {
                                $status1 = '<span class="badge badge-danger">Overdue</span>';
                                $rowClass = 'table-danger';
                            } else {
                                $status1 = '<span class="badge badge-warning">Pending</span>';
                                $rowClass = '';
                            }
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-center"><?= $count++ ?></td>
                        <td align='center'><?= htmlspecialchars($schedule['start_date']) ?></td>
                        <td align='center'><?= htmlspecialchars($schedule['end_date']) ?></td>
                        
                        <?php if ($showPremiumCols): ?>
                        <td class="text-right"><?= number_format($premium, 2) ?></td>
                        <td class="text-right"><?= number_format($premium_paid, 2) ?></td>
                        <td class="text-right"><?= number_format($prev_premium_balance, 2) ?></td>
                        <?php endif; ?>
                        
                        <td class="text-right"><?= number_format($annual, 2) ?></td>
                        <td class="text-right"><?= number_format($paid_rent, 2) ?></td>
                        <td class="text-right"><?= number_format($balance_rent, 2) ?></td>
                        
                        <td class="text-right">
                            <?php if ($canWriteOff && $penalty > 0):
                                $schedule_id = (int)($schedule['schedule_id'] ?? 0);
                                $lease_id_val = (int)$lease['rl_lease_id'];
                                $penalty_due = max(0, $penalty - $penalty_paid);
                                if ($penalty_due > 0):
                            ?>
                            <span class="badge rl-writeoff-badge" 
                                data-schedule-id="<?= $schedule_id ?>" 
                                data-lease-id="<?= $lease_id_val ?>"
                                data-default-amount="<?= number_format($penalty_due, 2, '.', '') ?>"
                                style="background:#006400;color:white; cursor:pointer;">W</span>
                            <?php endif; endif; ?>
                            <span class="penalty-amount" data-schedule-id="<?= (int)($schedule['schedule_id'] ?? 0) ?>"><?= number_format($penalty, 2) ?></span>
                        </td>
                        <td class="text-right"><?= number_format($penalty_paid, 2) ?></td>
                        <td class="text-right"><?= number_format($balance_penalty, 2) ?></td>
                        <td class="text-right"><?= number_format($total_payment, 2) ?></td>
                        <td class="text-right"><?= number_format($total_outstanding, 2) ?></td>
                        <td class="text-center"><?= $status1 ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Regenerate penalty button handler
(function() {
    var rb = document.getElementById('rl-regenerate-penalty-btn');
    if (!rb) return;
    rb.addEventListener('click', function() {
        var leaseId = this.getAttribute('data-lease-id') || '';
        if (!leaseId || leaseId === '0') {
            if (typeof Swal !== 'undefined') Swal.fire('Error', 'Invalid lease id', 'error');
            else alert('Invalid lease id');
            return;
        }
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                title: 'Regenerating penalties',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
        }
        fetch('ajax_residential_lease/rl_cal_penalty.php?lease_id=' + encodeURIComponent(leaseId))
            .then(function(r) { return r.text(); })
            .then(function(txt) {
                if (typeof Swal !== 'undefined' && Swal.close) Swal.close();
                if (typeof Swal !== 'undefined' && Swal.fire) {
                    Swal.fire('Done', 'Penalties regenerated successfully', 'success').then(function() {
                        try { window.dispatchEvent(new Event('rl:schedule-updated')); } catch(e) {}
                    });
                } else {
                    alert('Penalties regenerated successfully');
                }
            })
            .catch(function() {
                if (typeof Swal !== 'undefined' && Swal.close) Swal.close();
                if (typeof Swal !== 'undefined' && Swal.fire) Swal.fire('Error', 'Failed to regenerate penalties', 'error');
                else alert('Failed to regenerate penalties');
            });
    });
})();

// Write-off badge handler
(function() {
    document.addEventListener('click', function(ev) {
        var el = ev.target.closest && ev.target.closest('.rl-writeoff-badge');
        if (!el) return;
        var sid = el.getAttribute('data-schedule-id') || '';
        var lid = el.getAttribute('data-lease-id') || '';
        var defAmt = el.getAttribute('data-default-amount') || '0.00';
        
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'question',
                title: 'Write off Penalty?',
                html: 'Schedule ID: <b>' + String(sid) + '</b><br><br>' +
                    '<div style="text-align:left">Amount to write off</div>' +
                    '<input id="swal-writeoff-amount" type="number" step="0.01" min="0" class="swal2-input" style="width: 80%;" value="' + String(defAmt) + '">',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Submit',
                cancelButtonText: 'Cancel',
                preConfirm: function() {
                    var v = document.getElementById('swal-writeoff-amount').value;
                    var num = parseFloat(v);
                    if (!(num >= 0)) {
                        Swal.showValidationMessage('Please enter a valid amount');
                        return false;
                    }
                    return { amount: num };
                }
            }).then(function(result) {
                if (result && result.isConfirmed) {
                    var amt = result.value.amount;
                    fetch('ajax_residential_lease/write_off_penalty.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ lease_id: lid, schedule_id: sid, amount: amt.toFixed(2) }).toString()
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp && resp.success) {
                            Swal.fire({ icon: 'success', title: 'Saved', text: 'Penalty write-off recorded.' });
                            try { window.dispatchEvent(new Event('rl:schedule-updated')); } catch(e) {}
                        } else {
                            Swal.fire({ icon: 'error', title: 'Failed', text: (resp && resp.message) || 'Update failed' });
                        }
                    })
                    .catch((e) => Swal.fire({ icon: 'error', title: 'Error', text: 'Network error' }));
                }
            });
        }
    });
})();
</script>


