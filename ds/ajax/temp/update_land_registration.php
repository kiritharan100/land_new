<?php
include '../../auth.php';
include '../../db.php';
header('Content-Type: application/json');

$land_id = intval($_POST['land_id'] ?? 0);
if (!$land_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid land_id.']);
    exit;
}

// Collect and sanitize POST data
$gn_id = intval($_POST['gn_division'] ?? 0);
$address = trim($_POST['address'] ?? '');
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);
$lcg_area = isset($_POST['lcg_area']) ? floatval($_POST['lcg_area']) : null;
$lcg_area_unit = isset($_POST['lcg_area_unit']) ? strval($_POST['lcg_area_unit']) : null;
$lcg_hectares = isset($_POST['lcg_hectares']) ? floatval($_POST['lcg_hectares']) : null;
$lcg_plan_no = $_POST['lcg_plan_no'] ?? null;
$val_area = isset($_POST['val_area']) ? floatval($_POST['val_area']) : null;
$val_area_unit = isset($_POST['val_area_unit']) ? strval($_POST['val_area_unit']) : null;
$val_hectares = isset($_POST['val_hectares']) ? floatval($_POST['val_hectares']) : null;
$val_plan_no = $_POST['val_plan_no'] ?? null;
$survey_area = isset($_POST['survey_area']) ? floatval($_POST['survey_area']) : null;
$survey_area_unit = isset($_POST['survey_area_unit']) ? strval($_POST['survey_area_unit']) : null;
$survey_hectares = isset($_POST['survey_hectares']) ? floatval($_POST['survey_hectares']) : null;
$survey_plan_no = $_POST['survey_plan_no'] ?? null;

if (!$gn_id || !$address  ) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}


// Fetch current data for comparison
$sql = "SELECT * FROM short_term_land_registration WHERE land_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $land_id);
$stmt->execute();
$result = $stmt->get_result();
$old = $result->fetch_assoc();
$stmt->close();

$fields = [
    'gn_id' => $gn_id,
    'address' => $address,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'lcg_area' => $lcg_area,
    'lcg_area_unit' => $lcg_area_unit,
    'lcg_hectares' => $lcg_hectares,
    'lcg_plan_no' => $lcg_plan_no
];


$numericFields = [
    'latitude', 'longitude',
    'lcg_area', 'lcg_hectares'
];
$changed = [];
foreach ($fields as $col => $newVal) {
    $oldVal = $old[$col];
    if (in_array($col, $numericFields)) {
        // Compare as float, treat null/empty as 0
        $oldNum = ($oldVal === null || $oldVal === '') ? 0 : (float)$oldVal;
        $newNum = ($newVal === null || $newVal === '') ? 0 : (float)$newVal;
        if ($oldNum != $newNum) {
            $changed[$col] = ["old" => $oldVal, "new" => $newVal];
        }
    } else {
        if ((string)$oldVal !== (string)$newVal) {
            $changed[$col] = ["old" => $oldVal, "new" => $newVal];
        }
    }
}

if (count($changed) > 0) {
    // Build update query dynamically
    $set = [];
    $params = [];
    $types = '';
    foreach ($fields as $col => $val) {
        $set[] = "$col=?";
        $params[] = $val;
        // Type guessing
        if (is_int($val)) $types .= 'i';
        elseif (is_float($val)) $types .= 'd';
        else $types .= 's';
    }
    $params[] = $land_id;
    $types .= 'i';
    $sql = "UPDATE short_term_land_registration SET ".implode(", ", $set)." WHERE land_id=?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $success = $stmt->execute();
    $stmt->close();

    // Log changes
    $action_type = 'update';
    $old_data = json_encode(array_map(function($c){return $c['old'];}, $changed));
    $new_data = json_encode(array_map(function($c){return $c['new'];}, $changed));
    $changed_by = $_SESSION['user_id'] ?? 0;
    $log_sql = "INSERT INTO land_registration_log (land_id, action_type, old_data, new_data, changed_by, changed_on) VALUES (?, ?, ?, ?, ?, NOW())";
    $log_stmt = $con->prepare($log_sql);
    $log_stmt->bind_param('isssi', $land_id, $action_type, $old_data, $new_data, $changed_by);
    $log_stmt->execute();
    $log_stmt->close();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Land registration updated and changes logged.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $con->error]);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'No changes detected.']);
}
$con->close();
