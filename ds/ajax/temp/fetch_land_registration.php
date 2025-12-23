<?php
include '../../db.php';

// DataTables server-side processing
$location_id = intval($_GET['location_id'] ?? 0);
$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 50);
$search = trim($_GET['search']['value'] ?? '');


// Columns: ds_id, gn_id, address, extents, plan_no, created_on, created_by, action
$columns = [
    'ds_id', 'gn_id', 'address', 'extents', 'plan_no', 'created_on', 'created_by', 'action'
];



if ($location_id > 0) {
    $where  = "WHERE short_term_land_registration.ds_id = ?";
    $params = [$location_id];
    $types  = "i";
} else {
    $where  = "WHERE 1";
    $params = [];
    $types  = "";
}

// Search by address, GN, Extent (Ha) and Plan No using LCG fields only
if ($search !== '') {
    $where .= " AND (short_term_land_registration.address LIKE ? OR g.gn_name LIKE ? OR short_term_land_registration.lcg_hectares LIKE ? OR short_term_land_registration.lcg_plan_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= "ssss";
}

// Count total
$totalQuery = "SELECT COUNT(*) as cnt FROM short_term_land_registration WHERE ds_id = ?";
$stmt = $con->prepare($totalQuery);
$stmt->bind_param("i", $location_id);
$stmt->execute();
$totalRes = $stmt->get_result();
$totalRow = $totalRes->fetch_assoc();
$totalRecords = $totalRow['cnt'];
$stmt->close();

// Count filtered
$countFilteredQuery = "SELECT COUNT(*) as cnt FROM short_term_land_registration
    LEFT JOIN client_registration c ON short_term_land_registration.ds_id = c.c_id
    LEFT JOIN gn_division g ON short_term_land_registration.gn_id = g.gn_id
    LEFT JOIN user_license u ON short_term_land_registration.created_by = u.usr_id
    $where";
$stmt = $con->prepare($countFilteredQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$filteredRow = $result->fetch_assoc();
$filteredRecords = $filteredRow['cnt'];
$stmt->close();

// Main data query

// Handle DataTables ordering (sorting)
$orderColumnIndex = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 5; // default Registered On
$orderDir = isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
$orderColumns = [
    'c.client_name', // DS Division
    'g.gn_name',     // GN Division
    'short_term_land_registration.address',
    // Extents (dynamic, handled below)
    // Plan No (dynamic, handled below)
    'short_term_land_registration.created_on',
    'u.i_name',
    'short_term_land_registration.land_id' // Action (not really sortable, but needed for index)
];

// Map DataTables column index to SQL column
switch ($orderColumnIndex) {
    case 0: $orderBy = 'c.client_name'; break;
    case 1: $orderBy = 'g.gn_name'; break;
    case 2: $orderBy = 'short_term_land_registration.address'; break;
    case 3: $orderBy = 'short_term_land_registration.lcg_hectares'; break;
    case 4: $orderBy = 'short_term_land_registration.lcg_plan_no'; break;
    case 5: $orderBy = 'short_term_land_registration.created_on'; break;
    case 6: $orderBy = 'u.i_name'; break;
    default: $orderBy = 'short_term_land_registration.created_on';
}

$sql = "SELECT short_term_land_registration.*, c.client_name as ds_name, g.gn_name as gn_name, u.i_name as created_by_name
    FROM short_term_land_registration
    LEFT JOIN client_registration c ON short_term_land_registration.ds_id = c.c_id
    LEFT JOIN gn_division g ON short_term_land_registration.gn_id = g.gn_id
    LEFT JOIN user_license u ON short_term_land_registration.created_by = u.usr_id
    $where
    ORDER BY $orderBy $orderDir
    LIMIT ?, ?";

        $stmt = $con->prepare($sql);
        $params[] = $start;
        $params[] = $length;
        $types   .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $extents = number_format((float)$row['lcg_hectares'], 2) . ' Ha';
            $planNo = htmlspecialchars($row['lcg_plan_no']);
            $data[] = [
                htmlspecialchars($row['ds_name']),
                htmlspecialchars($row['gn_name']),
                htmlspecialchars($row['address']),
                $extents,
                $planNo,
                // htmlspecialchars($row['created_on']),
                date('Y-m-d', strtotime($row['created_on'])), // only date
                htmlspecialchars($row['created_by_name']),
                '<select class="form-select form-select-sm action-select" data-id="'.$row['land_id'].'">
                    <option value="">-- Action --</option>
                
                    <option value="edit">Edit</option>
                    <option value="log">Changes Log</option>
                   
                </select>'
            ];
    }

// DataTables response
$response = [
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
];
echo json_encode($response);
