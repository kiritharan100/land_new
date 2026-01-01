<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

// Correct timezone function
date_default_timezone_set('Asia/Colombo');

// Location filter
$locParam = null;
if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
    $locParam = (int)$_GET['location_id'];
    if ($locParam <= 0) { $locParam = null; }
}

// Build months array (oldest first, last 12 months including current)
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $months[] = ['ym' => $ym, 'label' => $label];
}

$amounts = [];
$typesBase = 's';

foreach ($months as $m) {
    // Long Term Lease payments
    $sql = "SELECT COALESCE(SUM(lp.amount),0) AS amt
            FROM lease_payments lp
            INNER JOIN leases l ON lp.lease_id = l.lease_id
            WHERE lp.status=1 AND DATE_FORMAT(lp.payment_date,'%Y-%m') = ?";
    $types = $typesBase; $params = [$m['ym']];
    if ($locParam !== null) {
        $sql .= " AND l.location_id = ?";
        $types .= 'i';
        $params[] = $locParam;
    }
    $ltlAmt = 0.0;
    if ($st = mysqli_prepare($con,$sql)) {
        mysqli_stmt_bind_param($st,$types,...$params);
        if (mysqli_stmt_execute($st)) {
            mysqli_stmt_bind_result($st,$ltlAmt);
            mysqli_stmt_fetch($st);
        }
        mysqli_stmt_close($st);
    }

    // Residential Lease payments
    $rlSql = "SELECT COALESCE(SUM(rlp.amount),0) AS amt
              FROM rl_lease_payments rlp
              INNER JOIN rl_lease rl ON rlp.lease_id = rl.rl_lease_id
              WHERE rlp.status=1 AND DATE_FORMAT(rlp.payment_date,'%Y-%m') = ?";
    $rlTypes = $typesBase; $rlParams = [$m['ym']];
    if ($locParam !== null) {
        $rlSql .= " AND rl.location_id = ?";
        $rlTypes .= 'i';
        $rlParams[] = $locParam;
    }
    $rlAmt = 0.0;
    if ($rlSt = mysqli_prepare($con,$rlSql)) {
        mysqli_stmt_bind_param($rlSt,$rlTypes,...$rlParams);
        if (mysqli_stmt_execute($rlSt)) {
            mysqli_stmt_bind_result($rlSt,$rlAmt);
            mysqli_stmt_fetch($rlSt);
        }
        mysqli_stmt_close($rlSt);
    }

    // Combined total
    $totalAmt = (float)$ltlAmt + (float)$rlAmt;
    $amounts[] = round($totalAmt, 2);
}

// If all amounts are null, signal failure
$allNull = true; foreach ($amounts as $a){ if ($a !== null){ $allNull = false; break; } }
echo json_encode([
    'success' => !$allNull,
    'location_id' => $locParam,
    'months' => array_column($months,'label'),
    'amounts' => $amounts,
    'message' => $allNull ? 'Query preparation failed' : 'OK'
]);
