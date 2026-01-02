<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');

$id = $_POST['ben_id'] ?? '';
$name = mysqli_real_escape_string($con, $_POST['name'] ?? '');
$name_tamil = mysqli_real_escape_string($con, $_POST['name_tamil'] ?? '');
$name_sinhala = mysqli_real_escape_string($con, $_POST['name_sinhala'] ?? '');
$is_individual = isset($_POST['is_individual']) ? 1 : 0;
$contact_person = mysqli_real_escape_string($con, $_POST['contact_person'] ?? '');
$address = mysqli_real_escape_string($con, $_POST['address'] ?? '');
$address_tamil = mysqli_real_escape_string($con, $_POST['address_tamil'] ?? '');
$address_sinhala = mysqli_real_escape_string($con, $_POST['address_sinhala'] ?? '');
$district = mysqli_real_escape_string($con, $_POST['district'] ?? '');
$ds_division_id = $_POST['ds_division_id'] !== '' ? ($_POST['ds_division_id'] ?? null) : null;
$ds_division_text = mysqli_real_escape_string($con, $_POST['ds_division_text'] ?? '');
$gn_division_id = $_POST['gn_division_id'] !== '' ? ($_POST['gn_division_id'] ?? null) : null;
$gn_division_text = mysqli_real_escape_string($con, $_POST['gn_division_text'] ?? '');
$nic = mysqli_real_escape_string($con, $_POST['nic_reg_no'] ?? '');
$dob = trim($_POST['dob'] ?? '');
$dob_sql = 'NULL';
if ($dob !== '') {
    // Accept only YYYY-MM-DD; otherwise keep NULL
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $dob_sql = "'" . mysqli_real_escape_string($con, $dob) . "'";
    }
}
$nat = mysqli_real_escape_string($con, $_POST['nationality'] ?? '');
$tel = mysqli_real_escape_string($con, $_POST['telephone'] ?? '');
$email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
$language = mysqli_real_escape_string($con, $_POST['language'] ?? 'English');
$location_id = $_POST['location_id'] !== '' ? ($_POST['location_id'] ?? null) : null;

