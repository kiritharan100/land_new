<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request');
    }

    $lease_id = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $inactive_date = trim($_POST['inactive_date'] ?? '');
    $inactive_reason = trim($_POST['inactive_reason'] ?? '');

    if ($lease_id <= 0) {
        throw new Exception('Missing lease id');
    }
    if ($inactive_date === '') {
        throw new Exception('Inactive date is required');
    }
    if ($inactive_reason === '') {
        throw new Exception('Reason is required');
    }

    $lease = null;
    $leaseSql = "SELECT lease_number, file_number, beneficiary_id, status, lease_status, inactive_date
                 FROM leases WHERE lease_id = ? LIMIT 1";
    if ($st = $con->prepare($leaseSql)) {
        $st->bind_param('i', $lease_id);
        $st->execute();
        $res = $st->get_result();
        $lease = $res ? $res->fetch_assoc() : null;
        $st->close();
    }
    if (!$lease) {
        throw new Exception('Lease not found');
    }

    $currentStatus = strtolower((string)($lease['status'] ?? ''));
    if ($currentStatus !== '' && $currentStatus !== 'active') {
        throw new Exception('Lease is not active');
    }

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $updSql = "UPDATE leases
               SET inactive_date = ?, inactive_reason = ?, status = 'inactive', lease_status = 0, updated_on = NOW(), updated_by = ?
               WHERE lease_id = ?";
    if ($stUp = $con->prepare($updSql)) {
        $stUp->bind_param('ssii', $inactive_date, $inactive_reason, $uid, $lease_id);
        if (!$stUp->execute()) {
            throw new Exception('Failed to inactivate lease');
        }
        $stUp->close();
    } else {
        throw new Exception('DB error');
    }

    if (function_exists('UserLog')) {
        $detail = "Lease {$lease['lease_number']} ({$lease['file_number']}), inactive_date {$inactive_date}, reason {$inactive_reason}";
        $ben_id = isset($lease['beneficiary_id']) ? (int)$lease['beneficiary_id'] : 0;
        UserLog('2', 'Lease Inactivated', $detail, $ben_id);
    }

    $response['success'] = true;
    $response['message'] = 'Lease inactivated successfully';
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);