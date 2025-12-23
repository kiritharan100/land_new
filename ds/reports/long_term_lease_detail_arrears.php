<?php
// long_term_lease_detail_arrears.php
// FULL REPORT with lease_type filter

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($v,$d=2){ if(!$v) $v=0; return number_format((float)$v,$d); }

/*--------------------------------------
 1. Resolve Location
---------------------------------------*/
$client_md5 = $_COOKIE['client_cook'] ?? '';
$location_id=''; $client_name='';
 

if($client_md5){
    $q=mysqli_prepare($con,"SELECT c_id,client_name FROM client_registration WHERE md5_client=? LIMIT 1");
    mysqli_stmt_bind_param($q,'s',$client_md5);
    mysqli_stmt_execute($q);
    $r=mysqli_stmt_get_result($q);
    if($r && ($row=mysqli_fetch_assoc($r))){
        $location_id=$row['c_id'];
        $client_name=$row['client_name'];
    }
}
if(!$location_id){
    echo "<div>No working location selected.</div>";
    exit;
}

/*--------------------------------------
 2. Validate as_at
---------------------------------------*/
$asAt=$_GET['as_at'] ?? '';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$asAt)){
    echo "<div>Invalid date.</div>";
    exit;
}

$leaseType = $_GET['lease_type'] ?? 'All';   // NEW FILTER
$asAtDate=$asAt;

$year=(int)substr($asAtDate,0,4);
$prevYear=$year-1;

$prevYearEnd="$prevYear-12-31";
$currYearStart="$year-01-01";
$today=date('Y-m-d H:i');

/*--------------------------------------
 3. Load leases with lease_type filter
---------------------------------------*/
$leases=[];
$sql="
SELECT 
 l.lease_id,l.file_number,l.lease_number,l.approved_date,
 l.valuation_amount,l.value_date,l.type_of_project,
 l.start_date,l.end_date,
 b.name AS beneficiary_name,b.telephone,
 lr.extent_ha,
 g.gn_name,
 fv.visite_status,fv.date AS visite_date
FROM leases l
INNER JOIN beneficiaries b ON b.ben_id=l.beneficiary_id
LEFT JOIN ltl_land_registration lr ON lr.land_id=l.land_id
LEFT JOIN gn_division g ON g.gn_id=lr.gn_id
LEFT JOIN (
    SELECT v1.lease_id,v1.visite_status,v1.date
    FROM ltl_feald_visits v1
    INNER JOIN(
       SELECT lease_id,MAX(date) AS max_date
       FROM ltl_feald_visits WHERE status=1 GROUP BY lease_id
    )t ON t.lease_id=v1.lease_id AND t.max_date=v1.date
    WHERE v1.status=1
)fv ON fv.lease_id=l.lease_id
WHERE 
  l.location_id=? 
  AND l.status!='cancelled'
  AND l.start_date<=?
  AND ( ?='All' OR l.type_of_project=? )   /* NEW FILTER */
ORDER BY l.file_number,l.lease_number,l.lease_id
";

