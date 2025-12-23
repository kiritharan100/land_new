<?php  
//     ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


   require('../db.php');
   require('../auth.php');
  
   
   $current_url = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);  
   if(isset($_POST['selected_client'])){
      $selected_client = $_POST['selected_client'];
      setcookie("client_cook", $selected_client, time() + (86400 * 180), "/"); // 86400 = 1 day
      header("Location: $current_url");
      die();
   }else{
      $selected_client = isset($_COOKIE['client_cook']) ? $_COOKIE['client_cook'] : '';
   }


   if(isset($_POST['selected_language'])){
      $selected_language = $_POST['selected_language'];
      setcookie("language_cook", $selected_language, time() + (86400 * 180), "/"); // 86400 = 1 day
      header("Location: $current_url");
      die();
   }else{
         $client_language = isset($_COOKIE['language_cook']) ? $_COOKIE['language_cook'] : 'English';
   }


      $sel_query="SELECT * from client_registration where md5_client='$selected_client'";
      $result = mysqli_query($con,$sel_query);
      $row = mysqli_fetch_assoc($result);

if ($row) {
    
    $client_cook = $row['md5_client'];
    $client_type = $row['client_type'];
    $client_name = $row['client_name'];
    $location_id = $row['c_id'];
} else {
    $client_cook = '';
    $client_type = '';
    $client_name = '';
    $location_id = '';
}
$client_id = $_SESSION['customer'];
$company_id = $_SESSION['customer'];
      
  ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo $_SESSION['app_name'].">".$_SESSION['company_name']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Quantum Able Bootstrap 4 Admin Dashboard Template by codedthemes">
    <meta name="keywords"
        content="appestia, Responsive, Landing, Bootstrap, App, Template, Mobile, iOS, Android, apple, creative app">
    <meta name="author" content="codedthemes">

    <!-- Favicon icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="shortcut icon" href="../assets/images/favicon.png" type="image/x-icon">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/w3.css">
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700" rel="stylesheet">

    <link rel="stylesheet" href="../assets/fonts/font-awesome.min.css">

    <link rel="stylesheet" type="text/css" href="../assets/icon/themify-icons/themify-icons.css">

    <!-- added newly kiri -->
    <link rel="stylesheet" type="text/css" href="../assets/newtheme.css">
    <link rel="stylesheet" type="text/css" href="../assets/newtheme-alter.css">

    <!-- iconfont -->
    <link rel="stylesheet" type="text/css" href="../assets/icon/icofont/css/icofont.css">

    <!-- simple line icon -->
    <link rel="stylesheet" type="text/css" href="../assets/icon/simple-line-icons/css/simple-line-icons.css">

    <!-- Required Fremwork -->
    <link rel="stylesheet" type="text/css" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap.min.css">
    <!-- <link href="https://azukcdncp.azureedge.net/contents/css/bootstrap?v=4.1" rel="stylesheet"/> -->

    <!-- Style.css -->
    <link rel="stylesheet" type="text/css" href="../assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Responsive.css-->
    <link rel="stylesheet" type="text/css" href="../assets/css/responsive.css">
    <link rel="stylesheet" type="text/css" href="../assets/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-select.min.css" />
    <script src="../assets/plugins/jquery/dist/jquery.min.js"></script>
    <script src="../assets/plugins/notification/js/bootstrap-growl.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <style>
    .loader-bg {
        margin: auto;
        border: 10px solid #EAF0F6;
        border-radius: 50%;
        border-top: 10px solid green;
        width: 80px;
        height: 80px;
        animation: spinner 4s linear infinite;
    }

    @keyframes spinner {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
    </style>

</head>

<body class="sidebar-mini fixed" style='overflow-x: visible;'>
    <div class="wrapper">
        <div class="loader-bg">
            <!--<div class="loader-bar">-->
            <!--</div> -->
        </div>
        <!-- Navbar-->
        <header class="main-header-top hidden-print">
            <a href="../index.php" class="logo"><img class="img-fluid able-logo" src="../assets/images/logo.png"
                    alt="Theme-logo"></a>
            <nav class="navbar navbar-static-top">
                <!-- Sidebar toggle button-->
                <a href="#!" data-toggle="offcanvas" class="sidebar-toggle"></a>
                <ul class="top-nav lft-nav">

                    <li class="dropdown" id='language-select'>
                        <a href="#!" style='font-weight: 800;    font-size: 25px;' data-toggle="dropdown" role="button"
                            aria-haspopup="true" aria-expanded="false"
                            class="dropdown-toggle drop icon-circle drop-image">
                            <span> <i class="fa fa-user" aria-hidden="true"></i> ADMIN</span>
                        </a>

                    </li>


                </ul>
                <!-- Navbar Right Menu-->
                <div class="navbar-custom-menu">
                    <ul class="top-nav">





                        <script>
                        $("#language-select li").on("click", function() {
                            var language_select = $(this).attr('id');
                            var form = document.createElement("form");
                            var element2 = document.createElement("input");
                            form.method = "POST";
                            form.action = "";
                            element2.value = language_select;
                            element2.name = "selected_language";
                            form.appendChild(element2);
                            document.body.appendChild(form);
                            form.submit();

                        });
                        </script>




                        <?php $count_client = 0 ;
                        $sel_query="SELECT client_registration.md5_client, client_registration.client_name from client_registration 
                        INNER JOIN
                        user_location ON user_location.location_id  = client_registration.c_id AND user_location.usr_id ='$user_id'
                        
                        where client_registration.user_license='1'";
                        $result = mysqli_query($con,$sel_query);
                         $rowcount=mysqli_num_rows($result);
                         
                        //  if($rowcount > 1){
                         
                         ?>
                        <li class="dropdown" id='client-select'>
                            <form action='' method="POST">
                                <select id="search_client" name="selected_client" class="form-control input-sm"
                                    style="width:300px;" onchange="this.form.submit()">
                                    <option value="0">Select Location</option>
                                    <?php while($row = mysqli_fetch_assoc($result)) { ?>
                                    <option value="<?php echo $row['md5_client']; ?>"
                                        <?php if ($client_cook == $row['md5_client']) { echo 'selected';  $count_client += 1; } ?>>
                                        <?php echo htmlspecialchars($row['client_name']); ?>
                                    </option>
                                    <?php } ?>
                                </select>

                            </form>
                        </li>





                        <li class="dropdown pc-rheader-submenu message-notification search-toggle">
                            <a href="../" id="morphsearch-search" class="drop icon-circle txt-white">
                                <i class="fa fa-home" aria-hidden="true"></i>
                            </a>
                        </li>

                        <li class="pc-rheader-submenu">

                        </li>
                        <li class="pc-rheader-submenu">
                            <a href="#!" class="drop icon-circle" onclick="javascript:toggleFullScreen()">
                                <i class="icon-size-fullscreen"></i>
                            </a>

                        </li>
                        <!-- User Menu-->
                        <li class="dropdown">
                            <a href="#!" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"
                                class="dropdown-toggle drop icon-circle drop-image">
                                <span><img class="img-circle " src="../assets/images/avatar-1.png" style="width:40px;"
                                        alt="User Image"></span>
                                <span><?php echo $_SESSION['i_name'] ?> <i class="fa fa-angle-down"
                                        aria-hidden="true"></i> </span>

                            </a>
                            <ul class="dropdown-menu settings-menu">
                                <!--<li><a href="#!"><i class="icon-settings"></i> Settings</a></li>-->
                                <!--<li><a href="#"><i class="icon-user"></i> Profile</a></li>-->
                                <!--<li><a href="#"><i class="icon-envelope-open"></i> My Messages</a></li>-->
                                <!--<li class="p-0">-->
                                <!--   <div class="dropdown-divider m-0"></div>-->
                                <!--</li>-->
                                <!--<li><a href="#"><i class="icon-lock"></i> Lock Screen</a></li>-->
                                <li><a href="../logout.php"><i class="icon-logout"></i> Logout</a></li>

                            </ul>
                        </li>
                    </ul>


                </div>
            </nav>
        </header>
        <!-- Side-Nav-->
        <aside class="main-sidebar hidden-print ">
            <section class="sidebar" id="sidebar-scroll">
                <!-- Sidebar Menu-->
                <ul class="sidebar-menu">
                    <li class="nav-level"> </li>
                    <li class="treeview"></li>
                    <li class="<?php $url='index.php'; if($url == $current_url  ){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-home"></i><span> Dashboard</span>
                        </a>
                    </li>
                    <li class="<?php $url='manage_user.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa-solid fa-user"></i><span> Manage Users</span>
                        </a>
                    </li>

                    <!-- <li class="<?php $url='land_usage_purposes.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-tags"></i><span> Land Usage Purposes</span>
                        </a>
                    </li> -->

                    <!-- <li class="<?php $url='penalty_rate_settings.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-percent"></i><span> Penalty Rate Settings</span>
                        </a>
                    </li> -->

                    <?php if (hasPermission(14)): ?>
                    <li class="<?php $url='lease_master.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-file"></i><span> Lease Master</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasPermission(2)): ?>
                    <li class="<?php $url='client_list.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-home"></i><span> Manage DS Division</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasPermission(3)): ?>
                    <li class="<?php $url='gn_division_list.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-list"></i><span> Manage GN Division</span>
                        </a>
                    </li>
                    <?php endif; ?>


                    <?php if (hasPermission(4)): ?>
                    <li class="<?php $url='sms_templates.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-envelope"></i><span> SMS Templates</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasPermission(15)): ?>
                    <li class="<?php $url='reports.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-file"></i><span> Reports</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission(15)): ?>
                    <li class="<?php $url='report_user_log.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-file"></i><span> User Log</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasPermission(24)): ?>
                    <li class="<?php $url='payment_category.php'; if($url == $current_url){echo "active";}?> treeview">
                        <a class="waves-effect waves-dark" href="<?php echo $url; ?>">
                            <i class="fa fa-money-bill"></i><span> Other Payment Category</span>
                        </a>
                    </li>
                    <?php endif; ?>

                </ul>
            </section>
        </aside>
        <!-- Sidebar chat start -->
        <div id="sidebar" class="p-fixed header-users showChat">


        </div>
        <div class="showChat_inner">
            <div class="media chat-inner-header">
                <a class="back_chatBox">
                    <i class="icofont icofont-rounded-left"></i> Josephin Doe
                </a>
            </div>


        </div>
        <?php $user = $_SESSION['username'];
      $sel_query="SELECT * from user_license where username='$user'";
         $result = mysqli_query($con,$sel_query);
      $user_row = mysqli_fetch_assoc($result);
      if($user_row['admin'] <> 1){ ?>
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
        <?php  include 'footer.php';  exit;}   ?>

        <?php
      if($count_client == 0){ ?>
        <br><br>
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="container permission-denied">
                    <div class="card text-center">
                        <div class="card">
                            <div class="card-header">
                                <i class="fa fa-info-circle fa-3x" aria-hidden="true"></i><br>

                                Select Client
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">Please select yout Working Location from List</h5>
                                <p class="card-text">Please contact your administrator if you don't have your location
                                    in the list.
                                </p><br>
                                <a href="../index.php" class="btn btn-primary">Go to Homepage</a><br>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
        .modal-content {
            height: 120px;
            width: 400px;
            margin-right: 20px;
        }

        .modal-arrow {
            top: 10vh;
            transform: translateY(-50%);
            content: "";
            left: 2.2em;
            transform: translateY(-50%) rotate(20deg);
            /* Rotate as needed */
            display: block;
            position: fixed;
            bottom: auto;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 25px 35px 25px 0;
            border-color: transparent #ffffff transparent transparent;
            -webkit-filter: drop-shadow(-2px 0px 1px rgba(0, 0, 0, .5));
            -moz-filter: drop-shadow(0px 1px 2px rgba(0, 0, 0, .5));
            -ms-filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .5));
            -o-filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .5));
            filter: drop-shadow(0px 1px 2px rgba(0, 0, 0, .5));
        }

        @media (min-width: 768px) {
            .modal-arrow {
                right: calc(09px + 40%);
                left: auto;
            }
        }
        </style>

        <script type="text/javascript">
        $(window).on('load', function() {
            $('#myModal').modal('show');
        });
        </script>
        <div class="modal fade in" id="myModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <a data-dismiss="modal" class="pull-right">
                            Close
                        </a>
                    </div>
                    <div align='center'> <br> Please Select your Working Location to continue </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
            <div class="modal-arrow"></div>
        </div><!-- /.modal -->
        <?php  include 'footer.php';  exit;}   ?>


        <script>
        function notify(type, title, message1) {
            $.growl({
                icon: '',
                title: title,
                message: message1,
                url: ''

            }, {
                element: 'body',
                type: type,
                allow_dismiss: true,
                placement: {
                    from: 'top',
                    align: "right"
                },
                offset: {
                    x: 30,
                    y: 30
                },
                spacing: 10,
                z_index: 999999,
                delay: 4500,
                timer: 1000,
                url_target: '_blank',
                mouse_over: false,
                animate: {
                    enter: 'animated fadeInDown',
                    exit: 'animated fadeOutUp'
                },
                icon_type: 'class',
                template: '<div data-growl="container" class="alert" role="alert">' +
                    '<button type="button" class="close" data-growl="dismiss">' +
                    '<span aria-hidden="true">&times;</span>' +
                    '<span class="sr-only">Close</span>' +
                    '</button>' +
                    '<span data-growl="icon"></span>' +
                    '<span data-growl="title"></span>' +
                    '<span data-growl="message"></span>' +
                    '<a href="#" data-growl="url"></a>' +
                    '</div>'
            });
        };
        </script>

        <style>
        .dropdown_change select {
            background: transparent;
            width: 100px;
            font-size: 16px;
            border: 1px solid #CCC;
            height: 30px;
        }

        .dropdown_change {
            margin: 0px;
            width: 90px;
            height: 24px;
            border: 2px solid #111;
            border-radius: 3px;
            overflow: hidden;
            /* background: url(down-arrow.ico) 96% / 20% no-repeat #EEE; */
        }

        .dataTables_length {
            position: absolute;
        }

        .table td,
        .table th {
            padding: 2px;
        }
        </style>

        <?php
function checkPermission($act_id) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    if (!isset($_SESSION['permissions']) || !in_array($act_id, $_SESSION['permissions'])) {
        // User doesn’t have permission
        echo '<div class="content-wrapper" ><br><br>
         <div class="container-fluid"   >
        
        
         <div class="row" >
          <div class="col-sm-12">
            <div class="alert alert-danger text-center p-4 mt-5" role="alert">
               <i class="fa fa-lock fa-5x" aria-hidden="true"></i> 
              <h1 class="alert-heading">Access Denied!</h1>
              <h5>You don\'t have permission to access this window.</h5>
              
            </div>
          </div>
          </div>
        </div>


        </div>';
        include 'footer.php';
        exit; // stop further page loading
    }
}
?>