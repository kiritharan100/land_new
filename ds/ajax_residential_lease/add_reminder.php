<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$response = ['success' => false, 'message' => ''];

try {
    // Permission check
    if (!hasPermission(20)) {
        throw new Exception('Access denied');
    }
    
    $type = trim($_POST['reminders_type'] ?? '');
    $date = trim($_POST['sent_date'] ?? '');
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    
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
        if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, location_id FROM rl_beneficiaries WHERE md5_ben_id = ? LIMIT 1')) {
            mysqli_stmt_bind_param($stmt, 's', $md5_ben_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($ben = mysqli_fetch_assoc($res))) {
                // IDOR Protection
                if ($user_location_id !== '' && (string)$ben['location_id'] !== (string)$user_location_id) {
                    mysqli_stmt_close($stmt);
                    throw new Exception('Access denied');
                }
                
                $ben_id = (int)$ben['rl_ben_id'];
                
                // Get land_id → lease_id
                if ($st2 = mysqli_prepare($con, 'SELECT land_id FROM rl_land_registration WHERE ben_id = ? ORDER BY land_id DESC LIMIT 1')) {
                    mysqli_stmt_bind_param($st2, 'i', $ben_id);
                    mysqli_stmt_execute($st2);
                    $r2 = mysqli_stmt_get_result($st2);
                    if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                        $land_id = (int)$land['land_id'];
                        
                        if ($st3 = mysqli_prepare($con, 'SELECT rl_lease_id FROM rl_lease WHERE land_id = ? ORDER BY rl_lease_id DESC LIMIT 1')) {
                            mysqli_stmt_bind_param($st3, 'i', $land_id);
                            mysqli_stmt_execute($st3);
                            $r3 = mysqli_stmt_get_result($st3);
                            if ($r3 && ($lease = mysqli_fetch_assoc($r3))) {
                                $lease_id = (int)$lease['rl_lease_id'];
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

    if ($lease_id <= 0) throw new Exception('Invalid lease');

    $sql = "INSERT INTO rl_reminders (lease_id, reminders_type, sent_date, status, created_by, created_on) 
            VALUES (?, ?, ?, 1, ?, NOW())";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'issi', $lease_id, $type, $date, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $response['message'] = 'Reminder added';
        if (function_exists('UserLog')) {
            $detail = sprintf('Added reminder: lease_id=%d | type=%s | sent_date=%s', (int)$lease_id, $type, $date);
            UserLog(2, 'RL Add Reminder', $detail, $ben_id, 'RL');
        }
    } else {
        throw new Exception(mysqli_error($con));
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

