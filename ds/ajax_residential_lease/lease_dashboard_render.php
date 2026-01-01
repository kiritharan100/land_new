<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$ben = null; $land = null; $lease = null; $error = '';
$land_rl = null; // Land info from rl_land_registration

if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, name, address, district, ds_division_id, ds_division_text, gn_division_id, gn_division_text, nic_reg_no, nationality, telephone, email, language FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['rl_ben_id'];
            
            // First: Find land by beneficiary, then find lease by land_id (same as main page)
            if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                    $land_id = (int)$land['land_id'];
                    if ($st3 = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);
                        if ($r3) { $lease = mysqli_fetch_assoc($r3); }
                        mysqli_stmt_close($st3);
                    }
                }
                mysqli_stmt_close($st2);
            }
            
            // Fallback: Try to find lease directly by beneficiary_id
            if (!$lease) {
                if ($stL = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE beneficiary_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                    mysqli_stmt_bind_param($stL, 'i', $ben_id);
                    mysqli_stmt_execute($stL);
                    $rL = mysqli_stmt_get_result($stL);
                    if ($rL) { $lease = mysqli_fetch_assoc($rL); }
                    mysqli_stmt_close($stL);
                }
            }

            // Always try to fetch detailed land info from rl_land_registration
            if ($st6 = mysqli_prepare($con, 'SELECT l.land_id, l.ds_id, cr.client_name AS ds_name, l.gn_id, gn.gn_name AS gn_name, l.land_address, l.sketch_plan_no, l.plc_plan_no, l.survey_plan_no, l.extent_ha
                             FROM rl_land_registration l
                             LEFT JOIN client_registration cr ON l.ds_id = cr.c_id
                             LEFT JOIN gn_division gn ON l.gn_id = gn.gn_id
                             WHERE l.ben_id = ?
                             ORDER BY l.land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st6, 'i', $ben_id);
                mysqli_stmt_execute($st6);
                $r6 = mysqli_stmt_get_result($st6);
                if ($r6) { $land_rl = mysqli_fetch_assoc($r6); }
                mysqli_stmt_close($st6);
            }

            // Error messaging: only if neither lease nor land info exists
            if (!$lease && !$land_rl) { $error = 'Land information pending.'; }
        } else { $error = 'Invalid beneficiary reference.'; }
        mysqli_stmt_close($stmt);
    }
} else { $error = 'Missing beneficiary id.'; }

