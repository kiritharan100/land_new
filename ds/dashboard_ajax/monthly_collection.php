<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

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

// Build query joining leases so we only sum payments belonging to leases at given location
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

$monthlyAmount = 0.0; $leaseCount = 0;
if ($stmt = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $monthlyAmount, $leaseCount);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

echo json_encode([
  'success' => true,
  'month' => $month,
  'location_id' => $locParam,
  'amount' => round((float)$monthlyAmount, 2),
  'lease_count' => (int)$leaseCount
]);
