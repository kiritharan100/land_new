<?php
// Disable error output to prevent JSON corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Custom error handler to capture PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
require_once __DIR__ . '/rl_payment_allocator.php';

function json_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function clean_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function calculate_initial_rent(string $basis, float $valuation_amount, float $annual_pct, float $income): float
{
    if (($basis === 'Valuvation basis' || $basis === 'Valuation basis') && $valuation_amount > 0 && $annual_pct > 0) {
        return $valuation_amount * ($annual_pct / 100);
    }

    if ($basis === 'Income basis' && $income > 0) {
        $calc = $income * 0.05;
        return $calc > 1000 ? 1000 : $calc;
    }

    return 0.0;
}

/**
 * Generate RL schedules (no revision for residential lease)
 * Premium is only in the first year if first lease
 */
function generateRLSchedules($con, $lease_id, $initial_rent, $premium, $start_date, $duration_years = 30, $penalty_rate = 10.0)
{
    $start_ts = strtotime($start_date);
    if (!$start_ts) {
        throw new Exception("Invalid start_date for schedule generation");
    }
    
    $duration = (int)$duration_years;
    
    for ($year = 0; $year < $duration; $year++) {
        $year_start_ts = strtotime("+{$year} years", $start_ts);
        $year_end_ts = strtotime("+1 year -1 day", $year_start_ts);
        
        $schedule_year = (int)date('Y', $year_start_ts);
        $year_start_date = date('Y-m-d', $year_start_ts);
        $year_end_date = date('Y-m-d', $year_end_ts);
        $due_date = date('Y-m-d', strtotime($schedule_year . '-03-31'));
        
        // Premium only in first year
        $first_year_premium = ($year === 0) ? $premium : 0.0;
        
        // No revision for residential lease - annual amount stays same
        $annual_amount = $initial_rent;
        
        $sql = "INSERT INTO rl_lease_schedules (
                    lease_id,
                    schedule_year,
                    start_date,
                    end_date,
                    due_date,
                    base_amount,
                    premium,
                    premium_paid,
                    annual_amount,
                    panalty,
                    paid_rent,
                    discount_apply,
                    total_paid,
                    panalty_paid,
                    revision_number,
                    is_revision_year,
                    penalty_rate,
                    status,
                    created_on
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, 0, 0, 0, 0, 0, 0, ?, 'pending', NOW()
                )";
        
        if ($st = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param(
                $st,
                'iisssdddd',
                $lease_id,
                $schedule_year,
                $year_start_date,
                $year_end_date,
                $due_date,
                $initial_rent,
                $first_year_premium,
                $annual_amount,
                $penalty_rate
            );
            
            if (!mysqli_stmt_execute($st)) {
                $err = mysqli_error($con);
                mysqli_stmt_close($st);
                throw new Exception("Schedule generation failed: " . $err);
            }
            mysqli_stmt_close($st);
        } else {
            throw new Exception("Schedule statement prepare error: " . mysqli_error($con));
        }
    }
    
    return true;
}

