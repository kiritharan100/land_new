<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: application/json');

function jsonError($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function resolveLocationId(mysqli $con): int {
    $loc = 0;
    if (isset($_REQUEST['location_id']) && (int)$_REQUEST['location_id'] > 0) {
        $loc = (int)$_REQUEST['location_id'];
    } elseif (isset($_COOKIE['client_cook'])) {
        $md5 = $_COOKIE['client_cook'];
        if ($st = $con->prepare("SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1")) {
            $st->bind_param('s', $md5);
            $st->execute();
            $res = $st->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $loc = (int)$row['c_id'];
            }
            $st->close();
        }
    }
    return $loc;
}

$action = $_REQUEST['action'] ?? 'list';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$locationId = resolveLocationId($con);

if ($locationId <= 0) {
    jsonError('Location not selected.');
}

if (!isset($_SESSION['permissions'])) {
    jsonError('No permissions.');
}

// Permissions: 25=view/create, 26=edit, 27=delete
if (!in_array(25, $_SESSION['permissions'])) {
    jsonError('Access denied.');
}

$locSerial = 0; // will be set per action

if ($action === 'list') {
    $start = $_REQUEST['start_date'] ?? '';
    $end   = $_REQUEST['end_date'] ?? '';
    // If no dates provided, fetch all
    if (empty($start)) {
        $start = '1900-01-01';
    }
    if (empty($end)) {
        $end = '2100-01-01';
    }
    $data = [];
    $sql = "SELECT pr.id, pr.pay_cat_id, pr.location_serial, pr.amount, pr.receipt_number,
                   COALESCE(pr.payment_date, DATE(pr.created_on)) AS payment_date,
                   pr.created_on, pr.status,
                   pc.payment_name, pc.category
            FROM payment_record pr
            LEFT JOIN payment_category pc ON pc.cat_id = pr.pay_cat_id
            WHERE pr.location_id=? AND pr.status IN (0,1)
              AND DATE(COALESCE(pr.payment_date, pr.created_on)) BETWEEN ? AND ?
            ORDER BY pr.payment_date DESC, pr.id DESC";
    if ($st = $con->prepare($sql)) {
        $st->bind_param('iss', $locationId, $start, $end);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $st->close();
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$payCat = isset($_POST['pay_cat_id']) ? (int)$_POST['pay_cat_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
$receiptNumber = trim($_POST['receipt_number'] ?? '');
$paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
$paymentDate = $paymentDate ?: date('Y-m-d');

if ($action === 'create') {
    if ($payCat <= 0 || $amount <= 0) {
        jsonError('All fields are required.');
    }
    // next serial for this location
    $locSerial = 0;
    if ($st = $con->prepare("SELECT COALESCE(MAX(location_serial),0)+1 AS next_serial FROM payment_record WHERE location_id=?")) {
        $st->bind_param('i', $locationId);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $locSerial = (int)$row['next_serial'];
        }
        $st->close();
    }
    if ($locSerial <= 0) {
        $locSerial = 1;
    }
    $stmt = $con->prepare("INSERT INTO payment_record (pay_cat_id, payment_date, location_id, location_serial, receipt_number, amount, create_by, created_on, status) VALUES (?,?,?,?,?,?,?,NOW(),1)");
    $stmt->bind_param('isiisdi', $payCat, $paymentDate, $locationId, $locSerial, $receiptNumber, $amount, $userId);
    if (!$stmt->execute()) {
        jsonError('Failed to save: ' . $stmt->error);
    }
    echo json_encode(['success' => true, 'message' => 'Payment recorded.']);
    exit;
}

if ($action === 'update') {
    if (!in_array(26, $_SESSION['permissions'])) {
        jsonError('No edit permission.');
    }
    if ($id <= 0 || $payCat <= 0 || $amount <= 0) {
        jsonError('All fields are required.');
    }
    // keep existing serial for this record/location
    if ($st = $con->prepare("SELECT location_serial FROM payment_record WHERE id=? AND location_id=? LIMIT 1")) {
        $st->bind_param('ii', $id, $locationId);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $locSerial = (int)$row['location_serial'];
        }
        $st->close();
    }
    if ($locSerial <= 0) {
        jsonError('Invalid payment reference for this location.');
    }
    $stmt = $con->prepare("UPDATE payment_record SET pay_cat_id=?, payment_date=?, location_serial=?, receipt_number=?, amount=? WHERE id=? AND location_id=?");
    $stmt->bind_param('isisdii', $payCat, $paymentDate, $locSerial, $receiptNumber, $amount, $id, $locationId);
    if (!$stmt->execute()) {
        jsonError('Failed to update: ' . $stmt->error);
    }
    echo json_encode(['success' => true, 'message' => 'Payment updated.']);
    exit;
}

if ($action === 'delete') {
    if (!in_array(27, $_SESSION['permissions'])) {
        jsonError('No delete permission.');
    }
    if ($id <= 0) {
        jsonError('Invalid payment.');
    }
    $stmt = $con->prepare("UPDATE payment_record SET status=0 WHERE id=? AND location_id=?");
    $stmt->bind_param('ii', $id, $locationId);
    if (!$stmt->execute()) {
        jsonError('Failed to delete: ' . $stmt->error);
    }
    echo json_encode(['success' => true, 'message' => 'Payment removed.']);
    exit;
}

jsonError('Unknown action.');
