<?php include 'header.php'; ?>

?>
<style>
#submenu-list .list-group-item.active,
#submenu-list .list-group-item.active:focus,
#submenu-list .list-group-item.active:hover {
    background-color: #7a7d81 !important;
    border-color: #3c3e40 !important;
    color: #ffffff !important;
}

#submenu-list .list-group-item:hover {
    color: inherit;
    text-decoration: none;
}

#submenu-list .list-group-item:focus {
    box-shadow: 0 0 0 0.15rem rgba(122, 125, 129, 0.35);
    outline: none;
}
</style>

<?php
$md5_ben_id = $_GET['id'] ?? '';
$ben = null;
$land = null;
$lease = null;

function valOrPending($v){
    return trim($v) !== "" ? htmlspecialchars($v) : "<span style='color:red;font-weight:bold;'>Pending</span>";
}

if (!empty($md5_ben_id) && isset($con)) {
    $sql = "SELECT 
              b.rl_ben_id,
              b.location_id,
              b.name,
              b.address,
              b.district,
              COALESCE(cr.client_name, b.ds_division_text) AS ds_division,
              COALESCE(gn.gn_name, b.gn_division_text) AS gn_division,
              b.nic_reg_no,
              b.telephone,
              b.language,
              b.dob
            FROM rl_beneficiaries b
            LEFT JOIN client_registration cr ON b.ds_division_id = cr.c_id
            LEFT JOIN gn_division gn ON b.gn_division_id = gn.gn_id
            WHERE b.md5_ben_id = ?
            LIMIT 1";

    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $md5_ben_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ben = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
    if ($ben && isset($location_id) && $location_id !== '' && (string)$ben['location_id'] !== (string)$location_id) {
        echo "<br><br><br><br><div align='center' class='alert alert-danger'>You do not have permission to view this lease.</div>";
        include 'footer.php';
        exit;
    }

    if ($ben) {
        $ben_id = (int) $ben['rl_ben_id'];

        $land_q = "SELECT land_id, ben_id, land_address FROM rl_land_registration WHERE ben_id = ? ORDER BY land_id DESC LIMIT 1";
        if ($st2 = mysqli_prepare($con, $land_q)) {
            mysqli_stmt_bind_param($st2, 'i', $ben_id);
            mysqli_stmt_execute($st2);
            $r2 = mysqli_stmt_get_result($st2);
            $land = mysqli_fetch_assoc($r2);
            mysqli_stmt_close($st2);
        }

        if ($land && isset($land['land_id'])) {
            $lease_q = "SELECT * FROM rl_lease WHERE land_id = ? ORDER BY rl_lease_id DESC LIMIT 1";
            if ($st3 = mysqli_prepare($con, $lease_q)) {
                $land_id_int = (int) $land['land_id'];
                mysqli_stmt_bind_param($st3, 'i', $land_id_int);
                mysqli_stmt_execute($st3);
                $r3 = mysqli_stmt_get_result($st3);
                $lease = mysqli_fetch_assoc($r3);
                mysqli_stmt_close($st3);
            }
        }
    }
}

$lease_id = $lease['rl_lease_id'] ?? 0;

// Check if lease is inactive
$inactive_date = $lease['inactive_date'] ?? '';
$lease_status_raw = $lease['lease_status'] ?? ($lease['status'] ?? '');
$status_text = is_string($lease_status_raw) ? strtolower(trim($lease_status_raw)) : $lease_status_raw;
$is_inactive = false;
if (!empty($inactive_date)) { $is_inactive = true; }
if (is_numeric($lease_status_raw) && intval($lease_status_raw) === 0) { $is_inactive = true; }
if (is_string($status_text) && in_array($status_text, ['inactive','cancelled','closed'], true)) { $is_inactive = true; }

// Check if grant is issued
$is_grant_issued = !empty($lease['outright_grants_date']);

// Set background color: grant issued takes priority over inactive
if ($is_grant_issued) {
    $overviewBg = '#9FF5A5';
} elseif ($is_inactive) {
    $overviewBg = '#F5C2B8';
} else {
    $overviewBg = '#ffffff';
}