try {
// ----------------------------------------------------
// Parse incoming values
// ----------------------------------------------------
$rl_lease_id = isset($_POST['rl_lease_id']) ? (int) $_POST['rl_lease_id'] : 0;
$land_id = isset($_POST['land_id']) ? (int) $_POST['land_id'] : 0;
$beneficiary_id = isset($_POST['beneficiary_id']) ? (int) $_POST['beneficiary_id'] : 0;
$location_id = isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0;
$lease_number = trim($_POST['lease_number'] ?? '');
$file_number = trim($_POST['file_number'] ?? '');
$is_first = isset($_POST['is_it_first_ease']) ? (int) $_POST['is_it_first_ease'] : 1;
$valuation_amount = isset($_POST['valuation_amount']) ? (float) $_POST['valuation_amount'] : 0;
$valuation_date = clean_date($_POST['valuvation_date'] ?? ($_POST['valuation_date'] ?? null));
$valuation_letter_date = clean_date($_POST['valuvation_letter_date'] ?? ($_POST['valuation_letter_date'] ?? null));
$start_date = clean_date($_POST['start_date'] ?? null);
$end_date = clean_date($_POST['end_date'] ?? null);
$status = 'active';
$lease_basis_raw = trim($_POST['lease_calculation_basic'] ?? '');
$lease_basis_raw = $lease_basis_raw === 'Valuation basis' ? 'Valuvation basis' : $lease_basis_raw;
$lease_calculation_basic = in_array($lease_basis_raw, ['Valuvation basis', 'Income basis'], true) ? $lease_basis_raw : '';
$annual_rent_percentage = isset($_POST['annual_rent_percentage']) ? (float) $_POST['annual_rent_percentage'] : 0;
$ben_income = isset($_POST['ben_income']) ? (float) $_POST['ben_income'] : 0;
$discount_rate = (isset($_POST['discount_rate']) && $_POST['discount_rate'] !== '') ? (float) $_POST['discount_rate'] : 10.0;
$penalty_rate = (isset($_POST['penalty_rate']) && $_POST['penalty_rate'] !== '') ? (float) $_POST['penalty_rate'] : 10.0;
$outright_grants_number = trim($_POST['outright_grants_number'] ?? '');
$outright_grants_date = clean_date($_POST['outright_grants_date'] ?? null);
$premium = isset($_POST['premium']) ? (float) $_POST['premium'] : null;

if ($rl_lease_id <= 0) {
    json_response(false, 'Lease id is required for update.');
}

// ----------------------------------------------------
// Fetch existing lease (for change detection and fallbacks)
// ----------------------------------------------------
$existing = null;
if ($stmtExisting = $con->prepare('SELECT * FROM rl_lease WHERE rl_lease_id = ? LIMIT 1')) {
    $stmtExisting->bind_param('i', $rl_lease_id);
    $stmtExisting->execute();
    $res = $stmtExisting->get_result();
    if ($res && $res->num_rows === 1) {
        $existing = $res->fetch_assoc();
    } else {
        $stmtExisting->close();
        json_response(false, 'Lease not found for update.');
    }
    
    // Check if grant is already issued - prevent editing
    if (!empty($existing['outright_grants_date'])) {
        $stmtExisting->close();
        json_response(false, 'Not allowed to edit. Outright Grant has been issued for this lease.');
    }
    $stmtExisting->close();
}

// Save old values for change detection
$oldLease = $existing;

// Reuse existing values if missing from request (avoid blocking on readonly fields)
if ($location_id <= 0 && isset($existing['location_id'])) {
    $location_id = (int) $existing['location_id'];
}
$hasValuationAmount = array_key_exists('valuation_amount', $_POST);
$hasValuationDate = array_key_exists('valuvation_date', $_POST) || array_key_exists('valuation_date', $_POST);
if (!$hasValuationAmount && isset($existing['valuation_amount'])) {
    $valuation_amount = (float) $existing['valuation_amount'];
}
if (!$hasValuationDate) {
    if (isset($existing['valuvation_date'])) {
        $valuation_date = $existing['valuvation_date'];
    } elseif (isset($existing['valuation_date'])) {
        $valuation_date = $existing['valuation_date'];
    }
}
if ($lease_number === '' && isset($existing['lease_number'])) {
    $lease_number = $existing['lease_number'];
}
if ($file_number === '' && isset($existing['file_number'])) {
    $file_number = $existing['file_number'];
}
if ($land_id <= 0 && isset($existing['land_id'])) {
    $land_id = (int) $existing['land_id'];
}
if ($beneficiary_id <= 0 && isset($existing['beneficiary_id'])) {
    $beneficiary_id = (int) $existing['beneficiary_id'];
}

if ($land_id <= 0 || $beneficiary_id <= 0) {
    json_response(false, 'Land and beneficiary are required to update a lease.');
}

if ($lease_number === '' || $file_number === '') {
    json_response(false, 'Lease number and file number are required.');
}

if (!$start_date) {
    json_response(false, 'Start date is required.');
}

$start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
if (!$start_dt) {
    json_response(false, 'Invalid start date format.');
}

// Always enforce end date = start + 30 years
$end_date = (clone $start_dt)->modify('+30 years')->format('Y-m-d');

if ($lease_calculation_basic === 'Valuvation basis' && $is_first) {
    if ($valuation_amount <= 0) {
        json_response(false, 'Valuvation amount is required for the first lease (valuation basis).');
    }
    if (!$valuation_date) {
        json_response(false, 'Valuvation date is required for the first lease (valuation basis).');
    }
}

if ($lease_calculation_basic === 'Valuvation basis') {
    if ($annual_rent_percentage <= 0) {
        json_response(false, 'Annual rent percentage is required for valuation basis.');
    }
} elseif ($lease_calculation_basic === 'Income basis') {
    if ($ben_income <= 0) {
        json_response(false, 'Beneficiary income is required for income basis.');
    }
}

// Fallback location from cookie if not provided
if ($location_id <= 0 && !empty($_COOKIE['client_cook'])) {
    $md5 = $con->real_escape_string($_COOKIE['client_cook']);
    $res = $con->query("SELECT c_id FROM client_registration WHERE md5_client='$md5' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $location_id = (int) $row['c_id'];
    }
}

// Calculate initial annual rent
$initial_annual_rent = 0.0;
if (!$is_first && array_key_exists('initial_annual_rent', $_POST)) {
    $initial_annual_rent = (float) $_POST['initial_annual_rent'];
} else {
    $initial_annual_rent = calculate_initial_rent($lease_calculation_basic, $valuation_amount, $annual_rent_percentage, $ben_income);
}

// Calculate premium if not provided
if ($premium === null) {
    if (isset($existing['premium'])) {
        $premium = (float) $existing['premium'];
    } else {
        $premium = $is_first ? ($initial_annual_rent * 3) : 0;
    }
}

// ----------------------------------------------------
// Check for active payments
// ----------------------------------------------------
$payments_count = 0;
if ($stP = mysqli_prepare($con, 'SELECT COUNT(*) AS cnt FROM rl_lease_payments WHERE lease_id=? AND status=1')){
    mysqli_stmt_bind_param($stP, 'i', $rl_lease_id);
    mysqli_stmt_execute($stP);
    $rp = mysqli_stmt_get_result($stP);
    if ($rp && ($row = mysqli_fetch_assoc($rp))) { 
        $payments_count = (int)$row['cnt']; 
    }
    mysqli_stmt_close($stP);
}

// ----------------------------------------------------
// Detect if schedule-affecting fields changed
// ----------------------------------------------------
$need_rebuild = false;
if ($oldLease) {
    $old_val   = floatval($oldLease['valuation_amount'] ?? 0);
    $old_start = $oldLease['start_date'] ?? '';
    $old_pct   = floatval($oldLease['annual_rent_percentage'] ?? 0);
    $old_income = floatval($oldLease['ben_income'] ?? 0);
    $old_first = (int)($oldLease['is_it_first_ease'] ?? 1);
    $old_premium = floatval($oldLease['premium'] ?? 0);
    $old_initial_rent = floatval($oldLease['initial_annual_rent'] ?? 0);
    
    if (
        round($old_val, 2) != round($valuation_amount, 2) ||
        $old_start != $start_date ||
        round($old_pct, 4) != round($annual_rent_percentage, 4) ||
        round($old_income, 2) != round($ben_income, 2) ||
        $old_first != $is_first ||
        round($old_premium, 2) != round($premium, 2) ||
        round($old_initial_rent, 2) != round($initial_annual_rent, 2)
    ) {
        $need_rebuild = true;
    }
}

// ----------------------------------------------------
// Check if valuation date empty - skip penalty if so
// ----------------------------------------------------
$skip_penalty = false;
if (empty($valuation_date) || $valuation_date == '0000-00-00') {
    $skip_penalty = true;
}

// ----------------------------------------------------
// Update Lease record
// ----------------------------------------------------
$sql = "UPDATE rl_lease SET
            land_id=?,
            beneficiary_id=?,
            location_id=?,
            lease_number=?,
            file_number=?,
            is_it_first_ease=?,
            valuation_amount=?,
            valuvation_date=?,
            valuvation_letter_date=?,
            premium=?,
            start_date=?,
            end_date=?,
            status=?,
            lease_calculation_basic=?,
            annual_rent_percentage=?,
            ben_income=?,
            initial_annual_rent=?,
            discount_rate=?,
            penalty_rate=?,
            outright_grants_number=?,
            outright_grants_date=?
        WHERE rl_lease_id=?";

if ($stmt = $con->prepare($sql)) {
    $stmt->bind_param(
        'iiissidssdssssdddddssi',
        $land_id,
        $beneficiary_id,
        $location_id,
        $lease_number,
        $file_number,
        $is_first,
        $valuation_amount,
        $valuation_date,
        $valuation_letter_date,
        $premium,
        $start_date,
        $end_date,
        $status,
        $lease_calculation_basic,
        $annual_rent_percentage,
        $ben_income,
        $initial_annual_rent,
        $discount_rate,
        $penalty_rate,
        $outright_grants_number,
        $outright_grants_date,
        $rl_lease_id
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        json_response(false, 'Failed to update lease. ' . $err);
    }
    $stmt->close();
} else {
    json_response(false, 'Database error: ' . $con->error);
}

// Log update
if (function_exists('UserLog')) {
    $detail = sprintf(
        'RL Lease Updated: ID=%d | Lease=%s | File=%s | Beneficiary=%d',
        $rl_lease_id,
        $lease_number,
        $file_number,
        $beneficiary_id
    );
    UserLog(2, 'RL Update Lease', $detail, $beneficiary_id, 'RL');
}

// ----------------------------------------------------
// If skip penalty, remove all penalties from schedules
// ----------------------------------------------------
if ($skip_penalty) {
    mysqli_query($con, "
        UPDATE rl_lease_schedules 
        SET panalty = 0, panalty_paid = 0
        WHERE lease_id = {$rl_lease_id}
    ");
}

// ----------------------------------------------------
// Schedule regeneration + Payment replay
// Always regenerate schedules on update (like LTL)
// ----------------------------------------------------
$note = '';

// Delete existing schedules
mysqli_query($con, "DELETE FROM rl_lease_schedules WHERE lease_id={$rl_lease_id}");

// Regenerate schedules
try {
    generateRLSchedules($con, $rl_lease_id, $initial_annual_rent, $premium, $start_date, 30, $penalty_rate);
} catch (Exception $e) {
    json_response(false, 'Failed to regenerate schedules: ' . $e->getMessage());
}

// Calculate penalties on fresh schedules
if (!$skip_penalty) {
    try {
        $_REQUEST['lease_id'] = $rl_lease_id;
        $_REQUEST['lease_type'] = 'rl';
        ob_start();
        include __DIR__ . '/rl_cal_penalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // non-fatal
    }
}

// Reapply payments if any exist
if ($payments_count > 0) {
    if (!reapplyRLPaymentsOnExistingSchedules($con, $rl_lease_id)) {
        json_response(false, 'Failed to reapply payments on regenerated schedules.');
    }
    $note = ' Schedules regenerated and payments reprocessed.';
} else {
    $note = ' Schedules regenerated (no payments exist).';
}

// Final penalty recalculation after schedules + payments are in their latest state
if (!$skip_penalty) {
    try {
        $_REQUEST['lease_id'] = $rl_lease_id;
        $_REQUEST['lease_type'] = 'rl';
        ob_start();
        include __DIR__ . '/rl_cal_penalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // non-fatal
    }
}

// If penalty re-calculation lowered the due amount, re-run allocation
if (!$skip_penalty && $payments_count > 0) {
    $penaltyOverpaid = false;
    if ($stCheck = $con->prepare("SELECT 1 FROM rl_lease_schedules WHERE lease_id=? AND panalty_paid > panalty + 0.01 LIMIT 1")) {
        $stCheck->bind_param('i', $rl_lease_id);
        $stCheck->execute();
        $stCheck->store_result();
        $penaltyOverpaid = $stCheck->num_rows > 0;
        $stCheck->close();
    }

    if ($penaltyOverpaid) {
        if (!reapplyRLPaymentsOnExistingSchedules($con, $rl_lease_id)) {
            json_response(false, 'Failed to reapply payments after penalty adjustment.');
        }
        $note .= ' Payments realigned after penalty adjustment.';
    }
}

json_response(true, 'Residential lease updated successfully!' . $note, ['rl_lease_id' => $rl_lease_id]);

} catch (Exception $e) {
    json_response(false, 'Error: ' . $e->getMessage());
} catch (Error $e) {
    json_response(false, 'Fatal Error: ' . $e->getMessage());
}
