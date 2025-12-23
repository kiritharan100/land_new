<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['privileges'])) {
    // Remove all current assignments
    $conn->query("UPDATE group_activity_map SET status=0");

    // Add new assignments
    foreach ($_POST['privileges'] as $act_id => $group_ids) {
        foreach ($group_ids as $group_id) {
            // Check if already exists
            $check = $conn->query("SELECT id FROM group_activity_map WHERE act_id='$act_id' AND group_id='$group_id'");
            if ($check && $check->num_rows > 0) {
                $conn->query("UPDATE group_activity_map SET status=1 WHERE act_id='$act_id' AND group_id='$group_id'");
            } else {
                $conn->query("INSERT INTO group_activity_map (act_id, group_id, status) VALUES ('$act_id', '$group_id', 1)");
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Privileges updated successfully.']);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'No privileges submitted.']);
    exit;
}
?>
