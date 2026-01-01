<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

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

$month = date('Y-m'); // current month

// Accept explicit month override (?month=YYYY-MM) for flexibility
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $month = $_GET['month'];
}

// location_id to filter by leases.location_id (not lease_payments.location_id)
$locParam = null;
if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
    $locParam = (int)$_GET['location_id'];
    if ($locParam <= 0) { $locParam = null; }
}

// IDOR Protection: If user has a location, they can only query their own location
if ($user_location_id !== '' && $locParam !== null && (string)$locParam !== (string)$user_location_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Build query joining leases so we only sum payments belonging to leases at given location
// Long Term Lease payments
$sql = "SELECT COALESCE(SUM(lp.amount),0) AS monthly_amount, COUNT(DISTINCT lp.lease_id) AS lease_count
        FROM lease_payments lp
        INNER JOIN leases l ON lp.lease_id = l.lease_id
        WHERE lp.status=1 AND DATE_FORMAT(lp.payment_date,'%Y-%m') = ?";
        
$types = 's';
$params = [$month];

if ($locParam !== null) {
    $sql .= " AND l.location_id = ?";
    $types .= 'i';
    $params[] = $locParam;
}

$ltlAmount = 0.0; $ltlCount = 0;
if ($stmt = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $ltlAmount, $ltlCount);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

// Residential Lease payments
$rlSql = "SELECT COALESCE(SUM(rlp.amount),0) AS monthly_amount, COUNT(DISTINCT rlp.lease_id) AS lease_count
          FROM rl_lease_payments rlp
          INNER JOIN rl_lease rl ON rlp.lease_id = rl.rl_lease_id
          WHERE rlp.status=1 AND DATE_FORMAT(rlp.payment_date,'%Y-%m') = ?";

$rlTypes = 's';
$rlParams = [$month];

if ($locParam !== null) {
    $rlSql .= " AND rl.location_id = ?";
    $rlTypes .= 'i';
    $rlParams[] = $locParam;
}

$rlAmount = 0.0; $rlCount = 0;
if ($rlStmt = mysqli_prepare($con, $rlSql)) {
    mysqli_stmt_bind_param($rlStmt, $rlTypes, ...$rlParams);
    mysqli_stmt_execute($rlStmt);
    mysqli_stmt_bind_result($rlStmt, $rlAmount, $rlCount);
    mysqli_stmt_fetch($rlStmt);
    mysqli_stmt_close($rlStmt);
}

// Combined totals
$monthlyAmount = (float)$ltlAmount + (float)$rlAmount;
$leaseCount = (int)$ltlCount + (int)$rlCount;

echo json_encode([
  'success' => true,
  'month' => $month,
  'location_id' => $locParam,
  'amount' => round($monthlyAmount, 2),
  'lease_count' => $leaseCount,
  'ltl_amount' => round((float)$ltlAmount, 2),
  'rl_amount' => round((float)$rlAmount, 2)
]);
