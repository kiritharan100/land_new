<?php
include '../db.php';
include '../auth.php';

// Accept either plain lease_id (legacy) or encrypted token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (function_exists('decrypt_id')) {
        $dec = decrypt_id($token);
        if ($dec === false) { echo 'Invalid token'; exit; }
        $lease_id = intval($dec);
    } else {
        echo 'Token decryption not available'; exit;
    }
} elseif (isset($_GET['lease_id'])) {
    $lease_id = intval($_GET['lease_id']);
} else {
    echo 'Missing lease_id';
    exit;
}

// Get lease details + beneficiary location and contact + land info
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
                            FROM leases l
                            LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
                            LEFT JOIN ltl_land_registration land ON l.land_id = land.land_id
                            LEFT JOIN client_registration cr ON land.ds_id = cr.c_id
                            LEFT JOIN gn_division gn ON land.gn_id = gn.gn_id
                            WHERE l.lease_id = ?";
$stmt = $con->prepare($lease_sql);
$stmt->bind_param("i", $lease_id);
$stmt->execute();
$lease = $stmt->get_result()->fetch_assoc();

// Get schedules
$schedule_sql = "SELECT 
                          schedule_id, lease_id, schedule_year, due_date, start_date, end_date,
                          base_amount, annual_amount, discount_apply,
                          premium, premium_paid,
                          panalty, paid_rent, total_paid, panalty_paid,
                          revision_number, is_revision_year, penalty_rate, status, created_on,
                          penalty_last_calc, penalty_updated_by, penalty_remarks
                      FROM lease_schedules
                      WHERE lease_id = ?
                      ORDER BY schedule_year";
$stmt = $con->prepare($schedule_sql);
$stmt->bind_param("i", $lease_id);
$stmt->execute();
$schedules_result = $stmt->get_result();
$schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);

// Get payments
$payment_sql = "SELECT lp.*, YEAR(lp.payment_date) as payment_year, ls.schedule_year as allocated_year
                FROM lease_payments lp
                LEFT JOIN lease_schedules ls ON lp.schedule_id = ls.schedule_id
                WHERE lp.lease_id = ? and lp.status = 1
                ORDER BY lp.payment_date DESC";
$stmt = $con->prepare($payment_sql);
$stmt->bind_param("i", $lease_id);
$stmt->execute();
$payments = $stmt->get_result();

