<?php
  require('../db.php');
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  if(empty($_SESSION['username'])) {
     session_unset();     
     session_destroy(); 
     header("Location: ../login.php?msg=true");
     exit;
  }

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'];

$sql = "UPDATE user_license SET account_status = 1 WHERE usr_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $userId);

$response = array();
if ($stmt->execute()) {
    $response['success'] = true;
} else {
    $response['success'] = false;
}

echo json_encode($response);
?>