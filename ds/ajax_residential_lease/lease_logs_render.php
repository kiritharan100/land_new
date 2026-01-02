<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: text/html; charset=UTF-8');

$md5 = $_GET['id'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Default range: current year
$now = new DateTime();
$yearStart = new DateTime($now->format('Y') . '-01-01');
if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = $yearStart->format('Y-m-d');
}
if ($to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $now->format('Y-m-d');
}

// Resolve user location from cookie for IDOR protection
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

$ben_id = 0;
$lease_number = '';
if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, location_id FROM rl_beneficiaries WHERE md5_ben_id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            if ($user_location_id !== '' && (string)$ben['location_id'] !== (string)$user_location_id) {
                echo '<div class="text-danger">Access denied</div>';
                exit;
            }
            $ben_id = (int)$ben['rl_ben_id'];

            // fetch latest lease number for display
            if ($st2 = mysqli_prepare($con, 'SELECT l.lease_number FROM rl_land_registration lr LEFT JOIN rl_lease l ON lr.land_id = l.land_id WHERE lr.ben_id = ? ORDER BY l.rl_lease_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($row2 = mysqli_fetch_assoc($r2))) {
                    $lease_number = $row2['lease_number'] ?? '';
                }
                mysqli_stmt_close($st2);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if ($ben_id <= 0) {
    echo '<div class="text-danger">Invalid beneficiary.</div>';
    exit;
}

$logs = [];
$sql = "SELECT ul.log_date, ul.action, ul.detail, ul.usr_id, ul.lease_type, u.username, u.i_name
        FROM user_log ul
        LEFT JOIN user_license u ON ul.usr_id = u.usr_id
        WHERE ul.ben_id = ? AND (ul.lease_type = 'RL' OR ul.lease_type = '' OR ul.lease_type IS NULL)
          AND DATE(ul.log_date) BETWEEN ? AND ?
        ORDER BY ul.log_date DESC, ul.id DESC";
if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, 'iss', $ben_id, $from, $to);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $logs[] = $row;
        }
    }
    mysqli_stmt_close($st);
}

?>
<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="bg-light">
            <tr>
                <th style="width:150px;">Date Time</th>
                <th style="width:140px;">User</th>
                <th style="width:180px;">Activity</th>
                <th>Detail</th>

            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="5" class="text-center text-muted">No logs found for the selected range.</td>
            </tr>
            <?php else: foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['log_date'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['i_name'] ?? $log['username'] ?? ('User #' . ($log['usr_id'] ?? ''))) ?>
                </td>
                <td><?= htmlspecialchars($log['action'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['detail'] ?? '') ?></td>

            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>