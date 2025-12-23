<?php
include '../../db.php';
include '../../auth.php';

$columns = ['ben_id','name','is_individual','contact_person','address','district','ds_division_id','gn_division_id','nic_reg_no','telephone','email'];

$limit = $_GET['length'];
$offset = $_GET['start'];
$search = $_GET['search']['value'];
$location_id = $_GET['location_id'];


$whereArr = [];
if($search){
  $whereArr[] = "(b.name LIKE '%$search%' OR b.nic_reg_no LIKE '%$search%' OR b.telephone LIKE '%$search%')";
}
if(isset($location_id) && $location_id !== ''){
  $location_id = mysqli_real_escape_string($con, $location_id);
  $whereArr[] = "b.location_id = '$location_id'";
}
$where = count($whereArr) ? ('WHERE ' . implode(' AND ', $whereArr)) : '';


$totalRes = mysqli_query($con,"SELECT COUNT(*) as cnt FROM short_term_beneficiaries b $where");
$totalRow = mysqli_fetch_assoc($totalRes)['cnt'];

$query = "SELECT b.*, cr.client_name AS ds_division_name, gd.gn_name AS gn_division_name
FROM short_term_beneficiaries b
LEFT JOIN client_registration cr ON b.ds_division_id = cr.c_id
LEFT JOIN gn_division gd ON b.gn_division_id = gd.gn_id
$where ORDER BY b.ben_id DESC LIMIT $offset,$limit";
$result = mysqli_query($con,$query);

$data = [];
while($row = mysqli_fetch_assoc($result)){
  // DS Division: use ds_division_name if id exists, else ds_division_text
  $ds_division = $row['ds_division_name'] ? $row['ds_division_name'] : $row['ds_division_text'];
  // GN Division: use gn_division_name if id exists, else gn_division_text
  $gn_division = $row['gn_division_name'] ? $row['gn_division_name'] : $row['gn_division_text'];
  $data[] = [
    "ben_id"=>$row['ben_id'],
    "name"=>$row['name'],
    "type"=>$row['is_individual'] ? "Individual":"Institution",
    "contact_person"=>$row['contact_person'],
    "address"=>$row['address'],
    "district"=>$row['district'],
    "ds_division"=>$ds_division,
    "gn_division"=>$gn_division,
    "nic_reg_no"=>$row['nic_reg_no'],
    "telephone"=>$row['telephone'],
    "email"=>$row['email'],
    "language"=>$row['language'] ?: 'English',
    "action"=>'<button class="btn btn-sm btn-info editBen" data-id="'.$row['ben_id'].'">Edit</button>'
  ];
}

echo json_encode([
  "draw"=>intval($_GET['draw']),
  "recordsTotal"=>$totalRow,
  "recordsFiltered"=>$totalRow,
  "data"=>$data
]);
