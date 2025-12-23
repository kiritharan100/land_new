<?php
require('../db.php');


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['username'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?msg=true");
    exit;
}

header('Content-Type: application/json'); // IMPORTANT for AJAX

$data = json_decode(file_get_contents('php://input'), true);
$userId = intval($data['userId']);

$response = ['success' => false];

/*
|--------------------------------------------------------------------------
| 1. Get user details
|--------------------------------------------------------------------------
*/
$query = "SELECT i_name, username, mobile_no FROM user_license WHERE usr_id = '$userId'";
$res   = mysqli_query($con, $query);

if (!$res || mysqli_num_rows($res) == 0) {
    echo json_encode(['success' => false, 'comment' => 'User not found']);
    exit;
}

$user = mysqli_fetch_assoc($res);
$name = $user['i_name'];
$mobile = $user['username'];

/*
|--------------------------------------------------------------------------
| 2. Generate new token
|--------------------------------------------------------------------------
*/
$token = rand(10000, 99999); // 6-digit token

$update = "UPDATE user_license SET token='$token' WHERE usr_id='$userId'";
mysqli_query($con, $update);

/*
|--------------------------------------------------------------------------
| 3. Prepare & send SMS using new SMS module
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../sms_helper.php';
$sms = new SMS_Helper();

$message = "Dear $name, your IRMIS login token is: $token. Use this token on New user setup.";

$sms_result = $sms->sendSMS(
    $lease_id = 0,
    $mobile,
    $message,
    $sms_type = "User Invitation"
);

/*
|--------------------------------------------------------------------------
| 4. Update account status
|--------------------------------------------------------------------------
*/
if ($sms_result['success']) {
    $sql2 = "UPDATE user_license SET account_status = 2 WHERE usr_id = ?";
    $stmt = $con->prepare($sql2);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $response = [
        'success' => true,
        'comment' => 'Invitation SMS sent successfully'
    ];
} else {
    $response = [
        'success' => false,
        'comment' => 'SMS failed: ' . $sms_result['comment']
    ];
}

echo json_encode($response);
exit;
?>