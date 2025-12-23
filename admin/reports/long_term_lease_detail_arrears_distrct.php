<?php
// long_term_lease_detail_arrears_district.php
// District-wise Long Term Lease Arrears Summary (matches full arrears logic)

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
date_default_timezone_set('Asia/Colombo');

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($v,$d=2){ return number_format((float)$v,$d); }

/* ---------------------------------------------------------
   1. INPUT VALIDATION
--------------------------------------------------------- */
$asAt = $_GET['as_at'] ?? '';
$districtFilter = $_GET['district'] ?? 'All';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asAt)) {
    exit("<h3>Invalid as_at date</h3>");
}

$year        = (int)substr($asAt, 0, 4);
$prevYear    = $year - 1;
$prevYearEnd = $prevYear . "-12-31";
$currYearStart = $year . "-01-01";

$today = date("Y-m-d H:i");

/* ---------------------------------------------------------
   2. LOAD ALL DS BY DISTRICT
--------------------------------------------------------- */
$whereDistrict = "";
if ($districtFilter !== "All") {
    $whereDistrict = "AND regNumber = '".mysqli_real_escape_string($con, $districtFilter)."'";
}

$dsList = [];
$q = mysqli_query($con,"
    SELECT c_id, client_name, regNumber
    FROM client_registration
    WHERE status=1
    $whereDistrict
    ORDER BY client_name ASC
");

while($r = mysqli_fetch_assoc($q)){
    $cid = $r['c_id'];
    $dsList[$cid] = [
        'client_name' => $r['client_name'],
        'district'    => $r['regNumber']
    ];
}

if (count($dsList) === 0){
    exit("<h3>No DS divisions found for district filter</h3>");
}

/* ---------------------------------------------------------
   3. LOAD ALL ACTIVE LEASES THAT BELONG TO THESE DS
--------------------------------------------------------- */
$cidList = implode(",", array_keys($dsList));

$sql = "
SELECT 
    l.lease_id,
    l.location_id AS c_id,
    l.type_of_project,
    lr.extent_ha
FROM leases l
LEFT JOIN ltl_land_registration lr ON lr.land_id = l.land_id
WHERE 
    l.status!='cancelled'
    AND l.lease_status = 1
    AND l.start_date <= ?
    AND l.location_id IN ($cidList)
";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $asAt);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$leases = [];
while($row = mysqli_fetch_assoc($result)){
    $leases[] = $row;
}

/* ---------------------------------------------------------
   4. PREPARE DS SUMMARY BUCKETS
--------------------------------------------------------- */
$groups = [];
foreach ($dsList as $cid => $d){
    $groups[$cid] = [
        'client_name' => $d['client_name'],
        'district'    => $d['district'],

        'leases'      => 0,
        'extent'      => 0,

        'opening_rent_arrears' => 0,
        'arrears_paid_cy'      => 0,
        'opening_rent_balance' => 0,

        'penalty_opening'      => 0,
        'penalty_paid_opening' => 0,
        'penalty_balance_opening' => 0,

        'opening_outstanding'  => 0,

        'cy_rent_due'          => 0,
        'cy_discount'          => 0,
        'cy_rent_received'     => 0,
        'cy_outstanding'       => 0,

        'cy_penalty'           => 0,
        'year_end_outstanding' => 0,

        'premium_opening_bal'  => 0,
        'premium_opening_paid' => 0,

        'outstanding_to_date'  => 0,
        'total_payments_year'  => 0
    ];
}

/* ---------------------------------------------------------
   5. PROCESS EACH LEASE USING FULL ARREARS LOGIC
--------------------------------------------------------- */
foreach($leases as $L){

    $lease_id = (int)$L['lease_id'];
    $cid = $L['c_id'];

    if (!isset($groups[$cid])) continue;

    $groups[$cid]['leases']++;
    $groups[$cid]['extent'] += (float)$L['extent_ha'];

    /* ---- LOAD SCHEDULES ---- */
    $sch = [];
    $q = mysqli_query($con,"
        SELECT schedule_id,start_date,annual_amount,panalty,premium
        FROM lease_schedules
        WHERE lease_id=$lease_id
          AND status=1
          AND start_date <= '$asAt'
        ORDER BY start_date
    ");
    while($r=mysqli_fetch_assoc($q)) $sch[]=$r;

    /* ---- LOAD PAYMENTS ---- */
    $pay = [];
    $q = mysqli_query($con,"
        SELECT p.*, s.start_date AS sched_start
        FROM lease_payments p
        LEFT JOIN lease_schedules s ON s.schedule_id=p.schedule_id
        WHERE p.lease_id=$lease_id
          AND p.status=1
          AND p.payment_date <= '$asAt'
    ");
    while($r=mysqli_fetch_assoc($q)) $pay[]=$r;

    /* ---- OPENING COMPUTATIONS ---- */
    $openingRentDue=0;
    $openingPenaltyDue=0;
    $openingPremiumDue=0;
    $currentYearRentDue=0;

    foreach($sch as $S){
        $sd = $S['start_date'];
        if ($sd <= $prevYearEnd){
            $openingRentDue    += $S['annual_amount'];
            $openingPenaltyDue += $S['panalty'];
            $openingPremiumDue += $S['premium'];
        }
        if ($sd >= $currYearStart && $sd <= $asAt){
            $currentYearRentDue += $S['annual_amount'];
        }
    }

    /* ---- PAYMENT SPLIT ---- */
    $rentPaidOpenPrev=0;
    $rentPaidOpenCurr=0;
    $discOpenPrev=0;
    $penaltyPaidOpening=0;
    $rentPaidCurrYr=0;
    $discCurrYr=0;
    $openingPremiumPaid=0;
    $totalPaymentForYear=0;

    foreach($pay as $P){

        $pd = $P['payment_date'];
        $sd = $P['sched_start'];

        $rent = $P['rent_paid'] ;
        $disc = $P['discount_apply'];
        $pen  = $P['panalty_paid'];
        $prem = $P['premium_paid'];

        if ($sd <= $prevYearEnd){
            if ($pd <= $prevYearEnd){
                $rentPaidOpenPrev += $rent;
                $discOpenPrev     += $disc;
                $openingPremiumPaid += $prem;
            }
            if ($pd >= $currYearStart && $pd <= $asAt){
                $rentPaidOpenCurr += $rent;
            }
            if ($pd <= $asAt){
                $penaltyPaidOpening += $pen;
            }
        }

        if ($sd >= $currYearStart && $sd <= $asAt){
            if ($pd >= $currYearStart && $pd <= $asAt){
                $rentPaidCurrYr += $rent;
                $discCurrYr     += $disc;
            }
        }

        if ($pd >= $currYearStart && $pd <= $asAt){
            $totalPaymentForYear += ($rent + $pen + $prem);
        }
    }

    /* ---- COMPUTE FINAL VALUES ---- */
    $openingRentArrears = $openingRentDue - ($rentPaidOpenPrev + $discOpenPrev);
    $balanceOpeningRent = $openingRentArrears - $rentPaidOpenCurr;

    $openingPenaltyBal = $openingPenaltyDue - $penaltyPaidOpening;

    $openingOutstanding = $balanceOpeningRent + $openingPenaltyBal;

    $currentYearRentOutstanding = $currentYearRentDue - $rentPaidCurrYr - $discCurrYr;

    $cyPenaltyDue=0;
    foreach($sch as $S){
        if ($S['start_date'] >= $currYearStart && $S['start_date'] <= $asAt){
            $cyPenaltyDue += $S['panalty'];
        }
    }

    $cyPenaltyPaid=0;
    foreach($pay as $P){
        $pd=$P['payment_date']; $sd=$P['sched_start'];
        if($sd >= $currYearStart && $sd <= $asAt){
            if($pd >= $currYearStart && $pd <= $asAt){
                $cyPenaltyPaid += $P['panalty_paid'];
            }
        }
    }

    $currentYearPenaltyBalance = $cyPenaltyDue - $cyPenaltyPaid;

    $yearEndOutstanding =
          $openingOutstanding
        + $currentYearRentOutstanding
        + $currentYearPenaltyBalance;

    $openingPremiumBalance = $openingPremiumDue - $openingPremiumPaid;

    $outstandingToDate = $yearEndOutstanding + $openingPremiumBalance;

    /* ---- ADD TO GROUP ---- */
    $g =& $groups[$cid];

    $g['opening_rent_arrears'] += $openingRentArrears;
    $g['arrears_paid_cy']      += $rentPaidOpenCurr;
    $g['opening_rent_balance'] += $balanceOpeningRent;

    $g['penalty_opening']      += $openingPenaltyDue;
    $g['penalty_paid_opening'] += $penaltyPaidOpening;
    $g['penalty_balance_opening'] += $openingPenaltyBal;

    $g['opening_outstanding']  += $openingOutstanding;

    $g['cy_rent_due']          += $currentYearRentDue;
    $g['cy_discount']          += $discCurrYr;
    $g['cy_rent_received']     += $rentPaidCurrYr;
    $g['cy_outstanding']       += $currentYearRentOutstanding;

    $g['cy_penalty']           += $currentYearPenaltyBalance;
    $g['year_end_outstanding'] += $yearEndOutstanding;

    $g['premium_opening_bal']  += $openingPremiumBalance;
    $g['premium_opening_paid'] += $openingPremiumPaid;

    $g['outstanding_to_date']  += $outstandingToDate;

    $g['total_payments_year']  += $totalPaymentForYear;
}

/* ---------------------------------------------------------
   6. OUTPUT HTML
--------------------------------------------------------- */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>District-wise Lease Arrears Summary</title>
<style>
body{font-family:Arial;margin:15px;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #222;padding:4px 6px;font-size:11px;}
th{background:#eee;}
.text-right{text-align:right}
.text-center{text-align:center}
@media print{@page{size:A4 landscape;margin:10mm}}
</style>
</head>
<body>

<h3>District-wise Long Term Lease Arrears Summary</h3>
As at: <b><?=h($asAt)?></b><br>
District: <b><?=h($districtFilter)?></b><br>
Printed: <?= $today ?><br><br>

<table>
<thead>
<tr>
<th>SN</th>
<th>DS Division</th>
<th>District</th>
<th>No. of Leases</th>
<th>Total Extent (Ha)</th>

<th class="text-right">Rent Arrears<br>till <?=$prevYearEnd?></th>
<th class="text-right">Arrears Paid (CY)</th>
<th class="text-right">Balance Rent</th>

<th class="text-right">Penalty Arrears</th>
<th class="text-right">Penalty Paid</th>
<th class="text-right">Penalty Balance</th>

<th class="text-right">Opening Outstanding</th>

<th class="text-right">CY Annual Rent</th>
<th class="text-right">Discount (CY)</th>
<th class="text-right">Received (CY)</th>
<th class="text-right">CY Outstanding</th>

<th class="text-right">Penalty (CY)</th>
<th class="text-right">Year-End Outstanding</th>

<th class="text-right">Premium Balance (Opening)</th>
<th class="text-right">Premium Paid</th>
<th class="text-right">Outstanding To Date</th>

<th class="text-right">Total Payment (CY)</th>
</tr>
</thead>

<tbody>
<?php
$sn=1;
// TOTAL BUCKET
$total = [
    'leases' => 0,
    'extent' => 0,
    'opening_rent_arrears' => 0,
    'arrears_paid_cy' => 0,
    'opening_rent_balance' => 0,
    'penalty_opening' => 0,
    'penalty_paid_opening' => 0,
    'penalty_balance_opening' => 0,
    'opening_outstanding' => 0,
    'cy_rent_due' => 0,
    'cy_discount' => 0,
    'cy_rent_received' => 0,
    'cy_outstanding' => 0,
    'cy_penalty' => 0,
    'year_end_outstanding' => 0,
    'premium_opening_bal' => 0,
    'premium_opening_paid' => 0,
    'outstanding_to_date' => 0,
    'total_payments_year' => 0
];



foreach($groups as $cid=>$G){
    if ($G['leases'] <= 0) continue;

    // ACCUMULATE TOTALS
$total['leases'] += $G['leases'];
$total['extent'] += $G['extent'];
$total['opening_rent_arrears'] += $G['opening_rent_arrears'];
$total['arrears_paid_cy'] += $G['arrears_paid_cy'];
$total['opening_rent_balance'] += $G['opening_rent_balance'];
$total['penalty_opening'] += $G['penalty_opening'];
$total['penalty_paid_opening'] += $G['penalty_paid_opening'];
$total['penalty_balance_opening'] += $G['penalty_balance_opening'];
$total['opening_outstanding'] += $G['opening_outstanding'];
$total['cy_rent_due'] += $G['cy_rent_due'];
$total['cy_discount'] += $G['cy_discount'];
$total['cy_rent_received'] += $G['cy_rent_received'];
$total['cy_outstanding'] += $G['cy_outstanding'];
$total['cy_penalty'] += $G['cy_penalty'];
$total['year_end_outstanding'] += $G['year_end_outstanding'];
$total['premium_opening_bal'] += $G['premium_opening_bal'];
$total['premium_opening_paid'] += $G['premium_opening_paid'];
$total['outstanding_to_date'] += $G['outstanding_to_date'];
$total['total_payments_year'] += $G['total_payments_year'];


?>
<tr>
<td class="text-center"><?=$sn++?></td>
<td><?=h($G['client_name'])?></td>
<td><?=h($G['district'])?></td>
<td class="text-center"><?=$G['leases']?></td>
<td class="text-right"><?=fmt($G['extent'],2)?></td>

<td class="text-right"><?=fmt($G['opening_rent_arrears'])?></td>
<td class="text-right"><?=fmt($G['arrears_paid_cy'])?></td>
<td class="text-right"><?=fmt($G['opening_rent_balance'])?></td>

<td class="text-right"><?=fmt($G['penalty_opening'])?></td>
<td class="text-right"><?=fmt($G['penalty_paid_opening'])?></td>
<td class="text-right"><?=fmt($G['penalty_balance_opening'])?></td>

<td class="text-right"><?=fmt($G['opening_outstanding'])?></td>

<td class="text-right"><?=fmt($G['cy_rent_due'])?></td>
<td class="text-right"><?=fmt($G['cy_discount'])?></td>
<td class="text-right"><?=fmt($G['cy_rent_received'])?></td>
<td class="text-right"><?=fmt($G['cy_outstanding'])?></td>

<td class="text-right"><?=fmt($G['cy_penalty'])?></td>
<td class="text-right"><?=fmt($G['year_end_outstanding'])?></td>

<td class="text-right"><?=fmt($G['premium_opening_bal'])?></td>
<td class="text-right"><?=fmt($G['premium_opening_paid'])?></td>
<td class="text-right"><?=fmt($G['outstanding_to_date'])?></td>

<td class="text-right"><?=fmt($G['total_payments_year'])?></td>
</tr>
<?php } ?>
<!-- TOTAL ROW -->
<tr style="font-weight:bold;background:#e0e0e0;">
    <td colspan="3" class="text-center">TOTAL</td>
    <td class="text-center"><?= $total['leases'] ?></td>
    <td class="text-right"><?= fmt($total['extent']) ?></td>

    <td class="text-right"><?= fmt($total['opening_rent_arrears']) ?></td>
    <td class="text-right"><?= fmt($total['arrears_paid_cy']) ?></td>
    <td class="text-right"><?= fmt($total['opening_rent_balance']) ?></td>

    <td class="text-right"><?= fmt($total['penalty_opening']) ?></td>
    <td class="text-right"><?= fmt($total['penalty_paid_opening']) ?></td>
    <td class="text-right"><?= fmt($total['penalty_balance_opening']) ?></td>

    <td class="text-right"><?= fmt($total['opening_outstanding']) ?></td>

    <td class="text-right"><?= fmt($total['cy_rent_due']) ?></td>
    <td class="text-right"><?= fmt($total['cy_discount']) ?></td>
    <td class="text-right"><?= fmt($total['cy_rent_received']) ?></td>
    <td class="text-right"><?= fmt($total['cy_outstanding']) ?></td>

    <td class="text-right"><?= fmt($total['cy_penalty']) ?></td>
    <td class="text-right"><?= fmt($total['year_end_outstanding']) ?></td>

    <td class="text-right"><?= fmt($total['premium_opening_bal']) ?></td>
    <td class="text-right"><?= fmt($total['premium_opening_paid']) ?></td>
    <td class="text-right"><?= fmt($total['outstanding_to_date']) ?></td>

    <td class="text-right"><?= fmt($total['total_payments_year']) ?></td>
</tr>


</tbody>

</table>

</body>
</html>
