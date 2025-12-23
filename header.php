<?php  
   require('db.php');
   if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
   if(empty($_SESSION['username'])) {
      session_unset();     
      session_destroy(); 
      header("Location: login.php?msg=true");
      exit;
   }
   $current_url = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);  
   if(isset($_POST['selected_client'])){
      $selected_client = $_POST['selected_client'];
      setcookie("client_cook", $selected_client, time() + (86400 * 180), "/"); // 86400 = 1 day
      header("Location: $current_url");
      die();
   }else{
      $selected_client = $_COOKIE['client_cook'];
   }

   $user = $_SESSION['username'];
   $sel_query="SELECT * from user_license where username='$user'";
      $result = mysqli_query($con,$sel_query);
   $user_row = mysqli_fetch_assoc($result);
   $user_id = $user_row['usr_id'];
   
   
   

      $sel_query="SELECT * from client_registration where md5_client='$selected_client'";
      $result = mysqli_query($con,$sel_query);
      $row = mysqli_fetch_assoc($result);
      $client_cook = $row['md5_client'];
      $client_type = $row['client_type'];
      $client_name = $row['client_name'];
      $location_id = $row['c_id'];
      $client_id = $_SESSION['customer'];
      $company_id = $_SESSION['customer'];
      
      
  ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title> <?php
  echo $_SESSION['app_name'].">".$_SESSION['company_name'];
   ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Quantum Able Bootstrap 4 Admin Dashboard Template by codedthemes">
    <meta name="keywords"
        content="appestia, Responsive, Landing, Bootstrap, App, Template, Mobile, iOS, Android, apple, creative app">
    <meta name="author" content="codedthemes">

    <!-- Favicon icon -->
    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/w3.css">
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700" rel="stylesheet">

    <link rel="stylesheet" href="assets/fonts/font-awesome.min.css">

    <link rel="stylesheet" type="text/css" href="assets/icon/themify-icons/themify-icons.css">

    <!-- added newly kiri -->
    <link rel="stylesheet" type="text/css" href="assets/newtheme.css">
    <link rel="stylesheet" type="text/css" href="assets/newtheme-alter.css">

    <!-- iconfont -->
    <link rel="stylesheet" type="text/css" href="assets/icon/icofont/css/icofont.css">

    <!-- simple line icon -->
    <link rel="stylesheet" type="text/css" href="assets/icon/simple-line-icons/css/simple-line-icons.css">

    <!-- Required Fremwork -->
    <link rel="stylesheet" type="text/css" href="assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap.min.css">
    <!-- <link href="https://azukcdncp.azureedge.net/contents/css/bootstrap?v=4.1" rel="stylesheet"/> -->

    <!-- Style.css -->
    <link rel="stylesheet" type="text/css" href="assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Responsive.css-->
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <link rel="stylesheet" type="text/css" href="assets/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-select.min.css" />
    <script src="assets/plugins/jquery/dist/jquery.min.js"></script>
    <script src="assets/plugins/notification/js/bootstrap-growl.min.js"></script>
     
    <style>
    .loader-bg {
        margin: auto;
        border: 10px solid #EAF0F6;
        border-radius: 50%;
        border-top: 10px solid green;
        width: 150px;
        height: 150px;
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


    <style>
    body:before {
        content: "";
        background-image: url("img/back.jpg");
        background-repeat: no-repeat;
        background-size: 100%;
        display: block;
        position: fixed;

        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: -1;
    }

    /* Hide background image on mobile devices */
    @media (max-width: 767px) {
        body:before {
            background-image: none;
        }
    }
    </style>






    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css"> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>



</head>

<body class="sidebar-mini fixed" style='overflow-x: visible;'>
    <div class="wrapper">
        <div class="loader-bg">
            <!--<div class="loader-bar">-->
            <!--</div>-->
        </div>
        <!-- Navbar-->
        <header class="main-header-top hidden-print">
            <a href="index.php" class="logo"><img class="img-fluid able-logo" src="assets/images/logo.png"
                    alt="Theme-logo"></a>
            <nav class="navbar navbar-static-top">
                <!-- Sidebar toggle button-->
                <!-- <a href="#!" data-toggle="offcanvas" class="sidebar-toggle"></a> -->
                <ul class="top-nav lft-nav">





                    <li class="dropdown" id='client-select'>







                    <li class="dropdown pc-rheader-submenu message-notification search-toggle">
                        <!-- <a href="#!" id="morphsearch-search" class="drop icon-circle txt-white">
                     <i class="ti-search"></i>
                  </a> -->
                    </li>
                </ul>
                <!-- Navbar Right Menu-->
                <div class="navbar-custom-menu">
                    <ul class="top-nav">
                        <!--Notification Menu-->
                        <!-- <li class="dropdown notification-menu">
                     <a href="#!" data-toggle="dropdown" aria-expanded="false" class="dropdown-toggle">
                        <i class="icon-bell"></i>
                        <span class="badge badge-danger header-badge">9</span>
                     </a>
                 
                  </li> -->
                        <!-- chat dropdown -->
                        <!-- <li class="pc-rheader-submenu ">
                     <a href="#!" class="drop icon-circle displayChatbox">
                        <i class="icon-bubbles"></i>
                        <span class="badge badge-danger header-badge">5</span>
                     </a>

                  </li> -->
                        <!-- window screen -->

                        <!-- User Menu-->
                        <li class="dropdown">
                            <a href="#!" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"
                                class="dropdown-toggle drop icon-circle drop-image">
                                <span><img class="img-circle " src="img/user.png" style="width:40px;"
                                        alt="User Image"></span>
                                <span><?php echo $_SESSION['i_name'] ?></b> <i class="fa fa-angle-down"
                                        aria-hidden="true"></i></span>

                            </a>
                            <ul class="dropdown-menu settings-menu">
                                <!--<li><a href="#!"><i class="icon-settings"></i> Settings</a></li>-->
                                <li><a href="reset_request.php"><i class="icon-user"></i> Change Password</a></li>
                                <!--<li><a href="#"><i class="icon-envelope-open"></i> My Messages</a></li>-->
                                <!--<li class="p-0">-->
                                <!--   <div class="dropdown-divider m-0"></div>-->
                                <!--</li>-->
                                <!--<li><a href="#"><i class="icon-lock"></i> Lock Screen</a></li>-->
                                <li><a href="logout.php"><i class="icon-logout"></i> Logout</a></li>

                            </ul>
                        </li>
                    </ul>


                </div>
            </nav>
        </header>


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
        <?php include("assets/js.php"); ?>