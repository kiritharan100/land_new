<?php
include '../../db.php';
include '../../auth.php';
if (isset($_GET['c_id'])) {
    $c_id = intval($_GET['c_id']);
    $result = mysqli_query($con, "SELECT gn_id, gn_name, gn_no FROM gn_division WHERE c_id = '$c_id' ORDER BY gn_name");
    echo '<option value="">Select GN Division</option>';
    while($gn = mysqli_fetch_assoc($result)) {
        echo '<option value="'.htmlspecialchars($gn['gn_id']).'">'.htmlspecialchars($gn['gn_name']).' ('.htmlspecialchars($gn['gn_no']).')</option>';
    }
}
?>