// Get GN name for land if available
$land_gn_name = '';
if ($land && !empty($land['land_id'])) {
    $gn_sql = "SELECT gn.gn_name FROM rl_land_registration lr 
               LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id 
               WHERE lr.land_id = ? LIMIT 1";
    if ($gn_stmt = mysqli_prepare($con, $gn_sql)) {
        mysqli_stmt_bind_param($gn_stmt, 'i', $land['land_id']);
        mysqli_stmt_execute($gn_stmt);
        $gn_res = mysqli_stmt_get_result($gn_stmt);
        if ($gn_row = mysqli_fetch_assoc($gn_res)) {
            $land_gn_name = $gn_row['gn_name'] ?? '';
        }
        mysqli_stmt_close($gn_stmt);
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <br>
        <div class="col-md-12" style="padding-top:5px;background-color:<?= $overviewBg ?> !important;">

            <h5 class="font-weight-bold" style="margin-bottom:5px;">Residential Lease > Overview <?php if ($is_grant_issued): ?><span style="color:#155724;"> ** Outright Grants Issued</span><?php endif; ?></h5>
            
            <?php if ($is_inactive): ?>
            <div class="alert alert-warning" style="background:#F5C2B8;border-color:#e2a396;color:#7a2d21;">
                This lease is inactive from: <?= htmlspecialchars($inactive_date ?: 'N/A') ?>
                <?php if (!empty($lease['inactive_reason'])): ?>
                <br>Reason: <?= htmlspecialchars($lease['inactive_reason']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($ben): ?>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Beneficiary Name</th>
                            <th>Land Address</th>
                            <th>Land GN Division</th>
                            <th>Lease Number</th>
                            <th>File Number</th>
                            
                            <th>Beneficiary Income</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><?= valOrPending($ben['name'] ?? '') ?></td>
                            <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                var lease_id = <?= json_encode($lease_id) ?>;
                                if (lease_id > 0) {
                                    fetch("ajax_residential_lease/rl_cal_penalty.php?lease_id=" + lease_id)
                                        .then(response => response.text())
                                        .then(data => {
                                            console.log("RL Penalty Script Executed:", data);
                                        })
                                        .catch(error => console.error("Error:", error));
                                }
                            });
                            </script>
                            <td><?= valOrPending($land['land_address'] ?? '') ?></td>
                            <td><?= valOrPending($land_gn_name) ?></td>
                            <td><?= valOrPending($lease['lease_number'] ?? '') ?></td>
                            <td><?= valOrPending($lease['file_number'] ?? '') ?></td>
                             
                            <td><?= isset($lease['ben_income']) && $lease['ben_income'] !== '' ? 'Rs. ' . number_format((float)$lease['ben_income'], 2) : '<span style="color:red;font-weight:bold;">Pending</span>' ?></td>
                        </tr>
                    </tbody>

                </table>
            </div>

            <?php else: ?>
            <div class="alert alert-warning mb-0">Beneficiary not found or invalid link.</div>
            <?php endif; ?>

        </div>

        <div class="row no-gutters" style="margin-right:2px;">

            <div class="col-md-3 col-lg-2 bg-light border-right" style='margin-top:20px;'>
                <div class="p-3">
                    <div class="list-group" id="submenu-list">
                        <a href="#" class="list-group-item list-group-item-action active"
                            data-target="#land-dashboard">Lease Dashboard</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#land-tab">Land
                            Information</a>
                        <a href="#" class="list-group-item list-group-item-action"
                            data-target="#request_letter">Documents</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#create_leases">
                            <?php echo $lease_id > 0 ? 'Manage' : 'Create'; ?> Leases</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#ltl_schedule">Schedule
                            - Settlement</a>
                        <a href="#" class="list-group-item list-group-item-action"
                            data-target="#ltl_schedule_payment">Schedule - Payment</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#payment">Payment</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#payment_valuation">Payment for Grant</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#field_visits">Field
                            Visits</a>
                        <a href="#" class="list-group-item list-group-item-action"
                            data-target="#write-off">Write-Off</a>
                        <a href="#" class="list-group-item list-group-item-action" data-target="#tab3">Reminders</a>
                    </div>
                </div>
                <div style='width:100%;text-align:center;'>
                    <a href="residential_lease.php"> <button class="btn btn-success">
                            <i class="fa fa-arrow-left" aria-hidden="true"></i> Back to List
                        </button></a>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 bg-white" style='margin-top:20px;padding-top:5px;'>
                <div class="p-4" id="submenu-content">

                    <div id="land-tab" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Land Information</h5>
                        <hr>
                        <div id="land-tab-container" data-loaded="0"></div>
                    </div>

                    <div id="request_letter" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Documents</h5>
                        <hr>
                        <div id="docs-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="create_leases" class="submenu-section d-none">
                        <h5 class="font-weight-bold"><?php echo $lease_id > 0 ? 'Manage Lease' : 'Create Lease'; ?></h5>
                        <hr>
                        <div id="rl-create-lease-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="write-off" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Write-Off</h5>
                        <hr>
                        <div id="ltl-write-off-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="ltl_schedule" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Schedule</h5>
                        <hr>
                        <div id="ltl-schedule-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="ltl_schedule_payment" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Schedule - Payment Based</h5>
                        <hr>
                        <div id="ltl-schedule-payment-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="land-dashboard" class="submenu-section">
                        <h5 class="font-weight-bold">Lease Dashboard</h5>
                        <hr>
                        <div id="lease-dashboard-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="payment" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Payments</h5>
                        <hr>
                        <div id="ltl-payment-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="payment_valuation" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Payment for Grant</h5>
                        <hr>
                        <div id="rl-valuation-payment-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="field_visits" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Field Visits</h5>
                        <hr>
                        <div id="ltl-field-visit-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                    <div id="tab3" class="submenu-section d-none">
                        <h5 class="font-weight-bold">Reminders</h5>
                        <hr>
                        <div id="ltl-reminders-container" data-loaded="0">
                            <div style="text-align:center;padding:16px">
                                <img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<script>
window.RL_IS_GRANT_ISSUED = <?php echo json_encode($is_grant_issued); ?>;
(function() {
    var MD5_BEN_ID = <?php echo json_encode($md5_ben_id ?? ''); ?>;
    var LOADER_HTML = '<div style="text-align:center;padding:16px"><img src="../img/Loading_icon.gif" alt="Loading..." style="width:96px;height:auto" /></div>';

    function ensureLeafletLoaded(cb) {
        if (window.L && typeof window.L.map === 'function') { cb && cb(); return; }
        var haveCss = !!document.querySelector('link[href*="leaflet.css"]');
        if (!haveCss) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet/dist/leaflet.css';
            document.head.appendChild(link);
        }
        var script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet/dist/leaflet.js';
        script.onload = function() { cb && cb(); };
        script.onerror = function() { cb && cb(); };
        document.head.appendChild(script);
    }

    function executeScripts(container) {
        var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
        scripts.forEach(function(old) {
            var s = document.createElement('script');
            if (old.src) { s.src = old.src; s.async = false; }
            else { s.text = old.text || old.textContent || ''; }
            document.body.appendChild(s);
        });
    }

    function ensureDocsScriptLoaded(cb) {
        if (window.RLDocs && typeof window.RLDocs.init === 'function') { cb && cb(); return; }
        var s = document.createElement('script');
        s.src = 'ajax_residential_lease/docs_tab.js?_ts=' + Date.now();
        s.onload = function() { cb && cb(); };
        s.onerror = function() { cb && cb(); };
        document.head.appendChild(s);
    }

    function loadLandTabOnce() {
        var container = document.getElementById('land-tab-container');
        if (!container || container.getAttribute('data-loaded') === '1') return;
        container.innerHTML = '<div style="text-align:center;padding:16px"><img src="../img/Loading_icon.gif" alt="Loading..." style="width:248px;height:auto" /></div>';
        var url = 'ajax_residential_lease/tab_land_infomation_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        ensureLeafletLoaded(function() {
            fetch(url).then(r => r.text()).then(html => {
                container.innerHTML = html;
                try { executeScripts(container); } catch(e) {}
                container.setAttribute('data-loaded', '1');
            }).catch(() => container.innerHTML = '<div class="text-danger">Failed to load.</div>');
        });
    }

    function loadCreateLeaseTabOnce() {
        var cont = document.getElementById('rl-create-lease-container');
        if (!cont || cont.getAttribute('data-loaded') === '1') return;
        cont.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/create_lease_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            cont.innerHTML = html;
            try { executeScripts(cont); } catch(e) {}
            cont.setAttribute('data-loaded', '1');
        }).catch(() => cont.innerHTML = '<div class="text-danger">Failed to load.</div>');
    }

    window.loadRLDashboard = function(force) {
        var cont = document.getElementById('lease-dashboard-container');
        if (!cont) return;
        if (cont.getAttribute('data-loaded') === '1' && !force) return;
        cont.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/lease_dashboard_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            cont.innerHTML = html;
            try { executeScripts(cont); } catch(e) {}
            cont.setAttribute('data-loaded', '1');
        }).catch(() => cont.innerHTML = '<div class="text-danger">Failed to load dashboard.</div>');
    };

    function loadScheduleTab() {
        var sc = document.getElementById('ltl-schedule-container');
        if (!sc) return;
        sc.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/lease_schedule_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            sc.innerHTML = html;
            try { executeScripts(sc); } catch(e) {}
        }).catch(() => sc.innerHTML = '<div class="text-danger">Failed to load schedule.</div>');
    }

    function loadSchedulePaymentTab() {
        var spc = document.getElementById('ltl-schedule-payment-container');
        if (!spc) return;
        spc.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/lease_schedule_payment_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            spc.innerHTML = html;
            try { executeScripts(spc); } catch(e) {}
        }).catch(() => spc.innerHTML = '<div class="text-danger">Failed to load payment-based schedule.</div>');
    }

    function loadPaymentTab() {
        var pc = document.getElementById('ltl-payment-container');
        if (!pc) return;
        pc.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/payment_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            pc.innerHTML = html;
            try { executeScripts(pc); } catch(e) {}
        }).catch(() => pc.innerHTML = '<div class="text-danger">Failed to load payments.</div>');
    }

    function loadValuationPaymentTab() {
        var vpc = document.getElementById('rl-valuation-payment-container');
        if (!vpc) return;
        vpc.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/valuation_payment_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            vpc.innerHTML = html;
            try { executeScripts(vpc); } catch(e) {}
        }).catch(() => vpc.innerHTML = '<div class="text-danger">Failed to load valuation payments.</div>');
    }

    function loadFieldVisitsTab() {
        var fv = document.getElementById('ltl-field-visit-container');
        if (!fv) return;
        fv.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/field_visits_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            fv.innerHTML = html;
            try { executeScripts(fv); } catch(e) {}
        }).catch(() => fv.innerHTML = '<div class="text-danger">Failed to load field visits.</div>');
    }

    function loadRemindersTab() {
        var rem = document.getElementById('ltl-reminders-container');
        if (!rem) return;
        rem.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/reminders_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            rem.innerHTML = html;
            try { executeScripts(rem); } catch(e) {}
        }).catch(() => rem.innerHTML = '<div class="text-danger">Failed to load reminders.</div>');
    }

    function loadWriteOffTab() {
        var woc = document.getElementById('ltl-write-off-container');
        if (!woc) return;
        woc.innerHTML = LOADER_HTML;
        var url = 'ajax_residential_lease/write_off_tab_render.php?id=<?php echo htmlspecialchars($md5_ben_id ?? "", ENT_QUOTES); ?>&_ts=' + Date.now();
        fetch(url).then(r => r.text()).then(html => {
            woc.innerHTML = html;
            try { executeScripts(woc); } catch(e) {}
        }).catch(() => woc.innerHTML = '<div class="text-danger">Failed to load write-offs.</div>');
    }

    // Tab switching
    document.querySelectorAll('#submenu-list a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = this.getAttribute('data-target');
            if (!target) return;
            document.querySelectorAll('.submenu-section').forEach(sec => sec.classList.add('d-none'));
            document.querySelector(target)?.classList.remove('d-none');
            document.querySelectorAll('#submenu-list a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');

            if (target === '#land-tab') loadLandTabOnce();
            if (target === '#land-dashboard') window.loadRLDashboard(true);
            if (target === '#create_leases') loadCreateLeaseTabOnce();
            if (target === '#request_letter') ensureDocsScriptLoaded(() => window.RLDocs && window.RLDocs.init(MD5_BEN_ID));
            if (target === '#ltl_schedule') loadScheduleTab();
            if (target === '#ltl_schedule_payment') loadSchedulePaymentTab();
            if (target === '#payment') loadPaymentTab();
            if (target === '#payment_valuation') loadValuationPaymentTab();
            if (target === '#field_visits') loadFieldVisitsTab();
            if (target === '#write-off') loadWriteOffTab();
            if (target === '#tab3') loadRemindersTab();
        });
    });

    // Initial load for active tab
    var active = document.querySelector('#submenu-list a.active');
    if (active) {
        var t = active.getAttribute('data-target');
        if (t === '#land-tab') loadLandTabOnce();
        if (t === '#land-dashboard') window.loadRLDashboard(true);
        if (t === '#create_leases') loadCreateLeaseTabOnce();
        if (t === '#request_letter') ensureDocsScriptLoaded(() => window.RLDocs && window.RLDocs.init(MD5_BEN_ID));
        if (t === '#ltl_schedule') loadScheduleTab();
        if (t === '#payment') loadPaymentTab();
        if (t === '#field_visits') loadFieldVisitsTab();
        if (t === '#write-off') loadWriteOffTab();
        if (t === '#tab3') loadRemindersTab();
    }

    // Event listeners for refresh
    window.addEventListener('rl:payments-updated', function() {
        loadPaymentTab();
        loadSchedulePaymentTab();
        window.loadRLDashboard(true);
    });
    window.addEventListener('rl:schedule-updated', function() {
        loadScheduleTab();
        loadSchedulePaymentTab();
        window.loadRLDashboard(true);
    });
    window.addEventListener('rl:writeoff-updated', function() {
        loadWriteOffTab();
        loadScheduleTab();
        loadSchedulePaymentTab();
        window.loadRLDashboard(true);
    });
    window.addEventListener('rl:fieldvisits-updated', loadFieldVisitsTab);
    window.addEventListener('rl:reminders-updated', loadRemindersTab);
    window.addEventListener('rl:valuation-payments-updated', loadValuationPaymentTab);
})();
</script>

<?php include 'footer.php'; ?>
