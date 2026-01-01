<?php
// Lease Recovery Letter generation
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
header('Content-Type: text/html; charset=utf-8');

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES,'UTF-8'); }

$md5 = isset($_GET['id']) ? $_GET['id'] : '';
$as_at = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date
$as_at_safe = preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_at) ? $as_at : date('Y-m-d');

// **NEW: Outstanding as at +30 days**
$outstanding_date = date('Y-m-d', strtotime($as_at_safe . ' +30 days'));

$ben = $land = $lease = $client = null;
$error = '';

if ($md5 === '') {
  $error = 'Missing beneficiary reference.';
}

if (!$error){
  if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name, address FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmt,'s',$md5);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && ($ben = mysqli_fetch_assoc($res))) {
      $ben_id = (int)$ben['ben_id'];

      if ($st2 = mysqli_prepare($con, 'SELECT * FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')){
        mysqli_stmt_bind_param($st2,'i',$ben_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);

        if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
          $land_id = (int)$land['land_id'];

          if ($st3 = mysqli_prepare($con, 'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')){
            mysqli_stmt_bind_param($st3,'i',$land_id);
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
     WHERE md5_client='" . mysqli_real_escape_string($con,$client_md5) . "' LIMIT 1"
  );
  if ($qClient && mysqli_num_rows($qClient) === 1) {
    $client = mysqli_fetch_assoc($qClient);
  }
}

/* ===================================================
   OUTSTANDING CALCULATION AS AT (selected date + 30 days)
   =================================================== */

$rent_outstanding      = 0.0;
$penalty_outstanding   = 0.0;
$premium_outstanding   = 0.0;
$total_outstanding     = 0.0;

if ($lease && isset($lease['lease_id'])) {
  $lid = (int)$lease['lease_id'];

  // 1) DUE amounts up to outstanding_date
  $rent_due_total     = 0.0;
  $penalty_due_total  = 0.0;
  $premium_due_total  = 0.0;

  if ($stD = mysqli_prepare(
    $con,
    "SELECT start_date, annual_amount, panalty, premium
     FROM lease_schedules
     WHERE lease_id=? AND status=1 AND start_date <= ?
     ORDER BY start_date, schedule_id"
  )) {
    mysqli_stmt_bind_param($stD,'is',$lid,$outstanding_date);
    mysqli_stmt_execute($stD);
    $resD = mysqli_stmt_get_result($stD);
    if ($resD) {
      while ($rowD = mysqli_fetch_assoc($resD)) {
        $rent_due_total    += (float)$rowD['annual_amount'];
        $penalty_due_total += (float)$rowD['panalty'];
        $premium_due_total += (float)$rowD['premium'];
      }
    }
    mysqli_stmt_close($stD);
  }

  // 2) PAID as at outstanding_date
  $rent_paid_total     = 0.0;
  $discount_total      = 0.0;
  $penalty_paid_total  = 0.0;
  $premium_paid_total  = 0.0;

  if ($stP = mysqli_prepare(
    $con,
    "SELECT payment_date, rent_paid, current_year_payment,
            panalty_paid, discount_apply, premium_paid
     FROM lease_payments
     WHERE lease_id=? AND status=1 AND payment_date <= ?"
  )) {
    mysqli_stmt_bind_param($stP,'is',$lid,$outstanding_date);
    mysqli_stmt_execute($stP);
    $resP = mysqli_stmt_get_result($stP);
    if ($resP) {
      while ($rowP = mysqli_fetch_assoc($resP)) {
        $rent_paid_total    += (float)$rowP['rent_paid'] + (float)$rowP['current_year_payment'];
        $discount_total     += (float)$rowP['discount_apply'];
        $penalty_paid_total += (float)$rowP['panalty_paid'];
        $premium_paid_total += (float)$rowP['premium_paid'];
      }
    }
    mysqli_stmt_close($stP);
  }

  // 3) OUTSTANDING
  $rent_outstanding    = max(0, $rent_due_total    - $rent_paid_total    - $discount_total);
  $penalty_outstanding = max(0, $penalty_due_total - $penalty_paid_total);
  $premium_outstanding = max(0, $premium_due_total - $premium_paid_total);

  $total_outstanding   = $rent_outstanding + $penalty_outstanding + $premium_outstanding;
}

/* ===================================================
   Number to words
   =================================================== */

$today_disp = date('d/m/Y', strtotime($as_at_safe));

function number_to_words_int($num) {
  $num = (int)$num;
  if ($num === 0) return 'Zero';
  $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  $tens = ['','Ten','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  $scales = [1000000000000=>'Trillion',1000000000=>'Billion',1000000=>'Million',1000=>'Thousand',100=>'Hundred'];
  $out = [];
  foreach($scales as $value=>$label){
    if ($num >= $value){
      $count = (int)floor($num / $value);
      $num = $num % $value;
      $out[] = trim(number_to_words_int($count) . ' ' . $label);
    }
  }
  if ($num >= 20){
    $out[] = $tens[(int)floor($num/10)];
    if ($num % 10) $out[] = $ones[$num%10];
  } elseif ($num > 0){
    $out[] = $ones[$num];
  }
  return trim(implode(' ', $out));
}

function amount_to_words($amount){
  $amount = round((float)$amount, 2);
  $rupees = (int)floor($amount);
  $cents = (int)round(($amount - $rupees) * 100);
  $parts = [];
  if ($rupees >= 0){
    $parts[] = number_to_words_int($rupees) . ' Rupees';
  }
  if ($cents > 0){
    $parts[] = number_to_words_int($cents) . ' Cents';
  }
  return trim(implode(' and ', $parts));
}

$amount_words = amount_to_words($total_outstanding);

// Check if beneficiary name exists
$benName = $ben['name'] ?? '';
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
    <meta charset="utf-8" />
    <title>Lease Recovery Letter</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        font-size: 14px;
        line-height: 1.55;
        color: #111;
        margin: 100px 50px 30px 50px;
    }

    .top-meta {
        font-size: 12px;
        margin-bottom: 18px;
    }

    h1 {
        font-size: 16px;
        margin: 0 0 6px;
    }

    .section {
        margin-top: 14px;
    }

    .label {
        font-weight: 600;
    }

    .bank-block {
        margin-top: 18px;
    }

    .underline {
        text-decoration: underline;
    }

    p {
        text-align: justify;
        line-height: 1.65;
    }

    @media print {
        body {
            font-size: 14px;
            margin-top: 100px;
        }
    }
    </style>
</head>

<body>
    <div class="top-meta"><br><br><br>
        <span>File No: <?= h($lease['file_number'] ?? '-') ?></span>
        <span style="float:right;">Date: <?= h($today_disp) ?></span>
        <div style="clear:both"></div>
    </div>

    <?php if ($error): ?>
    <div style="color:#c00;font-weight:600;">Error: <?= h($error) ?></div>
    <?php else: ?>

    <div class="section">
        <div><?= h($ben['name']) ?></div>
        <div><?= h($ben['address']) ?></div>

        <div>
            <span class="label">
                Annual Lease Rental Payment Rs. <?= number_format($total_outstanding,2) ?>
            </span>
            <br>
            <!-- <small>(Outstanding as at <?= date('d/m/Y', strtotime($outstanding_date)) ?>)</small> -->
        </div>
        <hr>
    </div>

    <div class="section">
        <p>
            This is to kindly inform you that to pay the amount of
            Rs. <?= number_format($total_outstanding,2) ?>
            (<?= h($amount_words) ?>) as lease rental with arrears
            to the below mentioned bank account number.
            <strong>(details annexed herewith)</strong>
        </p>
    </div>

    <div class="bank-block">
        <p><span class="label">Bank Name:</span> <?= h($client['bank_and_branch'] ?? 'N/A') ?></p>
        <p><span class="label">Account Name:</span> <?= h($client['account_name'] ?? 'N/A') ?></p>
        <p><span class="label">A/C No:</span> <?= h($client['account_number'] ?? 'N/A') ?></p>
    </div>

    <div class="section">
        <p>Further you are verified to send the paid slips to the email ID mentioned below.</p>
        <p>Email: <?= h($client['client_email'] ?? '') ?></p>
    </div>

    <div class="section" style="margin-top:60px;">
        <p>Thanking you in advance for your prompt action.</p>
    </div>

    <div class="section" style="margin-top:80px;">
        <p>Divisional Secretary<br><?= h($client['client_name'] ?? '') ?></p>
    </div>

    <?php endif; ?>
</body>

<script>
window.addEventListener('load', function() {
    try {
        window.print();
    } catch (e) {}
});
</script>

</html>