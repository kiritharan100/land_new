<?php
include '../../auth.php';
include '../../db.php';
header('Content-Type: application/json');

$land_id = intval($_GET['land_id'] ?? 0);
if (!$land_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid land_id.']);
    exit;
}

$sql = "SELECT * FROM short_term_land_registration WHERE land_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $land_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found.']);
}
$stmt->close();
$con->close();
