<?php
// include '../../db.php';
// include '../../auth.php';

// $id = $_POST['lease_type_id'] ?? '';

// $type = mysqli_real_escape_string($con,$_POST['lease_type_name']);
// $base = $_POST['base_rent_percent'];
// $prem = $_POST['premium_percent'];
// $dur  = $_POST['duration_years'];
// $revInt = $_POST['revision_interval'];
// $revPct = $_POST['revision_increase_percent'];
// $pen = $_POST['penalty_rate'];
// $waiver = $_POST['allow_interest_waiver'];
// $eff = $_POST['effective_from'];

// if($id){ // update
//   $sql = "UPDATE lease_master SET
//           lease_type_name='$type', base_rent_percent='$base', premium_percent='$prem',
//           duration_years='$dur', revision_interval='$revInt', revision_increase_percent='$revPct',
//           penalty_rate='$pen', allow_interest_waiver='$waiver', effective_from='$eff'
//           WHERE lease_type_id='$id'";
//   mysqli_query($con,$sql);
//   echo "Updated successfully!";
// } else { // insert
//   $sql = "INSERT INTO lease_master 
//           (lease_type_name, base_rent_percent, premium_percent, duration_years, revision_interval, revision_increase_percent, penalty_rate, allow_interest_waiver, effective_from)
//           VALUES 
//           ('$type','$base','$prem','$dur','$revInt','$revPct','$pen','$waiver','$eff')";
//   mysqli_query($con,$sql);
//   echo "Added successfully!";
// }

 
include '../../db.php';
include '../../auth.php';

$id = $_POST['lease_type_id'] ?? '';


$type   = mysqli_real_escape_string($con,$_POST['lease_type_name']);
$purpose = mysqli_real_escape_string($con,$_POST['purpose']);
$base   = $_POST['base_rent_percent'];
$prem   = $_POST['premium_percent'];
$ecoRate = isset($_POST['economy_rate']) ? $_POST['economy_rate'] : '';
$ecoVal  = isset($_POST['economy_valuvation']) ? $_POST['economy_valuvation'] : '';
$dur    = $_POST['duration_years'];
$revInt = $_POST['revision_interval'];
$revPct = $_POST['revision_increase_percent'];
$pen    = $_POST['penalty_rate'];
$waiver = $_POST['allow_interest_waiver'];
$eff    = $_POST['effective_from'];
$discount = isset($_POST['discount_rate']) ? $_POST['discount_rate'] : '';
$premium_times = isset($_POST['premium_times']) ? intval($_POST['premium_times']) : 0;


if($id){ 
    // 🔹 Fetch existing row
    $old = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM lease_master WHERE lease_type_id='$id'"));
    // 🔹 Compare fields
    $changes = [];
    if($old['lease_type_name']          != $type)   $changes[] = "Lease Type changed from '{$old['lease_type_name']}' to '$type'";
    if($old['purpose']                  != $purpose)$changes[] = "Purpose changed from '{$old['purpose']}' to '$purpose'";
    if($old['base_rent_percent']        != $base)   $changes[] = "Base Rent % changed from {$old['base_rent_percent']} to $base";
    if($old['premium_percent']          != $prem)   $changes[] = "Premium % changed from {$old['premium_percent']} to $prem";
    if(isset($old['economy_rate']) && $old['economy_rate'] != $ecoRate) $changes[] = "Economy Rate % changed from {$old['economy_rate']} to $ecoRate";
    if(isset($old['economy_valuvation']) && $old['economy_valuvation'] != $ecoVal) $changes[] = "Economy Valuvation changed from {$old['economy_valuvation']} to $ecoVal";
    if($old['duration_years']           != $dur)    $changes[] = "Duration changed from {$old['duration_years']} yrs to $dur yrs";
    if($old['revision_interval']        != $revInt) $changes[] = "Revision Interval changed from {$old['revision_interval']} yrs to $revInt yrs";
    if($old['revision_increase_percent']!= $revPct) $changes[] = "Revision % changed from {$old['revision_increase_percent']}% to $revPct%";
    if($old['penalty_rate']             != $pen)    $changes[] = "Penalty % changed from {$old['penalty_rate']}% to $pen%";
    if($old['allow_interest_waiver']    != $waiver) $changes[] = "Waiver option changed from ".($old['allow_interest_waiver']?'Yes':'No')." to ".($waiver?'Yes':'No');
    if($old['effective_from']           != $eff)    $changes[] = "Effective From changed from {$old['effective_from']} to $eff";
    if(isset($old['discount_rate']) && $old['discount_rate'] != $discount) $changes[] = "Discount % changed from {$old['discount_rate']} to $discount";
    if(isset($old['premium_times']) && intval($old['premium_times']) != $premium_times) $changes[] = "Premium Times changed from {$old['premium_times']} to $premium_times";

    $detail = implode(" | ", $changes);

        $sql = "UPDATE lease_master SET
            lease_type_name='$type', purpose='$purpose', base_rent_percent='$base', premium_percent='$prem',
            economy_rate='$ecoRate', economy_valuvation='$ecoVal',
            duration_years='$dur', revision_interval='$revInt', revision_increase_percent='$revPct',
            penalty_rate='$pen', allow_interest_waiver='$waiver', effective_from='$eff', discount_rate='$discount', premium_times='$premium_times'
            WHERE lease_type_id='$id'";
    if(mysqli_query($con,$sql)){
        if($detail=="") $detail="No changes detected.";
        UserLog("1", "Lease Master Edited", $detail);
        echo "Updated successfully!";
    } else {
        echo "Error: " . mysqli_error($con);
    }

} else { 
    // 🔹 Insert new
        $sql = "INSERT INTO lease_master 
            (lease_type_name, purpose, base_rent_percent, premium_percent, economy_rate, economy_valuvation, duration_years, revision_interval, revision_increase_percent, penalty_rate, allow_interest_waiver, effective_from, discount_rate, premium_times)
            VALUES 
            ('$type','$purpose','$base','$prem','$ecoRate','$ecoVal','$dur','$revInt','$revPct','$pen','$waiver','$eff','$discount','$premium_times')";
    if(mysqli_query($con,$sql)){
        $detail = "Lease Master Created: ".
              "LeaseType=$type | Purpose=$purpose | BaseRent=$base% | EconomyRate=$ecoRate% | EconomyValuvation=$ecoVal | Premium=$prem% | Duration=$dur yrs | ".
                  "Revision Every $revInt yrs +$revPct% | Penalty=$pen% | Waiver=".($waiver?'Yes':'No')." | EffectiveFrom=$eff | Discount=$discount% | PremiumTimes=$premium_times";
        UserLog("1", "Lease Master Created", $detail);
        echo "Added successfully!";
    } else {
        echo "Error: " . mysqli_error($con);
    }
}
