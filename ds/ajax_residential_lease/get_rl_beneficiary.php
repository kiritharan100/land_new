<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');

$id = isset($_POST['rl_ben_id']) ? (int)$_POST['rl_ben_id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

$sql = "SELECT rl_ben_id, md5_ben_id, name, name_tamil, name_sinhala, address, address_tamil, address_sinhala,
               district, ds_division_id, ds_division_text, gn_division_id, gn_division_text, nic_reg_no, dob, nationality, telephone, email, language
        FROM rl_beneficiaries
        WHERE rl_ben_id = ?
        LIMIT 1";

if ($stmt = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        echo json_encode($row);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'DB error']);
}
