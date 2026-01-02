<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

// Accept encrypted token or plain lease_id
if (isset($_GET['token'])) {
    if (!function_exists('decrypt_id')) { echo 'Token decryption not available'; exit; }
    $dec = decrypt_id($_GET['token']);
    if ($dec === false) { echo 'Invalid token'; exit; }
    $lease_id = (int)$dec;
} elseif (isset($_GET['lease_id'])) {
    $lease_id = (int)$_GET['lease_id'];
} else {
    echo 'Missing lease_id';
    exit;
}

// Lease + beneficiary + land info
$lease_sql = "SELECT 
    l.*,
    ben.name AS beneficiary_name,
    ben.address AS beneficiary_address,
    ben.telephone AS beneficiary_phone,
    ben.district AS beneficiary_district,
    land.land_address,
    land.ds_id,
    land.gn_id,
    COALESCE(cr.client_name, ben.ds_division_text) AS ds_division_name,
    COALESCE(gn.gn_name, ben.gn_division_text) AS gn_division_name
    FROM rl_lease l
    LEFT JOIN rl_land_registration land ON l.land_id = land.land_id
    LEFT JOIN rl_beneficiaries ben ON land.ben_id = ben.rl_ben_id
    LEFT JOIN client_registration cr ON land.ds_id = cr.c_id
    LEFT JOIN gn_division gn ON land.gn_id = gn.gn_id
    WHERE l.rl_lease_id = ?";
$stmt = $con->prepare($lease_sql);
$stmt->bind_param('i', $lease_id);
$stmt->execute();
$lease = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lease) { echo 'Lease not found'; exit; }

