<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');
$id = intval($_POST['ben_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}
$res = mysqli_query($con, "SELECT * FROM beneficiaries WHERE ben_id=$id LIMIT 1");
$row = mysqli_fetch_assoc($res);
if ($row && empty($row['language'])) $row['language'] = 'English';
echo json_encode($row ?: []);