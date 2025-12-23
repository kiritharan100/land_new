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

    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css?family=Ubuntu:400,500,700" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/icon/icofont/css/icofont.css">
    <link rel="stylesheet" type="text/css" href="assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/plugins/Waves/waves.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/main.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <link rel="stylesheet" type="text/css" href="assets/css/color/color-1.min.css" id="color" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        ul.password-req { list-style: none; padding-left: 0; }
        .password-req li { margin: 4px 0; }
        .password-req .valid { color: #198754; }
        .password-req .invalid { color: #dc3545; }
    </style>
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

                        <?php
                        ini_set('display_errors', 1);
                        ini_set('display_startup_errors', 1);
                        error_reporting(E_ALL);
                        require 'db.php';

                        $feedback = '';

                        // Final step: save password
                        if (isset($_POST['confirm_password'])) {
                            $token = $_POST['token'] ?? '';
                            $user_name = $_POST['user_name'] ?? '';
                            $password = $_POST['password'] ?? '';

                            // Rate limit
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $limit_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
                            $rl = $con->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address=? AND attempt_time>? AND try_for='setup_password_final'");
                            $rl->bind_param('ss', $ip, $limit_time);
                            $rl->execute();
                            $rl_cnt = $rl->get_result()->fetch_assoc()['c'] ?? 0;
                            $rl->close();
                            if ($rl_cnt > 5) {
                                echo "<h4 class='text-danger'>Too many attempts. Try again later.</h4>";
                                exit;
                            }
                            $log = $con->prepare("INSERT INTO login_attempts (ip_address, attempt_time, try_for) VALUES (?, NOW(), 'setup_password_final')");
                            $log->bind_param('s', $ip);
                            $log->execute();
                            $log->close();

                            // Server-side complexity
                            $len = strlen($password) >= 8;
                            $upper = preg_match('/[A-Z]/', $password);
                            $lower = preg_match('/[a-z]/', $password);
                            $num = preg_match('/[0-9]/', $password);
                            $spec = preg_match('/[\W_]/', $password);
                            if (!($len && $upper && $lower && $num && $spec)) {
                                echo "<h4 class='text-danger'>Password does not meet complexity requirements.</h4>";
                                exit;
                            }

                            // Verify token+username
                            $sel = $con->prepare("SELECT usr_id FROM user_license WHERE token=? AND username=? LIMIT 1");
                            $sel->bind_param('ss', $token, $user_name);
                            $sel->execute();
                            $u = $sel->get_result()->fetch_assoc();
                            $sel->close();
                            if (!$u) {
                                echo "<h4 class='text-danger'>Invalid token or user.</h4>";
                                exit;
                            }

                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $last_token = bin2hex(random_bytes(32));
                            $uid = (int)$u['usr_id'];
                            $upd = $con->prepare("UPDATE user_license SET password=?, account_status='1', token='Expired', last_log_in=NOW(), last_token=? WHERE usr_id=?");
                            $upd->bind_param('ssi', $hash, $last_token, $uid);
                            if ($upd->execute()) {
                                echo "<h3 class='text-primary text-center m-b-25'>Password set successfully. Redirecting to login...</h3>";
                                header('refresh:2;url=login.php');
                                exit;
                            } else {
                                echo "<h4 class='text-danger'>Failed to set password. Please try again.</h4>";
                                exit;
                            }
                        }
                        ?>

                        <h3 class="text-primary text-center m-b-25">Accept Invitation</h3>

                        <?php if (!isset($_POST['set_password'])) { ?>
                            <form method="POST" action="">
                                <div class="md-group" align="center">
                                    <div class="md-input-wrapper">
                                        <input type="number" name="mobile_number" style="text-align:center;" required class="md-form-control" style="width:200px;">
                                        <label>Enter Your Mobile Number:</label>
                                    </div>
                                </div>
                                <div class="md-group" align="center">
                                    <div class="md-input-wrapper">
                                        <input type="text" name="token" style="text-align:center;" required class="md-form-control" style="width:200px;">
                                        <label>Token</label>
                                    </div>
                                </div>
                                <div class="btn-forgot">
                                    <input type="submit" name="set_password" value="Set Password" class="btn btn-primary btn-md waves-effect waves-light text-center">
                                    <br><br>
                                    <a href="login.php" class="btn btn-outline-secondary btn-md waves-effect waves-light">Go to Login</a>
                                </div>
                            </form>
                        <?php } else { ?>
                            <?php
                            // Verify token + rate limit then show password form
                            $token = $_POST['token'] ?? '';
                            $user_name = $_POST['mobile_number'] ?? '';
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $limit_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
                            $rl = $con->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address=? AND attempt_time>? AND try_for='setup_password'");
                            $rl->bind_param('ss', $ip, $limit_time);
                            $rl->execute();
                            $c = $rl->get_result()->fetch_assoc()['c'] ?? 0;
                            $rl->close();
                            if ($c > 5) {
                                echo "<h4 class='text-danger'>Too many attempts. Try again later.</h4>";
                                exit;
                            }
                            $log = $con->prepare("INSERT INTO login_attempts (ip_address, attempt_time, try_for) VALUES (?, NOW(), 'setup_password')");
                            $log->bind_param('s', $ip);
                            $log->execute();
                            $log->close();

                            $stmt = $con->prepare("SELECT 1 FROM user_license WHERE token=? AND username=? LIMIT 1");
                            $stmt->bind_param('ss', $token, $user_name);
                            $stmt->execute();
                            $ok = $stmt->get_result()->num_rows === 1;
                            $stmt->close();
                            if (!$ok) {
                                echo "<h3 class='text-danger'>Invalid details.</h3>";
                                exit;
                            }
                            ?>
                            <form method="post" action="">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($user_name); ?>">

                                <div class="md-group" align="center">
                                    <div class="md-input-wrapper">
                                        <input type="password" name="password" id="new_password" class="md-form-control" style="width:200px; text-align:center;" required>
                                        <label>Please set your password:</label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="show_new">
                                        <label class="form-check-label" for="show_new">Show Password</label>
                                    </div>
                                </div>

                                <div class="md-group" align="center">
                                    <div class="md-input-wrapper">
                                        <input type="password" name="confirm_password" id="confirm_password" class="md-form-control" style="width:200px; text-align:center;" required>
                                        <label>Confirm password</label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="show_confirm">
                                        <label class="form-check-label" for="show_confirm">Show Confirm Password</label>
                                    </div>
                                    <div class="mt-3">
                                        <ul class="password-req" id="requirements">
                                            <li id="length" class="invalid"><i class="fa fa-times"></i> Min 8 characters</li>
                                            <li id="uppercase" class="invalid"><i class="fa fa-times"></i> At least one uppercase</li>
                                            <li id="lowercase" class="invalid"><i class="fa fa-times"></i> At least one lowercase</li>
                                            <li id="number" class="invalid"><i class="fa fa-times"></i> At least one number</li>
                                            <li id="special" class="invalid"><i class="fa fa-times"></i> At least one special</li>
                                            <li id="match" class="invalid"><i class="fa fa-times"></i> Passwords match</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="btn-forgot">
                                    <input type="submit" id="submitBtn" disabled value="Set Password" class="btn btn-primary btn-md waves-effect waves-light text-center">
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/plugins/jquery/dist/jquery.min.js"></script>
    <script src="assets/plugins/jquery-ui/jquery-ui.min.js"></script>
    <script src="assets/plugins/tether/dist/js/tether.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="assets/plugins/Waves/waves.min.js"></script>
    <script type="text/javascript" src="assets/pages/elements.js"></script>

    <script>
    $(document).ready(function(){
        function validatePassword(){
            var password = $("#new_password").val();
            var confirm  = $("#confirm_password").val();

            var length   = password.length >= 8;
            var upper    = /[A-Z]/.test(password);
            var lower    = /[a-z]/.test(password);
            var number   = /[0-9]/.test(password);
            var special  = /[\W_]/.test(password);
            var match    = password === confirm && password.length > 0;

            updateReq("#length", length);
            updateReq("#uppercase", upper);
            updateReq("#lowercase", lower);
            updateReq("#number", number);
            updateReq("#special", special);
            updateReq("#match", match);

            if(length && upper && lower && number && special && match){
                $("#submitBtn").prop("disabled", false);
            } else {
                $("#submitBtn").prop("disabled", true);
            }
        }

        function updateReq(id, status){
            if(status){
                $(id).removeClass("invalid").addClass("valid").html('<i class="fa fa-check"></i> ' + $(id).text().replace(/^.*? /, ""));
            } else {
                $(id).removeClass("valid").addClass("invalid").html('<i class="fa fa-times"></i> ' + $(id).text().replace(/^.*? /, ""));
            }
        }

        $("#new_password, #confirm_password").on("keyup", validatePassword);

        $("#show_new").on("change", function(){
            $("#new_password").attr("type", this.checked ? "text" : "password");
        });
        $("#show_confirm").on("change", function(){
            $("#confirm_password").attr("type", this.checked ? "text" : "password");
        });
    });
    </script>
</body>

</html><?php exit;  ?>