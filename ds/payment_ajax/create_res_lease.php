<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

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
$premium = isset($_POST['premium']) ? (float) $_POST['premium'] : 0;

if ($land_id <= 0 || $beneficiary_id <= 0) {
    json_response(false, 'Land and beneficiary are required to create a lease.');
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
$end_date = $start_dt->modify('+30 years')->format('Y-m-d');

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

// Prevent duplicate creation on the same land
if ($stmtCheck = $con->prepare('SELECT rl_lease_id FROM rl_lease WHERE land_id = ? LIMIT 1')) {
    $stmtCheck->bind_param('i', $land_id);
    $stmtCheck->execute();
    $res = $stmtCheck->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmtCheck->close();
        json_response(false, 'A lease already exists for this land. Please use update instead.', ['rl_lease_id' => (int) $row['rl_lease_id']]);
    }
    $stmtCheck->close();
}

$initial_annual_rent = 0.0;
if (!$is_first && isset($_POST['initial_annual_rent'])) {
    $initial_annual_rent = (float) $_POST['initial_annual_rent'];
} else {
    $initial_annual_rent = calculate_initial_rent($lease_calculation_basic, $valuation_amount, $annual_rent_percentage, $ben_income);
}

// Calculate premium if not provided: first lease = initial_annual_rent * 3, otherwise 0
if (!isset($_POST['premium']) || $_POST['premium'] === '') {
    $premium = $is_first ? ($initial_annual_rent * 3) : 0;
}

$sql = "INSERT INTO rl_lease (
            land_id, beneficiary_id, location_id,
            lease_number, file_number, is_it_first_ease,
            valuation_amount, valuvation_date, valuvation_letter_date,
            premium, start_date, end_date, status,
            lease_calculation_basic, annual_rent_percentage, ben_income,
            initial_annual_rent, discount_rate, penalty_rate,
            outright_grants_number, outright_grants_date
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

if ($stmt = $con->prepare($sql)) {
    $stmt->bind_param(
        'iiissidssdssssdddddss',
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
        $outright_grants_date
    );

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        json_response(true, 'Residential lease created successfully.', ['rl_lease_id' => (int) $newId]);
    }

    $err = $stmt->error;
    $stmt->close();
    json_response(false, 'Failed to create lease. ' . $err);
}

json_response(false, 'Database error: ' . $con->error);