$s=mysqli_prepare($con,$sql);
mysqli_stmt_bind_param($s,'isss',$location_id,$asAtDate,$leaseType,$leaseType);
mysqli_stmt_execute($s);
$r=mysqli_stmt_get_result($s);
while($row=mysqli_fetch_assoc($r)) $leases[]=$row;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Lease Arrears</title>
<style>
body{font-family:Arial;margin:16px;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #222;padding:4px 6px;font-size:11px;}
th{background:#f2f2f2;}
.text-right{text-align:right}
.text-center{text-align:center}
@media print{@page{size:A4 landscape;margin:10mm}}
</style>
<script>window.onload=()=>{try{window.print();}catch(e){}};</script>
</head>
<body>

<h3>Long Term Lease Detail Arrears</h3>
<b><?=h($client_name)?></b><br>
As at: <?=h($asAtDate)?>  
<br>Opening: <?=$prevYearEnd?>  
<br>Lease Type: <b><?=h($leaseType)?></b> <!-- NEW -->
<br>Printed: <?=$today?><br><br>

<table>
<thead>
<tr>
<th>SN</th>
<th>File No</th>
<th>LCG No</th>
<th>Approved</th>
<th>Holder</th>
<th>Tel</th>
<th>Extent(Ha)</th>
<th class="text-right">Valuation</th>
<th>Val.Date</th>
<th>GN</th>
<th>Purpose</th>

<th class="text-right">Rent Arrears<br>till <?=$prevYearEnd?></th>
<th class="text-right">Arrears<br>Paid(CY)</th>
<th class="text-right">Balance<br>Rent Arrears</th>

<th class="text-right">Penalty<br>Arrears</th>
<th class="text-right">Penalty<br>Paid</th>
<th class="text-right">Penalty<br>Balance</th>

<th class="text-right">Opening<br>Outstanding</th>

<th class="text-right">CY Annual<br> Rent</th>
<th class="text-right">Discount<br>(CY)</th>
<th class="text-right">Received<br>(CY)</th>

<th class="text-right">CY<br>Outstanding</th>
<th class="text-right">Penalty<br>For the Year</th>
 
<th class="text-right">Year-End<br>Outstanding</th>

<th class="text-right">Premium<br>Balance(Opening)</th>
<th class="text-right">Premium<br>Paid(Opening)</th>
<th class="text-right">Outstanding<br>To Date</th>

<th class="text-right">Total Payment<br>For the Year</th>

<th>Last Pay</th>
<th>Visit</th>
<th>Visit Date</th>
</tr>
</thead>
<tbody>
<?php
$sn=1;
$grandOutstandingToDate = 0.0; // Sum of Outstanding To Date (incl. premium)
// TOTALS FOR ALL COLUMNS
$T = [
    'extent' => 0,
    'valuation' => 0,
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
    'total_pay_year' => 0
];

foreach($leases as $L){

$lid=(int)$L['lease_id'];

/*--------------------------------------
 4. Load schedules
---------------------------------------*/
$sch=[];
$q=mysqli_prepare($con,"
 SELECT schedule_id,start_date,annual_amount,panalty,premium
 FROM lease_schedules
 WHERE lease_id=? AND status=1 AND start_date<=?
 ORDER BY start_date,schedule_id
");
mysqli_stmt_bind_param($q,'is',$lid,$asAtDate);
mysqli_stmt_execute($q);
$rs=mysqli_stmt_get_result($q);
while($r=mysqli_fetch_assoc($rs)) $sch[]=$r;

/*--------------------------------------
 5. Load payments
---------------------------------------*/
$pay=[];
$q=mysqli_prepare($con,"
 SELECT p.payment_date,p.rent_paid,p.current_year_payment,
        p.panalty_paid,p.discount_apply,p.premium_paid,
        s.start_date AS sched_start
 FROM lease_payments p
 LEFT JOIN lease_schedules s ON s.schedule_id=p.schedule_id
 WHERE p.lease_id=? AND p.status=1 AND p.payment_date<=?
");
mysqli_stmt_bind_param($q,'is',$lid,$asAtDate);
mysqli_stmt_execute($q);
$rs=mysqli_stmt_get_result($q);
while($r=mysqli_fetch_assoc($rs)) $pay[]=$r;

/*--------------------------------------
 6. Opening & current buckets
---------------------------------------*/
$openingRentDue=0;
$openingPenaltyDue=0;
$openingPremiumDue=0;
$currentYearRentDue=0;

foreach($sch as $S){
    $sd=$S['start_date'];
    $openingRentDue     += ($sd <= $prevYearEnd ? (float)$S['annual_amount'] : 0);
    $openingPenaltyDue  += ($sd <= $prevYearEnd ? (float)$S['panalty'] : 0);
    $openingPremiumDue  += ($sd <= $prevYearEnd ? (float)$S['premium'] : 0);

    if($sd >= $currYearStart && $sd <= $asAtDate){
        $currentYearRentDue += (float)$S['annual_amount'];
    }
}

/*--------------------------------------
 7. Payments Split
---------------------------------------*/
$rentPaidOpenPrev=0; $discOpenPrev=0;
$rentPaidOpenCurr=0;
$penaltyPaidOpening=0;
$rentPaidCurrYr=0; $discCurrYr=0;
$openingPremiumPaid=0;
$totalPaymentForYear=0;
$lastPayment=null;

foreach($pay as $P){

    $pd=$P['payment_date'];
    $sd=$P['sched_start'];

    $rent=(float)$P['rent_paid'] ;
    $disc=(float)$P['discount_apply'];
    $pen=(float)$P['panalty_paid'];
    $prem=(float)$P['premium_paid'];

    if(!$lastPayment || $pd>$lastPayment) $lastPayment=$pd;

    // Opening schedules
    if($sd <= $prevYearEnd){
        if($pd <= $prevYearEnd){
            $rentPaidOpenPrev+=$rent;
            $discOpenPrev+=$disc;
            $openingPremiumPaid+=$prem;
        }
        if($pd >= $currYearStart && $pd <= $asAtDate){
            $rentPaidOpenCurr+=$rent;
        }
        if($pd <= $asAtDate){
            $penaltyPaidOpening+=$pen;
        }
    }

    // Current year
    if($sd >= $currYearStart && $sd <= $asAtDate){
        if($pd >= $currYearStart && $pd <= $asAtDate){
            $rentPaidCurrYr+=$rent;
            $discCurrYr+=$disc;
        }
    }

    // NEW — Payment for whole year
    if($pd >= $currYearStart && $pd <= $asAtDate){
        $totalPaymentForYear += ($rent + $pen + $prem);
    }
}

/*--------------------------------------
 7A. Penalty for the Current Year
---------------------------------------*/
$currentYearPenaltyDue = 0;
$currentYearPenaltyPaid = 0;

// Penalty due for current year (based on schedules)
foreach($sch as $S){
    $sd = $S['start_date'];
    if($sd >= $currYearStart && $sd <= $asAtDate){
        $currentYearPenaltyDue += (float)$S['panalty'];
    }
}

// Penalty paid for current year (based on payments)
foreach($pay as $P){
    $pd = $P['payment_date'];
    $sd = $P['sched_start'];
    if($sd >= $currYearStart && $sd <= $asAtDate){
        if($pd >= $currYearStart && $pd <= $asAtDate){
            $currentYearPenaltyPaid += (float)$P['panalty_paid'];
        }
    }
}

$currentYearPenaltyBalance = $currentYearPenaltyDue - $currentYearPenaltyPaid;

/*--------------------------------------
 8. Final Calcs
---------------------------------------*/
$openingRentArrears = $openingRentDue - ($rentPaidOpenPrev + $discOpenPrev);
$arrearsPaidCurrYr   = $rentPaidOpenCurr;
$balanceLeaseOpening = $openingRentArrears - $arrearsPaidCurrYr;

$openingPenaltyBal   = $openingPenaltyDue - $penaltyPaidOpening;

$openingOutstanding  = $balanceLeaseOpening + $openingPenaltyBal;

$outstandingCurrYr   = $currentYearRentDue - $discCurrYr - $rentPaidCurrYr;

$totalYrEnd          = $openingOutstanding + $outstandingCurrYr;

$openingPremiumBalance = $openingPremiumDue - $openingPremiumPaid;

$totalInclPremium = $totalYrEnd + $openingPremiumBalance;
$grandOutstandingToDate += $totalInclPremium;


// ACCUMULATE TOTALS
$T['extent'] += (float)$L['extent_ha'];
$T['valuation'] += (float)$L['valuation_amount'];

$T['opening_rent_arrears'] += $openingRentArrears;
$T['arrears_paid_cy'] += $arrearsPaidCurrYr;
$T['opening_rent_balance'] += $balanceLeaseOpening;

$T['penalty_opening'] += $openingPenaltyDue;
$T['penalty_paid_opening'] += $penaltyPaidOpening;
$T['penalty_balance_opening'] += $openingPenaltyBal;

$T['opening_outstanding'] += $openingOutstanding;

$T['cy_rent_due'] += $currentYearRentDue;
$T['cy_discount'] += $discCurrYr;
$T['cy_rent_received'] += $rentPaidCurrYr;
$T['cy_outstanding'] += $outstandingCurrYr;

$T['cy_penalty'] += $currentYearPenaltyBalance;
$T['year_end_outstanding'] += ($totalYrEnd + $currentYearPenaltyBalance);

$T['premium_opening_bal'] += $openingPremiumBalance;
$T['premium_opening_paid'] += $openingPremiumPaid;

$T['outstanding_to_date'] += ($totalInclPremium + $currentYearPenaltyBalance);

$T['total_pay_year'] += $totalPaymentForYear;



?>
<tr>
<td class="text-center"><?=$sn++?></td>
<td><?=h($L['file_number'])?></td>
<td><?=h($L['lease_number'])?></td>
<td><?=h($L['approved_date'])?></td>
<td><?=h($L['beneficiary_name'])?></td>
<td><?=h($L['telephone'])?></td>
<td class="text-right"><?=fmt($L['extent_ha'])?></td>
<td class="text-right"><?=fmt($L['valuation_amount'])?></td>
<td><?=h($L['value_date'])?></td>
<td><?=h($L['gn_name'])?></td>
<td><?=h($L['type_of_project'])?></td>

<td class="text-right"><?=fmt($openingRentArrears)?></td>
<td class="text-right"><?=fmt($arrearsPaidCurrYr)?></td>
<td class="text-right"><?=fmt($balanceLeaseOpening)?></td>

<td class="text-right"><?=fmt($openingPenaltyDue)?></td>
<td class="text-right"><?=fmt($penaltyPaidOpening)?></td>
<td class="text-right"><?=fmt($openingPenaltyBal)?></td>

<td class="text-right"><?=fmt($openingOutstanding)?></td>

<td class="text-right"><?=fmt($currentYearRentDue)?></td>
<td class="text-right"><?=fmt($discCurrYr)?></td>
<td class="text-right"><?=fmt($rentPaidCurrYr)?></td>
<td class="text-right"><?=fmt($outstandingCurrYr)?></td>
<td class="text-right"><?=fmt($currentYearPenaltyBalance)?></td>
<td class="text-right"><?=fmt($totalYrEnd+$currentYearPenaltyBalance)?></td>

<td class="text-right"><?=fmt($openingPremiumBalance)?></td>
<td class="text-right"><?=fmt($openingPremiumPaid)?></td>
<td class="text-right"><?=fmt($totalInclPremium+$currentYearPenaltyBalance)?></td>

<td class="text-right"><?=fmt($totalPaymentForYear)?></td>

<td><?=h($lastPayment ?: '-')?></td>
<td><?=h($L['visite_status'])?></td>
<td><?=h($L['visite_date'])?></td>
</tr>
<?php } ?>

<!-- TOTAL ROW -->
<tr style="font-weight:bold;background:#e8e8e8;">
    <td colspan="6" class="text-center">TOTAL</td>

    <td class="text-right"><?=fmt($T['extent'])?></td>
    <td class="text-right"><?=fmt($T['valuation'])?></td>
    <td></td>
    <td></td>
    <td></td>

    <td class="text-right"><?=fmt($T['opening_rent_arrears'])?></td>
    <td class="text-right"><?=fmt($T['arrears_paid_cy'])?></td>
    <td class="text-right"><?=fmt($T['opening_rent_balance'])?></td>

    <td class="text-right"><?=fmt($T['penalty_opening'])?></td>
    <td class="text-right"><?=fmt($T['penalty_paid_opening'])?></td>
    <td class="text-right"><?=fmt($T['penalty_balance_opening'])?></td>

    <td class="text-right"><?=fmt($T['opening_outstanding'])?></td>

    <td class="text-right"><?=fmt($T['cy_rent_due'])?></td>
    <td class="text-right"><?=fmt($T['cy_discount'])?></td>
    <td class="text-right"><?=fmt($T['cy_rent_received'])?></td>
    <td class="text-right"><?=fmt($T['cy_outstanding'])?></td>
    <td class="text-right"><?=fmt($T['cy_penalty'])?></td>

    <td class="text-right"><?=fmt($T['year_end_outstanding'])?></td>

    <td class="text-right"><?=fmt($T['premium_opening_bal'])?></td>
    <td class="text-right"><?=fmt($T['premium_opening_paid'])?></td>

    <td class="text-right"><?=fmt($T['outstanding_to_date'])?></td>

    <td class="text-right"><?=fmt($T['total_pay_year'])?></td>

    <td colspan="3"></td>
</tr>



</tbody>
 </table>

<br>
<h4>Total Outstanding To Date (as at <?=h($asAtDate)?>): Rs. <?=fmt($grandOutstandingToDate)?></h4>

</body>
</html>
