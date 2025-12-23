 <?php include 'header.php'; ?>

<?php
// Default date range
if(isset($_REQUEST['from_date'])){
   $from_date = $_REQUEST['from_date'];
   $to_date = $_REQUEST['to_date'];
} else {
   $from_date = date('Y-m-01');
   $to_date = date("Y-m-t");
}

// Filters
$filter_location = $_REQUEST['c_id'] ?? '';
$filter_action   = $_REQUEST['action_filter'] ?? '';
?>

<div class="content-wrapper">
<div class="container-fluid">

<div class="row">
   <div class="col-sm-12 p-0">
      <div class="main-header">
         <h4>User Log / <?php echo $client_name; ?></h4>
      </div>
   </div>
</div>

<div class="row">
<div class="col-sm-12">
<div class="card">

<div class="card-header" align='right'>
<form method="GET">

   <!-- DATE FILTER -->
   From  
   <input type="date" name="from_date" value="<?php echo $from_date; ?>"> 
   To 
   <input type="date" name="to_date" value="<?php echo $to_date; ?>"> 

   <!-- LOCATION FILTER -->
   <select name="c_id" class="form-control input-sm select2" style="width:220px; display:inline-block;">
      <option value="">All Locations</option>
      <?php 
      $sel = mysqli_query($con, "SELECT c_id, md5_client, client_name FROM client_registration ORDER BY client_name ASC");
      while($r = mysqli_fetch_assoc($sel)){ ?>
         <option value="<?php echo $r['c_id']; ?>" 
            <?php if($filter_location == $r['c_id']) echo "selected"; ?>>
            <?php echo htmlspecialchars($r['client_name']); ?>
         </option>
      <?php } ?>
   </select>

   <!-- ACTION FILTER - DISTINCT -->
   <select name="action_filter" class="form-control input-sm select2" style="width:200px; display:inline-block;">
      <option value="">All Action</option>
      <?php 
      $act = mysqli_query($con, "SELECT DISTINCT action FROM user_log ORDER BY action ASC");
      while($a = mysqli_fetch_assoc($act)){ ?>
         <option value="<?php echo $a['action']; ?>" 
            <?php if($filter_action == $a['action']) echo "selected"; ?>>
            <?php echo htmlspecialchars($a['action']); ?>
         </option>
      <?php } ?>
   </select>

   <button type="submit" class="btn btn-success"><i class="fa fa-search"></i></button>
   <button type="button" id="exportButton" filename="<?php echo 'USER LOG_'.$client_name; ?>.xlsx" class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button>
</form>
</div>


<div class="card-block">
<div class="row">
<div class="col-sm-12 table-responsive">

<table id="example" class="table table-bordered table-striped" style="width:100%;">
<thead>
<tr>
   <th width="20px">#</th>
   <th width="100px">User</th>
   <th width="150px">Beneficiary</th>
   <th width="120px">Lease No</th>
   <th width="70px">Activity</th>
   <th>Detail</th>
   <th width="120px">Location</th>
   <th width="60px">Date</th>
</tr>
</thead>

<tbody>
<?php
$count = 1;

// Build WHERE conditions
$where = " log_date BETWEEN '$from_date' AND '$to_date' ";

if($filter_location != "")
   $where .= " AND user_log.location = '$filter_location' ";

if($filter_action != "")
   $where .= " AND user_log.action = '".mysqli_real_escape_string($con,$filter_action)."' ";

// MAIN QUERY
$query = "
SELECT 
    user_log.*, 
    user_license.i_name,
    b.name AS ben_name,
    l.lease_number,l.file_number,
    cr.client_name
FROM user_log
LEFT JOIN user_license ON user_license.usr_id = user_log.usr_id
LEFT JOIN client_registration cr ON cr.c_id = user_log.location
LEFT JOIN beneficiaries b ON b.ben_id = user_log.ben_id
LEFT JOIN leases l ON l.beneficiary_id = b.ben_id
WHERE $where
ORDER BY user_log.id DESC
";

$res = mysqli_query($con, $query);

while($row = mysqli_fetch_assoc($res)){ ?>
<tr>
   <td align="center"><?php echo $count++; ?></td>
   <td><?php echo $row['i_name']; ?></td>
   <td><?php echo $row['ben_name'] ?: '-'; ?></td>
   <!-- <td><?php echo $row['lease_number'] ?: '-'; ?></td> -->
    <td 
    data-toggle="tooltip" 
    data-placement="top" 
    title="File No :<?php echo htmlspecialchars($row['file_number'] ?: '-'); ?>">
    <?php echo $row['lease_number'] ?: '-'; ?>
</td>


   <td><?php echo $row['action']; ?></td>
   <td><?php echo $row['detail']; ?></td>
   <td><?php echo $row['client_name']; ?></td>
   <!-- <td><?php echo $row['log_date']; ?></td> -->
    <td><?php echo (!empty($row['log_date']) && strtotime($row['log_date']) !== false) ? date('d/m H:i', strtotime($row['log_date'])) : '-'; ?></td>
</tr>
<?php } ?>
</tbody>

</table>

</div>
</div>
</div>

</div>
</div>
</div>

</div>

<?php include 'footer.php'; ?>

<script>
   $(function () {
    $('[data-toggle="tooltip"]').tooltip();
});


$(document).ready(function() {
   

   $('.select2').select2();
});
</script>