if ($id) {
 
    $id = (int)$id;
    $old = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM beneficiaries WHERE ben_id=$id"));
    if (!$old) {
        echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
        exit;
    }

    // Prepare NEW values (same keys as DB columns)
    $new = [
        'name'            => $name,
        'name_tamil'            => $name_tamil,
        'name_sinhala'            => $name_sinhala,
        'is_individual'   => $is_individual,
        'contact_person'  => $contact_person,
        'address'         => $address,
        'address_tamil'   => $address_tamil,
        'address_sinhala' => $address_sinhala,
        'district'        => $district,
        'ds_division_id'  => $ds_division_id,
        'ds_division_text'=> $ds_division_text,
        'gn_division_id'  => $gn_division_id,
        'gn_division_text'=> $gn_division_text,
        'nic_reg_no'      => $nic,
        'dob'             => ($dob_sql === 'NULL' ? null : $dob),
        'nationality'     => $nat,
        'telephone'       => $tel,
        'email'           => $email,
        'language'        => $language
    ];

    // 🔥 ADD THIS HERE — normalization function
function normalize($v) {
    if ($v === null) return "";
    $v = (string)$v;
    $v = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $v);
    $v = str_replace(["\r", "\n", "\t"], " ", $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    return trim($v);
}

    // 🔥 ADD THIS — comparison loop
    $changes = [];
    foreach ($new as $field => $new_value_raw) {
        $old_value_raw = $old[$field] ?? '';

        // Normalize both values to avoid false logs
        $old_value = normalize($old_value_raw);
        $new_value = normalize($new_value_raw);

        if ($old_value !== $new_value) {
            $changes[] = ucfirst(str_replace('_', ' ', $field)) . ": $old_value > $new_value";
        }
    }

    // Build change text
    $change_text = count($changes) ? implode(" | ", $changes) : "No changes";

    // UPDATE Query
    $sql = "UPDATE beneficiaries SET 
        name='$name',name_sinhala='$name_sinhala',name_tamil='$name_tamil', is_individual='$is_individual', contact_person='$contact_person',
        address='$address',address_tamil='$address_tamil',address_sinhala='$address_sinhala',
         district='$district',
        ds_division_id=" . ($ds_division_id?"'".mysqli_real_escape_string($con,$ds_division_id)."'":"NULL") . ", ds_division_text='$ds_division_text',
        gn_division_id=" . ($gn_division_id?"'".mysqli_real_escape_string($con,$gn_division_id)."'":"NULL") . ", gn_division_text='$gn_division_text',
        nic_reg_no='$nic', dob=$dob_sql, nationality='$nat',
        telephone='$tel', email='$email', language='$language'
        WHERE ben_id=$id";

    if (mysqli_query($con, $sql)) {

        // Log ONLY IF changes exist
        if ($change_text !== "No changes") {
            UserLog('2', 'LTL Beneficiary Edited', 'ID=' . $id . ' | ' . $change_text, $id, 'LTL');
        }

        echo json_encode(['success' => true, 'message' => 'Beneficiary updated!']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
    }
    exit;
 

    // $id = (int)$id;
    // $old = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM beneficiaries WHERE ben_id=$id"));
    // if (!$old) {
    //     echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
    //     exit;
    // }
    // $sql = "UPDATE beneficiaries SET 
    //     name='$name', is_individual='$is_individual', contact_person='$contact_person',
    //     address='$address', district='$district',
    //     ds_division_id=" . ($ds_division_id?"'".mysqli_real_escape_string($con,$ds_division_id)."'":"NULL") . ", ds_division_text='$ds_division_text',
    //     gn_division_id=" . ($gn_division_id?"'".mysqli_real_escape_string($con,$gn_division_id)."'":"NULL") . ", gn_division_text='$gn_division_text',
    //     nic_reg_no='$nic', dob=$dob_sql, nationality='$nat',
    //     telephone='$tel', email='$email', language='$language'
    //     WHERE ben_id=$id";
    // if (mysqli_query($con, $sql)) {
    //     UserLog('2', 'LTL Beneficiary Edited', 'ID='.$id.' Name='.$name);
    //     echo json_encode(['success' => true, 'message' => 'Beneficiary updated!']);
    // } else {
    //     echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
    // }
    // exit;
} else {
    $sql = "INSERT INTO beneficiaries 
        (location_id,name,name_tamil,name_sinhala,is_individual,contact_person,address,address_tamil,address_sinhala,district,ds_division_id,ds_division_text,gn_division_id,gn_division_text,nic_reg_no,dob,nationality,telephone,email,language)
        VALUES 
        (" . ($location_id?"'".mysqli_real_escape_string($con,$location_id)."'":"NULL") . ",'$name','$name_tamil','$name_sinhala','$is_individual','$contact_person','$address','$address_tamil','$address_sinhala','$district'," . ($ds_division_id?"'".mysqli_real_escape_string($con,$ds_division_id)."'":"NULL") . ",'$ds_division_text'," . ($gn_division_id?"'".mysqli_real_escape_string($con,$gn_division_id)."'":"NULL") . ",'$gn_division_text','$nic'," . $dob_sql . ",'$nat','$tel','$email','$language')";
    if (mysqli_query($con, $sql)) {
        $new_id = mysqli_insert_id($con);
        if ($new_id) {
            $md5_ben = md5($new_id . "key-dtecstudio");
            mysqli_query($con, "UPDATE beneficiaries SET md5_ben_id='$md5_ben' WHERE ben_id=$new_id");
        }
        UserLog('2', 'LTL Beneficiary Created', 'ID='.$new_id.' Name='.$name, $new_id, 'LTL');
        echo json_encode(['success' => true, 'message' => 'Beneficiary added!']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
    }
    exit;
}
