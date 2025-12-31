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

        <div class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-1">
                        <label class="mb-0 text-muted small">Lessee</label>
                        <div class="font-weight-bold"><?php echo htmlspecialchars($ben['name'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group mb-1">
                        <label class="mb-0 text-muted small">Land</label>
                        <div class="font-weight-bold">
                            <?php echo htmlspecialchars($land['land_address'] ?? ('Land #' . ($land['land_id'] ?? ''))); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                        <label>Annual Rent Percentage</label>
                        <input type="number" step="0.01" class="form-control" id="rl_annual_rent_percentage"
                            name="annual_rent_percentage"
                            value="<?php echo htmlspecialchars($lease['annual_rent_percentage'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Beneficiary Income</label>
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
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="rl_start_date" name="start_date" required
                            value="<?php echo fmtDate($start_date_raw); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" id="rl_end_date" name="end_date"
                            value="<?php echo fmtDate($end_date_raw); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Initial Annual Rent</label>
                        <input type="text" class="form-control" id="rl_initial_annual_rent" name="initial_annual_rent"
                            value="<?php echo htmlspecialchars($lease['initial_annual_rent'] ?? ''); ?>" readonly>
                        <small class="text-muted">Valuation basis: valuation * annual rent %. Income basis: income * 5%
                            (max 1000).</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Discount Rate</label>
                        <input type="number" step="0.01" class="form-control" id="rl_discount_rate" name="discount_rate"
                            value="<?php echo htmlspecialchars($discount_rate); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Penalty Rate</label>
                        <input type="number" step="0.01" class="form-control" id="rl_penalty_rate" name="penalty_rate"
                            value="<?php echo htmlspecialchars($penalty_rate); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Outright Grants Number</label>
                        <input type="text" class="form-control" id="rl_outright_grants_number"
                            name="outright_grants_number"
                            value="<?php echo htmlspecialchars($lease['outright_grants_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Outright Grants Date</label>
                        <input type="date" class="form-control" id="rl_outright_grants_date"
                            name="outright_grants_date"
                            value="<?php echo fmtDate($lease['outright_grants_date'] ?? null); ?>">
                    </div>
                </div>
            </div>

            <div class="text-right">
                <button type="submit" class="btn btn-success" id="rl_save_btn"
                    onclick="return window.RLLeaseForm && typeof window.RLLeaseForm.submitDirect==='function' ? RLLeaseForm.submitDirect(event) : true;">
                    <i class="fa fa-save"></i> <?php echo $lease ? 'Update Lease' : 'Create Lease'; ?>
                </button>
                <button type="button" class="btn btn-secondary<?php echo $lease ? '' : ' d-none'; ?>" id="rl_edit_btn">
                    <i class="fa fa-edit"></i> Edit
                </button>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<script src="ajax_residential_lease/create_lease_tab.js?_ts=<?php echo time(); ?>"></script>
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
