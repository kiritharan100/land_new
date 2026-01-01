<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = $_GET['id'] ?? '';
$ben = null;
$land = null;
$lease = null;
$error = '';

function fmtDate(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : '';
}

if ($md5 !== '' && isset($con)) {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, name, location_id FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int) $ben['rl_ben_id'];
            if ($stmt2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($stmt2, 'i', $ben_id);
                mysqli_stmt_execute($stmt2);
                $res2 = mysqli_stmt_get_result($stmt2);
                if ($res2 && ($land = mysqli_fetch_assoc($res2))) {
                    $land_id_int = (int) $land['land_id'];
                    if ($stmt3 = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($stmt3, 'i', $land_id_int);
                        mysqli_stmt_execute($stmt3);
                        $res3 = mysqli_stmt_get_result($stmt3);
                        if ($res3) {
                            $lease = mysqli_fetch_assoc($res3) ?: null;
                        }
                        mysqli_stmt_close($stmt3);
                    }
                } else {
                    $error = 'No land record found. Please fill Land Information first.';
                }
                mysqli_stmt_close($stmt2);
            }
        } else {
            $error = 'Invalid beneficiary.';
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $error = 'Missing id.';
}

$lease_id = $lease['rl_lease_id'] ?? null;
$is_first = isset($lease['is_it_first_ease']) ? (int) $lease['is_it_first_ease'] : 1;
$lease_basis = $lease['lease_calculation_basic'] ?? 'Valuvation basis';
$discount_rate = isset($lease['discount_rate']) ? $lease['discount_rate'] : 10;
$penalty_rate = isset($lease['penalty_rate']) ? $lease['penalty_rate'] : 10;
$status = $lease['status'] ?? 'active';

