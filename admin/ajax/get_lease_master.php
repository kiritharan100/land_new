<?php
include '../../db.php';
include '../../auth.php';
$id = intval($_POST['lease_type_id']);
$res = mysqli_query($con, "SELECT * FROM lease_master WHERE lease_type_id=$id");
$row = mysqli_fetch_assoc($res);
echo json_encode($row);
