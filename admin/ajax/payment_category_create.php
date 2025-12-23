<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'list';

function jsonError($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if (!isset($_SESSION['permissions']) || !in_array(24, $_SESSION['permissions'])) {
    jsonError('Access denied.');
}

if ($action === 'list') {
    $data = [];
    $sql = "SELECT cat_id, payment_name, category, starus FROM payment_category ORDER BY cat_id DESC";
    if ($res = mysqli_query($con, $sql)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $row['starus_label'] = ((int)$row['starus'] === 1) ? 'Active' : 'Inactive';
            $data[] = $row;
        }
        mysqli_free_result($res);
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

$name = trim($_POST['payment_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
$catId = isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : 0;

if ($action === 'create') {
    if ($name === '' || $category === '') {
        jsonError('Payment name and category are required.');
    }
    $stmt = $con->prepare("INSERT INTO payment_category (payment_name, category, starus) VALUES (?,?,?)");
    $stmt->bind_param('ssi', $name, $category, $status);
    if (!$stmt->execute()) {
        jsonError('Failed to create: ' . $stmt->error);
    }
    if (function_exists('UserLog')) {
        UserLog('Payment Category', 'Create', "Created category '$name' ($category)");
    }
    echo json_encode(['success' => true, 'message' => 'Payment category created.']);
    exit;
}

if ($action === 'update') {
    if ($catId <= 0) {
        jsonError('Invalid category id.');
    }
    if ($name === '' || $category === '') {
        jsonError('Payment name and category are required.');
    }
    $stmt = $con->prepare("UPDATE payment_category SET payment_name=?, category=?, starus=? WHERE cat_id=?");
    $stmt->bind_param('ssii', $name, $category, $status, $catId);
    if (!$stmt->execute()) {
        jsonError('Failed to update: ' . $stmt->error);
    }
    if (function_exists('UserLog')) {
        UserLog('Payment Category', 'Update', "Updated category ID $catId to '$name' ($category), status=$status");
    }
    echo json_encode(['success' => true, 'message' => 'Payment category updated.']);
    exit;
}

if ($action === 'delete') {
    if ($catId <= 0) {
        jsonError('Invalid category id.');
    }
    $stmt = $con->prepare("UPDATE payment_category SET starus=0 WHERE cat_id=?");
    $stmt->bind_param('i', $catId);
    if (!$stmt->execute()) {
        jsonError('Failed to delete: ' . $stmt->error);
    }
    if (function_exists('UserLog')) {
        UserLog('Payment Category', 'Delete', "Soft-deleted category ID $catId");
    }
    echo json_encode(['success' => true, 'message' => 'Payment category removed.']);
    exit;
}

jsonError('Unknown action.');
