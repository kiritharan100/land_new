<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$response = ['success' => false, 'message' => ''];

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) throw new Exception('Invalid ID');

    $ben_id = null;
    $rem_type = '';
    $sent_date = '';
    if ($q = mysqli_prepare($con, 'SELECT r.lease_id, r.reminders_type, r.sent_date, l.beneficiary_id FROM rl_reminders r LEFT JOIN rl_lease l ON r.lease_id = l.rl_lease_id WHERE r.id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $res = mysqli_stmt_get_result($q);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $ben_id = isset($row['beneficiary_id']) ? (int)$row['beneficiary_id'] : null;
            $rem_type = $row['reminders_type'] ?? '';
            $sent_date = $row['sent_date'] ?? '';
        }
        mysqli_stmt_close($q);
    }

    $sql = "UPDATE rl_reminders SET status = 0 WHERE id = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $response['message'] = 'Cancelled';
        if (function_exists('UserLog')) {
            $detail = sprintf('Cancelled reminder: id=%d | type=%s | sent_date=%s', (int)$id, $rem_type, $sent_date);
            UserLog(2, 'RL Cancel Reminder', $detail, $ben_id, 'RL');
        }
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


