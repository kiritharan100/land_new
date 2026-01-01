<?php
// Residential Lease Recovery Letter generation
require_once dirname(__DIR__, 3) . '/db.php';
require_once dirname(__DIR__, 3) . '/auth.php';
header('Content-Type: text/html; charset=utf-8');

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$as_at = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date
$as_at_safe = preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_at) ? $as_at : date('Y-m-d');

// Outstanding as at +30 days
$outstanding_date = date('Y-m-d', strtotime($as_at_safe . ' +30 days'));

$ben = $land = $lease = $client = null;
$error = '';

if ($md5 === '') {
    $error = 'Missing beneficiary reference.';
}

if (!$error) {
    if ($stmt = mysqli_prepare($con, 'SELECT rl_ben_id, name, name_tamil, name_sinhala, address, address_tamil, address_sinhala FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['rl_ben_id'];

            if ($st2 = mysqli_prepare($con, 'SELECT * FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);

                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                    $land_id = (int)$land['land_id'];

                    if ($st3 = mysqli_prepare($con, 'SELECT * FROM rl_lease WHERE land_id=? ORDER BY rl_lease_id DESC LIMIT 1')) {
                        mysqli_stmt_bind_param($st3, 'i', $land_id);
                        mysqli_stmt_execute($st3);
                        $r3 = mysqli_stmt_get_result($st3);

                        if ($r3 && ($lease = mysqli_fetch_assoc($r3))) {
                            // lease loaded
                        }
                        mysqli_stmt_close($st3);
                    }
                }
                mysqli_stmt_close($st2);
            }
        } else {
            $error = 'Invalid beneficiary.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Client info (DS Division)
$client_md5 = isset($_COOKIE['client_cook']) ? $_COOKIE['client_cook'] : '';
if ($client_md5) {
    $qClient = mysqli_query(
        $con,
        "SELECT client_name, bank_and_branch, account_number, account_name, client_email
         FROM client_registration
         WHERE md5_client='" . mysqli_real_escape_string($con, $client_md5) . "' LIMIT 1"
    );
    if ($qClient && mysqli_num_rows($qClient) === 1) {
        $client = mysqli_fetch_assoc($qClient);
    }
}

/* ===================================================
   OUTSTANDING CALCULATION AS AT (selected date + 30 days)
   =================================================== */

$rent_outstanding = 0.0;
$penalty_outstanding = 0.0;
$premium_outstanding = 0.0;
$total_outstanding = 0.0;

if ($lease && isset($lease['rl_lease_id'])) {
    $lid = (int)$lease['rl_lease_id'];

    // 1) DUE amounts up to outstanding_date
    $rent_due_total = 0.0;
    $penalty_due_total = 0.0;
    $premium_due_total = 0.0;

    if ($stD = mysqli_prepare(
        $con,
        "SELECT start_date, annual_amount, panalty, premium
         FROM rl_lease_schedules
         WHERE lease_id=? AND status=1 AND start_date <= ?
         ORDER BY start_date, schedule_id"
    )) {
        mysqli_stmt_bind_param($stD, 'is', $lid, $outstanding_date);
        mysqli_stmt_execute($stD);
        $resD = mysqli_stmt_get_result($stD);
        if ($resD) {
            while ($rowD = mysqli_fetch_assoc($resD)) {
                $rent_due_total += (float)$rowD['annual_amount'];
                $penalty_due_total += (float)$rowD['panalty'];
                $premium_due_total += (float)$rowD['premium'];
            }
        }
        mysqli_stmt_close($stD);
    }

    // 2) PAID as at outstanding_date
    $rent_paid_total = 0.0;
    $discount_total = 0.0;
    $penalty_paid_total = 0.0;
    $premium_paid_total = 0.0;

    if ($stP = mysqli_prepare(
        $con,
        "SELECT payment_date, rent_paid, current_year_payment,
                panalty_paid, discount_apply, premium_paid
         FROM rl_lease_payments
         WHERE lease_id=? AND status=1 AND payment_date <= ?"
    )) {
        mysqli_stmt_bind_param($stP, 'is', $lid, $outstanding_date);
        mysqli_stmt_execute($stP);
        $resP = mysqli_stmt_get_result($stP);
        if ($resP) {
            while ($rowP = mysqli_fetch_assoc($resP)) {
                $rent_paid_total += (float)$rowP['rent_paid'] + (float)$rowP['current_year_payment'];
                $discount_total += (float)$rowP['discount_apply'];
                $penalty_paid_total += (float)$rowP['panalty_paid'];
                $premium_paid_total += (float)$rowP['premium_paid'];
            }
        }
        mysqli_stmt_close($stP);
    }

    // 3) OUTSTANDING
    $rent_outstanding = max(0, $rent_due_total - $rent_paid_total - $discount_total);
    $penalty_outstanding = max(0, $penalty_due_total - $penalty_paid_total);
    $premium_outstanding = max(0, $premium_due_total - $premium_paid_total);

    $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;
}

/* ===================================================
   Number to words helper
   =================================================== */
function numToWords($num) {
    $num = (float)$num;
    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
    return ucfirst($f->format((int)$num));
}

$lease_number = $lease['lease_number'] ?? '-';
$file_number = $lease['file_number'] ?? '-';
$benName = $ben['name'] ?? '';
$benAddress = $ben['address'] ?? '';

// Check if beneficiary name exists
if (empty(trim($benName))) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Error</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head><body>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Beneficiary Name Missing',
            text: 'The beneficiary name has not been entered. Please update the beneficiary details before generating this letter.',
            confirmButtonText: 'Close'
        }).then(function() { window.close(); });
    </script>
    </body></html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recovery Letter - Residential Lease <?= h($benName) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; padding: 30px; }
        p { text-align: justify; margin: 0 0 12px; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .section { margin-top: 15px; }
        .header { margin-bottom: 20px; }
        table.details { border-collapse: collapse; width: 60%; margin: 15px auto; }
        table.details th, table.details td { border: 1px solid #000; padding: 6px 10px; text-align: right; }
        table.details th { background: #f0f0f0; text-align: left; }
        @media print {
            body { padding: 20px; }
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div style="color:red; font-weight:bold;"><?= h($error) ?></div>
    <?php else: ?>
        <div class="header right">
            File No: <?= h($file_number) ?><br>
            Date: <?= date('d/m/Y') ?>
        </div>

        <p class="bold">RECOVERY LETTER - RESIDENTIAL LEASE</p>

        <p>
            <?= h($benName) ?><br>
            <?= nl2br(h($benAddress)) ?>
        </p>

        <p class="section">Dear Sir/Madam,</p>

        <p>
            <u>Re: Lease No. <?= h($lease_number) ?> - Outstanding Lease Rent Payment</u>
        </p>

        <p class="section">
            We wish to inform you that the following amounts are outstanding for the residential lease granted to you/your institution as at <?= date('d/m/Y', strtotime($outstanding_date)) ?>:
        </p>

        <table class="details">
            <tr>
                <th>Rent Outstanding</th>
                <td>Rs. <?= number_format($rent_outstanding, 2) ?></td>
            </tr>
            <?php if ($penalty_outstanding > 0): ?>
            <tr>
                <th>Penalty Outstanding</th>
                <td>Rs. <?= number_format($penalty_outstanding, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($premium_outstanding > 0): ?>
            <tr>
                <th>Premium Outstanding</th>
                <td>Rs. <?= number_format($premium_outstanding, 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr style="font-weight: bold; background: #f8f8f8;">
                <th>Total Outstanding</th>
                <td>Rs. <?= number_format($total_outstanding, 2) ?></td>
            </tr>
        </table>

        <p class="section">
            You are kindly requested to settle the above outstanding amount within one month from the date of this letter. Failure to do so may result in the cancellation of the lease and recovery proceedings.
        </p>

        <p class="section">
            If you have already made the payment, please disregard this notice and provide proof of payment to our office.
        </p>

        <?php if ($client && $client['bank_and_branch'] && $client['account_number'] && $client['account_name']): ?>
        <p class="section">
            <strong>Bank Details for Payment:</strong><br>
            Bank and Branch: <?= h($client['bank_and_branch']) ?><br>
            Account Number: <?= h($client['account_number']) ?><br>
            Account Name: <?= h($client['account_name']) ?>
        </p>
        <?php endif; ?>

        <br><br>

        <p>
            Yours faithfully,<br><br><br>
            Divisional Secretary
        </p>
    <?php endif; ?>
</body>
</html>

