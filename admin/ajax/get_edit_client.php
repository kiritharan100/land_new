<?php 
$request = $_REQUEST; 
require('../../db.php');
$md5_client = $request['md5_client']; 
$sel_query="SELECT * from client_registration where md5_client ='$md5_client'";
$result = mysqli_query($con,$sel_query);
$row = mysqli_fetch_assoc($result);
echo json_encode($row);
?>