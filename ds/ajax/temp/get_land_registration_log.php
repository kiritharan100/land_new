<?php
include '../../auth.php';
include '../../db.php';
header('Content-Type: application/json');

$land_id = intval($_GET['land_id'] ?? 0);
if (!$land_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid land_id.']);
    exit;
}

$sql = "SELECT l.*, u.i_name as changed_by_name FROM land_registration_log l LEFT JOIN user_license u ON l.changed_by = u.usr_id WHERE l.land_id = ? ORDER BY l.changed_on DESC";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $land_id);
$stmt->execute();
$result = $stmt->get_result();
$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = [
        'changed_on' => $row['changed_on'],
        'changed_by' => $row['changed_by_name'] ?? $row['changed_by'],
        'old_data' => json_decode($row['old_data'], true),
        'new_data' => json_decode($row['new_data'], true)
    ];
}
$stmt->close();
$con->close();
echo json_encode(['success' => true, 'logs' => $logs]);
