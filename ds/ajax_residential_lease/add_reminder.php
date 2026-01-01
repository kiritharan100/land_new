<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$response = ['success' => false, 'message' => ''];

try {
    $lease_id = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $type = trim($_POST['reminders_type'] ?? '');
    $date = trim($_POST['sent_date'] ?? '');
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($lease_id <= 0) throw new Exception('Invalid lease ID');

    $sql = "INSERT INTO rl_reminders (lease_id, reminders_type, sent_date, status, created_by, created_on) 
            VALUES (?, ?, ?, 1, ?, NOW())";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'issi', $lease_id, $type, $date, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $response['message'] = 'Reminder added';
    } else {
        throw new Exception(mysqli_error($con));
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


