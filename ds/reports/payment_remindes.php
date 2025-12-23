<?php
// long_term_lease_detail_arrears.php
// FINAL VERSION – includes new reminder logic

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
 2. Validate year -> as_at = 31/12/{year}
---------------------------------------*/
$yearParam = $_GET['year'] ?? '';
if(!preg_match('/^\d{4}$/', $yearParam)){
    echo "<div>Invalid or missing year. Use ?year=2025</div>";
    exit;
}
$year=(int)$yearParam;
$asAtDate=sprintf('%04d-12-31',$year);
$prevYear=$year-1;

$prevYearEnd="$prevYear-12-31";
$currYearStart="$year-01-01";
$today=date('Y-m-d H:i');

/*--------------------------------------
 3. Load leases
---------------------------------------*/
$leases=[];
$sql="
SELECT 
 l.lease_id,l.file_number,l.lease_number,l.approved_date,
 b.name AS beneficiary_name,
 l.start_date,l.end_date
FROM leases l
INNER JOIN beneficiaries b ON b.ben_id=l.beneficiary_id
WHERE 
  l.location_id=? 
  AND l.status!='cancelled'
  AND l.start_date<=?
ORDER BY l.file_number,l.lease_number,l.lease_id
";

$s=mysqli_prepare($con,$sql);
mysqli_stmt_bind_param($s,'is',$location_id,$asAtDate);
mysqli_stmt_execute($s);
$r=mysqli_stmt_get_result($s);
while($row=mysqli_fetch_assoc($r)) $leases[]=$row;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Payment Reminders</title>
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

<h3>Payment Reminders</h3>
<b><?=h($client_name)?></b><br>
Up to: <?=h($asAtDate)?>  
 
<br>Printed: <?=$today?><br><br>

<table>
<thead>
<tr>
<th>SN</th>
<th>File No</th>
<th>LCG No</th>
<th>Approved Date</th>
<th>Holder</th>
<th class="text-right">CY Annual</th>
<th class="text-right">Outstanding To Date</th>
<th>Current Period<br>Start Date</th>
<th>Recovery Letter</th>
<th>Annexure 09</th>
<th>Annexure 12</th>
<th>Annexure 12A</th>
</tr>
</thead>
<tbody>
<?php
$sn=1;
$grandOutstandingToDate = 0.0;

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
 9. Current Period Start Date
---------------------------------------*/
$currentPeriodStartDate = '';
foreach($sch as $S){
    if($S['start_date'] >= $currYearStart && $S['start_date'] <= $asAtDate){
        $currentPeriodStartDate = $S['start_date'];
        break;
    }
}


/*--------------------------------------
 10. NEW REMINDER LOGIC (FINAL)
---------------------------------------*/

$remRecovery = [];
$remA09 = [];
$remA12 = [];
$remA12A = [];

if ($currentPeriodStartDate) {

    // NEW RULE:
    // Include reminders if:
    // sent_date >= start_date - 60 days
    // sent_date <= start_date + 10 months

    $rangeStart = date('Y-m-d', strtotime($currentPeriodStartDate . ' -60 days'));
    $rangeEnd   = date('Y-m-d', strtotime($currentPeriodStartDate . ' +10 months'));

    $remSql = "
        SELECT reminders_type, sent_date
        FROM ltl_reminders
        WHERE lease_id=? 
          AND status=1
          AND sent_date BETWEEN ? AND ?
        ORDER BY sent_date ASC
    ";

    $stR = mysqli_prepare($con, $remSql);
    mysqli_stmt_bind_param($stR, 'iss', $lid, $rangeStart, $rangeEnd);
    mysqli_stmt_execute($stR);
    $resR = mysqli_stmt_get_result($stR);

    while ($rr = mysqli_fetch_assoc($resR)) {
        $d = $rr['sent_date'];

        switch ($rr['reminders_type']) {
            case 'Recovery Letter': $remRecovery[] = $d; break;
            case 'Annexure 09':     $remA09[] = $d; break;
            case 'Annexure 12':     $remA12[] = $d; break;
            case 'Annexure 12A':    $remA12A[] = $d; break;
        }
    }
}

$remRecoveryStr = implode("<br>", $remRecovery);
$remA09Str      = implode("<br>", $remA09);
$remA12Str      = implode("<br>", $remA12);
$remA12AStr     = implode("<br>", $remA12A);

?>
<tr>
<td class="text-center"><?=$sn++?></td>
<td><?=h($L['file_number'])?></td>
<td><?=h($L['lease_number'])?></td>
<td><?=h($L['approved_date'])?></td>
<td><?=h($L['beneficiary_name'])?></td>
<td class="text-right">0.00</td>
<td class="text-right">0.00</td>
<td><?=h($currentPeriodStartDate)?></td>
<td><?= $remRecoveryStr ?></td>
<td><?= $remA09Str ?></td>
<td><?= $remA12Str ?></td>
<td><?= $remA12AStr ?></td>
</tr>
<?php } ?>
</tbody>
 </table>

</body>
</html>
