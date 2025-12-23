<?php
// Long Term Lease Payments Summary Report
// Inputs: GET from (Y-m-d), to (Y-m-d)

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Resolve selected client via cookie (same mechanism as ds/header.php)
  $client_md5 = isset($_REQUEST['c_id']) ? $_REQUEST['c_id'] : '';
 
$client_name = '';
$location_id = '';
if ($client_md5) {
    if ($st = mysqli_prepare($con, 'SELECT c_id, client_name FROM client_registration WHERE md5_client = ? LIMIT 1')) {
        mysqli_stmt_bind_param($st, 's', $client_md5);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $location_id = (string)$row['c_id'];
            $client_name = (string)$row['client_name'];
        }
        mysqli_stmt_close($st);
    }
}

// Validate date inputs
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to   = isset($_GET['to'])   ? $_GET['to']   : '';
$date_re = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($date_re, $from) || !preg_match($date_re, $to)) {
    http_response_code(400);
    echo '<div style="padding:16px;color:#b94a48;background:#f2dede;border:1px solid #ebccd1">Invalid date range. Use YYYY-MM-DD.</div>';
    exit;
}

if ($location_id === '') {
    echo '<div style="padding:16px;color:#8a6d3b;background:#fcf8e3;border:1px solid #faebcc">No working location selected. Please select a location and try again.</div>';
    exit;
}

// Fetch payments for this location and date range.
// status=1 (exclude cancelled), join leases and beneficiaries to get required fields.
$rows = [];
if ($st = mysqli_prepare($con, 'SELECT 
    lp.payment_id,
    lp.payment_date,
    lp.payment_method,
    lp.reference_number,
    lp.amount,
    lp.premium_paid,
    lp.rent_paid,
    lp.panalty_paid,
    lp.discount_apply,
    lp.created_on,
    l.lease_number,
    l.file_number,
    b.name AS beneficiary_name
  FROM lease_payments lp
  INNER JOIN leases l ON l.lease_id = lp.lease_id
  INNER JOIN beneficiaries b ON b.ben_id = l.beneficiary_id
  WHERE lp.status = 1 AND l.location_id = ? AND lp.payment_date BETWEEN ? AND ?
  ORDER BY lp.payment_date ASC, lp.payment_id ASC')) {
    mysqli_stmt_bind_param($st, 'iss', $location_id, $from, $to);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    mysqli_stmt_close($st);
}

$today = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Long Term Lease Payments Summary</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 18px; }
    .report-header { margin-bottom: 14px; }
    .report-title { font-size: 18px; font-weight: 700; }
    .meta { color: #555; font-size: 12px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #080808ff; padding: 6px 8px; font-size: 12px; }
    th { background: #f5f5f5; text-align: left; }
    tfoot td { font-weight: 700; }
    .text-right { text-align: right; }
    @media print { @page { size: A4 landscape; margin: 12mm; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
  </style>
  <script>
    // Always open as a standalone printable view in landscape
    window.addEventListener('load', function(){
      try { window.print(); } catch(e) {}
    });
  </script>
  <base target="_blank">
  <meta name="referrer" content="no-referrer" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' data:;">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="dns-prefetch" href="//fonts.googleapis.com">
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <noscript>Your browser does not support JavaScript.</noscript>
  <!-- Open in a new tab safety hint -->
</head>
<body>
  <div class="report-header">
    <div class="report-title">Long Term Lease Payments Summary</div>
    <div><strong><?= h($client_name) ?></strong></div>
    <div class="meta">Period: <?= h($from) ?> to <?= h($to) ?> &nbsp; | &nbsp; Generated: <?= h($today) ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>SN</th>
        <th>Recrd Date</th>
        <th>Methord</th>
        <th>Lease Holder Name</th>
        <th>LCG No</th>
        <th>File No</th>
        <th>Receipt Date</th>
        <th>Receipt No</th>
        <th class="text-right">Total Payment</th>
        <th class="text-right">Premium Paid</th>
        <th class="text-right">Rent Paid</th>
        <th class="text-right">Penalty Paid</th>
        <th class="text-right">Discount</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="14" style="text-align:center;color:#888">No payments found for the selected period.</td></tr>
        <?php else: $i=1; $totAmt=0; $totPrem=0; $totRent=0; $totPen=0; $totDisc=0; $totCYA=0; foreach ($rows as $r):
          $prem = (float)($r['premium_paid'] ?? 0);
          $rent = (float)($r['rent_paid'] ?? 0);
          $pen  = (float)($r['panalty_paid'] ?? 0);
          $disc = (float)($r['discount_apply'] ?? 0);
     
          $amt  = (float)($r['amount'] ?? ($prem + $rent + $pen - $disc));
          $totAmt += $amt; $totPrem += $prem; $totRent += $rent; $totPen += $pen; $totDisc += $disc; $totCYA += $cya;
      ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= h(date('Y-m-d', strtotime($r['created_on']))) ?></td>
          <td><?= h(ucfirst((string)$r['payment_method'])) ?></td>
          <td><?= h($r['beneficiary_name']) ?></td>
          <td><?= h($r['lease_number']) ?></td>
          <td><?= h($r['file_number']) ?></td>
          <td><?= h($r['payment_date']) ?></td>
          <td><?= h($r['reference_number']) ?></td>
          
          <td class="text-right"><?= number_format($amt, 2) ?></td>
          <td class="text-right"><?= number_format($prem, 2) ?></td>
          <td class="text-right"><?= number_format($rent, 2) ?></td>
          <td class="text-right"><?= number_format($pen, 2) ?></td>
          <td class="text-right"><?= number_format($disc, 2) ?></td>
           
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if ($rows): ?>
    <tfoot>
      <tr>
        <td colspan="8" align='center'>Total</td>
        <td class="text-right"><?= number_format($totAmt, 2) ?></td>
        <td class="text-right"><?= number_format($totPrem, 2) ?></td>
        <td class="text-right"><?= number_format($totRent, 2) ?></td>
        <td class="text-right"><?= number_format($totPen, 2) ?></td>
        <td class="text-right"><?= number_format($totDisc, 2) ?></td>
    
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>
</body>
</html>
