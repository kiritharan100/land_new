<?php
include '../../db.php';
include '../../auth.php';

if (isset($_GET['c_id'])) {
    $c_id = intval($_GET['c_id']);
    
    // IDOR Protection: Verify user has access to this location
    $user_location_id = '';
    if (isset($_COOKIE['client_cook']) && $_COOKIE['client_cook'] !== '') {
        $selected_client = $_COOKIE['client_cook'];
        if ($stmtLoc = mysqli_prepare($con, 'SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1')) {
            mysqli_stmt_bind_param($stmtLoc, 's', $selected_client);
            mysqli_stmt_execute($stmtLoc);
            $resLoc = mysqli_stmt_get_result($stmtLoc);
            if ($resLoc && ($rowLoc = mysqli_fetch_assoc($resLoc))) {
                $user_location_id = $rowLoc['c_id'];
            }
            mysqli_stmt_close($stmtLoc);
        }
    }
    
    // If user has a location selected, only allow access to their own location's GN divisions
    if ($user_location_id !== '' && (string)$c_id !== (string)$user_location_id) {
        echo '<option value="">Access denied</option>';
        exit;
    }
    
    // Use prepared statement
    $stmt = mysqli_prepare($con, "SELECT gn_id, gn_name, gn_no FROM gn_division WHERE c_id = ? ORDER BY gn_name");
    mysqli_stmt_bind_param($stmt, 'i', $c_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    echo '<option value="">Select GN Division</option>';
    while ($gn = mysqli_fetch_assoc($result)) {
        echo '<option value="'.htmlspecialchars($gn['gn_id']).'">'.htmlspecialchars($gn['gn_name']).' ('.htmlspecialchars($gn['gn_no']).')</option>';
    }
    mysqli_stmt_close($stmt);
}
?>