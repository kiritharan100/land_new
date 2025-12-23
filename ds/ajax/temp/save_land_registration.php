<?php
include '../../auth.php';
include '../../db.php';
header('Content-Type: application/json');

// Collect and sanitize POST data
// Collect and sanitize POST data
$ds_id = intval($_POST['ds_division'] ?? 0);
$gn_id = intval($_POST['gn_division'] ?? 0);
$address = trim($_POST['address'] ?? '');
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);
$created_by = intval($_SESSION['user_id'] ?? 0);

// New fields
$lcg_area = isset($_POST['lcg_area']) ? floatval($_POST['lcg_area']) : null;
$lcg_area_unit = $_POST['lcg_area_unit'] ?? null;
$lcg_hectares = isset($_POST['lcg_hectares']) ? floatval($_POST['lcg_hectares']) : null;
$lcg_plan_no = $_POST['lcg_plan_no'] ?? null;
$val_area = isset($_POST['val_area']) ? floatval($_POST['val_area']) : null;
$val_area_unit = $_POST['val_area_unit'] ?? null;  
$val_hectares = isset($_POST['val_hectares']) ? floatval($_POST['val_hectares']) : null;
$val_plan_no = $_POST['val_plan_no'] ?? null;
$survey_area = isset($_POST['survey_area']) ? floatval($_POST['survey_area']) : null;
$survey_area_unit = $_POST['survey_area_unit'] ?? null;
$survey_hectares = isset($_POST['survey_hectares']) ? floatval($_POST['survey_hectares']) : null;
$survey_plan_no = $_POST['survey_plan_no'] ?? null;

if (!$ds_id || !$gn_id || !$address  ) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$sql = "INSERT INTO short_term_land_registration (
    ds_id, gn_id, address, latitude, longitude, created_by, created_on,
    lcg_area, lcg_area_unit, lcg_hectares, lcg_plan_no,
    val_area, val_area_unit, val_hectares, val_plan_no,
    survey_area, survey_area_unit, survey_hectares, survey_plan_no
) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


$stmt = $con->prepare($sql);
$stmt->bind_param(
    'iisssidsdsssssdssd',
    $ds_id, $gn_id, $address, $latitude, $longitude, $created_by,
    $lcg_area, $lcg_area_unit, $lcg_hectares, $lcg_plan_no,
    $val_area, $val_area_unit, $val_hectares, $val_plan_no,
    $survey_area, $survey_area_unit, $survey_hectares, $survey_plan_no
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Land registration saved successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $con->error]);
}
$stmt->close();
$con->close();
?>
