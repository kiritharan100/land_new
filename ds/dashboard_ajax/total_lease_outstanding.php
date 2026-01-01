<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Colombo');

$res = [
  'success' => false,
  'location_id' => null,
  'as_at' => null,
  'rent_component' => 0.0,
  'penalty_component' => 0.0,
  'premium_component' => 0.0,
  'total_outstanding' => 0.0,
  'ltl_outstanding' => 0.0,
  'rl_outstanding' => 0.0,
  'message' => ''
];

/**
 * Calculate outstanding for a single LTL lease (same logic as long_term_lease.php)
 */
function compute_ltl_outstanding($con, $lease_id, $as_at) {
    $out = 0.0;
    if (!$lease_id) return $out;
    $lid = (int)$lease_id;
    
    $rent_due = $rent_paid = 0; 
    $pen_due = $pen_paid = 0; 
    $prem_due = $prem_paid = 0;
    
    // Rent Due (schedules where start_date <= as_at)
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) FROM lease_schedules WHERE lease_id=? AND start_date <= ?")) {
        mysqli_stmt_bind_param($st, 'is', $lid, $as_at);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $rent_due);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Rent Paid (all schedules)
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(paid_rent),0) FROM lease_schedules WHERE lease_id=?")) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $rent_paid);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Penalty Due
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(panalty),0) FROM lease_schedules WHERE lease_id=? AND start_date <= ?")) {
        mysqli_stmt_bind_param($st, 'is', $lid, $as_at);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $pen_due);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Penalty Paid
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(panalty_paid),0) FROM lease_schedules WHERE lease_id=?")) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $pen_paid);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Premium Due
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(premium),0) FROM lease_schedules WHERE lease_id=? AND start_date <= ?")) {
        mysqli_stmt_bind_param($st, 'is', $lid, $as_at);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $prem_due);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Premium Paid
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(premium_paid),0) FROM lease_schedules WHERE lease_id=?")) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $prem_paid);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    
    $rent_outstanding = max(0, $rent_due - $rent_paid);
    $pen_outstanding  = max(0, $pen_due - $pen_paid);
    $prem_outstanding = max(0, $prem_due - $prem_paid);
    
    return $rent_outstanding + $pen_outstanding + $prem_outstanding;
}

/**
 * Calculate outstanding for a single RL lease (same logic as residential_lease.php)
 */
function compute_rl_outstanding($con, $lease_id, $as_at) {
    $out = 0.0;
    if (!$lease_id) return $out;
    $lid = (int)$lease_id;
    
    $rent_due = $rent_paid = 0; 
    $pen_due = $pen_paid = 0; 
    $prem_due = $prem_paid = 0;
    
    // Rent Due
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) FROM rl_lease_schedules WHERE lease_id=? AND start_date <= ?")) {
        mysqli_stmt_bind_param($st, 'is', $lid, $as_at);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $rent_due);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Rent Paid
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(paid_rent),0) FROM rl_lease_schedules WHERE lease_id=?")) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $rent_paid);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Penalty Due
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(panalty),0) FROM rl_lease_schedules WHERE lease_id=? AND start_date <= ?")) {
        mysqli_stmt_bind_param($st, 'is', $lid, $as_at);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $pen_due);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Penalty Paid
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(panalty_paid),0) FROM rl_lease_schedules WHERE lease_id=?")) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $pen_paid);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Premium Due
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(premium),0) FROM rl_lease_schedules WHERE lease_id=? AND start_date <= ?")) {
        mysqli_stmt_bind_param($st, 'is', $lid, $as_at);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $prem_due);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    // Premium Paid
    if ($st = mysqli_prepare($con, "SELECT COALESCE(SUM(premium_paid),0) FROM rl_lease_schedules WHERE lease_id=?")) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $prem_paid);
        mysqli_stmt_fetch($st);
        mysqli_stmt_close($st);
    }
    
    $rent_outstanding = max(0, $rent_due - $rent_paid);
    $pen_outstanding  = max(0, $pen_due - $pen_paid);
    $prem_outstanding = max(0, $prem_due - $prem_paid);
    
    return $rent_outstanding + $pen_outstanding + $prem_outstanding;
}

