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

/* -------------------- Fetch Beneficiary / Lease Details -------------------- */
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

/* -------------------- Outstanding Calculation -------------------- */
$rent_outstanding = 0.0; 
$penalty_outstanding = 0.0; 
$premium_outstanding = 0.0; 
$total_outstanding = 0.0;
$outstanding_only_rent = 0.0;

if ($lease && isset($lease['rl_lease_id'])) {
    $lid = (int)$lease['rl_lease_id'];

    /* ---- Rent Due ---- */
    $rent_due_total = 0.0; 
    $penalty_due_total = 0.0; 
    $premium_due_total = 0.0;

    if ($stD = mysqli_prepare($con, "SELECT start_date, annual_amount, panalty, premium FROM rl_lease_schedules 
                                     WHERE lease_id=? AND status=1 AND start_date <= ? 
                                     ORDER BY start_date, schedule_id")) {
        mysqli_stmt_bind_param($stD, 'is', $lid, $outstanding_date);
        mysqli_stmt_execute($stD);
        $resD = mysqli_stmt_get_result($stD);
        while ($rowD = mysqli_fetch_assoc($resD)) {
            $rent_due_total += (float)$rowD['annual_amount'];
            $penalty_due_total += (float)$rowD['panalty'];
            $premium_due_total += (float)$rowD['premium'];
        }
        mysqli_stmt_close($stD);
    }

    /* ---- Payments ---- */
    $rent_paid_total = 0.0;
    $discount_total = 0.0;
    $penalty_paid_total = 0.0;
    $premium_paid_total = 0.0;

    if ($stP = mysqli_prepare($con, "SELECT payment_date, rent_paid, current_year_payment, panalty_paid, discount_apply, premium_paid 
                                     FROM rl_lease_payments 
                                     WHERE lease_id=? AND status=1 AND payment_date <= ?")) {
        mysqli_stmt_bind_param($stP, 'is', $lid, $outstanding_date);
        mysqli_stmt_execute($stP);
        $resP = mysqli_stmt_get_result($stP);
        while ($rowP = mysqli_fetch_assoc($resP)) {
            $rent_paid_total += (float)$rowP['rent_paid'] + (float)$rowP['current_year_payment'];
            $discount_total += (float)$rowP['discount_apply'];
            $penalty_paid_total += (float)$rowP['panalty_paid'];
            $premium_paid_total += (float)$rowP['premium_paid'];
        }
        mysqli_stmt_close($stP);
    }

    $rent_outstanding = max(0, $rent_due_total - $rent_paid_total - $discount_total);
    $penalty_outstanding = max(0, $penalty_due_total - $penalty_paid_total);
    $premium_outstanding = max(0, $premium_due_total - $premium_paid_total);

    $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;
    $outstanding_only_rent = $rent_outstanding;
}

$payment_year = date('Y', strtotime($as_at_safe));
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

if($_REQUEST['language'] == "TA"){ 
?>
<!DOCTYPE html>
<html lang="ta">
<head>
    <meta charset="UTF-8">
    <title>குத்தகை அறிவிப்பு -- இணைப்பு 12A <?= h($benName) ?></title>
    <style>
    @font-face {
        font-family: 'UniIlaSundaram';
        src: url('../../letters/Uni Ila.Sundaram-04.ttf') format('truetype');
    }
    body {
        font-family: 'UniIlaSundaram';
        font-size: 17px;
        line-height: 1.5;
        padding: 20px;
    }
    p { margin: 0 0 10px; text-align: justify; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .section { margin-top: 15px; }
    </style>
</head>
<body>
    பதிவுத்தபால்
    <br><br>
    <p class="bold">இணைப்பு - 12A</p>
    எமது இல. <?= $file_number ?> <br>
    <br><br><br>
    <p>
        <?= h($benName) ?><br>
        <?= nl2br(h($benAddress)) ?>
    </p>

    <p class="section">ஜயா / அம்மணி,</p>

    <b><u>
        <?= $lease_number ?> ம் இலக்க குத்தகை அளிப்பிற்காக குத்தகைப்பணம் செலுத்தாமை பற்றிய இறுதி அறிவித்தல்
    </u></b>

    <p class="section">
        தங்களுக்கு / தங்கன் ஸ்தாபனத்திற்கு வழங்கப்பெற்றுள்ள மேற்குறிப்பிட்ட குத்தகை அளிப்பிற்காகச்
        செலுத்தவேண்டிய குத்தகைபணம் சம்மந்தமாக
        <?php echo $_REQUEST['last_reminder_date']; ?>ம் திகதி என்னால் அனுப்பி வைக்கப்பெற்ற கடிதத்திற்குத் தங்கள்
        கவனத்தை ஈர்ப்பதுடன் அதில் குறிப்பிட்டவாறு குத்தகைப்பணம் செலுத்துவதற்கோஇ அது சம்மந்தமாக
        ஏதாவது பதில் அனுப்புவதற்கோ தாங்கள் / தங்கள் ஸ்தாபனம் இதுவரை எதுவித நடவடிக்கையும்
        மேற்கொள்ளவில்லையென மிக வருத்தத்துடன் தெரிவித்துக்கொள்கின்றேன்.
    </p>

    <p class="section">
        ஆகையால் <?php echo $outstanding_date; ?> ந் திகதியன்றோ அல்லது இதற்கு முன்னர் தங்களால் செலுத்தப்பட
        வேண்டிய குத்தகைப்பணமும், வட்டி - குற்றப்பணமான ருபா <span id="total_outstanding_tamil"></span>.யையும்
        இக்காரியாலயத்தில் செலுத்தத் தவறுமிடத்து தங்களுக்கு / தங்கன் ஸ்தாபனத்திற்கு வழங்கப்பெற்ற குத்தகை
        அளிப்பை இரத்துச்செய்து காணியின் ஆட்சி உரிமையை அரசிற்கு பாரமெடுப்பதற்கு நடவடிக்கை
        மேற்கொள்ளுபடுமென்பதை இறுதியாக இத்தால் தங்பளுக்கு அறியத்தருகின்றேன்
    </p>

    <br><br>

    <p>
        தங்கள் சேவைக்குரிய, <br>
        பிரதேச செயலாளர்
    </p>
</body>
</html>
<?php } else { ?>

<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="UTF-8">
    <title>ඇමුණුම් අංක : 12A <?= h($benName) ?></title>
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

    <p class="bold">ඇමුණුම් අංක : 12ඒ් </p>

    <p>
        <?= h($benName) ?> <br>
        <?= nl2br(h($benAddress)) ?>
    </p>

    <br>
    <p class="section">මහත්මයාණනි,/මහත්මියණි ,</p>

    <p><u>
        අංක <?= $lease_number ?> දරණ බදුකරය සඳහා බදු මුදල් නොගෙවීම පිළිබඳ අවසාන නිවේදනය.
    </u></p>

    <p class="section">
        ඔබ /ඔබ ආයතනය වෙත ලබා දී ඇති ඉහත සඳහන් බදු කරය වෙනුවෙන් ගෙවිය යුතු බදු මුදල් පිළිබඳව
        <?php echo $_REQUEST['last_reminder_date']; ?> දින
        මවිසින් එවා ඇති ලිපිය කෙරෙහි ඔබගේ අවධානය යොමු කරන අතර ,එහි සඳහන් පරිදි නියමිත මුදල් ගෙවීමට හෝ ඒ සම්බන්ධයෙන් යම්
        ප්‍රතිචාරයක් දැක්වීමට හො
        ඔබ/ඔබ ආයතනය මේ දක්වාම ක්‍රියාකර නොමැති බව දැක්වීමට කණගාටු වෙමි.
    </p>

    <p class="section">
        02.ඒ නිසා <?php echo $outstanding_date; ?> දින හෝ ඊට පෙර ඔබ විසින් ගෙවිය යුතුව ඇති බදු හා පොලී/ දඩ
        වශයෙන් වූ රු <span id="total_outstanding_sinhala"></span> ක් මෙම කාර්යාලයට ගෙවීමට අපොහොසත් වුව හොත් ඔබට/ ඔබ
        ආයතනයට ලබා දී ඇති බදු කරය අවලංගු කර ඉඩමේ සන්තකය රජය වෙත ලබා ගැනීමට ක්‍රියා කරන බව අවසාන වශයෙන් මෙයින් දැනුම්
        දෙමි.
    </p>

    <p class="section">
        හිඟ බදු මුදල් පිළිබඳ විස්තර ෙමෙස්ය .
    </p>

    <br><br>

    <p>
        මෙයට - විශ්වාසී, <br>
        ප්‍රාදේශීය ලේකම්
    </p>
</body>
</html>
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
}
?>

<?php if($_REQUEST['language'] == "TA"){ ?>
<script>
let total_outstanding = <?= $totalAll; ?>;
document.getElementById("total_outstanding_tamil").innerText = total_outstanding.toLocaleString();
</script>
<?php } else { ?>
<script>
let total_outstanding = <?= $totalAll; ?>; 
document.getElementById("total_outstanding_sinhala").innerText = total_outstanding.toLocaleString();
</script>
<?php } ?>