// Render full HTML print page
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Print Schedule - <?= htmlspecialchars($lease['lease_number'] ?? '') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    @page {
        size: A4 landscape;
        margin: 6mm;
    }

    html,
    body {
        width: 99%;
        height: 100%;
        margin: 0;
        padding: 8px;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    h1 {
        font-size: 16px;
        margin: 0;
    }

    .meta {
        font-size: 11px;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 6px;
    }

    table,
    th,
    td {
        border: 1px solid #333;
    }

    th,
    td {
        padding: 4px 6px;
        font-size: 11px;
        line-height: 1.1;
    }

    thead th {
        background: #f5f5f5;
    }

    tr {
        page-break-inside: avoid;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    .no-print {
        display: none;
    }

    .controls {
        margin-bottom: 6px;
    }

    .lease-info {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 6px 0 10px;
    }

    .lease-info td {
        border: none;
        padding: 3px 6px;
        font-size: 10px;
        vertical-align: top;
        line-height: 1.2;
    }

    .lease-info .field {
        white-space: normal;
    }

    .lease-info .label {
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #444;
        display: inline-block;
        margin-right: 4px;
    }

    .lease-info .value {
        font-weight: 600;
        color: #000;
    }

    .lease-info .value span {
        font-weight: 400;
    }

    @media print {
        .controls {
            display: none;
        }
    }
    </style>
</head>

<body>
    <div class="controls">
        <button onclick="window.print();" style="padding:6px 10px;">Print</button>
        <button onclick="window.close();" style="padding:6px 10px;">Close</button>
    </div>


    <?php
        // Prepare compact info rows
        $valuation_amount = isset($lease['valuation_amount']) && $lease['valuation_amount'] !== ''
            ? (float)$lease['valuation_amount']
            : null;
        $annual_rent_percentage = isset($lease['annual_rent_percentage']) && $lease['annual_rent_percentage'] !== ''
            ? (float)$lease['annual_rent_percentage']
            : null;
        $annual_rent_amount = null;
        if ($valuation_amount !== null && $annual_rent_percentage !== null) {
            $annual_rent_amount = $valuation_amount * ($annual_rent_percentage / 100);
        }
        $type_of_project = $lease['type_of_project'] ?? '';
        $project_name = $lease['name_of_the_project'] ?? '';
        $lease_status = $lease['lease_status'] ?? $lease['status'] ?? '';
        $lease_number = $lease['lease_number'] ?? '';
        $ds_division = $lease['ds_division_name'] ?? '';
        $gn_division = $lease['gn_division_name'] ?? '';
        $contact = $lease['beneficiary_phone'] ?? '';
        $lease_holder = $lease['beneficiary_name'] ?? '';
        $ben_address = $lease['beneficiary_address'] ?? '';
        $land_address = $lease['land_address'] ?? '';
        $approved_date = $lease['approved_date'] ?? '';
        $lease_start_date = $lease['start_date'] ?? '';
        $file_no = $lease['file_number'] ?? '';
    ?>
    <div align='center'>
        <h3>Lease Schedule</h3>
    </div>
    <table class="lease-info">
        <tr>
            <td class="field"><span class="label">Lease Number</span><span
                    class="value"><?= htmlspecialchars($lease['lease_number'] ?? '-') ?></span></td>
            <td class="field"><span class="label">File No:</span><span class="value">
                    <?= htmlspecialchars($file_no ?: '-') ?></span></td>
            <td class="field"><span class="label">Approved Date</span><span
                    class="value"><?= htmlspecialchars($approved_date ?: '-') ?></span></td>
            <td class="field"><span class="label">Start Date</span><span
                    class="value"><?= htmlspecialchars($lease_start_date ?: '-') ?></span></td>
        </tr>
        <tr>
            <td class="field"><span class="label">Lease Holder</span><span
                    class="value"><?= htmlspecialchars($lease_holder ?: '-') ?></span></td>
            <td class="field"><span class="label"> </span><span class="value"> </span></td>
            <td class="field"><span class="label">Contact Number</span><span
                    class="value"><?= htmlspecialchars($contact ?: '-') ?></span></td>
            <td class="field"><span class="label">Lessee Address</span><span
                    class="value"><?= htmlspecialchars($ben_address ?: '-') ?></span></td>
        </tr>
        <tr>
            <td class="field"><span class="label">Land Address</span><span
                    class="value"><?= htmlspecialchars($land_address ?: '-') ?></span></td>
            <td class="field"><span class="label">Valuation Amount</span><span
                    class="value"><?= $valuation_amount !== null ? number_format($valuation_amount, 2) : '-' ?></span>
            </td>
            <td class="field"><span class="label">Annual Rent</span><span
                    class="value"><?= $annual_rent_amount !== null ? number_format($annual_rent_amount, 2) : '-' ?></span>
            </td>
            <td class="field"><span class="label">Land DS Division</span><span
                    class="value"><?= htmlspecialchars($ds_division ?: '-') ?></span></td>
        </tr>
        <tr>
            <td class="field"><span class="label">GN Division</span><span
                    class="value"><?= htmlspecialchars($gn_division ?: '-') ?></span></td>
            <td class="field"><span class="label">Type of Project</span><span
                    class="value"><?= htmlspecialchars($type_of_project ?: '-') ?></span></td>
            <td class="field"><span class="label">Name of the Project</span><span
                    class="value"><?= htmlspecialchars($project_name ?: '-') ?></span></td>
            <td class="field"><span class="label">Lease Status</span><span
                    class="value"><?= htmlspecialchars($lease_status ?: '-') ?></span></td>
        </tr>
    </table>

    <!-- <h3>Payment Schedule</h3> -->
    <!-- <hr> -->
    <?php
            // Determine conditional columns like the Schedule tab
            $showPremiumCols = false; $showDiscountCol = false;
            if (!empty($lease['start_date']) && strtotime($lease['start_date']) < strtotime('2020-01-01')) {
                $showPremiumCols = true;
            }
            foreach ($schedules as $sc) {
                if (!empty($sc['discount_apply']) && (float)$sc['discount_apply'] > 0) { $showDiscountCol = true; break; }
            }
        ?>
    <table>
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th class="text-center">Start Date</th>
                <th class="text-center">End Date</th>
                <?php if ($showPremiumCols): ?>
                <th class="text-center">Premium</th>
                <th class="text-center">Premium Paid</th>
                <th class="text-center">Premium Balance</th>
                <?php endif; ?>
                <th class="text-center">Annual Lease</th>
                <th class="text-center">Paid Rent</th>
                <?php if ($showDiscountCol): ?>
                <th class="text-center">Discount</th>
                <?php endif; ?>
                <th class="text-center">Balance Rent payable</th>
                <th class="text-center">Penalty</th>
                <th class="text-center">Penalty Paid</th>
                <th class="text-center">Balance Penalty payable</th>
                <th class="text-center">Total Payment</th>
                <th class="text-center">Total Outstanding</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $count = 1;
                $prev_balance_rent = 0.0;
                $prev_balance_penalty = 0.0;
                $prev_premium_balance_cumulative = 0.0;
        $today = date('Y-m-d');
        foreach ($schedules as $schedule) {
            $paid_rent = isset($schedule['paid_rent']) ? (float)$schedule['paid_rent'] : 0.0;
                        $annual_amount = isset($schedule['annual_amount']) ? (float)$schedule['annual_amount'] : 0.0;
                        $discount_applied = isset($schedule['discount_apply']) ? (float)$schedule['discount_apply'] : 0.0;
                        // Adjust annual due by discount for this schedule
                        $effective_annual_due = $annual_amount - $discount_applied;
                        $balance_rent = $prev_balance_rent + $effective_annual_due - $paid_rent;
            $prev_balance_rent = $balance_rent;

            $penalty_total = isset($schedule['panalty']) ? (float)$schedule['panalty'] : 0.0;
            $penalty_paid = isset($schedule['panalty_paid']) ? (float)$schedule['panalty_paid'] : 0.0;
                        $balance_penalty = $prev_balance_penalty + $penalty_total - $penalty_paid;
            $prev_balance_penalty = $balance_penalty;

                        $premium_val = isset($schedule['premium']) ? (float)$schedule['premium'] : 0.0;
                        $premium_paid = isset($schedule['premium_paid']) ? (float)$schedule['premium_paid'] : 0.0;
                        if ($showPremiumCols) {
                            $prev_premium_balance_cumulative += ($premium_val - $premium_paid);
                        }

                        $total_payment = $paid_rent + $penalty_paid + ($showPremiumCols ? $premium_paid : 0.0);
                        // Total Outstanding can be negative; include premium cumulative if shown
                        $total_outstanding = $balance_rent + $balance_penalty + ($showPremiumCols ? $prev_premium_balance_cumulative : 0.0);

            if ($schedule['end_date'] < $today) {
                $status_class = 'paid';
            } else {
                $status_class = '';
            }
            ?>
            <tr class="<?= $status_class ?>">
                <td class="text-center"><?= $count ?></td>
                <td class="text-center"><?= htmlspecialchars($schedule['start_date']) ?></td>
                <td class="text-center"><?= htmlspecialchars($schedule['end_date']) ?></td>
                <?php if ($showPremiumCols): ?>
                <td class="text-right"><?= number_format($premium_val, 2) ?></td>
                <td class="text-right"><?= number_format($premium_paid, 2) ?></td>
                <td class="text-right"><?= number_format($prev_premium_balance_cumulative, 2) ?></td>
                <?php endif; ?>
                <td class="text-right"><?= number_format($annual_amount, 2) ?></td>
                <td class="text-right"><?= number_format($paid_rent, 2) ?></td>
                <?php if ($showDiscountCol): ?>
                <td class="text-right"><?= number_format($discount_applied, 2) ?></td>
                <?php endif; ?>
                <td class="text-right"><?= number_format($balance_rent, 2) ?></td>
                <td class="text-right"><?= number_format($penalty_total, 2) ?></td>
                <td class="text-right"><?= number_format($penalty_paid, 2) ?></td>
                <td class="text-right"><?= number_format($balance_penalty, 2) ?></td>
                <td class="text-right"><strong><?= number_format($total_payment, 2) ?></strong></td>
                <td class="text-right"><strong><?= number_format($total_outstanding, 2) ?></strong></td>
            </tr>
            <?php
            $count++;
        }
        ?>
        </tbody>
    </table>

    <h3>Payment History</h3>
    <table style='width:40%; important;'>
        <thead>
            <tr>
                <th>Date</th>
                <th>Receipt No</th>
                <th>Amount</th>


            </tr>
        </thead>
        <tbody>
            <?php if ($payments->num_rows == 0): ?>
            <tr>
                <td colspan="8" class="text-center">No payments recorded</td>
            </tr>
            <?php else: while ($payment = $payments->fetch_assoc()): ?>
            <tr>
                <td align='center'><?= htmlspecialchars($payment['payment_date']) ?></tda>
                <td><?= htmlspecialchars($payment['reference_number']) ?></td>

                <td class="text-right"><?= number_format($payment['amount'], 2) ?></td>


            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>

    <script>
    // Auto print after small delay to allow styles to apply
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 250);
    };
    </script>
</body>

</html>