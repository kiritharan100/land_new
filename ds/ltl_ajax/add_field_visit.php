<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

// Permission check
if (!hasPermission(20)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$date     = $_POST['date'] ?? '';
$officers = trim($_POST['officers'] ?? '');
$vstatus  = trim($_POST['status'] ?? '');

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

// Accept md5_ben_id (id parameter) and derive lease_id securely
$lease_id = 0;
$ben_id = 0;
$md5_ben_id = isset($_POST['id']) ? $_POST['id'] : '';

if ($md5_ben_id !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT ben_id, location_id FROM beneficiaries WHERE md5_ben_id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5_ben_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            // IDOR Protection
            if ($user_location_id !== '' && (string)$ben['location_id'] !== (string)$user_location_id) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                mysqli_stmt_close($stmt);
                exit;
            }
            
            $ben_id = (int)$ben['ben_id'];
            
            // Get land_id → lease_id
            if ($st2 = mysqli_prepare($con, 'SELECT land_id FROM ltl_land_registration WHERE ben_id = ? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                    $land_id = (int)$land['land_id'];
                    
                    if ($st3 = mysqli_prepare($con, 'SELECT lease_id FROM leases WHERE land_id = ? ORDER BY created_on DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);
                        if ($r3 && ($lease = mysqli_fetch_assoc($r3))) {
                            $lease_id = (int)$lease['lease_id'];
                        }
                        mysqli_stmt_close($st3);
                    }
                }
                mysqli_stmt_close($st2);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Validate required fields
if ($lease_id <= 0 || $date === '' || $officers === '') {
    echo json_encode(['success'=>false,'message'=>'Missing required fields or invalid lease']);
    exit;
}



// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
    echo json_encode(['success'=>false,'message'=>'Invalid date format']);
    exit;
}

// Prepared statement
$sql = "INSERT INTO ltl_feald_visits 
        (lease_id, `date`, officers_visited, visite_status, status)
        VALUES (?, ?, ?, ?, 1)";

$stmt = $con->prepare($sql);
$stmt->bind_param("isss", $lease_id, $date, $officers, $vstatus);

if ($stmt->execute()) {
    $new_id = mysqli_insert_id($con);
    if (function_exists('UserLog')) {
        $detail = sprintf('Added field visit: id=%d  | date=%s | officers=%s | status=%s',
            (int)$new_id, (int)$lease_id, $date, $officers, $vstatus);
        UserLog(2,'LTL Add Field Visits', $detail,$ben_id);
    }
    echo json_encode(['success'=>true, 'message'=>'Added']);
} else {
    echo json_encode(['success'=>false, 'message'=>$stmt->error]);
}

$stmt->close();
?>
