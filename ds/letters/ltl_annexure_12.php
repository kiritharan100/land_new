<?php 
require_once dirname(__DIR__,2) . '/db.php';
require_once dirname(__DIR__,2) . '/auth.php';

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES,'UTF-8'); }

$md5 = $_GET['id'] ?? '';
$as_at = $_GET['as_at_date'] ?? date('Y-m-d');
$as_at_safe = preg_match('/^\d{4}-\d{2}-\d{2}$/',$as_at) ? $as_at : date('Y-m-d');
$outstanding_date = date('Y-m-d', strtotime($as_at_safe . ' +30 days'));

$benName=''; $benAddress='';
$lease = $land = null;
$lease_number = '-';

/* -------------------- Fetch Beneficiary / Lease Details -------------------- */
if ($md5 !== '') {
    if ($st = mysqli_prepare($con,'SELECT ben_id, name_tamil as name,name_sinhala, address_tamil as address, address_sinhala FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($st,'s',$md5);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $ben_id = (int)($row['ben_id'] ?? 0);
            // $benName = $row['name'] ?? '';
            // $benAddress = $row['address'] ?? '';
             if ($_REQUEST['language'] == "TA"){
                $benName = $row['name'] ?? '';
            } else {
                $benName = $row['name_sinhala'] ?? '';
            }
            if ($_REQUEST['language'] == "TA"){
                $benAddress = $row['address'] ?? '';
            } else {
                $benAddress = $row['address_sinhala'] ?? '';
            }

            if ($ben_id > 0) {
                if ($st2 = mysqli_prepare($con,'SELECT land_id FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')){
                    mysqli_stmt_bind_param($st2,'i',$ben_id);
                    mysqli_stmt_execute($st2);
                    $r2 = mysqli_stmt_get_result($st2);
                    if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                        $land_id = (int)$land['land_id'];
                        if ($st3 = mysqli_prepare($con,'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')){
                            mysqli_stmt_bind_param($st3,'i',$land_id);
                            mysqli_stmt_execute($st3);
                            $r3 = mysqli_stmt_get_result($st3);
                            if ($r3) { $lease = mysqli_fetch_assoc($r3); }
                            mysqli_stmt_close($st3);
                        }
                        if ($lease) { $lease_number = $lease['lease_number'] ?? '-'; }
                        $file_number = $lease['file_number'] ?? '-';
                    }
                    mysqli_stmt_close($st2);
                }
            }
        }
        mysqli_stmt_close($st);
    }
}

/* -------------------- Outstanding Calculation (same as your existing script) -------------------- */
$rent_outstanding = 0.0; 
$penalty_outstanding = 0.0; 
$premium_outstanding = 0.0; 
$total_outstanding = 0.0;
$outstanding_only_rent = 0.0;