// Schedules
$schedules = [];
if ($stS = $con->prepare('SELECT * FROM rl_lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
    $stS->bind_param('i', $lease_id);
    $stS->execute();
    $rs = $stS->get_result();
    $schedules = $rs->fetch_all(MYSQLI_ASSOC);
    $stS->close();
}

$valuation_amount = isset($lease['valuation_amount']) && $lease['valuation_amount'] !== '' ? (float)$lease['valuation_amount'] : null;
$annual_pct = isset($lease['annual_rent_percentage']) && $lease['annual_rent_percentage'] !== '' ? (float)$lease['annual_rent_percentage'] : null;
$annual_rent_amount = ($valuation_amount !== null && $annual_pct !== null) ? $valuation_amount * ($annual_pct / 100) : null;
$lease_status = $lease['lease_status'] ?? ($lease['status'] ?? '');
$lease_number = $lease['lease_number'] ?? '';
$file_no = $lease['file_number'] ?? '';
$contact = $lease['beneficiary_phone'] ?? '';
$lease_holder = $lease['beneficiary_name'] ?? '';
$ben_address = $lease['beneficiary_address'] ?? '';
$land_address = $lease['land_address'] ?? '';
$approved_date = $lease['approved_date'] ?? '';
$lease_start_date = $lease['start_date'] ?? '';
$lease_end_date = $lease['end_date'] ?? '';
$ds_division = $lease['ds_division_name'] ?? '';
$gn_division = $lease['gn_division_name'] ?? '';
$type_of_project = $lease['type_of_project'] ?? '';
$project_name = $lease['name_of_the_project'] ?? '';

// Show premium column only for first lease
$showPremiumCols = ((int)$lease['is_it_first_ease'] === 1);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Lease Schedule - <?= htmlspecialchars($lease_number) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    @page { size: A4 landscape; margin: 6mm; }
    body { font-family: Arial, Helvetica, sans-serif; color:#000; margin:0; padding:8px; }
    table { border-collapse: collapse; width:100%; margin-bottom:8px; }
    table, th, td { border: 1px solid #333; }
    th, td { padding: 4px 6px; font-size: 11px; line-height:1.1; }
    thead th { background:#f5f5f5; }
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    .controls { margin-bottom:6px; }
    .lease-info { width:100%; border-collapse: collapse; table-layout: fixed; margin: 6px 0 10px; }
    .lease-info td { border:none; padding:3px 6px; font-size:10px; vertical-align: top; line-height:1.2; }
    .lease-info .label { font-weight:600; letter-spacing:0.4px; color:#444; margin-right:4px; }
    .lease-info .value { font-weight:600; color:#000; }
    @media print { .controls { display:none; } }
    .current-row { background:#8DF78D !important; }
    .old-row { background:#FCEADC !important; }
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="window.print();" style="padding:6px 10px;">Print</button>
        <button onclick="window.close();" style="padding:6px 10px;">Close</button>
    </div>

    <div align="center"><h3>Lease Payment Schedule</h3></div>
    <table class="lease-info">
        <tr>
            <td><span class="label">Lease Number</span><span class="value"><?= htmlspecialchars($lease_number ?: '-') ?></span></td>
            <td><span class="label">File No</span><span class="value"><?= htmlspecialchars($file_no ?: '-') ?></span></td>
            <td><span class="label">Approved Date</span><span class="value"><?= htmlspecialchars($approved_date ?: '-') ?></span></td>
            <td><span class="label">Start Date</span><span class="value"><?= htmlspecialchars($lease_start_date ?: '-') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">Lease Holder</span><span class="value"><?= htmlspecialchars($lease_holder ?: '-') ?></span></td>
            <td><span class="label">Contact</span><span class="value"><?= htmlspecialchars($contact ?: '-') ?></span></td>
            <td><span class="label">Lessee Address</span><span class="value"><?= htmlspecialchars($ben_address ?: '-') ?></span></td>
            <td><span class="label">Land Address</span><span class="value"><?= htmlspecialchars($land_address ?: '-') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">Valuation Amount</span><span class="value"><?= $valuation_amount !== null ? number_format($valuation_amount, 2) : '-' ?></span></td>
            <td><span class="label">Annual Rent</span><span class="value"><?= $annual_rent_amount !== null ? number_format($annual_rent_amount, 2) : '-' ?></span></td>
            <td><span class="label">Type of Project</span><span class="value"><?= htmlspecialchars($type_of_project ?: '-') ?></span></td>
            <td><span class="label">Project Name</span><span class="value"><?= htmlspecialchars($project_name ?: '-') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">DS Division</span><span class="value"><?= htmlspecialchars($ds_division ?: '-') ?></span></td>
            <td><span class="label">GN Division</span><span class="value"><?= htmlspecialchars($gn_division ?: '-') ?></span></td>
            <td><span class="label">Lease Status</span><span class="value"><?= htmlspecialchars($lease_status ?: '-') ?></span></td>
            <td><span class="label">End Date</span><span class="value"><?= htmlspecialchars($lease_end_date ?: '-') ?></span></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th class="text-center">Start</th>
                <th class="text-center">End</th>
                <?php if ($showPremiumCols): ?>
                <th class="text-center">Premium</th>
                <th class="text-center">Premium Paid</th>
                <th class="text-center">Premium Bal</th>
                <?php endif; ?>
                <th class="text-center">Annual Lease</th>
                <th class="text-center">Paid Rent</th>
                <th class="text-center">Rent Bal</th>
                <th class="text-center">Penalty</th>
                <th class="text-center">Penalty Paid</th>
                <th class="text-center">Penalty Bal</th>
                <th class="text-center">Total Paid</th>
                <th class="text-center">Total Outst</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $count = 1;
            $prev_balance_rent = 0.0;
            $prev_balance_penalty = 0.0;
            $prev_premium_balance = 0.0;
            $today = date('Y-m-d');
            foreach ($schedules as $schedule):
                $paid_rent = (float)$schedule['paid_rent'];
                $annual = (float)$schedule['annual_amount'];
                $discount = (float)$schedule['discount_apply'];

                $effective_due = $annual - $discount;
                $balance_rent = $prev_balance_rent + ($effective_due - $paid_rent);
                $prev_balance_rent = $balance_rent;

                $penalty = (float)$schedule['panalty'];
                $penalty_paid = (float)$schedule['panalty_paid'];
                $balance_penalty = $prev_balance_penalty + ($penalty - $penalty_paid);
                $prev_balance_penalty = $balance_penalty;

                $premium = (float)$schedule['premium'];
                $premium_paid = (float)$schedule['premium_paid'];
                if ($showPremiumCols) {
                    $prev_premium_balance += ($premium - $premium_paid);
                }

                $total_payment = $paid_rent + $penalty_paid + ($showPremiumCols ? $premium_paid : 0);
                $total_outstanding = $balance_rent + $balance_penalty + ($showPremiumCols ? $prev_premium_balance : 0);

                $rowClass = '';
                $statusText = '';
                $isCurrent = ($schedule['start_date'] <= $today && $schedule['end_date'] >= $today);

                if ($isCurrent) {
                    $statusText = 'Current';
                    $rowClass = 'current-row';
                } else {
                    if ($schedule['end_date'] < $today && $total_outstanding <= 0) {
                        $statusText = 'Settled';
                        $rowClass = 'old-row';
                    } elseif ($schedule['end_date'] < $today && $total_outstanding > 0) {
                        $statusText = 'Overdue';
                        $rowClass = 'old-row';
                    } else {
                        $statusText = 'Pending';
                    }
                }
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="text-center"><?= $count++ ?></td>
                <td class="text-center"><?= htmlspecialchars($schedule['start_date']) ?></td>
                <td class="text-center"><?= htmlspecialchars($schedule['end_date']) ?></td>
                <?php if ($showPremiumCols): ?>
                <td class="text-right"><?= number_format($premium, 2) ?></td>
                <td class="text-right"><?= number_format($premium_paid, 2) ?></td>
                <td class="text-right"><?= number_format($prev_premium_balance, 2) ?></td>
                <?php endif; ?>
                <td class="text-right"><?= number_format($annual, 2) ?></td>
                <td class="text-right"><?= number_format($paid_rent, 2) ?></td>
                <td class="text-right"><?= number_format($balance_rent, 2) ?></td>
                <td class="text-right"><?= number_format($penalty, 2) ?></td>
                <td class="text-right"><?= number_format($penalty_paid, 2) ?></td>
                <td class="text-right"><?= number_format($balance_penalty, 2) ?></td>
                <td class="text-right"><?= number_format($total_payment, 2) ?></td>
                <td class="text-right"><?= number_format($total_outstanding, 2) ?></td>
                <td class="text-center"><?= htmlspecialchars($statusText) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    window.onload = function() { setTimeout(function(){ window.print(); }, 200); };
    </script>
</body>
</html>
