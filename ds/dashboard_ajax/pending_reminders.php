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

  // Same remind date expression used in long_term_lease.php
  $remindExpr = "DATE(
    DATE_ADD(
        MAKEDATE(YEAR(CURDATE()), 1)
        + INTERVAL (MONTH(l.start_date) - 1) MONTH
        + INTERVAL (DAY(l.start_date) - 1) DAY
        - INTERVAL 1 MONTH,
        INTERVAL (YEAR(CURDATE()) - YEAR(
            MAKEDATE(YEAR(CURDATE()), 1)
            + INTERVAL (MONTH(l.start_date) - 1) MONTH
            + INTERVAL (DAY(l.start_date) - 1) DAY
            - INTERVAL 1 MONTH
        )) YEAR
    )
  )";

  $filters = ['l.lease_id IS NOT NULL'];
  $types = '';
  $params = [];
  if ($locParam !== null) {
    $filters[] = 'b.location_id = ?';
    $types .= 'i';
    $params[] = $locParam;
  }
  $whereSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

  $sql = "SELECT COUNT(*) AS pending_cnt
          FROM (
            SELECT 
              {$remindExpr} AS remind_date,
              (
                SELECT COUNT(*)
                FROM ltl_reminders lr
                WHERE lr.lease_id = l.lease_id
                  AND lr.reminders_type = 'Annexure 09'
                  AND lr.status = 1
                  AND lr.sent_date BETWEEN DATE_SUB({$remindExpr}, INTERVAL 45 DAY)
                      AND STR_TO_DATE(CONCAT(YEAR({$remindExpr}), '-12-31'), '%Y-%m-%d')
              ) AS annexure09_sent
            FROM beneficiaries b
            LEFT JOIN (
              SELECT l2.beneficiary_id, l2.start_date, l2.lease_id
              FROM leases l2
              INNER JOIN (
                SELECT beneficiary_id, MAX(lease_id) AS max_id
                FROM leases
                GROUP BY beneficiary_id
              ) lm ON lm.beneficiary_id = l2.beneficiary_id AND lm.max_id = l2.lease_id
            ) l ON l.beneficiary_id = b.ben_id
            $whereSql
          ) x
          WHERE x.annexure09_sent = 0
            AND x.remind_date <= CURDATE()";

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

  // Residential Lease pending reminders
  $rlRemindExpr = "DATE(
    DATE_ADD(
        MAKEDATE(YEAR(CURDATE()), 1)
        + INTERVAL (MONTH(rl.start_date) - 1) MONTH
        + INTERVAL (DAY(rl.start_date) - 1) DAY
        - INTERVAL 1 MONTH,
        INTERVAL (YEAR(CURDATE()) - YEAR(
            MAKEDATE(YEAR(CURDATE()), 1)
            + INTERVAL (MONTH(rl.start_date) - 1) MONTH
            + INTERVAL (DAY(rl.start_date) - 1) DAY
            - INTERVAL 1 MONTH
        )) YEAR
    )
  )";

  $rlFilters = ['rl.rl_lease_id IS NOT NULL'];
  $rlTypes = '';
  $rlParams = [];
  if ($locParam !== null) {
    $rlFilters[] = 'rb.location_id = ?';
    $rlTypes .= 'i';
    $rlParams[] = $locParam;
  }
  $rlWhereSql = $rlFilters ? 'WHERE ' . implode(' AND ', $rlFilters) : '';

  $rlSql = "SELECT COUNT(*) AS pending_cnt
          FROM (
            SELECT 
              {$rlRemindExpr} AS remind_date,
              (
                SELECT COUNT(*)
                FROM rl_reminders rr
                WHERE rr.lease_id = rl.rl_lease_id
                  AND rr.reminders_type = 'Annexure 09'
                  AND rr.status = 1
                  AND rr.sent_date BETWEEN DATE_SUB({$rlRemindExpr}, INTERVAL 45 DAY)
                      AND STR_TO_DATE(CONCAT(YEAR({$rlRemindExpr}), '-12-31'), '%Y-%m-%d')
              ) AS annexure09_sent
            FROM rl_beneficiaries rb
            LEFT JOIN rl_land_registration rland ON rland.ben_id = rb.rl_ben_id
            LEFT JOIN rl_lease rl ON rl.land_id = rland.land_id
            $rlWhereSql
          ) x
          WHERE x.annexure09_sent = 0
            AND x.remind_date <= CURDATE()";

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
