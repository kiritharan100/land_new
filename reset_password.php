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
 
	
<script>
            var passwordStrength = 0;

            function checkPasswordStrength(password) {
                passwordStrength = 0;
                if (password.length >= 8) passwordStrength += 1;
                if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) passwordStrength += 1;
                if (password.match(/([0-9])/)) passwordStrength += 1;
                if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) passwordStrength += 1;
                
                var strengthText;
                switch(passwordStrength) {
                    case 0:
                    case 1:
                    case 2:
                        strengthText = "Weak";
                        break;
                    case 3:
                        strengthText = "Medium";
                        break;
                    case 4:
                        strengthText = "Strong";
                        break;
                }
                document.getElementById('strength').innerHTML = strengthText;
            }

            function validatePasswords() {
                var password = document.getElementById('password').value;
                var confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    alert("Passwords do not match.");
                    return false;
                }
                
                if (passwordStrength < 3) {
                    alert("Password strength must be Medium or Strong.");
                    return false;
                }
                return true;
            }

            function disableCopyPaste() {
                var fields = [document.getElementById('password'), document.getElementById('confirm_password')];
                fields.forEach(function(field) {
                    field.onpaste = function(e) {
                        e.preventDefault();
                        alert("Pasting is not allowed.");
                    };
                    field.oncopy = function(e) {
                        e.preventDefault();
                        alert("Copying is not allowed.");
                    };
                });
            }

            window.onload = disableCopyPaste;
        </script>
 
</head>

<body>

	<section class="login p-fixed d-flex text-center bg-primary common-img-bg">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xs-12">
					<div class="login-card card-block">
					 
							<div class="text-center">
								<img src="assets/images/logo-black.png" alt="logo"  >
							</div>
							
							
							
							<?php 
							require('db.php');
							if ($_SERVER["REQUEST_METHOD"] == "POST") {
							    
							    function getSettings($con) {
								$result = mysqli_query($con, "SELECT * FROM letter_head WHERE id = 1");
								return mysqli_fetch_assoc($result);
									} 
									$settings = getSettings($con);
									$entity =  $settings['company_name'];
									
									
									
                                    $token = $_POST['token'];
                                    $new_password = md5("893121129V".$_POST['password']);
                                    
                             
								$query1 = "SELECT * from user_license where token='$token'";
								$result = mysqli_query($con, $query1) or die(mysqli_error());
						 
								$row = mysqli_fetch_assoc($result);
                                $username = $row['username'];
   
    
                                 $sql = "UPDATE user_license SET password='$new_password', account_status='1' ,token='Expired'  WHERE token='$token'";

                                          if ($con->query($sql) === TRUE) {
        
        
        	                            $date = date('Y-m-d h:i', time());
										$token2 = md5(round(microtime(true)) / 0.2);
										$update = "UPDATE user_license SET `last_log_in`= '" . $date . "',`last_token`= '" . $token2 . "'  WHERE username = '" . $username . "'";
						                mysqli_query($con, $update);
						                
						                		session_destroy();
                            									if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
                                       $_SESSION['username'] = $row['username'];
										$_SESSION['customer'] = $row['customer'];
										$_SESSION['i_name'] = $row['i_name'];
										$_SESSION['app_name'] = "e-Land";
										$_SESSION['company_name'] = $entity;
										$_SESSION['last_token'] = $token2;
										 $_SESSION['company'] = $entity;
							 $_SESSION['user_id'] = $row['usr_id'];
										$_SESSION['address1'] = "Eastern Province";
										
										
										
										
										
        
                                   $token1 = MD5(rand(1000, 2000));
									$update = "INSERT INTO `user_device`( pf_no, token, IP) VALUES ('$username','$token1','$ip')";
									mysqli_query($con, $update) or die(mysqli_error());




									$simple_string = $token1;
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

								 
									 	header("refresh:2;url=index.php");
        
                                     echo "<h3 class='text-primary text-center m-b-25'>password reset successfully . You will be redirected shortly.  </h23";
                                   
                                     
                                     
                                     
                                     
                                    } else {
                                       echo "<h2 class='text-primary text-center m-b-25'> Invitation Alredy used </h2>";
                                    }

    $con->close();
} else {
    $user = $_GET['user'];
    
    
    
                    	$query1 = "SELECT * from user_license where username='$user'";
								$result1 = mysqli_query($con, $query1) or die(mysqli_error());
								 $rowcount=mysqli_num_rows($result1);
							 
                            
                                
                                if($rowcount == 0 ){
                                    echo "<h3 class='text-primary text-center m-b-25'>Request expired.<br> <a href='login.php'>Go to Login Page</a> </h2";
                                    exit;
                                }
    ?>
    
    
    
    
    
     
							
							
							<h3 class="text-primary text-center m-b-25">Reset Password </h3>
							
						 
                
                 <form method="post" action="" onsubmit="return validatePasswords()">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
        
                
                
                               <div class="md-group" align='center'>
								<div class="md-input-wrapper">
								     <input type="password" name="password" id="password" style='text-align:center;' onkeyup="checkPasswordStrength(this.value)" required class="md-form-control" style="width:200px;">
									<!--<input type="number" name='token_input' class="md-form-control" style="width:200px;" required />-->
									<label>Please set your password:</label>
								</div>
								  <p>Password Strength: <span id="strength"></span></p>
							</div>
                
						 
							<div class="md-group" align='center'>
								<div class="md-input-wrapper">
								     <input type="password" name="confirm_password" id="confirm_password" style='text-align:center;' required class="md-form-control" style="width:200px;">
									<!--<input type="number" name='token_input' class="md-form-control" style="width:200px;" required />-->
									<label>Confirm password</label>
								</div>
								 <p>Passwords are case sensitive, use a combination of uppercase and lowercase letters. Use a mixture of letters, numbers, and special characters. Minimum 8 characters.</p>
							</div>
							<div class="btn-forgot">
							                       <input type="submit" value="Set Password" class="btn btn-primary btn-md waves-effect waves-light text-center" onclick="submitForm()">
				 
								</button>
							</div>
							</form> <?php } ?>
							<div class="row">
								<div class="col-xs-12 text-center m-t-25">

									 

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