// Outstanding calculations
$rent_outstanding = 0.0; $penalty_outstanding = 0.0; $premium_outstanding = 0.0; $total_outstanding = 0.0;
$next_schedule = null; $next_payment_amount = 0.0; $next_discount_amount = 0.0; $next_discount_deadline = '';
$schedule_stats = ['total' => 0, 'completed' => 0];
$payment_stats = ['count' => 0, 'total_paid' => 0.0];
$payment_mix = ['rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0, 'discount' => 0.0];

if ($lease && isset($lease['rl_lease_id'])) {
    $lid = (int)$lease['rl_lease_id'];
    
    // Rent due (start_date <= today) minus all paid
    $sqlRentDue = "SELECT COALESCE(SUM(annual_amount - COALESCE(discount_apply,0)),0) AS due_rent FROM rl_lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
    $sqlRentPaid = "SELECT COALESCE(SUM(paid_rent),0) AS paid_rent_all FROM rl_lease_schedules WHERE lease_id=?";
    $due_rent = 0; $paid_rent_all = 0;
    if ($st = mysqli_prepare($con, $sqlRentDue)) { mysqli_stmt_bind_param($st, 'i', $lid); mysqli_stmt_execute($st); $r = mysqli_stmt_get_result($st); if ($r && ($rw = mysqli_fetch_assoc($r))) $due_rent = (float)$rw['due_rent']; mysqli_stmt_close($st); }
    if ($st = mysqli_prepare($con, $sqlRentPaid)) { mysqli_stmt_bind_param($st, 'i', $lid); mysqli_stmt_execute($st); $r = mysqli_stmt_get_result($st); if ($r && ($rw = mysqli_fetch_assoc($r))) $paid_rent_all = (float)$rw['paid_rent_all']; mysqli_stmt_close($st); }
    $rent_outstanding = max(0, $due_rent - $paid_rent_all);

    // Penalty outstanding
    $sqlPenDue = "SELECT COALESCE(SUM(panalty),0) AS due_penalty FROM rl_lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
    $sqlPenPaid = "SELECT COALESCE(SUM(panalty_paid),0) AS paid_penalty_all FROM rl_lease_schedules WHERE lease_id=?";
    $due_penalty = 0; $paid_penalty_all = 0;
    if ($st = mysqli_prepare($con, $sqlPenDue)) { mysqli_stmt_bind_param($st, 'i', $lid); mysqli_stmt_execute($st); $r = mysqli_stmt_get_result($st); if ($r && ($rw = mysqli_fetch_assoc($r))) $due_penalty = (float)$rw['due_penalty']; mysqli_stmt_close($st); }
    if ($st = mysqli_prepare($con, $sqlPenPaid)) { mysqli_stmt_bind_param($st, 'i', $lid); mysqli_stmt_execute($st); $r = mysqli_stmt_get_result($st); if ($r && ($rw = mysqli_fetch_assoc($r))) $paid_penalty_all = (float)$rw['paid_penalty_all']; mysqli_stmt_close($st); }
    $penalty_outstanding = max(0, $due_penalty - $paid_penalty_all);

    // Premium outstanding
    $sqlPremDue = "SELECT COALESCE(SUM(premium),0) AS due_premium FROM rl_lease_schedules WHERE lease_id=? AND start_date <= CURDATE()";
    $sqlPremPaid = "SELECT COALESCE(SUM(premium_paid),0) AS paid_premium_all FROM rl_lease_schedules WHERE lease_id=?";
    $due_premium = 0; $paid_premium_all = 0;
    if ($st = mysqli_prepare($con, $sqlPremDue)) { mysqli_stmt_bind_param($st, 'i', $lid); mysqli_stmt_execute($st); $r = mysqli_stmt_get_result($st); if ($r && ($rw = mysqli_fetch_assoc($r))) $due_premium = (float)$rw['due_premium']; mysqli_stmt_close($st); }
    if ($st = mysqli_prepare($con, $sqlPremPaid)) { mysqli_stmt_bind_param($st, 'i', $lid); mysqli_stmt_execute($st); $r = mysqli_stmt_get_result($st); if ($r && ($rw = mysqli_fetch_assoc($r))) $paid_premium_all = (float)$rw['paid_premium_all']; mysqli_stmt_close($st); }
    $premium_outstanding = max(0, $due_premium - $paid_premium_all);

    $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;

    // Schedule stats & next schedule detection
    $schedules = [];
    if ($st = mysqli_prepare($con, 'SELECT schedule_id, schedule_year, start_date, end_date, annual_amount, discount_apply, paid_rent, panalty, panalty_paid, premium, premium_paid FROM rl_lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs) { $schedules = mysqli_fetch_all($rs, MYSQLI_ASSOC); }
        mysqli_stmt_close($st);
    }
    $schedule_stats['total'] = count($schedules);
    $today = date('Y-m-d');
    foreach ($schedules as $sc) {
        if ($sc['end_date'] <= $today) { $schedule_stats['completed']++; }
        $rent_due_effective = (float)$sc['annual_amount'] - (float)$sc['discount_apply'];
        $rent_remaining = $rent_due_effective - (float)$sc['paid_rent'];
        $pen_remaining = (float)$sc['panalty'] - (float)$sc['panalty_paid'];
        $prem_remaining = (float)$sc['premium'] - (float)$sc['premium_paid'];
        if ($rent_remaining > 0 || $pen_remaining > 0 || $prem_remaining > 0) {
            $next_schedule = $sc;
            $next_payment_amount = max(0, $rent_remaining) + max(0, $pen_remaining) + max(0, $prem_remaining);
            $next_discount_amount = (float)$sc['discount_apply'];
            if (!empty($sc['start_date'])) { $next_discount_deadline = date('Y-m-d', strtotime($sc['start_date'] . ' +30 days')); }
            break; // first upcoming unpaid schedule
        }
    }

    // Payment stats
    if ($st = mysqli_prepare($con, 'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total_paid FROM rl_lease_payments WHERE lease_id=?')) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        if ($r && ($rw = mysqli_fetch_assoc($r))) {
            $payment_stats['count'] = (int)$rw['cnt'];
            $payment_stats['total_paid'] = (float)$rw['total_paid'];
        }
        mysqli_stmt_close($st);
    }

    // Composition of active payments (status=1)
    if ($st = mysqli_prepare(
        $con,
        'SELECT 
             COALESCE(SUM(rent_paid),0) AS rent_paid_sum,
             COALESCE(SUM(panalty_paid),0) AS penalty_paid_sum,
             COALESCE(SUM(premium_paid),0) AS premium_paid_sum,
             COALESCE(SUM(discount_apply),0) AS discount_sum
         FROM rl_lease_payments
         WHERE lease_id=? AND status=1'
    )) {
        mysqli_stmt_bind_param($st, 'i', $lid);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        if ($r && ($rw = mysqli_fetch_assoc($r))) {
            $payment_mix['rent'] = (float)$rw['rent_paid_sum'];
            $payment_mix['penalty'] = (float)$rw['penalty_paid_sum'];
            $payment_mix['premium'] = (float)$rw['premium_paid_sum'];
            $payment_mix['discount'] = (float)$rw['discount_sum'];
        }
        mysqli_stmt_close($st);
    }
}

// Grant Payment Balance calculations
$grant_total_valuation = 0.0;
$grant_rent_premium_discount_paid = 0.0;
$grant_valuation_payments = 0.0;
$grant_balance_to_pay = 0.0;

if ($lease && isset($lease['rl_lease_id'])) {
    $lid = (int)$lease['rl_lease_id'];
    $grant_total_valuation = (float)($lease['valuation_amount'] ?? 0);
    
    // Sum of paid_rent + premium_paid + discount_apply from schedules
    $sql_grant = "SELECT 
        COALESCE(SUM(paid_rent), 0) AS total_rent_paid,
        COALESCE(SUM(premium_paid), 0) AS total_premium_paid,
        COALESCE(SUM(discount_apply), 0) AS total_discount
        FROM rl_lease_schedules 
        WHERE lease_id = ?";
    if ($stg = mysqli_prepare($con, $sql_grant)) {
        mysqli_stmt_bind_param($stg, 'i', $lid);
        mysqli_stmt_execute($stg);
        $rsg = mysqli_stmt_get_result($stg);
        if ($rowg = mysqli_fetch_assoc($rsg)) {
            $grant_rent_premium_discount_paid = (float)$rowg['total_rent_paid'] + (float)$rowg['total_premium_paid'] + (float)$rowg['total_discount'];
        }
        mysqli_stmt_close($stg);
    }
    
    // Valuation payments
    $sql_val = "SELECT COALESCE(SUM(amount), 0) AS total FROM rl_valuvation_paid WHERE rl_lease_id = ? AND status = 1";
    if ($stv = mysqli_prepare($con, $sql_val)) {
        mysqli_stmt_bind_param($stv, 'i', $lid);
        mysqli_stmt_execute($stv);
        $rsv = mysqli_stmt_get_result($stv);
        if ($rowv = mysqli_fetch_assoc($rsv)) {
            $grant_valuation_payments = (float)$rowv['total'];
        }
        mysqli_stmt_close($stv);
    }
    
    // Total paid towards grant = rent+premium+discount + valuation payments
    $grant_total_paid = $grant_rent_premium_discount_paid + $grant_valuation_payments;
    $grant_balance_to_pay = max(0, $grant_total_valuation - $grant_total_paid);
}
?>
<div class="card" id="rl-lease-dashboard-card">
    <div class="card-block" style="padding:1rem;">
        <?php if ($error): ?>
        <div class="alert alert-info mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($lease): ?>
        <!-- Outstanding Summary Bar -->
        <div class="mb-3" align='center'
            style="background:#fff;border:2px solid #dc3545;color:#dc3545;font-size:1.05rem;font-weight:600;padding:10px 12px;border-radius:6px;letter-spacing:0.5px;">
            <span style="font-weight:700;text-transform:uppercase;">Outstanding:</span>
            Premium: <?= number_format($premium_outstanding, 2) ?> &nbsp;|
            Penalty: <?= number_format($penalty_outstanding, 2) ?> &nbsp;|
            Rent: <?= number_format($rent_outstanding, 2) ?> &nbsp;|
            <span style="font-weight:800;">Total: <?= number_format($total_outstanding, 2) ?></span>
        </div>

        <div class="row">
            <!-- Beneficiary Information -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Beneficiary Information</strong></div>
                    <div class="card-block p-2" style="font-size:0.9rem;">
                        <?php if ($ben): ?>
                        <?php
                        $contact_name = $ben['name'] ?? '';
                        $ben_address = $ben['address'] ?? '';
                        $ben_district = $ben['district'] ?? '';
                        $ben_gn = $ben['gn_division_text'] ?? '';
                        // If gn_division_text empty but id present, try lookup
                        if (empty($ben_gn) && !empty($ben['gn_division_id'])) {
                            $gstmt = mysqli_prepare($con, 'SELECT gn_name FROM gn_division WHERE gn_id = ? LIMIT 1');
                            if ($gstmt) {
                                mysqli_stmt_bind_param($gstmt, 'i', $ben['gn_division_id']);
                                mysqli_stmt_execute($gstmt);
                                $gr = mysqli_stmt_get_result($gstmt);
                                if ($gr && ($grw = mysqli_fetch_assoc($gr))) { $ben_gn = $grw['gn_name']; }
                                mysqli_stmt_close($gstmt);
                            }
                        }
                        ?>
                        <div><strong>Name (Contact):</strong> <?= htmlspecialchars($contact_name) ?></div>
                        <div><strong>Address:</strong> <?= htmlspecialchars($ben_address ?: '-') ?></div>
                        <div><strong>District:</strong> <?= htmlspecialchars($ben_district ?: '-') ?></div>
                        <div><strong>GN Division:</strong> <?= htmlspecialchars($ben_gn ?: '-') ?></div>
                        <div><strong>Telephone:</strong> <?= htmlspecialchars($ben['telephone'] ?? '-') ?></div>
                        <div><strong>NIC/Reg No:</strong> <?= htmlspecialchars($ben['nic_reg_no'] ?? '-') ?></div>
                        <div><strong>Email:</strong> <?= htmlspecialchars($ben['email'] ?? '-') ?></div>
                        <div><strong>Language:</strong> <?= htmlspecialchars($ben['language'] ?? '-') ?></div>
                        <?php else: ?>
                        <div class="text-muted">Beneficiary information not available.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Composition Pie Chart -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Payment Composition (Active Payments)</strong></div>
                    <div class="card-block p-2">
                        <div id="rl-payments-pie" style="height:175px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Payment Balance for Grant Pie Chart -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Payment Balance for Grant</strong></div>
                    <div class="card-block p-2">
                        <div id="rl-grant-balance-pie" style="height:200px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Grant Summary</strong></div>
                    <div class="card-block p-2" style="font-size:0.9rem;">
                        <div><strong>Total Valuation:</strong> Rs. <?= number_format($grant_total_valuation, 2) ?></div>
                        <div><strong>Rent + Premium + Discount Paid:</strong> Rs. <?= number_format($grant_rent_premium_discount_paid, 2) ?></div>
                        <div><strong>Valuation Payments:</strong> Rs. <?= number_format($grant_valuation_payments, 2) ?></div>
                        <hr style="margin:8px 0;">
                        <div style="font-size:1.1rem;<?= $grant_balance_to_pay <= 0 ? 'color:#28a745;' : 'color:#dc3545;' ?>">
                            <strong>Balance to be Paid:</strong> Rs. <?= number_format($grant_balance_to_pay, 2) ?>
                            <?php if ($grant_balance_to_pay <= 0): ?> ✓<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Lease Information -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Lease Information</strong></div>
                    <div class="card-block p-2" style="font-size:0.85rem;">
                        <div><strong>Lease Number:</strong> <?= htmlspecialchars($lease['lease_number'] ?? '-') ?></div>
                        <div><strong>File Number:</strong> <?= htmlspecialchars($lease['file_number'] ?? '-') ?></div>
                        <div><strong>Start Date:</strong> <?= htmlspecialchars($lease['start_date'] ?? '-') ?></div>
                        <div><strong>End Date:</strong> <?= htmlspecialchars($lease['end_date'] ?? '-') ?></div>
                        <div><strong>Initial Annual Rent:</strong> Rs. <?= number_format($lease['initial_annual_rent'] ?? 0, 2) ?></div>
                        <div><strong>Premium:</strong> Rs. <?= number_format($lease['premium'] ?? 0, 2) ?></div>
                        <div><strong>Valuation Amount:</strong> Rs. <?= number_format($lease['valuation_amount'] ?? 0, 2) ?></div>
                        <div><strong>Discount Rate:</strong> <?= htmlspecialchars($lease['discount_rate'] ?? 0) ?>%</div>
                        <div><strong>Penalty Rate:</strong> <?= htmlspecialchars($lease['penalty_rate'] ?? 0) ?>%</div>
                        <div><strong>Status:</strong> 
                            <span class="badge badge-<?= ($lease['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($lease['status'] ?? '-') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Land Information -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Land Information</strong></div>
                    <div class="card-block p-2" style="font-size:0.85rem;">
                        <?php if ($land_rl): ?>
                        <div><strong>DS Division:</strong> <?= htmlspecialchars($land_rl['ds_name'] ?? '-') ?></div>
                        <div><strong>GN Division:</strong> <?= htmlspecialchars($land_rl['gn_name'] ?? '-') ?></div>
                        <div><strong>Land Address:</strong> <?= htmlspecialchars($land_rl['land_address'] ?? '-') ?></div>
                        <div><strong>Sketch Plan No:</strong> <?= htmlspecialchars($land_rl['sketch_plan_no'] ?? '-') ?></div>
                        <div><strong>PLC Plan No:</strong> <?= htmlspecialchars($land_rl['plc_plan_no'] ?? '-') ?></div>
                        <div><strong>Survey Plan No:</strong> <?= htmlspecialchars($land_rl['survey_plan_no'] ?? '-') ?></div>
                        <div><strong>Hectares:</strong> <?= htmlspecialchars($land_rl['extent_ha'] ?? '-') ?></div>
                        <?php else: ?>
                        <div class="text-muted">Land information pending.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule & Payment Stats -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Schedule Summary</strong></div>
                    <div class="card-block p-2" style="font-size:0.85rem;">
                        <div><strong>Total Schedules:</strong> <?= $schedule_stats['total'] ?></div>
                        <div><strong>Completed:</strong> <?= $schedule_stats['completed'] ?></div>
                        <div><strong>Remaining:</strong> <?= $schedule_stats['total'] - $schedule_stats['completed'] ?></div>
                        <?php if ($next_schedule): ?>
                        <hr style="margin:8px 0;">
                        <div><strong>Next Payment Due:</strong></div>
                        <div>Year: <?= htmlspecialchars($next_schedule['schedule_year']) ?></div>
                        <div>Amount: Rs. <?= number_format($next_payment_amount, 2) ?></div>
                        <?php if ($next_discount_deadline): ?>
                        <div>Discount Deadline: <?= htmlspecialchars($next_discount_deadline) ?></div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header p-2" style='padding-bottom: 0px;'><strong>Payment Summary</strong></div>
                    <div class="card-block p-2" style="font-size:0.85rem;">
                        <div><strong>Total Payments Made:</strong> <?= $payment_stats['count'] ?></div>
                        <div><strong>Total Amount Paid:</strong> Rs. <?= number_format($payment_stats['total_paid'], 2) ?></div>
                        <hr style="margin:8px 0;">
                        <div><strong>Rent Paid:</strong> Rs. <?= number_format($payment_mix['rent'], 2) ?></div>
                        <div><strong>Premium Paid:</strong> Rs. <?= number_format($payment_mix['premium'], 2) ?></div>
                        <div><strong>Penalty Paid:</strong> Rs. <?= number_format($payment_mix['penalty'], 2) ?></div>
                        <div><strong>Discount Applied:</strong> Rs. <?= number_format($payment_mix['discount'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="alert alert-warning">Lease not yet created. Outstanding and payment visuals will appear once a lease is generated.</div>
        <?php if ($land_rl): ?>
        <div class="card mt-3">
            <div class="card-header p-2"><strong>Land Information</strong></div>
            <div class="card-block p-2" style="font-size:0.85rem;">
                <div><strong>DS Division:</strong> <?= htmlspecialchars($land_rl['ds_name'] ?? '-') ?></div>
                <div><strong>GN Division:</strong> <?= htmlspecialchars($land_rl['gn_name'] ?? '-') ?></div>
                <div><strong>Land Address:</strong> <?= htmlspecialchars($land_rl['land_address'] ?? '-') ?></div>
                <div><strong>Sketch Plan No:</strong> <?= htmlspecialchars($land_rl['sketch_plan_no'] ?? '-') ?></div>
                <div><strong>PLC Plan No:</strong> <?= htmlspecialchars($land_rl['plc_plan_no'] ?? '-') ?></div>
                <div><strong>Survey Plan No:</strong> <?= htmlspecialchars($land_rl['survey_plan_no'] ?? '-') ?></div>
                <div><strong>Hectares:</strong> <?= htmlspecialchars($land_rl['extent_ha'] ?? '-') ?></div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    // Refresh button reloads dashboard via parent loader function
    var ref = document.getElementById('rl-lease-dashboard-refresh-btn');
    if (ref) {
        ref.addEventListener('click', function() {
            if (typeof window.loadRLDashboard === 'function') {
                window.loadRLDashboard(true);
            }
        });
    }
    
    // Pie chart
    if (typeof Highcharts === 'undefined') {
        var hc = document.createElement('script');
        hc.src = 'https://code.highcharts.com/highcharts.js';
        hc.onload = renderChart;
        hc.onerror = renderChart; // attempt anyway
        document.head.appendChild(hc);
    } else {
        renderChart();
    }

    function renderChart() {
        if (typeof Highcharts === 'undefined') return; // give up silently
        
        // Payment Composition Pie Chart
        var container = document.getElementById('rl-payments-pie');
        if (container) {
            Highcharts.chart('rl-payments-pie', {
                chart: {
                    type: 'pie',
                    backgroundColor: 'transparent'
                },
                title: {
                    text: null
                },
                tooltip: {
                    pointFormat: '<b>{point.y:,.2f}</b>'
                },
                credits: {
                    enabled: false
                },
                plotOptions: {
                    pie: {
                        dataLabels: {
                            enabled: true,
                            format: '{point.name}: {point.y:,.0f}'
                        }
                    }
                },
                series: [{
                    name: 'Payments',
                    colorByPoint: true,
                    data: [
                        { name: 'Premium Paid', y: <?= json_encode($payment_mix['premium']) ?> },
                        { name: 'Penalty Paid', y: <?= json_encode($payment_mix['penalty']) ?> },
                        { name: 'Rent Paid', y: <?= json_encode($payment_mix['rent']) ?> },
                        { name: 'Discount Applied', y: <?= json_encode($payment_mix['discount']) ?> }
                    ]
                }]
            });
        }
        
        // Grant Balance Pie Chart
        var grantContainer = document.getElementById('rl-grant-balance-pie');
        if (grantContainer) {
            var grantPaid = <?= json_encode($grant_rent_premium_discount_paid + $grant_valuation_payments) ?>;
            var grantBalance = <?= json_encode($grant_balance_to_pay) ?>;
            
            Highcharts.chart('rl-grant-balance-pie', {
                chart: {
                    type: 'pie',
                    backgroundColor: 'transparent'
                },
                title: {
                    text: null
                },
                tooltip: {
                    pointFormat: '<b>Rs. {point.y:,.2f}</b>'
                },
                credits: {
                    enabled: false
                },
                plotOptions: {
                    pie: {
                        dataLabels: {
                            enabled: true,
                            format: '{point.name}: Rs. {point.y:,.0f}'
                        }
                    }
                },
                series: [{
                    name: 'Grant Balance',
                    colorByPoint: true,
                    data: [
                        { name: 'Paid (Rent+Premium+Discount)', y: grantPaid, color: '#28a745' },
                        { name: 'Balance to be Paid', y: grantBalance, color: '#dc3545' }
                    ]
                }]
            });
        }
    }
})();
</script>
