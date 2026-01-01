<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = $land = $lease = null;
$error = '';

if ($md5 !== '') {
    if ($st = mysqli_prepare($con, 'SELECT rl_ben_id, name FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($st, 's', $md5);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs && ($ben = mysqli_fetch_assoc($rs))) {
            $ben_id = (int)$ben['rl_ben_id'];
            if ($st2 = mysqli_prepare($con, 'SELECT land_id, land_address FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                    $land_id = (int)$land['land_id'];
                    if ($st3 = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);
                        if ($r3) {
                            $lease = mysqli_fetch_assoc($r3);
                        }
                        mysqli_stmt_close($st3);
                    }
                    if (!$lease) {
                        $error = 'No lease found for this land.';
                    }
                } else {
                    $error = 'No land found for beneficiary.';
                }
                mysqli_stmt_close($st2);
            }
        } else {
            $error = 'Invalid beneficiary reference.';
        }
        mysqli_stmt_close($st);
    }
} else {
    $error = 'Missing id parameter.';
}
?>
<div class="card">
    <div class="card-block" style="padding:1rem;">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>

        <!-- Recovery Letter Section -->
        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">01. Recovery Letter</span><br>
                    <small style="color:#666;">Generate recovery letter for outstanding amounts as at selected date.</small>
                </div>
                <div style="margin-top:8px;">
                    <label for="rl-recovery-date" style="font-size:12px;font-weight:600;margin-right:6px;">As At Date</label>
                    <input type="date" id="rl-recovery-date" class="form-control form-control-sm d-inline-block"
                        style="width:160px;" value="<?= date('Y-m-d') ?>" />
                    <button type="button" class="btn btn-outline-primary btn-sm" id="rl-recovery-letter-btn"
                        style="margin-left:6px;">
                        <i class="fa fa-print"></i> Print Letter
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Payment Reminders Section -->
        <div class="reminder-item"
            style="border:1px solid #805858ff; background-color:#f8f0f0; padding:12px 14px;border-radius:6px; ">
            <div style="border-radius:6px; background-color:#F0D89C; padding:8px; display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
                <div>
                    <span style="font-weight:600;font-size:14px;">Add Payment Reminders</span><br>
                    <small style="color:#666;">Track sent reminders. Add new entries and manage it.</small>
                </div>
                <div style="margin-top:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <label>Letter Type:</label>
                    <select id="rl-rem-type" class="form-control form-control-sm"
                        style="width:160px; background-color: #ffffff !important;">
                        <option value="">Select</option>
                        <option>Annexure 09</option>
                        <option>Annexure 12A</option>
                        <option>Annexure 12</option>
                    </select>
                    <label>Letter Date:</label>
                    <input type="date" id="rl-rem-date" class="form-control form-control-sm" style="width:150px;"
                        value="<?= date('Y-m-d') ?>" />
                    <button type="button" id="rl-rem-add-btn" class="btn btn-success btn-sm" disabled
                        title="Select type & date"><i class="fa fa-plus"></i> Add Record</button>
                </div>
            </div>
            <style>
            .rl-rem-table th,
            .rl-rem-table td {
                font-size: 13px;
            }
            .rl-rem-row-cancelled {
                background: #fde2e2 !important;
            }
            .rl-rem-row-cancelled td {
                color: #842029 !important;
            }
            </style>
            <div class="table-responsive" style="max-height:320px;overflow:auto;">
                <table class="table table-bordered table-sm mb-0 rl-rem-table">
                    <thead class="bg-light">
                        <tr>
                            <th style="width:5%;">SN</th>
                            <th style="width:18%;">Sent Date</th>
                            <th>Reminder Type</th>
                            <th style="width:12%;">Status</th>
                            <th style="width:14%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="rl-rem-body">
                        <tr>
                            <td colspan="5" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr>
        <h5>Print Letter</h5>

        <!-- Annexure 09 -->
        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">02. Annexure 09</span><br>
                    <small style="color:#666;">Generate Annexure 09 letter for outstanding amounts as at selected date.</small>
                </div>
                <div style="margin-top:8px;">
                    <label for="rl-annexure-09-date" style="font-size:12px;font-weight:600;margin-right:6px;">As At Date</label>
                    <input type="date" id="rl-annexure-09-date" name="as_at_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />
                    <button type="button" class="btn btn-outline-primary btn-sm" style="margin-left:6px;"
                        onclick="printRLAnnexure09('TA')">
                        <i class="fa fa-print"></i> Print Tamil Letter
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" style="margin-left:6px;"
                        onclick="printRLAnnexure09('SN')">
                        <i class="fa fa-print"></i> Print Sinhala Letter
                    </button>
                </div>
            </div>
        </div>

        <!-- Annexure 12 -->
        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">03. Annexure 12</span><br>
                    <small style="color:#666;">Generate Annexure 12 letter for outstanding amounts as at selected date.</small>
                </div>
                <div style="margin-top:8px;">
                    <label for="rl-annexure-12-date" style="font-size:12px;font-weight:600;margin-right:6px;">As At Date</label>
                    <input type="date" id="rl-annexure-12-date" name="as_at_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />
                    <button type="button" class="btn btn-outline-primary btn-sm" style="margin-left:6px;"
                        onclick="printRLAnnexure12('TA')">
                        <i class="fa fa-print"></i> Print Tamil Letter
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" style="margin-left:6px;"
                        onclick="printRLAnnexure12('SN')">
                        <i class="fa fa-print"></i> Print Sinhala Letter
                    </button>
                </div>
            </div>
        </div>

        <!-- Annexure 12A -->
        <div class="reminder-item"
            style="border:1px solid #ddd;padding:12px 14px;border-radius:6px;margin-bottom:14px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <span style="font-weight:600;font-size:14px;">04. Annexure 12A</span><br>
                    <small style="color:#666;">Generate Annexure 12A letter for outstanding amounts as at selected date.</small>
                </div>
                <div style="margin-top:8px;">
                    <label for="rl-annexure-12a-date1" style="font-size:12px;font-weight:600;margin-right:6px;">Last Reminder Date</label>
                    <input type="date" id="rl-annexure-12a-date1" name="last_reminder_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />
                    <label for="rl-annexure-12a-date" style="font-size:12px;font-weight:600;margin-right:6px;">As At Date</label>
                    <input type="date" id="rl-annexure-12a-date" name="as_at_date"
                        class="form-control form-control-sm d-inline-block" style="width:160px;"
                        value="<?= date('Y-m-d') ?>" />
                    <button type="button" class="btn btn-outline-primary btn-sm" style="margin-left:6px;"
                        onclick="printRLAnnexure12A('TA')">
                        <i class="fa fa-print"></i> Print Tamil Letter
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" style="margin-left:6px;"
                        onclick="printRLAnnexure12A('SN')">
                        <i class="fa fa-print"></i> Print Sinhala Letter
                    </button>
                </div>
            </div>
        </div>

        <script>
        function printRLAnnexure09(lang) {
            const date = document.getElementById('rl-annexure-09-date').value;
            const url = `ajax_residential_lease/letters/rl_annexure_09.php?id=<?= urlencode($md5) ?>&as_at_date=${date}&language=${lang}`;
            window.open(url, '_blank');
        }

        function printRLAnnexure12(lang) {
            const date = document.getElementById('rl-annexure-12-date').value;
            const url = `ajax_residential_lease/letters/rl_annexure_12.php?id=<?= urlencode($md5) ?>&as_at_date=${date}&language=${lang}`;
            window.open(url, '_blank');
        }

        function printRLAnnexure12A(lang) {
            const date = document.getElementById('rl-annexure-12a-date').value;
            const lastReminderDate = document.getElementById('rl-annexure-12a-date1').value;
            const url = `ajax_residential_lease/letters/rl_annexure_12A.php?id=<?= urlencode($md5) ?>&as_at_date=${date}&last_reminder_date=${lastReminderDate}&language=${lang}`;
            window.open(url, '_blank');
        }

        (function() {
            var rDate = document.getElementById('rl-recovery-date');
            var rBtn = document.getElementById('rl-recovery-letter-btn');
            var leaseId = <?= isset($lease['rl_lease_id']) ? (int)$lease['rl_lease_id'] : 0 ?>;

            if (rBtn) {
                rBtn.addEventListener('click', function() {
                    if (!rDate.value) {
                        Swal.fire('Validation', 'Select date first', 'warning');
                        return;
                    }
                    var url = 'ajax_residential_lease/letters/rl_recovery_letter.php?id=<?= urlencode($md5) ?>&date=' +
                        encodeURIComponent(rDate.value) + '&_ts=' + Date.now();
                    window.open(url, '_blank');
                });
            }

            // ---------------- Reminders Table Logic ----------------
            var LEASE_ID = leaseId;
            var remTypeEl = document.getElementById('rl-rem-type');
            var remDateEl = document.getElementById('rl-rem-date');
            var remAddBtn = document.getElementById('rl-rem-add-btn');
            var remBody = document.getElementById('rl-rem-body');

            function validateRemInputs() {
                if (remTypeEl && remDateEl && remTypeEl.value && remDateEl.value) {
                    remAddBtn.disabled = false;
                    remAddBtn.title = 'Add';
                } else {
                    remAddBtn.disabled = true;
                    remAddBtn.title = 'Select type & date';
                }
            }
            if (remTypeEl) remTypeEl.addEventListener('change', validateRemInputs);
            if (remDateEl) remDateEl.addEventListener('change', validateRemInputs);

            function loadReminders() {
                if (!LEASE_ID) {
                    remBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">No lease.</td></tr>';
                    return;
                }
                remBody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
                fetch('ajax_residential_lease/list_reminders.php?lease_id=' + LEASE_ID + '&_ts=' + Date.now())
                    .then(r => r.text())
                    .then(html => {
                        remBody.innerHTML = html;
                        bindCancelReminders();
                        validateRemInputs();
                    })
                    .catch(() => {
                        remBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Load failed.</td></tr>';
                    });
            }

            function bindCancelReminders() {
                remBody.querySelectorAll('.rl-rem-cancel-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        if (!id) return;
                        var doCancel = function() {
                            var fd = new URLSearchParams();
                            fd.append('id', id);
                            fetch('ajax_residential_lease/cancel_reminder.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: fd.toString()
                                })
                                .then(r => r.json())
                                .then(resp => {
                                    if (resp.success) {
                                        loadReminders();
                                    } else {
                                        Swal.fire('Error', resp.message || 'Cancel failed', 'error');
                                    }
                                })
                                .catch(() => Swal.fire('Error', 'Network error', 'error'));
                        };
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Cancel this reminder?',
                                icon: 'warning',
                                showCancelButton: true
                            }).then(function(res) {
                                if (res.isConfirmed) doCancel();
                            });
                        } else {
                            if (confirm('Cancel this reminder?')) doCancel();
                        }
                    });
                });
            }

            if (remAddBtn) {
                remAddBtn.addEventListener('click', function() {
                    if (remAddBtn.disabled) return;
                    var t = remTypeEl.value;
                    var d = remDateEl.value;
                    if (!t || !d) {
                        Swal.fire('Validation', 'Select type and date', 'warning');
                        return;
                    }
                    remAddBtn.disabled = true;
                    remAddBtn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Saving...';
                    var fd = new URLSearchParams();
                    fd.append('lease_id', LEASE_ID);
                    fd.append('reminders_type', t);
                    fd.append('sent_date', d);
                    fetch('ajax_residential_lease/add_reminder.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: fd.toString()
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.success) {
                                if (window.Swal) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Added',
                                        timer: 1200,
                                        showConfirmButton: false
                                    });
                                }
                                remTypeEl.value = '';
                                validateRemInputs();
                                loadReminders();
                            } else {
                                Swal.fire('Error', resp.message || 'Insert failed', 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Network error', 'error'))
                        .finally(() => {
                            remAddBtn.disabled = false;
                            remAddBtn.innerHTML = '<i class="fa fa-plus"></i> Add Record';
                        });
                });
            }

            loadReminders();
        })();
        </script>
        <?php endif; ?>
    </div>
</div>
