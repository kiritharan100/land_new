<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = $_GET['id'] ?? '';
$lease = null;

if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['rl_ben_id'];
            
            if ($st2 = mysqli_prepare($con, 'SELECT land_id FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                $land = mysqli_fetch_assoc($r2);
                mysqli_stmt_close($st2);
                
                if ($land) {
                    $land_id = (int)$land['land_id'];
                    if ($st3 = mysqli_prepare($con, 'SELECT rl_lease_id FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);
                        $lease = mysqli_fetch_assoc($r3);
                        mysqli_stmt_close($st3);
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$writeoffs = [];
if ($lease) {
    $lease_id = (int)$lease['rl_lease_id'];
    $sql = "SELECT w.*, s.schedule_year 
            FROM rl_write_off w
            LEFT JOIN rl_lease_schedules s ON w.schedule_id = s.schedule_id
            WHERE w.lease_id = ? AND w.status = 1 
            ORDER BY w.created_on DESC";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $lease_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $writeoffs = mysqli_fetch_all($res, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-header-text mb-0">Write-Off History</h5>
    </div>
    <div class="card-body">
        <?php if (!$lease): ?>
        <div class="alert alert-info">No lease found.</div>
        <?php elseif (empty($writeoffs)): ?>
        <div class="alert alert-info">No write-offs recorded. Use the Schedule tab to write off penalties.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="bg-light">
                    <tr>
                        <th>#</th>
                        <th>Schedule Year</th>
                        <th>Amount</th>
                        <th>Created On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($writeoffs as $w): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($w['schedule_year'] ?? '-') ?></td>
                        <td class="text-right"><?= number_format($w['write_off_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($w['created_on']) ?></td>
                        <td>
                            <button type="button" class="btn btn-danger btn-xs rl-wo-cancel" 
                                data-id="<?= $w['id'] ?>"
                                data-amount="<?= number_format($w['write_off_amount'], 2) ?>">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    document.addEventListener('click', function(ev) {
        var btn = ev.target.closest('.rl-wo-cancel');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var amt = btn.getAttribute('data-amount');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Cancel Write-Off?',
                html: 'Amount: <b>Rs. ' + amt + '</b><br>This will reinstate the penalty.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    fetch('ajax_residential_lease/cancel_write_off.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: id }).toString()
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            Swal.fire('Cancelled', 'Write-off reversed', 'success');
                            try { window.dispatchEvent(new Event('rl:writeoff-updated')); } catch(e) {}
                        } else {
                            Swal.fire('Error', resp.message || 'Failed', 'error');
                        }
                    });
                }
            });
        }
    });
})();
</script>


