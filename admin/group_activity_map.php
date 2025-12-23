<?php
 
    error_reporting(E_ALL); // Report all errors
    error_reporting(E_ALL & ~E_NOTICE); // Report all errors except notices
   
include 'header.php';
include '../db.php';

// Fetch activities, ordered by module and order_no
$activities = [];
$res = $conn->query("SELECT act_id, activity, module, order_no FROM manage_activities WHERE status=1 ORDER BY module, order_no, activity");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $activities[$row['module']][] = $row;
    }
}

// Fetch groups
$groups = [];
$res = $conn->query("SELECT group_id, group_name FROM manage_user_group WHERE status=1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $groups[] = $row;
    }
}

// Fetch current assignments
$assignments = [];
$res = $conn->query("SELECT group_id, act_id FROM group_activity_map WHERE status=1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $assignments[$row['act_id']][] = $row['group_id'];
    }
}
?>

<div class="content-wrapper">
   <div class="container-fluid">
      <div class="row">
         <div class="col-sm-12 p-0">
            <div class="main-header">
               <h4>User Privileges</h4>
               <ol class="breadcrumb breadcrumb-title breadcrumb-arrow">
                  <li class="breadcrumb-item">
                     <a href="index.php">
                        <i class="fa fa-lock" aria-hidden="true"></i>
                     </a>
                  </li>
                  <li class="breadcrumb-item"><a href="#">Admin</a></li>
               </ol>
            </div>
         </div>
      </div>
      <div class="row">
         <div class="col-sm-12">
            <div class="card">
                
               <div class="card-block">
                  <div class="row">

                    
                     <div class="col-sm-12 table-responsive">
                        <div align='right'>
                             <a href='manage_user.php'><button class="btn btn-primary"  >Back To User List</button></a>
                        </div>
                        <hr>
                        <form id="privilegesForm" method="post" action="ajax/save_privileges.php">
                            <table class="table table-striped table-bordered align-middle">
                                <thead class="thead-dark">
                                    <tr>
                                        <th style="min-width:20px; text-align:center;">ID</th>
                                        <th style="min-width:180px; text-align:center;">Activity</th>
                                        
                                        <?php foreach ($groups as $group) { ?>
                                            <th style="min-width:120px; text-align:center; "><?php echo htmlspecialchars($group['group_name']); ?></th>
                                        <?php } ?>
                                             
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $module => $acts) { ?>
                                        <!-- Module header row -->
                                        <tr class="table-primary">
                                            <td style='background-color: #c6bcbbff;' colspan="<?php echo count($groups) + 2; ?>">
                                                <strong><?php echo htmlspecialchars($module); ?></strong>
                                            </td>
                                        </tr>
                                        <?php foreach ($acts as $activity) { ?>
                                            <tr><td><?php echo $activity['act_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($activity['activity']); ?></strong>
                                                     
                                                </td>
                                                    
                                                <?php foreach ($groups as $group) { ?>
                                                    <td class="text-center">
                                                        <input type="checkbox"
                                                            name="privileges[<?php echo $activity['act_id']; ?>][]"
                                                            value="<?php echo $group['group_id']; ?>"
                                                            <?php if (!empty($assignments[$activity['act_id']]) && in_array($group['group_id'], $assignments[$activity['act_id']])) echo 'checked'; ?>
                                                        >
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-success">Save Privileges</button>
                        </form>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>
<script>
document.getElementById('privilegesForm').addEventListener('submit', function(e) {
    e.preventDefault();
       
    var form = this;
    var formData = new FormData(form);
    fetch('ajax/save_privileges.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? 'Saved!' : 'Error',
            text: data.message
        }).then(() => {
            if (data.success) window.location.reload();
        });
    });
});
</script>
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var formData = new FormData(form);

    fetch('ajax/save_privileges.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? 'Saved!' : 'Error',
            text: data.message
        }).then(() => {
            if (data.success) window.location.reload();
        });
    });
});
</script>
<?php include 'footer.php'; ?>