<?php
 
require('../db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gn_id = intval($_POST['gn_id']);
    $gn_name = mysqli_real_escape_string($con, $_POST['gn_name']);
    $gn_no = mysqli_real_escape_string($con, $_POST['gn_no']);
    $gn_code = mysqli_real_escape_string($con, $_POST['gn_code']);
    $c_id = intval($_POST['c_id']);

    $sql = "UPDATE gn_division 
            SET gn_name = '$gn_name', gn_no = '$gn_no', gn_code = '$gn_code', c_id = $c_id
            WHERE gn_id = $gn_id";

    if (mysqli_query($con, $sql)) {
        header("Location: gn_division_list.php?success=1");
        exit();
    } else {
        echo "Error updating record: " . mysqli_error($con);
    }
} else {
    echo "Invalid request.";
}
?>