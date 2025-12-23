<!DOCTYPE html>
<html lang="en">

<head>
	<title>Device Registration</title>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="codedthemes">
	<meta name="keywords" content=", Responsive, Landing, Bootstrap, App, Template, Mobile, iOS, Android, apple, creative app">
	<meta name="author" content="codedthemes">

	<!-- Favicon icon -->
	<link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
	<link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

	<!-- Google font-->
	<link href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700" rel="stylesheet">

	<!-- iconfont -->
	<link rel="stylesheet" type="text/css" href="assets/icon/icofont/css/icofont.css">

	<!-- Required Fremwork -->
	<link rel="stylesheet" type="text/css" href="assets/plugins/bootstrap/css/bootstrap.min.css">

	<!-- waves css -->
	<link rel="stylesheet" type="text/css" href="assets/plugins/Waves/waves.min.css">

	<!-- Style.css -->
	<link rel="stylesheet" type="text/css" href="assets/css/main.css">

	<!-- Responsive.css-->
	<link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
	<!--color css-->
	<link rel="stylesheet" type="text/css" href="assets/css/color/color-1.min.css" id="color" />

</head>

<body>

	<section class="login p-fixed d-flex text-center bg-primary common-img-bg">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xs-12">
					<div class="login-card card-block">
						<form class="md-float-material" action="" method="POST">
							 
							<div class="text-center">
								 	<img src="img/gov.png" alt="logo" width='40px;' >
									<h2 style='color:#000;' >IRMIS</h2>
									<font style='color:#000;'>Department of Land Administration <br>
 									Eastern Province</font>
							</div>
							<?php
                            	if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
							if(empty($_SESSION['username'])) {  
								session_unset();     
								session_destroy(); 
								header("Location: login.php?msg=true");
								exit;
							}
							
							if (isset($_POST['submit_reg'])) {
								require('db.php');
								$username = $_SESSION['username'];
								$query = "SELECT dr_token,usr_id from user_license where username='" . $username . "'";
								$result = mysqli_query($con, $query) or die(mysqli_error());
								$row = mysqli_fetch_assoc($result);

								if ($_POST['token_input'] == $row['dr_token']) {
									$token = MD5(rand(1000, 2000));
									$update = "INSERT INTO `user_device`( pf_no, token, IP) VALUES ('$username','$token','$ip')";
									mysqli_query($con, $update) or die(mysqli_error());


									$simple_string = $token;
									$ciphering = "AES-128-CTR";
									$iv_length = openssl_cipher_iv_length($ciphering);
									$options = 0;
									$encryption_iv = '1234567891011121';
									$encryption_key = "kiritharan100@gmail.com";
									$encryption = openssl_encrypt(
										$simple_string,
										$ciphering,
										$encryption_key,
										$options,
										$encryption_iv
									);

									$cookie_name = "DR" . $row['usr_id'];
									$cookie_value = $encryption;
									 
									setcookie($cookie_name, $cookie_value, time() + (86400 * 665), "/");

									echo "<br>Device registered Successfully";

									header("refresh:1;url=index.php");
								} else {

									$cookie_name = "DR" . $username;
									$cookie_value = $_COOKIE[$cookie_name] + 1;
									setcookie($cookie_name, $cookie_value, time() + (86400 * 650), "/");
									if ($cookie_value < 3) {
										header("Location: login.php?device");
									} else {
										// $update = "update user_license set account_status ='10' where username='" . $username . "'";
										// mysqli_query($con, $update) or die(mysqli_error());
										header("Location: login.php?acc=failed_device");
									}
								}
							}
							?>
</h3>
							<div class="md-group" align='center'>
								<div class="md-input-wrapper">
									<input type="number" name='token_input' class="md-form-control" style="width:200px;" required />
									<label>Please enter the token that was sent to your Mobile.</label>
								</div>
							</div>
							<div class="btn-forgot">
								<button type="submit" name='submit_reg' class="btn btn-primary btn-md waves-effect waves-light text-center">Register
								</button>
							</div>
							<div class="row">
								<div class="col-xs-12 text-center m-t-25">

									<!-- <a href="login1.html" class="f-w-600 p-l-5"> Sign In Here</a> -->

								</div>
							</div>
							<!-- end of btn-forgot class-->
						</form>
						<!-- end of form -->
					</div>
					<!-- end of login-card -->
				</div>
				<!-- end of col-sm-12 -->
			</div>
			<!-- end of row -->
		</div>
		<!-- end of container-fluid -->
	</section>


	<script src="assets/plugins/jquery/dist/jquery.min.js"></script>
	<script src="assets/plugins/jquery-ui/jquery-ui.min.js"></script>
	<script src="assets/plugins/tether/dist/js/tether.min.js"></script>

	<!-- Required Fremwork -->
	<script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>

	<!-- waves effects.js -->
	<script src="assets/plugins/Waves/waves.min.js"></script>

	<!-- Custom js -->
	<script type="text/javascript" src="assets/pages/elements.js"></script>

</body>

</html>