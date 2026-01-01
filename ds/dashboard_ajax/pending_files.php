<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Colombo');

// IDOR Protection: Resolve user's location from cookie
$user_location_id = '';
if (isset($_COOKIE['client_cook']) && $_COOKIE['client_cook'] !== '') {
    $selected_client = $_COOKIE['client_cook'];
    if ($stmtLoc = mysqli_prepare($con, 'SELECT c_id FROM client_registration WHERE md5_client = ? LIMIT 1')) {
        mysqli_stmt_bind_param($stmtLoc, 's', $selected_client);
        mysqli_stmt_execute($stmtLoc);
        $resLoc = mysqli_stmt_get_result($stmtLoc);
        if ($resLoc && ($rowLoc = mysqli_fetch_assoc($resLoc))) {
            $user_location_id = $rowLoc['c_id'];
        }
        mysqli_stmt_close($stmtLoc);
    }
}

// IDOR Protection: If user has a location, they can only query their own location
$requestedLoc = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : null;
if ($user_location_id !== '' && $requestedLoc !== null && $requestedLoc > 0 && (string)$requestedLoc !== (string)$user_location_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$res = [
  'success' => false,
  'location_id' => null,
  'pending_count' => 0,
  'message' => ''
];

try {
  $locParam = null;
  if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
    $locParam = (int)$_GET['location_id'];
    if ($locParam <= 0) { $locParam = null; }
  }
  $res['location_id'] = $locParam;

$filters = [];
  $types = '';
  $params = [];
  if ($locParam !== null) {
    $filters[] = 'b.location_id = ?';
    $types .= 'i';
    $params[] = $locParam;
  }
$whereParts = $filters;
$whereParts[] = "(l.lease_id IS NULL OR l.file_number IS NULL OR l.file_number = '' OR l.file_number = 'Pending')";
$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

// Latest lease per beneficiary (or none), count where file number missing/pending
$sql = "SELECT COUNT(*) AS pending_cnt
        FROM beneficiaries b
        LEFT JOIN (
          SELECT l2.beneficiary_id, l2.file_number, l2.lease_id
          FROM leases l2
          INNER JOIN (
            SELECT beneficiary_id, MAX(lease_id) AS max_id
            FROM leases
            GROUP BY beneficiary_id
          ) lm ON lm.beneficiary_id = l2.beneficiary_id AND lm.max_id = l2.lease_id
        ) l ON l.beneficiary_id = b.ben_id
        $whereSql";

$ltlPendingCnt = 0;
if ($stmt = mysqli_prepare($con, $sql)) {
  if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }
  if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_bind_result($stmt, $ltlPendingCnt);
    mysqli_stmt_fetch($stmt);
  }
  mysqli_stmt_close($stmt);
}

// Residential Lease pending files
$rlFilters = [];
$rlTypes = '';
$rlParams = [];
if ($locParam !== null) {
  $rlFilters[] = 'rb.location_id = ?';
  $rlTypes .= 'i';
  $rlParams[] = $locParam;
}
$rlWhereParts = $rlFilters;
$rlWhereParts[] = "(rl.rl_lease_id IS NULL OR rl.file_number IS NULL OR rl.file_number = '' OR rl.file_number = 'Pending')";
$rlWhereSql = 'WHERE ' . implode(' AND ', $rlWhereParts);

$rlSql = "SELECT COUNT(*) AS pending_cnt
        FROM rl_beneficiaries rb
        LEFT JOIN rl_land_registration rland ON rland.ben_id = rb.rl_ben_id
        LEFT JOIN (
          SELECT rl2.land_id, rl2.file_number, rl2.rl_lease_id
          FROM rl_lease rl2
          INNER JOIN (
            SELECT land_id, MAX(rl_lease_id) AS max_id
            FROM rl_lease
            GROUP BY land_id
          ) rlm ON rlm.land_id = rl2.land_id AND rlm.max_id = rl2.rl_lease_id
        ) rl ON rl.land_id = rland.land_id
        $rlWhereSql";

$rlPendingCnt = 0;
if ($rlStmt = mysqli_prepare($con, $rlSql)) {
  if ($rlTypes !== '') {
    mysqli_stmt_bind_param($rlStmt, $rlTypes, ...$rlParams);
  }
  if (mysqli_stmt_execute($rlStmt)) {
    mysqli_stmt_bind_result($rlStmt, $rlPendingCnt);
    mysqli_stmt_fetch($rlStmt);
  }
  mysqli_stmt_close($rlStmt);
}

// Combined count
$res['pending_count'] = (int)$ltlPendingCnt + (int)$rlPendingCnt;
$res['ltl_pending'] = (int)$ltlPendingCnt;
$res['rl_pending'] = (int)$rlPendingCnt;
$res['success'] = true;
$res['message'] = 'OK';

} catch (Throwable $e) {
  $res['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($res);