if ($lease && isset($lease['lease_id'])) {
    $lid = (int)$lease['lease_id'];

    /* ---- Rent Due ---- */
    $rent_due_total = 0.0; 
    $penalty_due_total = 0.0; 
    $premium_due_total = 0.0;

    if ($stD = mysqli_prepare($con, "SELECT start_date, annual_amount, panalty, premium FROM lease_schedules 
                                     WHERE lease_id=? AND status=1 AND start_date <= ? 
                                     ORDER BY start_date, schedule_id")) {
        mysqli_stmt_bind_param($stD,'is',$lid,$outstanding_date);
        mysqli_stmt_execute($stD);
        $resD = mysqli_stmt_get_result($stD);
        while ($rowD = mysqli_fetch_assoc($resD)) {
            $rent_due_total    += (float)$rowD['annual_amount'];
            $penalty_due_total += (float)$rowD['panalty'];
            $premium_due_total += (float)$rowD['premium'];
        }
        mysqli_stmt_close($stD);
    }

    /* ---- Payments ---- */
    $rent_paid_total = 0.0;
    $discount_total  = 0.0;
    $penalty_paid_total = 0.0;
    $premium_paid_total = 0.0;

    if ($stP = mysqli_prepare($con, "SELECT payment_date, rent_paid, current_year_payment, panalty_paid, discount_apply, premium_paid 
                                     FROM lease_payments 
                                     WHERE lease_id=? AND status=1 AND payment_date <= ?")) {
        mysqli_stmt_bind_param($stP,'is',$lid,$outstanding_date);
        mysqli_stmt_execute($stP);
        $resP = mysqli_stmt_get_result($stP);
        while ($rowP = mysqli_fetch_assoc($resP)) {
            $rent_paid_total    += (float)$rowP['rent_paid'] + (float)$rowP['current_year_payment'];
            $discount_total     += (float)$rowP['discount_apply'];
            $penalty_paid_total += (float)$rowP['panalty_paid'];
            $premium_paid_total += (float)$rowP['premium_paid'];
        }
        mysqli_stmt_close($stP);
    }

    $rent_outstanding    = max(0, $rent_due_total - $rent_paid_total - $discount_total);
    $penalty_outstanding = max(0, $penalty_due_total - $penalty_paid_total);
    $premium_outstanding = max(0, $premium_due_total - $premium_paid_total);

    $total_outstanding = $rent_outstanding + $penalty_outstanding + $premium_outstanding;
    $outstanding_only_rent = $rent_outstanding;
}

$payment_year  = date('Y', strtotime($as_at_safe));
$due_date      = date('d/m/Y', strtotime($outstanding_date));

if($_REQUEST['language'] == "TA"){ 
    
?>
<!DOCTYPE html>
<html lang="ta">

<head>
    <meta charset="UTF-8">
    <title>குத்தகை அறிவிப்பு -- இணைப்பு 12 <?= h($benName) ?></title>

    <style>
    @font-face {
        font-family: 'UniIlaSundaram';
        src: url('Uni Ila.Sundaram-04.ttf') format('truetype');
    }

    body {
        font-family: 'UniIlaSundaram';
        font-size: 17px;
        line-height: 1.5;
        padding: 20px;
    }

    p {
        margin: 0 0 10px;
        text-align: justify;
    }

    .right {
        text-align: right;
    }

    .bold {
        font-weight: bold;
    }

    .section {
        margin-top: 15px;
    }
    </style>
</head>

<body>

    <div class="right">
        எமது இல. <?=  $file_number ?> <br>
        <br>
        தேதி : .........................................
    </div>

    <p class="bold">இணைப்பு - 12</p>

    <p>
        <?= h($benName) ?><br>
        <?= nl2br(h($benAddress)) ?>
    </p>

    <p class="section">ஜயா / அம்மணி,</p>

    <p>
        <?= $lease_number ?> இலக்கத்தைக் கொண்ட குத்தகைக்காக செலுத்தப்பட வேண்டிய
        குத்தகைபணம் <?= $payment_year ?> வருடம்.
    </p>

    <p class="section">

        தங்களுக்கு / தங்கன் ஸ்தாபனத்திற்கு குத்தகையில் வழங்கப் பெற்ற குத்தகை காணிக்காக
        <?= $payment_year ?> வருடத்திற்கு செலுத்தவேண்டிய ரூபா
        <?= number_format($outstanding_only_rent,2) ?> குத்தகைபணம்
        <?= $due_date ?> திகதியில் அல்லது அதற்கு முன்னர் வெலுத்தப்பட்ட வேண்டுமெனத் தயவுடன் அறியத்தருகின்றேன்.
    </p>

    <p class="section">
        மேலும் கீழ் குறிப்பிடப்பட்டுள்ள வருடங்கள் / வருடத்திற்காகச் செலுத்தப்படவேண்டிய குத்தகைபணம்
        இற்றைவரை செலுத்தப்படவில்லையெனத் தெரியவந்துள்ளது. இக்குத்தகைபணமும் செலுத்துவதற்கு ஏற்பட்ட
        காலதாமதத்திற்கான வட்டிப்பணமும் சேர்த்து செலுத்தவேண்டிய மொத்த நிலுவைப்பணம் ருபா.
        <?= number_format($total_outstanding,2) ?> வாகும். இப்பணம் இக்கடிதத் திகதியிலிருந்து ஒரு மாதகாலத்திற்குள்
        செலுத்தப்பட வேண்டுமென அறியத்தர விரும்புகின்றேன். குறிப்பிட்ட திகதியில் குத்தகைபணம் செலுத்துவதால்
        குற்றப்பணம் செலுத்தவேண்டிய தேவையும் குத்தகை அளிப்பு இரத்துச்செய்வதிலிருந்து விலகிக்கொள்ளலாமென
        மேலும் குறிப்பிட விரும்புபின்றேன். ஏதாவது ஒரு வருடத்திற்கு செலுத்தப்படவேண்டிய குத்தகைப்பணம்
        செலுத்தப்படாதவிடத்து குத்தகை அளிப்பு இரத்துச் செய்ய நேரிடும் என்பதை தங்கள் கவனத்திற்குக்
        கொண்டுவரப்படுகினறது.
    </p>

    <p class="section">
        நிலுவையான குத்தகைப்பணம் பற்றிய விபரம் இணைக்கப்பட்டுள்ளது.
    </p>

    <br><br>

    <p>
        தங்கள் சேவைக்குரிய, <br>
        பிரதேச செயலாளர்
    </p>

</body>

</html>
<?php } else {  ?>


<!DOCTYPE html>
<html lang="ta">

<head>
    <meta charset="UTF-8">
    <title>ඇමුණුම්. 12 <?= h($benName) ?></title>

    <style>
    /* Load Uni Ila.Sundaram-04.ttf from same folder */
    @font-face {
        font-family: 'UN-Abhaya';
        src: url('UN-Abhaya.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    body {
        font-family: 'UN-Abhaya', sans-serif;
        font-size: 16px;
        line-height: 1.5;
        padding: 20px;
    }

    /* Paragraph formatting: justify + tighter spacing */
    p {
        text-align: justify;
        margin: 0 0 8px;
    }

    .right {
        text-align: right;
    }

    .bold {
        font-weight: bold;
    }

    .section {
        margin-top: 12px;
    }
    </style>
</head>

<body>

    <div class="right">
        මාගේ අංකය <?=  $file_number ?> <br> <br>
        ....................................................... <br>
        දිනය : .........................................
    </div>

    <p class="bold">ඇමුණුම්. 12</p>

    <p>
        <?= h($benName) ?> <br>
        <?= nl2br(h($benAddress)) ?>
    </p>


    <br>
    <p class="section">
        මහත්මයාණනි,/මහත්මියණි ,
    </p>

    <p><u>
            අංක <?= $lease_number ?> දරණ බදු කරය වෙනුවෙන් නියමිත බදු මුදල් නොගෙවීම නිසා අවලංගු කිරීම - රජයේ ඉඩම් ආඥා
            පනතේ 86 වෙනි වගන්තිය.
        </u>
    </p>

    <p class="section">
        ඔබ /ඔබ ආයතනය වෙත ලබා දී ඇති පහත සඳහන් බදු කරය වෙනුවෙන් <?= $payment_shedule_start_year  ?> වර්ෂය /වර්ෂ සඳහා
        ගෙවිය
        යුතු බදු මුදල් ගෙවන ලෙස දන්වමින් එවන ලද <?= $lease_number ?> අංක හා
        ..........................................
        දිනැති මාගේ ලිපිය හා ...........................................දින එවන ලද අවසාන නිවේදනය කෙරෙහි ඔබගේ අවධානය යොමු
        කරමි <br>
    </p>

    <p class="section">
        02. නියමිත මුදල් ගෙවීමට මේ දක්වාම ඔබ අපොහොසත් වී ඇති හෙයින් ඔබට ලබා දී ඇති බදුකරය
        ..................................................
        දින සිට අවලංගු කිරීමට කටයුතු කරනු ලැබේ .එදින සිට එහි බදු ගැණුම් කරු වන ඔබට හෝ එම පාඨයෙන් අදහස් කරනු ලබන වෙනත්
        කිසිවෙකුට
        හෝ නියමිත ඉඩමේ කිසිදු හිමිකමක් නොලැබේ. එම නිසා දිනට හෝ ඊට පෙර නියමිත ඉඩමේ නිරවුල් භුක්තිය හා අංක
        ..................................
        ...................හා ......................................දිනැති බදුකරයේ මුල් පිටපතද
        ........................................මා වෙත භාර
        දිය යුතු බව හෙයින් දක්වමි. මීට ඇතුළත් කරුණූ දැක්වීමට ඔබ අදහස් කරන්නේ නම් ............................දින හෝ ඊට
        ප්‍රථම මා වෙත ලැබෙන පරිදි
        ලියාපදිංචි තැපෑලෙන් එවා ඉදිරිපත් තළ යුතු බවද සඳහන් කරනු කැමැත්තෙමි.

    <div align="center">
        මෙයට විශ්වාසී , <br><br> ප්‍රාදේශීය ලේකම්

    </div>


    </p>

    <p class="section">
        පිටපත් : <br>
        ග්‍රාම නිලධාරී :අංක ..........................දරණ පිඹුරේ කැබලි අංක ...............................න් පෙන්වන
        වපසරිය හෙක්/අක්
        .............................ක්වූ ඉඩම සඳහා බදු මුදල් ගෙවන ලද බවට කුවින්තාන්සියක් ඉදිරිපත් කිරීමට හෝ ඉඩමේ නිරවුල්
        භුක්තිය
        සමඟ බදුකරයේ මුල් පිටපත ......................දින හෝ ඊට පෙර භාරදීමට බදු ලැබුම්කරු අපොහොසත් වුවහොත් සන්තකය ආපසු
        ගැනීමේ පනත යටයතේ නඩු පැවරීම පිණිස ආ.ඒ.60 ආකෘතිය සම්පූර්ණ කර එවීම සඳහා ක්‍රියාකිරීමට බදුකරය අවලංගු කිරීමට නියමිත
        දිනය අනවසරයෙන් අල්ලා ගත් දිනය වශයෙන් සැලකිය යුතුය.
    </p>

    <br><br>

    <p>

    </p>

</body>

</html>















<?php } ?>