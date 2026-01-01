<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

// Resolve user's location from cookie for IDOR protection
$user_location_id = '';
if (isset($_COOKIE['client_cook']) && $_COOKIE['client_cook'] !== '') {
    $selected_client = $_COOKIE['client_cook'];
    if ($stmtLoc = mysqli_prepare($con, 'SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmtLoc, 's', $selected_client);
        mysqli_stmt_execute($stmtLoc);
        $resLoc = mysqli_stmt_get_result($stmtLoc);
        if ($resLoc && ($rowLoc = mysqli_fetch_assoc($resLoc))) {
            $user_location_id = $rowLoc['c_id'];
        }
        mysqli_stmt_close($stmtLoc);
    }
}

// Accept md5_ben_id (id parameter) instead of plain ben_id for security
$ben_id = 0;
$md5_ben_id = isset($_GET['id']) ? $_GET['id'] : '';
if ($md5_ben_id !== '') {
    if ($stmtB = mysqli_prepare($con, 'SELECT rl_ben_id, location_id FROM rl_beneficiaries WHERE md5_ben_id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmtB, 's', $md5_ben_id);
        mysqli_stmt_execute($stmtB);
        $resB = mysqli_stmt_get_result($stmtB);
        if ($resB && ($rowB = mysqli_fetch_assoc($resB))) {
            // IDOR Protection: verify user has access to this beneficiary's location
            if ($user_location_id !== '' && (string)$rowB['location_id'] !== (string)$user_location_id) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            $ben_id = (int)$rowB['rl_ben_id'];
        }
        mysqli_stmt_close($stmtB);
    }
}

$land_id = isset($_GET['land_id']) ? (int)$_GET['land_id'] : 0;

function with_extent_ha(array $row): array {
    if (!isset($row['extent_ha']) || $row['extent_ha'] === '' || $row['extent_ha'] === null) {
        $factors = [
            'hectares' => 1,
            'sqft' => 0.0000092903,
            'sqyd' => 0.0000836127,
            'perch' => 0.0252929,
            'rood' => 0.1011714,
            'acre' => 0.4046856,
            'cent' => 0.00404686,
            'ground' => 0.0023237,
            'sqm' => 0.0001
        ];
        $extent = isset($row['extent']) ? floatval($row['extent']) : 0;
        $unit = isset($row['extent_unit']) ? strtolower((string)$row['extent_unit']) : 'hectares';
        $ha = $extent * ($factors[$unit] ?? 1);
        $row['extent_ha'] = $extent ? number_format($ha, 6, '.', '') : '';
    }
    return $row;
}

if ($ben_id > 0) {
    $sql = "SELECT land_id, ben_id, ds_id, gn_id, land_address, landBoundary, status, developed_status, sketch_plan_no, plc_plan_no, survey_plan_no, extent, extent_unit, extent_ha
            FROM rl_land_registration WHERE ben_id = ? ORDER BY land_id DESC LIMIT 1";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $ben_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $row = with_extent_ha($row);
            if (!isset($row['developed_status']) || $row['developed_status'] === null || $row['developed_status'] === '') {
                $row['developed_status'] = 'Not Developed';
            }
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No land record for beneficiary']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($con)]);
    }
    exit;
}

if ($land_id > 0) {
    // IDOR Protection: verify land belongs to user's location via beneficiary
    if ($user_location_id !== '') {
        $sqlCheck = "SELECT rb.location_id FROM rl_land_registration rl 
                     INNER JOIN rl_beneficiaries rb ON rl.ben_id = rb.rl_ben_id 
                     WHERE rl.land_id = ? LIMIT 1";
        if ($stmtCheck = mysqli_prepare($con, $sqlCheck)) {
            mysqli_stmt_bind_param($stmtCheck, 'i', $land_id);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);
            if ($resCheck && ($rowCheck = mysqli_fetch_assoc($resCheck))) {
                if ((string)$rowCheck['location_id'] !== (string)$user_location_id) {
                    mysqli_stmt_close($stmtCheck);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
            }
            mysqli_stmt_close($stmtCheck);
        }
    }
    
    $sql = "SELECT land_id, ben_id, ds_id, gn_id, land_address, landBoundary, status, developed_status, sketch_plan_no, plc_plan_no, survey_plan_no, extent, extent_unit, extent_ha
            FROM rl_land_registration WHERE land_id = ? LIMIT 1";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $land_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $row = with_extent_ha($row);
            if (!isset($row['developed_status']) || $row['developed_status'] === null || $row['developed_status'] === '') {
                $row['developed_status'] = 'Not Developed';
            }
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($con)]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Provide id (md5_ben_id) or land_id']);
