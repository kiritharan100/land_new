<!DOCTYPE html>
<html lang="en">

<head>
	<title>User Registration</title>

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
					 
							<div class="text-center">
								 	<img src="img/gov.png" alt="logo" width='40px;' >
									<h2 style='color:#000;' >IRMIS</h2>
									<font style='color:#000;'>Department of Land Administration <br>
 									Eastern Province</font>
							</div>
		 
							<h3 class="text-primary text-center m-b-25">Password Reset Request </h3>
							<?php
						 
							require 'db.php';
							require_once __DIR__ . '/sms_helper.php'; // New SMS gateway helper
							$smsHelper = new SMS_Helper();

							$settings_stmt = $con->prepare("SELECT system_email, company_name, domain FROM letter_head LIMIT 1");
							$settings_stmt->execute();
							$settings_res = $settings_stmt->get_result();
							$rowx = $settings_res->fetch_assoc();
							$settings_stmt->close();
							$company_name = $rowx['company_name'] ?? '';

							$feedback = '';
							if ($_SERVER['REQUEST_METHOD'] === 'POST') {
								$ip = $_SERVER['REMOTE_ADDR'];
								$limit_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
								$attempt_stmt = $con->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address = ? AND attempt_time > ? AND try_for='reset'");
								$attempt_stmt->bind_param('ss', $ip, $limit_time);
								$attempt_stmt->execute();
								$attempt_row = $attempt_stmt->get_result()->fetch_assoc();
								$attempt_stmt->close();
								if ($attempt_row && $attempt_row['c'] > 5) {
									$feedback = 'Too many requests. Please wait a moment.';
								} else {
									$mobile = trim($_POST['email'] ?? '');
									// Basic format check (Sri Lanka 10 digits starting with 0) fallback generic.
									if (!preg_match('/^0\d{9}$/', $mobile)) {
										$feedback = 'If the account exists, a reset code will be sent.'; // generic
									} else {
										$user_stmt = $con->prepare("SELECT usr_id, username FROM user_license WHERE username = ? AND account_status='1' LIMIT 1");
										$user_stmt->bind_param('s', $mobile);
										$user_stmt->execute();
										$user = $user_stmt->get_result()->fetch_assoc();
										$user_stmt->close();
										// Always behave same timing
										$raw_token = (string)random_int(100000, 999999); // 6-digit secure token
										if ($user) {
											$upd_stmt = $con->prepare("UPDATE user_license SET token = ? WHERE usr_id = ?");
											$upd_stmt->bind_param('si', $raw_token, $user['usr_id']);
											$upd_stmt->execute();
											$upd_stmt->close();
												$sms_msg = "Reset Token: $raw_token\nUse this to verify and set a new password.";
												$smsHelper->sendSMS(0, $mobile, $sms_msg, 'RESET');
										}
										$log_stmt = $con->prepare("INSERT INTO login_attempts (ip_address, attempt_time, try_for) VALUES (?, NOW(), 'reset')");
										$log_stmt->bind_param('s', $ip);
										$log_stmt->execute();
										$log_stmt->close();
										$feedback = 'If the account exists, a reset code will be sent.';
										header('Location: setup_password.php');
										exit;
									}
								}
							}
							?>
							<?php if ($feedback !== '') { echo '<p class="text-danger">' . htmlspecialchars($feedback) . '</p>'; } ?>  
						 
                
                  <form method="post" action="">
            <!--    <p>Please enter your email address to reset your password:</p>-->
            <!--    <input type="email" name="email" required>-->
            <!--    <input type="submit" value="Submit">-->
            <!--</form>-->
            
            
            
                 
        
                
                
                               <div class="md-group" align='center'>
								<div class="md-input-wrapper">
								     <input type="number" name="email"     required class="md-form-control" style="width:200px;">
						 
									<label>Mobile Number </label>
								</div>
								  
							</div>
                
						 
							<div class="btn-forgot">
							    <input type="submit" value="Submit" class="btn btn-primary btn-md waves-effect waves-light text-center">
				 
								</button>
							</div>
							</form>  
							<div class="row">
								<div class="col-xs-12 text-center m-t-25">
  <a href="login.php" class="btn btn-outline-secondary btn-md waves-effect waves-light">Go to Login</a>
									 

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