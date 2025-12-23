<?php
// long_term_lease_payment_summary.php
// DS-wise summary with date range, district filter, lease_type filter

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
date_default_timezone_set('Asia/Colombo');

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($v,$d=2){ return number_format((float)$v,$d); }

/* ---------------------------------------------------------
   INPUTS
--------------------------------------------------------- */
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$district = $_GET['district'] ?? 'All';
$leaseType = $_GET['lease_type'] ?? 'All';

$date_re = '/^\d{4}-\d{2}-\d{2}$/';

if (!preg_match($date_re, $from) || !preg_match($date_re, $to)) {
    exit("<h3>Invalid date range. Use YYYY-MM-DD.</h3>");
}

$today = date("Y-m-d H:i");

/* ---------------------------------------------------------
   1. LOAD DS LIST (DISTRICT FILTER)
--------------------------------------------------------- */
$whereDistrict = "";
if ($district !== "All") {
    $d = mysqli_real_escape_string($con, $district);
    $whereDistrict = "AND regNumber = '$d'";
}

$dsList = [];
$sql_ds = "
    SELECT c_id, client_name, regNumber
    FROM client_registration
    WHERE status = 1
    $whereDistrict
    ORDER BY client_name ASC
";
$q = mysqli_query($con, $sql_ds);
while($r = mysqli_fetch_assoc($q)){
    $cid = $r['c_id'];
    $dsList[$cid] = [
        'client_name' => $r['client_name'],
        'district'    => $r['regNumber']
    ];
}

if (empty($dsList)){
    exit("<h3>No DS divisions found for selected district</h3>");
}

$cids = implode(",", array_keys($dsList));
if ($cids == "") $cids = "0";

/* ---------------------------------------------------------
   2. LOAD PAYMENTS FOR THESE DS + lease_type + date range
--------------------------------------------------------- */
$whereLeaseType = "";
if ($leaseType !== "All") {
    $lt = mysqli_real_escape_string($con, $leaseType);
    $whereLeaseType = "AND l.type_of_project = '$lt'";
}

$sql = "
SELECT 
    lp.payment_id,
    lp.payment_date,
    lp.amount,
    lp.premium_paid,
    lp.rent_paid,
    lp.panalty_paid,
    lp.discount_apply,
    l.location_id AS c_id
FROM lease_payments lp
INNER JOIN leases l ON l.lease_id = lp.lease_id
WHERE 
    lp.status = 1
    AND lp.payment_date BETWEEN '$from' AND '$to'
    AND l.location_id IN ($cids)
    $whereLeaseType
ORDER BY lp.payment_date
";

$res = mysqli_query($con, $sql);

$rows = [];
while($r = mysqli_fetch_assoc($res)) $rows[] = $r;

/* ---------------------------------------------------------
   3. INIT GROUP BUCKETS
--------------------------------------------------------- */
$groups = [];
foreach($dsList as $cid=>$info){
    $groups[$cid] = [
        'client_name'=>$info['client_name'],
        'district'=>$info['district'],
        'count'=>0,

        'total_payment'=>0,
        'premium_paid'=>0,
        'rent_paid'=>0,
        'penalty_paid'=>0,
        'discount'=>0,
        'total_with_discount'=>0
    ];
}

/* ---------------------------------------------------------
   4. PROCESS PAYMENT ROWS
--------------------------------------------------------- */
foreach($rows as $r){

    $cid = $r['c_id'];
    if (!isset($groups[$cid])) continue;

    $groups[$cid]['count']++;

    $amt = (float)$r['amount'];
    $prem= (float)$r['premium_paid'];
    $rent= (float)$r['rent_paid'];
    $pen = (float)$r['panalty_paid'];
    $disc= (float)$r['discount_apply'];

    $groups[$cid]['total_payment']       += $amt;
    $groups[$cid]['premium_paid']        += $prem;
    $groups[$cid]['rent_paid']           += $rent;
    $groups[$cid]['penalty_paid']        += $pen;
    $groups[$cid]['discount']            += $disc;
    $groups[$cid]['total_with_discount'] += ($amt + $disc);
}

/* ---------------------------------------------------------
   5. GRAND TOTALS
--------------------------------------------------------- */
$gt = [
    'count'=>0,
    'total_payment'=>0,
    'premium_paid'=>0,
    'rent_paid'=>0,
    'penalty_paid'=>0,
    'discount'=>0,
    'total_with_discount'=>0
];

foreach($groups as $g){
    $gt['count']               += $g['count'];
    $gt['total_payment']       += $g['total_payment'];
    $gt['premium_paid']        += $g['premium_paid'];
    $gt['rent_paid']           += $g['rent_paid'];
    $gt['penalty_paid']        += $g['penalty_paid'];
    $gt['discount']            += $g['discount'];
    $gt['total_with_discount'] += $g['total_with_discount'];
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Long Term Lease Payment Summary</title>
<style>
body{font-family:Arial;margin:15px;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #000;padding:5px;font-size:12px;}
th{background:#efefef;}
.text-right{text-align:right}
.text-center{text-align:center}
tfoot td{font-weight:bold;background:#ddd;}
@media print{@page{size:A4 landscape;margin:10mm}}
</style>
</head>
<body>

<h2>Long Term Lease Payment Summary</h2>

Period: <b><?=h($from)?> to <?=h($to)?></b><br>
District: <b><?=h($district)?></b><br>
Lease Type: <b><?=h($leaseType)?></b><br>
Generated: <?= $today ?><br><br>

<table>
<thead>
<tr>
<th>SN</th>
<th>DS Division</th>
<th>District</th>
<th>No. of Payments</th>
<th class="text-right">Total Payment</th>
<th class="text-right">Premium Paid</th>
<th class="text-right">Rent Paid</th>
<th class="text-right">Penalty Paid</th>
<th class="text-right">Discount</th>
<th class="text-right">Total Including Discount</th>
</tr>
</thead>

<tbody>
<?php
$sn=1;
foreach($groups as $cid=>$g){
    if ($g['count']==0) continue;
?>
<tr>
<td class="text-center"><?=$sn++?></td>
<td><?=h($g['client_name'])?></td>
<td><?=h($g['district'])?></td>

<td class="text-center"><?=fmt($g['count'],0)?></td>
<td class="text-right"><?=fmt($g['total_payment'])?></td>
<td class="text-right"><?=fmt($g['premium_paid'])?></td>
<td class="text-right"><?=fmt($g['rent_paid'])?></td>
<td class="text-right"><?=fmt($g['penalty_paid'])?></td>
<td class="text-right"><?=fmt($g['discount'])?></td>
<td class="text-right"><?=fmt($g['total_with_discount'])?></td>
</tr>
<?php } ?>
</tbody>

<tfoot>
<tr>
<td colspan="3" class="text-center">GRAND TOTAL</td>
<td class="text-center"><?=fmt($gt['count'],0)?></td>
<td class="text-right"><?=fmt($gt['total_payment'])?></td>
<td class="text-right"><?=fmt($gt['premium_paid'])?></td>
<td class="text-right"><?=fmt($gt['rent_paid'])?></td>
<td class="text-right"><?=fmt($gt['penalty_paid'])?></td>
<td class="text-right"><?=fmt($gt['discount'])?></td>
<td class="text-right"><?=fmt($gt['total_with_discount'])?></td>
</tr>
</tfoot>

</table>

</body>
</html>
