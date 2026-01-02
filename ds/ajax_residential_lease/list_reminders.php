<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

// Permission check
if (!hasPermission(20)) {
    echo '<tr><td colspan="5" class="text-danger text-center">Access denied</td></tr>';
    exit;
}

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
$md5_ben_id = isset($_GET['id']) ? $_GET['id'] : '';
$is_grant_issued = !empty($_GET['grant_issued']);

if ($md5_ben_id !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, location_id FROM rl_beneficiaries WHERE md5_ben_id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5_ben_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            // IDOR Protection
            if ($user_location_id !== '' && (string)$ben['location_id'] !== (string)$user_location_id) {
                echo '<tr><td colspan="5" class="text-danger text-center">Access denied</td></tr>';
                mysqli_stmt_close($stmt);
                exit;
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

if ($lease_id <= 0) {
    echo '<tr><td colspan="5" class="text-danger text-center">Invalid lease.</td></tr>';
    exit;
}

$sql = 'SELECT id, lease_id, reminders_type, sent_date, status, created_by, created_on FROM rl_reminders WHERE lease_id=? ORDER BY sent_date DESC';

if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, 'i', $lease_id);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $sn = 1;
    
    if ($rs && mysqli_num_rows($rs) > 0) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $cancelled = ((int)$row['status'] === 0);
            echo '<tr class="' . ($cancelled ? 'rl-rem-row-cancelled' : '') . '">';
            echo '<td>' . ($sn++) . '</td>';
            echo '<td>' . htmlspecialchars($row['sent_date']) . '</td>';
            echo '<td>' . htmlspecialchars($row['reminders_type']) . '</td>';
            echo '<td>' . ($cancelled ? '<span class="badge badge-danger">Cancelled</span>' : '<span class="badge badge-success">Active</span>') . '</td>';
            echo '<td>';
            if (!$cancelled) {
                if ($is_grant_issued) {
                    echo '<span class="text-muted">-</span>';
                } else {
                    echo '<button class="btn btn-outline-danger btn-sm rl-rem-cancel-btn" data-id="' . (int)$row['id'] . '"><i class="fa fa-times"></i> Cancel</button>';
                }
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5" class="text-center">No reminders found.</td></tr>';
    }
    mysqli_stmt_close($st);
} else {
    echo '<tr><td colspan="5" class="text-danger text-center">Query failed.</td></tr>';
}
