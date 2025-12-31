<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

try {
$location_id = isset($_GET['location_id']) ? (int) $_GET['location_id'] : 0;
 
 //get last file number form rl_leases table for this location
 $stmt = $con->prepare("SELECT file_number,lease_number FROM rl_lease WHERE location_id = ? ORDER BY rl_lease_id DESC LIMIT 1");
 if ($stmt) {
     $stmt->bind_param('i', $location_id);
     $stmt->execute();
     $r = $stmt->get_result();
     if ($r && ($row = $r->fetch_assoc())) {
         $file_number = $row['file_number'];
         $lease_number = $row['lease_number'];
     }
     $stmt->close();
 }
     $file_prefix = preg_replace('#[^/]+$#', '', $file_number);
     $lease_prefix = preg_replace('#[^/]+$#', '', $lease_number);
      

    // Same pattern as long-term leases but scoped to residential lease table
    $file_number =  $file_prefix;
    $lease_number = $lease_prefix;

    echo json_encode([
        'success' => true,
        'lease_number' => $lease_number,
        'file_number' => $file_number
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}