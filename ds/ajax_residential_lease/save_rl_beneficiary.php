<?php
include '../../db.php';
include '../../auth.php';
header('Content-Type: application/json');

$id = $_POST['rl_ben_id'] ?? '';
$name = mysqli_real_escape_string($con, $_POST['name'] ?? '');
$name_tamil = mysqli_real_escape_string($con, $_POST['name_tamil'] ?? '');
$name_sinhala = mysqli_real_escape_string($con, $_POST['name_sinhala'] ?? '');
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
if ($dob !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dob)) {
    $dob_sql = "'" . mysqli_real_escape_string($con, $dob) . "'";
}
$nat = mysqli_real_escape_string($con, $_POST['nationality'] ?? '');
$tel = mysqli_real_escape_string($con, $_POST['telephone'] ?? '');
$email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
$language = mysqli_real_escape_string($con, $_POST['language'] ?? 'English');

$location_id = null;
if (isset($_COOKIE['client_cook'])) {
    $selected_client = $_COOKIE['client_cook'];
    $sel_query = "SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1";
    if ($stmtC = mysqli_prepare($con, $sel_query)) {
        mysqli_stmt_bind_param($stmtC, 's', $selected_client);
        mysqli_stmt_execute($stmtC);
        $resC = mysqli_stmt_get_result($stmtC);
        if ($resC && ($rowC = mysqli_fetch_assoc($resC))) {
            $location_id = (int)$rowC['c_id'];
        }
        mysqli_stmt_close($stmtC);
    }
}

if ($id) {
    $id = (int)$id;
    $old = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM rl_beneficiaries WHERE rl_ben_id=$id"));
    if (!$old) {
        echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
        exit;
    }

    // Prepare NEW values (same keys as DB columns)
    $new = [
        'name'            => $name,
        'name_tamil'      => $name_tamil,
        'name_sinhala'    => $name_sinhala,
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

    // Normalize to avoid false positives
    function normalize_rl($v) {
        if ($v === null) return "";
        $v = (string)$v;
        $v = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $v);
        $v = str_replace(["\r", "\n", "\t"], " ", $v);
        $v = preg_replace('/\s+/u', ' ', $v);
        return trim($v);
    }

    $changes = [];
    foreach ($new as $field => $new_value_raw) {
        $old_value_raw = $old[$field] ?? '';
        $old_value = normalize_rl($old_value_raw);
        $new_value = normalize_rl($new_value_raw);
        if ($old_value !== $new_value) {
            $changes[] = ucfirst(str_replace('_', ' ', $field)) . ": $old_value > $new_value";
        }
    }
    $change_text = count($changes) ? implode(" | ", $changes) : "No changes";

    $sql = "UPDATE rl_beneficiaries SET 
        name='$name', name_tamil='$name_tamil', name_sinhala='$name_sinhala',
        address='$address', address_tamil='$address_tamil', address_sinhala='$address_sinhala',
        district='$district',
        ds_division_id=" . ($ds_division_id ? "'" . mysqli_real_escape_string($con, $ds_division_id) . "'" : "NULL") . ",
        ds_division_text='$ds_division_text',
        gn_division_id=" . ($gn_division_id ? "'" . mysqli_real_escape_string($con, $gn_division_id) . "'" : "NULL") . ",
        gn_division_text='$gn_division_text',
        nic_reg_no='$nic', dob=$dob_sql, nationality='$nat',
        telephone='$tel', email='$email', language='$language'
        WHERE rl_ben_id=$id";

    if (mysqli_query($con, $sql)) {
        if ($change_text !== "No changes") {
            UserLog('2', 'RL Beneficiary Edited', 'ID=' . $id . ' | ' . $change_text, $id, 'RL');
        }
        echo json_encode(['success' => true, 'message' => 'Beneficiary updated!']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
    }
    exit;
} else {
    $sql = "INSERT INTO rl_beneficiaries 
        (location_id,name,name_tamil,name_sinhala,address,address_tamil,address_sinhala,district,ds_division_id,ds_division_text,gn_division_id,gn_division_text,nic_reg_no,dob,nationality,telephone,email,language)
        VALUES 
        (" . ($location_id ? "'" . mysqli_real_escape_string($con, $location_id) . "'" : "NULL") . ",'$name','$name_tamil','$name_sinhala','$address','$address_tamil','$address_sinhala','$district'," . ($ds_division_id ? "'" . mysqli_real_escape_string($con, $ds_division_id) . "'" : "NULL") . ",'$ds_division_text'," . ($gn_division_id ? "'" . mysqli_real_escape_string($con, $gn_division_id) . "'" : "NULL") . ",'$gn_division_text','$nic'," . $dob_sql . ",'$nat','$tel','$email','$language')";
    if (mysqli_query($con, $sql)) {
        $new_id = mysqli_insert_id($con);   
        if ($new_id) {
            $md5_ben = md5($new_id . "RL-key-dtecstudio");
            mysqli_query($con, "UPDATE rl_beneficiaries SET md5_ben_id='$md5_ben' WHERE rl_ben_id=$new_id");
        }
        UserLog('2', 'RL Beneficiary Created', 'ID=' . $new_id . ' Name=' . $name, $new_id, 'RL');
        echo json_encode(['success' => true, 'message' => 'Beneficiary added!']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
    }
    exit;
}