try {
    $locParam = null;
    if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
        $locParam = (int)$_GET['location_id'];
        if ($locParam <= 0) { $locParam = null; }
    }
    $asAt = isset($_GET['as_at']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['as_at']) ? $_GET['as_at'] : date('Y-m-d');
    $leaseType = isset($_GET['lease_type']) ? $_GET['lease_type'] : 'All';
    $res['location_id'] = $locParam;
    $res['as_at'] = $asAt;

    // ==================== LTL OUTSTANDING ====================
    // Get all active LTL leases for this location and sum their individual outstanding
    $ltlFilters = ["l.status NOT IN ('canceled','Canceled','cancelled','Cancelled')"];
    $ltlTypes = '';
    $ltlParams = [];
    if ($locParam !== null) { 
        $ltlFilters[] = "l.location_id=?"; 
        $ltlTypes .= 'i'; 
        $ltlParams[] = $locParam; 
    }
    if ($leaseType !== 'All') { 
        $ltlFilters[] = "l.type_of_project=?"; 
        $ltlTypes .= 's'; 
        $ltlParams[] = $leaseType; 
    }
    $ltlFilterSql = implode(' AND ', $ltlFilters);
    
    $ltlTotalOutstanding = 0.0;
    $ltlSql = "SELECT l.lease_id FROM leases l WHERE $ltlFilterSql";
    
    if ($ltlTypes !== '') {
        $ltlStmt = mysqli_prepare($con, $ltlSql);
        mysqli_stmt_bind_param($ltlStmt, $ltlTypes, ...$ltlParams);
        mysqli_stmt_execute($ltlStmt);
        $ltlResult = mysqli_stmt_get_result($ltlStmt);
    } else {
        $ltlResult = mysqli_query($con, $ltlSql);
    }
    
    if ($ltlResult) {
        while ($row = mysqli_fetch_assoc($ltlResult)) {
            $ltlTotalOutstanding += compute_ltl_outstanding($con, $row['lease_id'], $asAt);
        }
        if (isset($ltlStmt)) { mysqli_stmt_close($ltlStmt); }
    }
    
    // ==================== RL OUTSTANDING ====================
    // Get all active RL leases for this location and sum their individual outstanding
    // RL location is determined by beneficiary's location_id
    $rlTotalOutstanding = 0.0;
    
    if ($locParam !== null) {
        // Filter by beneficiary's location
        $rlSql = "SELECT rl.rl_lease_id 
                  FROM rl_lease rl
                  INNER JOIN rl_land_registration rland ON rl.land_id = rland.land_id
                  INNER JOIN rl_beneficiaries rb ON rland.ben_id = rb.rl_ben_id
                  WHERE rb.location_id = ?";
        $rlStmt = mysqli_prepare($con, $rlSql);
        mysqli_stmt_bind_param($rlStmt, 'i', $locParam);
        mysqli_stmt_execute($rlStmt);
        $rlResult = mysqli_stmt_get_result($rlStmt);
    } else {
        // No location filter - get all RL leases
        $rlSql = "SELECT rl_lease_id FROM rl_lease";
        $rlResult = mysqli_query($con, $rlSql);
    }
    
    if ($rlResult) {
        while ($row = mysqli_fetch_assoc($rlResult)) {
            $rlTotalOutstanding += compute_rl_outstanding($con, $row['rl_lease_id'], $asAt);
        }
        if (isset($rlStmt)) { mysqli_stmt_close($rlStmt); }
    }
    
    // ==================== COMBINED TOTAL ====================
    $res['ltl_outstanding'] = round($ltlTotalOutstanding, 2);
    $res['rl_outstanding'] = round($rlTotalOutstanding, 2);
    $res['total_outstanding'] = round($ltlTotalOutstanding + $rlTotalOutstanding, 2);
    $res['success'] = true;
    $res['message'] = 'OK';

} catch (Throwable $e) {
    $res['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($res);
