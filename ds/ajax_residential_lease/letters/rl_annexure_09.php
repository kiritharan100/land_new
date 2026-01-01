<?php 
require_once dirname(__DIR__, 3) . '/db.php';
require_once dirname(__DIR__, 3) . '/auth.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$md5 = $_GET['id'] ?? '';
$as_at = $_GET['as_at_date'] ?? date('Y-m-d');
$as_at_safe = preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_at) ? $as_at : date('Y-m-d');
$outstanding_date = date('Y-m-d', strtotime($as_at_safe . ' +30 days'));

$benName = ''; $benAddress = '';
$lease = $land = null;
$lease_number = '-';
$file_number = '-';

if ($md5 !== '') {
    if ($st = mysqli_prepare($con, 'SELECT rl_ben_id, name, name_tamil, name_sinhala, address, address_tamil, address_sinhala FROM rl_beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($st, 's', $md5);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $ben_id = (int)($row['rl_ben_id'] ?? 0);
            
            if ($_REQUEST['language'] == "TA") {
                $benName = $row['name_tamil'] ?? $row['name'] ?? '';
                $benAddress = $row['address_tamil'] ?? $row['address'] ?? '';
            } else {
                $benName = $row['name_sinhala'] ?? $row['name'] ?? '';
                $benAddress = $row['address_sinhala'] ?? $row['address'] ?? '';
            }

            if ($ben_id > 0) {
                if ($st2 = mysqli_prepare($con, 'SELECT land_id FROM rl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
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
                        if ($lease) {
                            $lease_number = $lease['lease_number'] ?? '-';
                            $file_number = $lease['file_number'] ?? '-';
                        }
                    }
                    mysqli_stmt_close($st2);
                }
            }
        }
        mysqli_stmt_close($st);
    }
}

// Outstanding calculations
$rent_outstanding = 0.0; $penalty_outstanding = 0.0; $premium_outstanding = 0.0; $total_outstanding = 0.0;
$outstanding_only_rent = 0.0;

if ($lease && isset($lease['rl_lease_id'])) {
    $lid = (int)$lease['rl_lease_id'];
    $rent_due_total = 0.0; $penalty_due_total = 0.0; $premium_due_total = 0.0;
    
    if ($stD = mysqli_prepare($con, "SELECT start_date, annual_amount, panalty, premium FROM rl_lease_schedules WHERE lease_id=? AND status=1 AND start_date <= ? ORDER BY start_date, schedule_id")) {
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

    $rent_paid_total = 0.0; $discount_total = 0.0; $penalty_paid_total = 0.0; $premium_paid_total = 0.0;
    if ($stP = mysqli_prepare($con, "SELECT payment_date, rent_paid, current_year_payment, panalty_paid, discount_apply, premium_paid FROM rl_lease_payments WHERE lease_id=? AND status=1 AND payment_date <= ?")) {
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

    $rent_outstanding = max(0, $rent_due_total - $rent_paid_total - $discount_total);
    $penalty_outstanding = max(0, $penalty_due_total - $penalty_paid_total);
    $premium_outstanding = max(0, $premium_due_total - $premium_paid_total);

    $outstanding_only_rent = $rent_outstanding;
    $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;

    // Compute only current period (year) rent outstanding
    $curr_year = (int)date('Y', strtotime($as_at_safe));
    $prev_cutoff = ($curr_year - 1) . '-12-31';

    $prev_rent_due_total = 0.0; $prev_rent_paid_total = 0.0; $prev_discount_total = 0.0;
    if ($stD2 = mysqli_prepare($con, "SELECT annual_amount FROM rl_lease_schedules WHERE lease_id=? AND status=1 AND start_date <= ?")) {
        mysqli_stmt_bind_param($stD2, 'is', $lid, $prev_cutoff);
        mysqli_stmt_execute($stD2);
        $resD2 = mysqli_stmt_get_result($stD2);
        if ($resD2) {
            while ($rowD2 = mysqli_fetch_assoc($resD2)) {
                $prev_rent_due_total += (float)$rowD2['annual_amount'];
            }
        }
        mysqli_stmt_close($stD2);
    }
    if ($stP2 = mysqli_prepare($con, "SELECT rent_paid, current_year_payment, discount_apply FROM rl_lease_payments WHERE lease_id=? AND status=1 AND payment_date <= ?")) {
        mysqli_stmt_bind_param($stP2, 'is', $lid, $prev_cutoff);
        mysqli_stmt_execute($stP2);
        $resP2 = mysqli_stmt_get_result($stP2);
        if ($resP2) {
            while ($rowP2 = mysqli_fetch_assoc($resP2)) {
                $prev_rent_paid_total += (float)$rowP2['rent_paid'] + (float)$rowP2['current_year_payment'];
                $prev_discount_total += (float)$rowP2['discount_apply'];
            }
        }
        mysqli_stmt_close($stP2);
    }

    $prev_rent_outstanding = max(0, $prev_rent_due_total - $prev_rent_paid_total - $prev_discount_total);
    $current_year_rent_outstanding = max(0, $rent_outstanding - $prev_rent_outstanding);
    $outstanding_only_rent = $current_year_rent_outstanding;
}

$payment_shedule_start_year = date('Y', strtotime($as_at_safe));
$due_date = date('d/m/Y', strtotime($outstanding_date));

// Check if beneficiary name exists in selected language
if (empty(trim($benName))) {
    $langLabel = ($_REQUEST['language'] == "TA") ? 'Tamil' : 'Sinhala';
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Error</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head><body>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Beneficiary Name Missing',
            text: 'The beneficiary name in <?= $langLabel ?> has not been entered. Please update the beneficiary details before generating this letter.',
            confirmButtonText: 'Close'
        }).then(function() { window.close(); });
    </script>
    </body></html>
    <?php
    exit;
}
?>

