<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

// Permission check
if (!hasPermission(20)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$type      = isset($_POST['reminders_type']) ? trim($_POST['reminders_type']) : '';
$sent_date = isset($_POST['sent_date']) ? trim($_POST['sent_date']) : '';
$allowed   = ['Recovery Letter','Annexure 09','Annexure 12A','Annexure 12'];
$created_by = isset($user_id) ? (int)$user_id : 0; // from auth.php

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
$ben_id = null;
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

if ($lease_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid lease']); exit; }
if ($type === '' || !in_array($type,$allowed,true)) { echo json_encode(['success'=>false,'message'=>'Select valid reminder type']); exit; }
if ($sent_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$sent_date)) { echo json_encode(['success'=>false,'message'=>'Invalid date']); exit; }

// Insert with created_by & created_on
$sql = 'INSERT INTO ltl_reminders (lease_id, reminders_type, sent_date, status, created_by, created_on) VALUES (?,?,?,?,?,NOW())';
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st,'issii',$lease_id,$type,$sent_date,$dummyStatus,$created_by);
} else {
  // Fallback if including status placeholder fails (e.g. different schema) use inline status value
  $sql = 'INSERT INTO ltl_reminders (lease_id, reminders_type, sent_date, status, created_by, created_on) VALUES (?,?,?,?,?,NOW())';
  $st = mysqli_prepare($con, $sql);
}

// Adjust binding: if previous prepare used inline status (1) question marks count differs.
// Determine number of placeholders quickly
$placeholders = substr_count($sql,'?');
if ($st) {
  if ($placeholders === 5) {
    // lease_id, reminders_type, sent_date, status, created_by
    $status = 1;
    mysqli_stmt_bind_param($st,'issii',$lease_id,$type,$sent_date,$status,$created_by);
  } elseif ($placeholders === 4) {
    // lease_id, reminders_type, sent_date, created_by (status fixed inline to 1)
    mysqli_stmt_bind_param($st,'issi',$lease_id,$type,$sent_date,$created_by);
  } else {
    echo json_encode(['success'=>false,'message'=>'Unexpected statement structure']);
    exit;
  }
  if (mysqli_stmt_execute($st)) {
    if (function_exists('UserLog')) {
      $detail = sprintf('Added reminder: lease_id=%d | type=%s | sent_date=%s ', $lease_id, $type, $sent_date);
      UserLog('2','LTL Add Reminders',$detail,$ben_id,'LTL');
    }
    echo json_encode(['success'=>true]);
  } else {
    echo json_encode(['success'=>false,'message'=>'DB insert failed']);
  }
  mysqli_stmt_close($st);
} else {
  echo json_encode(['success'=>false,'message'=>'Prepare failed']);
}
