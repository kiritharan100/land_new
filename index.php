
<?php include 'header.php'; ?>
<?php include("assets/js.php"); ?>
<div class="modal fade" id="browserModal" tabindex="-1" role="dialog" aria-labelledby="browserModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="browserModalLabel">Suggestion for changing the browser</h4>
            </div>
            <div class="modal-body" align='center'>
                <p>It looks like you are not using Google Chrome. For the best experience, please use the Chrome
                    browser.</p>
                <a href="https://www.google.com/chrome/" class="btn btn-primary" target="_blank">Download Google
                    Chrome</a>
            </div>
        </div>
    </div>
</div>

<!-- <div class="content-wrapper"> -->
<!-- Container-fluid starts -->
<div class="container-fluid" style='padding-top: 50px;'>



    <!-- Header Starts -->
    <div class="row">
        <div class="col-sm-12 p-0">
            <div class="main-header">
                <!-- <h4>Table</h4> -->
                <ol class="breadcrumb breadcrumb-title breadcrumb-arrow">
                    <li class="breadcrumb-item">
                        <a href="index.php">
                            <i class="fa fa-home" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li class="breadcrumb-item"><a href="#">Home Menu</a>
                    </li>

                </ol>




                <div class="card-block" style='width:100%'>
                    <div class="row boot-ui">



                        <?php if (hasPermission(11)): ?>
                        <a href='ds'>
                            <div class="col-sm-3 col-xs-12 waves-effect waves-light">
                                <div class="grid-material bg-danger" style="opacity: .9;"><img src='img/ds.png'
                                        width='100px;'><br> Divisional Secretariat</div>
                            </div>
                        </a> <?php endif; ?>



                        <?php if (hasPermission(9)): ?>
                        <a href='admin'>
                            <div class="col-sm-3 col-xs-12 waves-effect waves-light">
                                <div class="grid-material bg-danger" style="opacity: .9;"><img src='img/admin1.png'
                                        width='100px;'><br> ADMIN</div>
                            </div>
                        </a>
                        <?php endif; ?>




                    </div>
                </div>


            </div>
        </div>
    </div>
    <!-- Header end -->

    <div class="row">
        <div class="col-sm-12">



            <?php
// Assume $user_id and $con are already defined

// Check if the user has at least one location
$location_q = mysqli_query($con, "SELECT id FROM user_location WHERE usr_id = '$user_id'");
$has_location = mysqli_num_rows($location_q) > 0;

// Check if the user has at least one module
$module_q = mysqli_query($con, "
    SELECT accounts, store, admin, report 
    FROM user_license 
    WHERE usr_id = '$user_id' 
    LIMIT 1
");

$has_module = false;
if ($row = mysqli_fetch_assoc($module_q)) {
    $has_module = $row['accounts'] || $row['store'] || $row['admin'] || $row['report'];
}

// Determine message
$alertMessage = '';
if (!$has_location && !$has_module) {
    $alertMessage = "No modules or locations have been allocated to your account.<br>Please contact the system administrator.";
} elseif (!$has_location) {
    $alertMessage = "No location has been allocated to your account.<br>Please contact the system administrator.";
} elseif (!$has_module) {
    $alertMessage = "No module has been allocated to your account.<br>Please contact the system administrator.";
}

// Show alert if needed
if ($alertMessage !== '') {
    ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
            Swal.fire({
                icon: 'warning',
                title: 'Access Restricted',
                html: `<?= $alertMessage ?>`,
                confirmButtonText: 'OK'
            }).then(() => {
                // window.location.href = 'logout.php'; // Or redirect to a contact page
            });
            </script>
            <?php
   
}
?>




            <!------------------------------------------------------------------------------------------------------------------------------  -->


        </div>
    </div>
    <!-- Row end -->
    <!-- Tables end -->
</div>

<!-- Container-fluid ends -->
</div>



<?php include 'footer.php';?>
<?php include("assets/js.php"); ?>
<!-- Include jQuery -->
<!--<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>-->
<!-- Include Bootstrap JS -->
<!--<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>-->