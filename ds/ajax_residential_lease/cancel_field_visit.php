<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

$response = ['success' => false, 'message' => ''];

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) throw new Exception('Invalid ID');

    $sql = "UPDATE rl_field_visits SET status = 0 WHERE id = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $response['message'] = 'Cancelled';
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