<style>
@media print {
    .page-break {
        page-break-before: always;
        break-before: page;
    }
}

.outstanding-table {
    width: 90%;
    margin: 12px auto 0;
    border: 1px solid #000;
    border-collapse: collapse;
}

.outstanding-table th,
.outstanding-table td {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
}
</style>

<?php if($_REQUEST['language'] == "TA"){ ?>
<!DOCTYPE html>
<html lang="ta">
<head>
    <meta charset="UTF-8">
    <title>குத்தகை அறிவிப்பு கடிதம் <?= h($benName) ?></title>
    <style>
    @font-face {
        font-family: 'UniIlaSundaram';
        src: url('../../letters/Uni Ila.Sundaram-04.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    body {
        font-family: 'UniIlaSundaram', sans-serif;
        font-size: 17px;
        line-height: 1.5;
        padding: 20px;
    }
    p { text-align: justify; margin: 0 0 8px; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .section { margin-top: 12px; }
    </style>
</head>
<body>
    <div class="right">
        எமது இல. <?= $file_number ?> <br><br>
        திகதி : .................................
    </div>

    <p class="bold">இணைப்பு - 09</p>

    <p>
        <?= h($benName) ?> <br>
        <?= nl2br(h($benAddress)) ?>
    </p>

    <p class="section">ஜயா / அம்மணி</p>

    <p>
        <u><?= $lease_number ?> இலக்கத்திற்கு கொண்ட குத்தகைக்காக
            செலுதபடவேண்டியா குத்தகைபணம் <?= $payment_shedule_start_year ?> வருடம் </u>
    </p>

    <p class="section">
        தங்களுக்கு / தங்கன் ஸ்தாபனத்திற்கு குத்தகையில் வழங்கப் பெற்ற குத்தகை காணிக்காக
        <?= $payment_shedule_start_year ?> வருடத்திற்கு செலுத்தவேண்டிய ரூபா <span id="value_rent_tamil"></span>
        குத்தகைபணம்
        <?= $due_date ?> திகதியில் அல்லது அதற்கு முன்னர் வெலுத்தப்பட்ட வேண்டுமெனத் தயவுடன் அறியத்தருகின்றேன்.
    </p>

    <p class="section">
        மேலும் கீழ் குறிப்பிடப்பட்டுள்ள வருடங்கள் / வருடத்திற்காகச் செலுத்தப்படவேண்டிய
        குத்தகைபணம் இற்றைவரை செலுத்தப்படவில்லையெனத் தெரியவந்துள்ளது.
        இக்குத்தகைபணமும் செலுத்துவதற்கு ஏற்பட்ட காலதாமதத்திற்கான வட்டிப்பணமும்
        சேர்த்து செலுத்தவேண்டிய மொத்த நிலுவைப்பணம் ருபா <span id="total_outstanding_tamil"></span> வாகும்.
        இப்பணம் இக்கடிதத் திகதியிலிருந்து ஒரு மாதகாலத்திற்குள் செலுத்தப்பட
        வேண்டுமென அறியத்தர விரும்புகின்றேன். குறிப்பிட்ட திகதியில் குத்தகைபணம்
        செலுத்துவதால்இ குற்றப்பணம் செலுத்தவேண்டிய தேவையும் குத்தகை அளிப்பு
        இரத்துச்செய்வதிலிருந்து விலகிக்கொள்ளலாமென மேலும் குறிப்பிட விரும்புபின்றேன்.
        ஏதாவது ஒரு வருடத்திற்கு செலுத்தப்படவேண்டிய குத்தகைப்பணம் செலுத்தப்படாதவிடத்து
        குத்தகை அளிப்பு இரத்துச் செய்ய நேரிடும் என்பதை தங்கள் கவனத்திற்குக்
        கொண்டுவரப்படுகினறது
    </p>

    <p class="section">
        நிலுவையான குத்தகைப்பணம் பற்றிய விபரம் இத்துடன் இணைக்கப்பட்டுள்ளது.
    </p>

<?php } else { ?>

<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="UTF-8">
    <title>ඇමුණුම් අංක : 09 <?= h($benName) ?></title>
    <style>
    @font-face {
        font-family: 'UN-Abhaya';
        src: url('../../letters/UN-Abhaya.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    body {
        font-family: 'UN-Abhaya', sans-serif;
        font-size: 17px;
        line-height: 1.5;
        padding: 20px;
    }
    p { text-align: justify; margin: 0 0 8px; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .section { margin-top: 12px; }
    </style>
</head>
<body>
    <div class="right">
        මාගේ අංකය <?= $file_number ?> <br><br>
        දිනය : .........................................
    </div>

    <p class="bold">ඇමුණුම් අංක : 09</p>

    <p>
        <?= h($benName) ?> <br>
        <?= nl2br(h($benAddress)) ?>
    </p>

    <br>
    <p class="section">මහත්මයාණනි,/මහත්මියණි ,</p>

    <p><u>
        <?= $lease_number ?> අංක දරණ බදුකරය සඳහා ගෙවිය යුතු බදු මුදල් - <?= $payment_shedule_start_year ?>
        වර්ෂය
    </u></p>

    <p class="section">
        ඔබ /ඔබ ආයතනය වෙත ලබා දී ඇති ඉහත සඳහන් බදුකරය වෙනුවෙන් <?= $payment_shedule_start_year ?> වර්ෂය සඳහා
        ගෙවීමට නියමිත
        රු. <span id="value_rent_sinhala"></span> ක් වූ බදු මුදල <?= $due_date ?> දින හෝ ඊට පෙර ගෙවිය යුතුව
        ඇති බව කාරුණිකව දන්වමි.
    </p>

    <p class="section">
        තවද පහත සඳහන් වර්ෂය/වර්ෂ සඳහා ගෙවිය යුතු බදු මුදල්ද ,මේ දක්වා ගෙවා නොමැති බව පෙනී යයි.එම මුදල් හා ප්‍රමාදය
        හේතු කොට අය විය
        යුතු පොලියද ඇතුළුව ගෙවිය යුතු හිඟ මුදලද රු. <span id="total_outstanding_sinhala"></span> ක් වේ.
        එම මුදල මෙම ලිපියේ දින සිට මසක් ඇතුළත ගෙවිය යුතු බව දන්වනු කැමැත්තෙමි.නියමිත දිනට බදු මුදල් ගෙවීමේ දඩ මුදල්
        ගෙවීමේ
        අවශ්‍යතාවයෙන් හා බදුකරය අවලංගු කිරීමේ අවදානමින් නිදහස් විය හැකි බවද වැඩිදුරටත් සඳහන් කරනු කැමැත්තෙමි.
        යම් වර්ෂයක් සඳහා ගෙවිය යුතු බදු මුදල නොගෙව්වහොත් බදුකරය අවංගු කිරීමට හැකියාව ඇති බවද සිහිපත් කෙර්.
    </p>

    <p class="section">
        හිඟ බදු මුදල් පිළිබඳ විස්තර ෙමෙස්ය .
    </p>

<?php } ?>

<?php
// =====================================================================
//   FIFO RENT + PENALTY + PREMIUM OUTSTANDING  (WITH OPTIONAL COLUMNS)
// =====================================================================

$lease_id = $lid ?? 0;
$today = date('Y-m-d');

// Load schedules
$sql = "SELECT schedule_year, annual_amount, discount_apply,
               premium, premium_paid,
               paid_rent, panalty, panalty_paid,
               start_date, end_date
        FROM rl_lease_schedules
        WHERE lease_id = ?
          AND start_date <= ?
        ORDER BY schedule_year ASC";

$stmt = $con->prepare($sql);
$stmt->bind_param("is", $lease_id, $today);
$stmt->execute();
$res = $stmt->get_result();

$rentPayable = [];
$penaltyPayable = [];
$premiumPayable = [];

$totalRentPaid = 0;
$totalPenaltyPaid = 0;
$totalPremiumPaid = 0;

$hasPenalty = false;
$hasPremium = false;

while ($sc = $res->fetch_assoc()) {
    $year = $sc['schedule_year'];
    $annual = floatval($sc['annual_amount']);
    $discount = floatval($sc['discount_apply']);
    $paidRent = floatval($sc['paid_rent']);
    $penalty = floatval($sc['panalty']);
    $penaltyPaid = floatval($sc['panalty_paid']);
    $premium = floatval($sc['premium']);
    $premiumPaid = floatval($sc['premium_paid']);

    if ($penalty > 0 || $penaltyPaid > 0) $hasPenalty = true;
    if ($premium > 0 || $premiumPaid > 0) $hasPremium = true;

    $effectiveAnnual = $annual - $discount;
    $rentPayable[$year] = $effectiveAnnual;
    $penaltyPayable[$year] = $penalty;
    $premiumPayable[$year] = $premium;

    $totalRentPaid += $paidRent;
    $totalPenaltyPaid += $penaltyPaid;
    $totalPremiumPaid += $premiumPaid;
}

// FIFO Rent
$rentAfterFIFO = [];
foreach ($rentPayable as $year => $due) {
    $used = 0;
    if ($totalRentPaid > 0) {
        $used = min($due, $totalRentPaid);
        $totalRentPaid -= $used;
    }
    $rentAfterFIFO[$year] = $due - $used;
}

// FIFO Penalty
$penaltyAfterFIFO = [];
foreach ($penaltyPayable as $year => $due) {
    $used = 0;
    if ($totalPenaltyPaid > 0) {
        $used = min($due, $totalPenaltyPaid);
        $totalPenaltyPaid -= $used;
    }
    $penaltyAfterFIFO[$year] = $due - $used;
}

// FIFO Premium
$premiumAfterFIFO = [];
foreach ($premiumPayable as $year => $due) {
    $used = 0;
    if ($totalPremiumPaid > 0) {
        $used = min($due, $totalPremiumPaid);
        $totalPremiumPaid -= $used;
    }
    $premiumAfterFIFO[$year] = $due - $used;
}

// Build final rows
$finalRows = [];
$totalRent = $totalPen = $totalPrem = $totalAll = 0;
$current_year_rent = 0;

foreach ($rentAfterFIFO as $year => $rentBal) {
    $penBal = $penaltyAfterFIFO[$year] ?? 0;
    $premBal = $premiumAfterFIFO[$year] ?? 0;
    $total = $rentBal + $penBal + $premBal;

    if ($total > 0) {
        $finalRows[] = [
            'year' => $year,
            'rent' => $rentBal,
            'pen' => $penBal,
            'prem' => $premBal,
            'total' => $total
        ];

        $totalRent += $rentBal;
        $totalPen += $penBal;
        $totalPrem += $premBal;
        $totalAll += $total;
    }
    $current_year_rent = $rentBal;
}

// Bank details
$client_md5 = isset($_COOKIE['client_cook']) ? $_COOKIE['client_cook'] : '';
$client = null;
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

if ($client && $client['bank_and_branch'] != "" && $client['account_number'] != "" && $client['account_name'] != "") { 
?>
<p>
    <strong>Bank Details for Payment:</strong><br>
    Bank and Branch: <?= h($client['bank_and_branch']) ?><br>
    Account Number: <?= h($client['account_number']) ?><br>
    Account Name: <?= h($client['account_name']) ?>
</p>
<?php } ?>

<?php if($_REQUEST['language'] == "TA"){ ?>
<br><br>
<p>
    தங்கள் சேவைக்குரிய, <br>
    பிரதேச செயலாளர்
</p>

<script>
let current_year_rent = <?= $current_year_rent; ?>;
document.getElementById("value_rent_tamil").innerText = current_year_rent.toLocaleString();
let total_outstanding = <?= $totalAll; ?>;
document.getElementById("total_outstanding_tamil").innerText = total_outstanding.toLocaleString();
</script>
</body>
</html>

<?php } else { ?>
<br><br>
<p>
    මෙයට - විශ්වාසී, <br>
    ප්‍රාදේශීය ලේකම්
</p>
</body>

<script>
let current_year_rent = <?= $current_year_rent; ?>;
document.getElementById("value_rent_sinhala").innerText = current_year_rent.toLocaleString();
let total_outstanding = <?= $totalAll; ?>;
document.getElementById("total_outstanding_sinhala").innerText = total_outstanding.toLocaleString();
</script>
</html>
<?php } ?>

<div class="page-break"></div>

<div align='center'>
    <h4>Annexure<br>Details of Outstanding (Lease No:<?= h($lease_number) ?>)</h4>
</div>

<table border="1" cellpadding="4" cellspacing="0" class="outstanding-table"
    style="border-collapse:collapse; width:70%; font-size:12px;">
    <thead>
        <tr>
            <th>Year</th>
            <th>Balance Rent</th>
            <?php if ($hasPenalty): ?>
            <th>Balance Penalty</th>
            <?php endif; ?>
            <?php if ($hasPremium): ?>
            <th>Balance Premium</th>
            <?php endif; ?>
            <th>Total Outstanding</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($finalRows)): ?>
        <tr>
            <td colspan="<?= 3 + ($hasPenalty ? 1 : 0) + ($hasPremium ? 1 : 0) ?>" style="text-align:center;">No Outstanding</td>
        </tr>
        <?php else: foreach ($finalRows as $row): ?>
        <tr>
            <td><?= $row['year'] ?></td>
            <td style="text-align:right;"><?= number_format($row['rent'], 2) ?></td>
            <?php if ($hasPenalty): ?>
            <td style="text-align:right;"><?= number_format($row['pen'], 2) ?></td>
            <?php endif; ?>
            <?php if ($hasPremium): ?>
            <td style="text-align:right;"><?= number_format($row['prem'], 2) ?></td>
            <?php endif; ?>
            <td style="text-align:right;"><?= number_format($row['total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <!-- TOTAL ROW -->
        <tr style="font-weight:bold; background:#f8f8f8;">
            <td>Total</td>
            <td style="text-align:right;"><?= number_format($totalRent, 2) ?></td>
            <?php if ($hasPenalty): ?>
            <td style="text-align:right;"><?= number_format($totalPen, 2) ?></td>
            <?php endif; ?>
            <?php if ($hasPremium): ?>
            <td style="text-align:right;"><?= number_format($totalPrem, 2) ?></td>
            <?php endif; ?>
            <td style="text-align:right;"><?= number_format($totalAll, 2) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

