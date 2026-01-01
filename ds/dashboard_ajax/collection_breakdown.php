<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Colombo');

$response = [
  'success' => false,
  'location_id' => null,
  'types' => [],
  'total' => 0,
  'message' => ''
];

try {
  $locParam = null;
  if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
    $locParam = (int)$_GET['location_id'];
    if ($locParam <= 0) { $locParam = null; }
  }
  $response['location_id'] = $locParam;

  $filters = [];
  $typesStr = '';
  $params = [];
  if ($locParam !== null) {
    $filters[] = 'b.location_id = ?';
    $typesStr .= 'i';
    $params[] = $locParam;
  }
  $whereSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

  // Long Term Lease types
  $sql = "SELECT 
            IFNULL(l.type_of_project, 'Unknown') AS lease_type,
            COUNT(*) AS total_cnt
          FROM leases l
          INNER JOIN (
            SELECT beneficiary_id, MAX(lease_id) AS max_id
            FROM leases
            GROUP BY beneficiary_id
          ) lm ON lm.max_id = l.lease_id
          INNER JOIN beneficiaries b ON b.ben_id = l.beneficiary_id
          $whereSql
          GROUP BY lease_type
          ORDER BY lease_type";

  if ($stmt = mysqli_prepare($con, $sql)) {
    if ($typesStr !== '') {
      mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
    }
    if (mysqli_stmt_execute($stmt)) {
      mysqli_stmt_bind_result($stmt, $leaseType, $totalCnt);
      while (mysqli_stmt_fetch($stmt)) {
        $name = ($leaseType !== null && $leaseType !== '') ? $leaseType : 'Unknown';
        $count = (int)$totalCnt;
        $response['types'][] = ['name' => $name, 'count' => $count];
        $response['total'] += $count;
      }
    }
    mysqli_stmt_close($stmt);
  }

  // Residential Lease count (as a separate type)
  $rlFilters = [];
  $rlTypesStr = '';
  $rlParams = [];
  if ($locParam !== null) {
    $rlFilters[] = 'rb.location_id = ?';
    $rlTypesStr .= 'i';
    $rlParams[] = $locParam;
  }
  $rlWhereSql = $rlFilters ? 'WHERE ' . implode(' AND ', $rlFilters) : '';

  $rlSql = "SELECT COUNT(*) AS total_cnt
            FROM rl_lease rl
            INNER JOIN rl_land_registration rland ON rl.land_id = rland.land_id
            INNER JOIN rl_beneficiaries rb ON rland.ben_id = rb.rl_ben_id
            INNER JOIN (
              SELECT land_id, MAX(rl_lease_id) AS max_id
              FROM rl_lease
              GROUP BY land_id
            ) rlm ON rlm.max_id = rl.rl_lease_id
            $rlWhereSql";

  $rlCount = 0;
  if ($rlStmt = mysqli_prepare($con, $rlSql)) {
    if ($rlTypesStr !== '') {
      mysqli_stmt_bind_param($rlStmt, $rlTypesStr, ...$rlParams);
    }
    if (mysqli_stmt_execute($rlStmt)) {
      mysqli_stmt_bind_result($rlStmt, $rlCount);
      mysqli_stmt_fetch($rlStmt);
    }
    mysqli_stmt_close($rlStmt);
  }

  // Add Residential Lease as a type
  if ((int)$rlCount > 0) {
    $response['types'][] = ['name' => 'Residential Lease', 'count' => (int)$rlCount];
    $response['total'] += (int)$rlCount;
  }

  $response['success'] = true;
  $response['message'] = 'OK';

} catch (Throwable $e) {
  $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
