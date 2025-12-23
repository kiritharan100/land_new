<?php
  require('../db.php');
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  if(empty($_SESSION['username'])) {
     session_unset();     
     session_destroy(); 
     header("Location: ../login.php?msg=true");
     exit;
  }

$userId = $_GET['userId'];

$sql = "SELECT * FROM user_license WHERE usr_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

echo json_encode($user);
?>