// Calculate default end date when start date exists but end date missing
$start_date_raw = $lease['start_date'] ?? '';
$end_date_raw = $lease['end_date'] ?? '';
if ($end_date_raw === '' && $start_date_raw) {
    $sd = strtotime($start_date_raw);
    if ($sd) {
        $end_date_raw = date('Y-m-d', strtotime('+30 years', $sd));
    }
}
?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-header-text mb-0">
            <?php echo $lease ? 'Manage Lease' : 'Create Lease'; ?>
        </h5>
        <?php if ($lease): ?>
        <span class="badge badge-info">Existing lease found</span>
        <?php endif; ?>
    </div>
    <div class="card-block">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>

 

        <form id="rlCreateLeaseForm" method="post" action="">
            <?php if ($lease_id): ?>
            <input type="hidden" name="rl_lease_id" id="rl_lease_id" value="<?php echo (int) $lease_id; ?>">
            <?php endif; ?>
            <input type="hidden" name="land_id" value="<?php echo (int) ($land['land_id'] ?? 0); ?>">
            <input type="hidden" name="beneficiary_id" value="<?php echo (int) ($ben['rl_ben_id'] ?? 0); ?>">
            <input type="hidden" name="location_id"
                value="<?php echo htmlspecialchars($ben['location_id'] ?? ($location_id ?? ''), ENT_QUOTES); ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>File Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rl_file_number" name="file_number" required
                            value="<?php echo htmlspecialchars($lease['file_number'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Lease Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rl_lease_number" name="lease_number" required
                            value="<?php echo htmlspecialchars($lease['lease_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Is it first lease?</label>
                        <select class="form-control" id="rl_is_first_lease" name="is_it_first_ease">
                            <option value="1" <?php echo $is_first ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo !$is_first ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Lease Basis</label>
                        <select class="form-control" id="rl_lease_calculation_basic" name="lease_calculation_basic">
                            <option value="" <?php echo $lease_basis === '' ? 'selected' : ''; ?>>Select</option>
                            <option value="Valuvation basis"
                                <?php echo $lease_basis === 'Valuvation basis' || $lease_basis === 'Valuation basis' ? 'selected' : ''; ?>>Valuvation basis</option>
                            <option value="Income basis"
                                <?php echo $lease_basis === 'Income basis' ? 'selected' : ''; ?>>Income basis</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Annual Rent Percentage (%)</label>
                        <input type="number" step="0.01" max='20' class="form-control" id="rl_annual_rent_percentage"
                            name="annual_rent_percentage"
                            value="<?php echo htmlspecialchars($lease['annual_rent_percentage'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Beneficiary Income ( Rs. per year )</label>
                        <input type="number" step="0.01" class="form-control" id="rl_ben_income" name="ben_income"
                            value="<?php echo htmlspecialchars($lease['ben_income'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Valuvation Amount</label>
                        <input type="number" step="0.01" class="form-control" id="rl_valuation_amount"
                            name="valuation_amount"
                            <?php echo ($is_first && ($lease_basis === 'Valuvation basis' || $lease_basis === 'Valuation basis')) ? 'required' : ''; ?>
                            value="<?php echo htmlspecialchars($lease['valuation_amount'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Valuvation Date</label>
                        <input type="date" class="form-control" id="rl_valuvation_date" name="valuvation_date"
                            <?php echo ($is_first && ($lease_basis === 'Valuvation basis' || $lease_basis === 'Valuation basis')) ? 'required' : ''; ?>
                            value="<?php echo fmtDate($lease['valuvation_date'] ?? ($lease['valuation_date'] ?? null)); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Valuvation Letter Date</label>
                        <input type="date" class="form-control" id="rl_valuvation_letter_date"
                            name="valuvation_letter_date"
                            value="<?php echo fmtDate($lease['valuvation_letter_date'] ?? ($lease['valuation_letter_date'] ?? null)); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="rl_start_date" name="start_date" required
                            value="<?php echo fmtDate($start_date_raw); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" id="rl_end_date" name="end_date"
                            value="<?php echo fmtDate($end_date_raw); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Initial Annual Rent</label>
                        <input type="text" class="form-control" id="rl_initial_annual_rent" name="initial_annual_rent"
                            value="<?php echo htmlspecialchars($lease['initial_annual_rent'] ?? ''); ?>" readonly>
                        <small class="text-muted">Valuation basis: valuation * annual rent %. Income basis: income * 5%
                            (max 1000).</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Premium Amount</label>
                        <input type="number" step="0.01" class="form-control" id="rl_premium" name="premium"
                            value="<?php echo htmlspecialchars($lease['premium'] ?? ''); ?>">
                        <small class="text-muted">First lease: Initial Rent × 3. Otherwise 0. Editable.</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Discount Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" id="rl_discount_rate" name="discount_rate"
                            value="<?php echo htmlspecialchars($discount_rate); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Penalty Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" id="rl_penalty_rate" name="penalty_rate"
                            value="<?php echo htmlspecialchars($penalty_rate); ?>" readonly>
                    </div>
                </div>
            </div>

            <?php
            // Check if lease is active (same logic as LTL)
            $lease_status_raw = $lease['status'] ?? '';
            $lease_status_val = is_numeric($lease_status_raw) ? (int)$lease_status_raw : strtolower((string)$lease_status_raw);
            $is_active_lease = $lease && ($lease_status_val === '' || $lease_status_val === 1 || $lease_status_val === 'active');
            ?>
            <div class="text-right">
                <button type="submit" class="btn btn-success<?php echo $lease ? ' d-none' : ''; ?>" id="rl_save_btn"
                    onclick="return window.RLLeaseForm && typeof window.RLLeaseForm.submitDirect==='function' ? RLLeaseForm.submitDirect(event) : true;">
                    <i class="fa fa-save"></i> <?php echo $lease ? 'Update Lease & Recalculate Schedule' : 'Create Lease & Generate Schedule'; ?>
                </button>
                <?php if ($lease): ?>
                    <?php if (hasPermission(20)): ?>
                        <?php if ($is_active_lease): ?>
                        <button type="button" class="btn btn-secondary" id="rl_edit_btn">
                            <i class="fa fa-edit"></i> Edit
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary disabled" id="rl_edit_btn" disabled title="Lease is inactive">
                            <i class="fa fa-edit"></i> Edit
                        </button>
                        <div class="text-muted small mt-2">Lease is inactive; editing is disabled.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($lease && hasPermission(20)): ?>
        <hr>
        <h5 class="font-weight-bold mb-3">Update Grant Details</h5>
        
        <?php
        // Calculate Valuation Summary
        $total_valuation = (float)($lease['valuation_amount'] ?? 0);
        $total_rent_premium_paid = 0.0;
        $total_discount_applied = 0.0;
        $valuation_payments_total = 0.0;
        
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
            
            // Valuation payments already made
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
        
        $total_settled = $total_rent_premium_paid + $total_discount_applied;
        $balance_to_pay = $total_valuation - $total_settled - $valuation_payments_total;
        $grant_fields_enabled = ($balance_to_pay <= 0);
        ?>
        
        <!-- Valuation Summary -->
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="mb-0" role="alert"
                    style="background:#f8f9fa;border:2px solid <?php echo $grant_fields_enabled ? '#28a745' : '#17a2b8'; ?>;color:#333;font-size:1rem;font-weight:500;padding:12px 16px;border-radius:6px;">
                    <span style="font-weight:700;text-transform:uppercase;">Valuation Summary:</span><br>
                    Total Valuation: <strong>Rs. <?php echo number_format($total_valuation, 2); ?></strong> &nbsp;|
                    Rent + Premium + Discount Paid: <strong>Rs. <?php echo number_format($total_settled, 2); ?></strong> &nbsp;|
                    Valuation Payments: <strong>Rs. <?php echo number_format($valuation_payments_total, 2); ?></strong><br>
                    <?php if ($grant_fields_enabled): ?>
                    <span style="font-weight:800;color:#28a745;">Balance to be Paid: Rs. <?php echo number_format($balance_to_pay, 2); ?> ✓ Grant Details can be updated</span>
                    <?php else: ?>
                    <span style="font-weight:800;color:#dc3545;">Balance to be Paid: Rs. <?php echo number_format($balance_to_pay, 2); ?></span>
                    <br><small class="text-muted">Grant details will be enabled when balance is fully paid (≤ 0)</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <form id="rlGrantDetailsForm">
            <input type="hidden" name="rl_lease_id" value="<?php echo (int) $lease_id; ?>">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Outright Grants Number</label>
                        <input type="text" class="form-control" id="rl_grant_outright_grants_number"
                            name="outright_grants_number"
                            value="<?php echo htmlspecialchars($lease['outright_grants_number'] ?? ''); ?>"
                            <?php echo !$grant_fields_enabled ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Outright Grants Date</label>
                        <input type="date" class="form-control" id="rl_grant_outright_grants_date"
                            name="outright_grants_date"
                            value="<?php echo fmtDate($lease['outright_grants_date'] ?? null); ?>"
                            <?php echo !$grant_fields_enabled ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-primary" id="rl_grant_save_btn"
                                <?php echo !$grant_fields_enabled ? 'disabled' : ''; ?>>
                                <i class="fa fa-save"></i> Save Grant Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script src="ajax_residential_lease/create_lease_tab.js?_ts=<?php echo time(); ?>"></script>
