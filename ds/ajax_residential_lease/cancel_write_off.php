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
        
        $response['success'] = true;
        $response['message'] = 'Write-off cancelled';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


