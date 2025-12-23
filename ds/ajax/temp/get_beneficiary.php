<?php
include '../../db.php';
include '../../auth.php';
$id = intval($_POST['ben_id']);
// fetch and return beneficiary record (includes language)
$res = mysqli_query($con,"SELECT * FROM short_term_beneficiaries WHERE ben_id=$id");
$row = mysqli_fetch_assoc($res);
if ($row && !isset($row['language'])) $row['language'] = 'English';
echo json_encode($row);