<?php if ($lease): ?>
<script src="ajax_residential_lease/grant_details_tab.js?_ts=<?php echo time(); ?>"></script>
<?php endif; ?>
<script>
(function initRLLeaseForm() {
    var hasExisting = <?php echo $lease ? 'true' : 'false'; ?>;
    var tryInit = function() {
        if (window.RLLeaseForm && typeof window.RLLeaseForm.init === 'function') {
            window.RLLeaseForm.init({ hasExisting: hasExisting });
            // ensure numbers and rent compute even if init was late
            if (window.RLLeaseForm.numbers) window.RLLeaseForm.numbers(true);
            if (window.RLLeaseForm.recompute) window.RLLeaseForm.recompute();
            return true;
        }
        return false;
    };
    // prevent default post if JS is present but init is delayed
    document.addEventListener('submit', function(ev) {
        var f = ev.target;
        if (f && f.id === 'rlCreateLeaseForm') {
            ev.preventDefault();
            ev.stopPropagation();
            if (window.RLLeaseForm && typeof window.RLLeaseForm.submitDirect === 'function') {
                window.RLLeaseForm.submitDirect(ev);
            } else if (window.Swal) {
                Swal.fire('Error', 'Form script not ready. Please reload the tab.', 'error');
            } else {
                alert('Form script not ready. Please reload the tab.');
            }
        }
    }, true);

    var started = false;
    var boot = function() {
        if (started) return;
        started = true;
        // immediate attempt
        if (tryInit()) return;
        // retry a few times if script loads slowly
        var attempts = 0;
        var t = setInterval(function() {
            attempts++;
            if (tryInit() || attempts > 10) {
                clearInterval(t);
            }
        }, 100);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
