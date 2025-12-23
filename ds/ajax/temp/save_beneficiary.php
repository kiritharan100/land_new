<?php
include '../../db.php';
include '../../auth.php';

header('Content-Type: application/json');

$id = $_POST['ben_id'] ?? '';
$name = mysqli_real_escape_string($con,$_POST['name']);
$is_individual = isset($_POST['is_individual']) ? 1 : 0;
$contact_person = mysqli_real_escape_string($con,$_POST['contact_person']);
$address = mysqli_real_escape_string($con,$_POST['address']);
$district = $_POST['district'];
$ds_division_id = $_POST['ds_division_id'] ?: NULL;
$ds_division_text = $_POST['ds_division_text'];
$gn_division_id = $_POST['gn_division_id'] ?: NULL;
$gn_division_text = $_POST['gn_division_text'];
$nic = $_POST['nic_reg_no'];
$dob = $_POST['dob'];
$nat = $_POST['nationality'];
$tel = $_POST['telephone'];
$email = $_POST['email'];
$language = isset($_POST['language']) ? mysqli_real_escape_string($con, $_POST['language']) : 'English';
$location_id = $_POST['location_id'] ?? NULL;

if($id){
  // Fetch old row
  $old = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM short_term_beneficiaries WHERE ben_id='$id'"));
  $changes = [];
  if($old['name']            != $name)            $changes[] = "Name changed from '{$old['name']}' to '$name'";
  if($old['is_individual']   != $is_individual)   $changes[] = "Type changed from ".($old['is_individual']?'Individual':'Institution')." to ".($is_individual?'Individual':'Institution');
  if($old['contact_person']  != $contact_person)  $changes[] = "Contact Person changed from '{$old['contact_person']}' to '$contact_person'";
  if($old['address']         != $address)         $changes[] = "Address changed from '{$old['address']}' to '$address'";
  if($old['district']        != $district)        $changes[] = "District changed from '{$old['district']}' to '$district'";
  if($old['ds_division_id']  != $ds_division_id)  $changes[] = "DS Division changed from '{$old['ds_division_id']}' to '$ds_division_id'";
  if($old['ds_division_text']!= $ds_division_text)$changes[] = "DS Division Text changed from '{$old['ds_division_text']}' to '$ds_division_text'";
  if($old['gn_division_id']  != $gn_division_id)  $changes[] = "GN Division changed from '{$old['gn_division_id']}' to '$gn_division_id'";
  if($old['gn_division_text']!= $gn_division_text)$changes[] = "GN Division Text changed from '{$old['gn_division_text']}' to '$gn_division_text'";
  if($old['nic_reg_no']      != $nic)             $changes[] = "NIC/Reg No changed from '{$old['nic_reg_no']}' to '$nic'";
  if($old['dob']             != $dob)             $changes[] = "DOB changed from '{$old['dob']}' to '$dob'";
  if($old['nationality']     != $nat)             $changes[] = "Nationality changed from '{$old['nationality']}' to '$nat'";
  if($old['telephone']       != $tel)             $changes[] = "Telephone changed from '{$old['telephone']}' to '$tel'";
  if($old['email']           != $email)           $changes[] = "Email changed from '{$old['email']}' to '$email'";
  if(isset($old['language']) && $old['language'] != $language) $changes[] = "Language changed from '{$old['language']}' to '$language'";
  $detail = implode(" | ", $changes);

    $sql = "UPDATE short_term_beneficiaries SET 
      name='$name', is_individual='$is_individual', contact_person='$contact_person',
      address='$address', district='$district',
      ds_division_id='$ds_division_id', ds_division_text='$ds_division_text',
      gn_division_id='$gn_division_id', gn_division_text='$gn_division_text',
      nic_reg_no='$nic', dob='$dob', nationality='$nat',
      telephone='$tel', email='$email', language='$language'
      WHERE ben_id='$id'";
  if(mysqli_query($con,$sql)){
    if($detail=="") $detail="No changes detected.";
    UserLog("2", "Beneficiary Edited", $detail);
    echo json_encode(['success' => true, 'message' => 'Beneficiary updated!']);
    exit;
  } else {
    $err = "Error: " . mysqli_error($con);
    echo json_encode(['success' => false, 'message' => $err]);
    exit;
  }
} else {
    $sql = "INSERT INTO short_term_beneficiaries 
      (location_id,name,is_individual,contact_person,address,district,ds_division_id,ds_division_text,gn_division_id,gn_division_text,nic_reg_no,dob,nationality,telephone,email,language)
      VALUES 
      ('$location_id','$name','$is_individual','$contact_person','$address','$district','$ds_division_id','$ds_division_text','$gn_division_id','$gn_division_text','$nic','$dob','$nat','$tel','$email','$language')";
  if(mysqli_query($con,$sql)){
    // After successful insert, compute and store md5_ben_id = md5(ben_id . "keydtecstudio")
    $new_id = mysqli_insert_id($con);
    if ($new_id) {
      $md5_ben = md5($new_id . "key-dtecstudio");
      // Best-effort update; ignore failure but do not block main success flow
      mysqli_query($con, "UPDATE short_term_beneficiaries SET md5_ben_id='$md5_ben' WHERE ben_id='$new_id'");
    }

    $detail = "Beneficiary Created: Name=$name | Type=".($is_individual?'Individual':'Institution')." | Contact=$contact_person | Address=$address | District=$district | DS Division=".($ds_division_id?$ds_division_id:$ds_division_text)." | GN Division=".($gn_division_id?$gn_division_id:$gn_division_text)." | NIC=$nic | DOB=$dob | Nationality=$nat | Tel=$tel | Email=$email";
    UserLog("2", "Beneficiary Created", $detail);
    echo json_encode(['success' => true, 'message' => 'Beneficiary added!']);
    exit;
  } else {
    $err = "Error: " . mysqli_error($con);
    echo json_encode(['success' => false, 'message' => $err]);
    exit;
  }
}
