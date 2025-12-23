<?php
require_once "../../auth.php";
require_once "../../db.php";

$md5_client = $_POST['md5_client'] ?? '';
$field = $_POST['field'] ?? '';
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

// only allow the 2 valid column names
$allowed = ['payment_sms', 'remindes_sms'];

if (!in_array($field, $allowed) || $md5_client == '') {
    echo "invalid";
    exit;
}

$sql = "UPDATE client_registration 
        SET $field = '$status'
        WHERE md5_client = '$md5_client'";

if(mysqli_query($con, $sql)){
    echo "success";
} else {
    echo "error";
}
?>
