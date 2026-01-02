<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$response = ['success' => false, 'message' => ''];

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) throw new Exception('Invalid ID');

    $ben_id = null;
    $lease_id = null;
    $date = '';
    $officers = '';
    $vstatus = '';
    if ($q = mysqli_prepare($con, 'SELECT v.lease_id, v.date, v.officers_visited, v.visite_status, l.beneficiary_id FROM rl_field_visits v LEFT JOIN rl_lease l ON v.lease_id = l.rl_lease_id WHERE v.id = ? LIMIT 1')) {
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $res = mysqli_stmt_get_result($q);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $lease_id = isset($row['lease_id']) ? (int)$row['lease_id'] : null;
            $ben_id   = isset($row['beneficiary_id']) ? (int)$row['beneficiary_id'] : null;
            $date     = $row['date'] ?? '';
            $officers = $row['officers_visited'] ?? '';
            $vstatus  = $row['visite_status'] ?? '';
        }
        mysqli_stmt_close($q);
    }

    $sql = "UPDATE rl_field_visits SET status = 0 WHERE id = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $response['message'] = 'Cancelled';
        if (function_exists('UserLog')) {
            $detail = 'Cancelled field visit: id=' . (int)$id .
                      ' | date=' . $date .
                      ' | officers=' . $officers .
                      ' | status=' . $vstatus;
            UserLog(2, 'RL Cancel Field Visit', $detail, $ben_id, 'RL');
        }
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


