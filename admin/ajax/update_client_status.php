<?php
// Assuming you have a database connection
include('../../db.php');

if(isset($_POST['md5_client']) && isset($_POST['status'])) {
    $md5_client = $_POST['md5_client'];
    
    $status = $_POST['status'];
    if($status == "Active"){
         $status = 1;
    }else{
         $status = 0;
        
    }

    // Update the client status in the database
    $query = "UPDATE client_registration SET user_license = ? WHERE md5_client = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $status, $md5_client);
    
    if($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
?>
