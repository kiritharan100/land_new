<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[], 'error'=>'Auth required']);
    exit;
}

// DataTables params
$draw = intval($_GET['draw'] ?? 1);
$start = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 50);
$searchValue = trim($_GET['search']['value'] ?? '');

$location_id = intval($_GET['location_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Default date range: current year if missing
if (empty($from_date) || empty($to_date)) {
    $year = date('Y');
    $from_date = $year . '-01-01';
    $to_date = $year . '-12-31';
}

// Basic validation
$from_dt = date('Y-m-d', strtotime($from_date));
$to_dt = date('Y-m-d', strtotime($to_date));

$where = [];
$params = [];
$types = '';

$where[] = 'p.payment_date BETWEEN ? AND ?';
$types .= 'ss';
$params[] = $from_dt; $params[] = $to_dt;

if ($location_id > 0) {
    $where[] = 'l.location_id = ?';
    $types .= 'i';
    $params[] = $location_id;
}

if ($searchValue !== '') {
    $where[] = '(l.lease_number LIKE ? OR p.reference_number LIKE ? OR b.name LIKE ?)';
    $types .= 'sss';
    $like = '%' . $searchValue . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) AS cnt
             FROM short_term_lease_payments p
             JOIN short_term_leases l ON l.st_lease_id = p.st_lease_id
             LEFT JOIN short_term_beneficiaries b ON b.ben_id = l.beneficiary_id
             $whereSql";
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[], 'error'=>'Prepare failed: '.$conn->error]);
    exit;
}
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countRes = $countStmt->get_result();
$totalFiltered = ($row = $countRes->fetch_assoc()) ? intval($row['cnt']) : 0;
$countStmt->close();

// Main data query
$dataSql = "SELECT p.payment_id, p.payment_date, p.reference_number, p.lease_amount_paid, p.penalty_amount_paid,
                   p.total_amount, p.receipt_number, l.lease_number, l.st_lease_id, b.name AS beneficiary_name
            FROM short_term_lease_payments p
            JOIN short_term_leases l ON l.st_lease_id = p.st_lease_id
            LEFT JOIN short_term_beneficiaries b ON b.ben_id = l.beneficiary_id
            $whereSql
            ORDER BY p.payment_date DESC, p.payment_id DESC
            LIMIT ?, ?";

// Add pagination params
$typesPage = $types . 'ii';
$paramsPage = $params;
$paramsPage[] = $start;
$paramsPage[] = $length;

$dataStmt = $conn->prepare($dataSql);
if (!$dataStmt) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[], 'error'=>'Prepare data failed: '.$conn->error]);
    exit;
}
$dataStmt->bind_param($typesPage, ...$paramsPage);
$dataStmt->execute();
$res = $dataStmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $isCancelled = (float)$r['total_amount'] == 0 || (stripos((string)$r['receipt_number'], 'CANCELLED') !== false);
    $rows[] = [
        $r['payment_id'],
        date('Y-m-d', strtotime($r['payment_date'])),
        htmlspecialchars($r['lease_number']),
        htmlspecialchars($r['beneficiary_name'] ?? '-'),
        number_format((float)$r['lease_amount_paid'],2),
        number_format((float)$r['penalty_amount_paid'],2),
        number_format((float)$r['total_amount'],2),
        htmlspecialchars($r['reference_number'] ?: '-'),
        $isCancelled ? '<span class="badge badge-danger">Cancelled</span>' : '<span class="badge badge-success">Active</span>',
        $isCancelled
            ? '<button class="btn btn-sm btn-secondary" disabled>Cancelled</button>'
            : '<button class="btn btn-sm btn-danger btn-cancel-payment" data-payment-id="'.(int)$r['payment_id'].'"><i class="fa fa-times"></i> Cancel</button>'
    ];
}
$dataStmt->close();

// recordsTotal: total in range ignoring search? For simplicity using filtered count for both if search applied.
$recordsTotal = $totalFiltered; // Could run separate count without search, omitted for brevity.

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $totalFiltered,
    'data' => $rows
]);
