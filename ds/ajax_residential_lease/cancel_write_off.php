<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

$response = ['success' => false, 'message' => ''];

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) throw new Exception('Invalid ID');

    // Get lease_id before cancelling
    $stmt = mysqli_prepare($con, 'SELECT lease_id FROM rl_write_off WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $wo = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    // Fetch beneficiary for logging
    $ben_id_log = null;
    if ($wo && isset($wo['lease_id'])) {
        if ($stBen = mysqli_prepare($con, 'SELECT beneficiary_id FROM rl_lease WHERE rl_lease_id = ? LIMIT 1')) {
            $lid = (int)$wo['lease_id'];
            mysqli_stmt_bind_param($stBen, 'i', $lid);
            mysqli_stmt_execute($stBen);
            $resBen = mysqli_stmt_get_result($stBen);
            if ($resBen && ($rowBen = mysqli_fetch_assoc($resBen))) {
                $ben_id_log = isset($rowBen['beneficiary_id']) ? (int)$rowBen['beneficiary_id'] : null;
            }
            mysqli_stmt_close($stBen);
        }
    }

    $sql = "UPDATE rl_write_off SET status = 0 WHERE id = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Recalculate penalties
        if ($wo && $wo['lease_id']) {
            $_REQUEST['lease_id'] = $wo['lease_id'];
            ob_start();
            include __DIR__ . '/rl_cal_penalty.php';
            ob_end_clean();
        }

        if (function_exists('UserLog')) {
            $detail = sprintf('Cancelled write-off: ID=%d, Lease ID=%d', $id, (int)($wo['lease_id'] ?? 0));
            UserLog(2, 'RL Cancel Write Off', $detail, $ben_id_log, 'RL');
        }
        
        $response['success'] = true;
        $response['message'] = 'Write-off cancelled';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


