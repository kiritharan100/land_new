<?php
include '../../db.php';
include '../../auth.php'; // $user_id

$fields = [
    'entity', 'address', 'email', 'telephone', 'vat_no',
    'reg_no', 'invoice_prefix', 'VAT', 'gm_mobile', 'admin_device_approval', 'company_name', 'system_email', 'domain'
];

$updates = [];
foreach ($fields as $field) {
    $value = mysqli_real_escape_string($con, $_POST[$field] ?? '');
    $updates[] = "`$field` = '$value'";
}

$sql = "UPDATE letter_head SET " . implode(', ', $updates) . " WHERE id = 1";

if (mysqli_query($con, $sql)) {
    UserLog("System", "Updated Settings", "Letter head settings updated");
    echo "<script> 
    setTimeout(() => { window.location.href = '../setting.php?saved'; }, 1000);
    </script>";
} else {
    echo " ";
}
