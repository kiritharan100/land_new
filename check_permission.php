<?php
  require('db.php');
function has_permission($module) {
     $user = $_SESSION['username'];
    echo $sel_query="SELECT * from user_license where username='$user'";
       $result = mysqli_query($con,$sel_query);
    $user_row = mysqli_fetch_assoc($result);
    if($user_row[$module] <> 1){ ?>     
      <br><br>
      <div class="content-wrapper">
          <div class="container-fluid">
              <div class="container permission-denied">
                  <div class="card text-center">
                      <div class="card">
                          <div class="card-header">
                              <i class="fa fa-exclamation-triangle fa-3x" aria-hidden="true"></i><br>
                              Access Denied
                          </div>
                          <div class="card-body">
                              <h5 class="card-title">You don't have permission to access this module</h5>
                              <p class="card-text">Please contact your administrator if you believe this is a mistake.
                              </p><br>
                              <a href="../index.php" class="btn btn-primary">Go to Homepage</a><br>.
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <?php  include 'footer.php';  exit;}  
}
?>
