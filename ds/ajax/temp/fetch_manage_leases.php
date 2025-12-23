<?php
include '../../db.php';

// Server-side DataTables for leases
$location_id = intval($_GET['location_id'] ?? 0);
$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 50); // default 50
$search = trim($_GET['search']['value'] ?? '');

// Build where
$where = "WHERE l.location_id = ?";
$params = [$location_id];
$types = "i";

if ($search !== '') {
    $where .= " AND (l.lease_number LIKE ? OR land.address LIKE ? OR g.gn_name LIKE ? OR ben.name LIKE ? OR l.status LIKE ? )";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= "sssss";
}

// Count total
$totalQ = $con->prepare("SELECT COUNT(*) as cnt FROM leases l WHERE l.location_id = ?");
$totalQ->bind_param('i', $location_id);
$totalQ->execute();
$totalRes = $totalQ->get_result()->fetch_assoc();
$totalRecords = $totalRes ? intval($totalRes['cnt']) : 0;
$totalQ->close();

// Count filtered
$countSql = "SELECT COUNT(*) as cnt FROM leases l
    LEFT JOIN land_registration land ON l.land_id = land.land_id
    LEFT JOIN gn_division g ON land.gn_id = g.gn_id
    LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
    $where";
$stmt = $con->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$filteredRes = $stmt->get_result()->fetch_assoc();
$filteredRecords = $filteredRes ? intval($filteredRes['cnt']) : 0;
$stmt->close();

// Ordering
$orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 6; // default start/end
$orderDir = isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
// Map DataTable column index to DB column/expression. The DataTable columns are:
// 0 lease_number, 1 land_address, 2 gn_name, 3 beneficiary, 4 valuation, 5 annual_pct,
// 6 start_end, 7 rent_balance, 8 penalty_balance, 9 status, 10 actions
$orderColumns = [
    'l.lease_number',
    'CONCAT(land.address_line1, " ", land.address_line2)',
    'g.gn_name',
    'ben.name',
    'l.valuation_amount',
    'l.annual_rent_percentage',
    'l.start_date',
    // rent_balance and penalty_balance are computed fields; order by numeric value from subqueries
    '(
        SELECT COALESCE(SUM((IFNULL(s.annual_amount,0) - IFNULL(s.paid_rent,0))),0)
        FROM lease_schedules s
        WHERE s.lease_id = l.lease_id AND s.end_date < CURDATE()
    )',
    '(
        SELECT COALESCE(SUM((IFNULL(s.panalty,0) - IFNULL(s.panalty_paid,0))),0)
        FROM lease_schedules s
        WHERE s.lease_id = l.lease_id AND s.end_date < CURDATE()
    )',
    'l.status'
];
$orderBy = $orderColumns[$orderColumnIndex] ?? 'l.lease_id';

// Main query: fetch leases and compute last_end_date and outstanding per lease
$sql = "SELECT l.lease_id, l.lease_number, l.land_id, land.address AS land_address, g.gn_name AS gn_name, ben.name AS beneficiary_name,
    l.valuation_amount, l.annual_rent_percentage, l.start_date, l.end_date, l.status,
    (SELECT MAX(s.end_date) FROM lease_schedules s WHERE s.lease_id = l.lease_id) AS last_end_date,
    -- Balance Rent: sum of (annual_amount - paid_rent) for past schedules (end_date < today)
    (SELECT COALESCE(SUM((IFNULL(s.annual_amount,0) - IFNULL(s.paid_rent,0))),0) FROM lease_schedules s WHERE s.lease_id = l.lease_id AND s.end_date < CURDATE()) AS rent_balance,
    -- Balance Penalty: sum of (panalty - panalty_paid) for past schedules (end_date < today)
    (SELECT COALESCE(SUM((IFNULL(s.panalty,0) - IFNULL(s.panalty_paid,0))),0) FROM lease_schedules s WHERE s.lease_id = l.lease_id AND s.end_date < CURDATE()) AS penalty_balance
    FROM leases l
    LEFT JOIN land_registration land ON l.land_id = land.land_id
    LEFT JOIN gn_division g ON land.gn_id = g.gn_id
    LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
    $where
    ORDER BY $orderBy $orderDir
    LIMIT ?, ?";

$stmt = $con->prepare($sql);
// bind params + pagination
$paramsWithLimit = $params;
$typesWithLimit = $types . 'ii';
$paramsWithLimit[] = $start;
$paramsWithLimit[] = $length;

$stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $startEnd = '';
    if ($row['start_date']) $startEnd = $row['start_date'];
    if ($row['end_date']) $startEnd .= '  ' . $row['end_date'];

    $statusBadge = '<span class="badge badge-' . ($row['status'] == 'active' ? 'success' : ($row['status'] == 'expired' ? 'warning' : 'danger')) . '">' . htmlspecialchars(ucfirst($row['status'])) . '</span>';

    $actions = '<select class="form-select form-select-sm action-select" data-id="' . $row['lease_id'] . '">'
        . '<option value="">-- Action --</option>'
        . '<option value="edit"  >Edit</option>'
        . '<option value="schedule">Schedule</option>'
        . '<option value="payment">Payment</option>'
        . '</select>';

    $data[] = [
        htmlspecialchars($row['lease_number']),
        htmlspecialchars($row['land_address']),
        htmlspecialchars($row['gn_name']),
        htmlspecialchars($row['beneficiary_name']),
        number_format((float)$row['valuation_amount'], 2),
        htmlspecialchars($row['annual_rent_percentage']) . '%',
        htmlspecialchars($startEnd),
        number_format((float)$row['rent_balance'], 2),
        number_format((float)$row['penalty_balance'], 2),
        $statusBadge,
        $actions
    ];
}

$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
];

echo json_encode($response);

?>
