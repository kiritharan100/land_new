<?php
 
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

date_default_timezone_set("Asia/Colombo");

// ----------------------------
// Load Database & Settings
// -------------------------
include 'db.php';
require_once __DIR__ . '/sms_helper.php'; // New SMS gateway helper

function getSettings($con) {
    $result = mysqli_query($con, "SELECT * FROM letter_head WHERE id = 1");
    return mysqli_fetch_assoc($result);
}

// Instantiate reusable SMS helper (Dialog API based)
$smsHelper = new SMS_Helper();

// -------------------------
// Handle Login Submission
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['LoginSubmit'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $limit_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));

    // Brute force protection (prepared)
    $attempt_stmt = $con->prepare("SELECT COUNT(*) AS attempt_count FROM login_attempts WHERE ip_address = ? AND attempt_time > ? AND try_for='login'");
    $attempt_stmt->bind_param('ss', $ip, $limit_time);
    $attempt_stmt->execute();
    $attempt_res = $attempt_stmt->get_result();
    $attempt_row = $attempt_res->fetch_assoc();
    $attempt_stmt->close();

    if ($attempt_row && $attempt_row['attempt_count'] > 5) {
        echo "<h4 style='color:red;'>Too many attempts. Try again later.</h4>";
        exit;
    }

    $raw_username = $_POST['username'] ?? '';
    $raw_password = $_POST['password'] ?? '';

    if ($raw_username === '' || $raw_password === '') {
        echo "<script>alert('Please enter username and password');</script>";
    } else {
        $user_stmt = $con->prepare("SELECT usr_id, username, password, customer, i_name, role_id, account_status FROM user_license WHERE username = ? AND account_status = '1' LIMIT 1");
        $user_stmt->bind_param('s', $raw_username);
        $user_stmt->execute();
        $user_res = $user_stmt->get_result();
        $row = $user_res->fetch_assoc();
        $user_stmt->close();

        $login_ok = false;
        if ($row) {
            $stored = $row['password'];
            if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
                $legacy_hash = md5('893121129V' . $raw_password);
                if (hash_equals($stored, $legacy_hash)) {
                    $new_hash = password_hash($raw_password, PASSWORD_DEFAULT);
                    $upd_stmt = $con->prepare("UPDATE user_license SET password = ? WHERE usr_id = ?");
                    $upd_stmt->bind_param('si', $new_hash, $row['usr_id']);
                    $upd_stmt->execute();
                    $upd_stmt->close();
                    $login_ok = true;
                }
            } else {
                if (password_verify($raw_password, $stored)) {
                    if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                        $rehash = password_hash($raw_password, PASSWORD_DEFAULT);
                        $reh_stmt = $con->prepare("UPDATE user_license SET password = ? WHERE usr_id = ?");
                        $reh_stmt->bind_param('si', $rehash, $row['usr_id']);
                        $reh_stmt->execute();
                        $reh_stmt->close();
                    }
                    $login_ok = true;
                }
            }
        }

        if ($login_ok) {
            session_regenerate_id(true);
            $settings = getSettings($con);
            $entity = $settings['company_name'];
            $vat_no = $settings['vat_no'];
            $vat_client = !empty($vat_no) ? 1 : 0;
            $token = bin2hex(random_bytes(32));
            $user_id = (int)$row['usr_id'];
            $token_stmt = $con->prepare("UPDATE user_license SET last_log_in = NOW(), last_token = ? WHERE usr_id = ?");
            $token_stmt->bind_param('si', $token, $user_id);
            $token_stmt->execute();
            $token_stmt->close();

            $_SESSION['username'] = $row['username'];
            $_SESSION['user_id'] = $user_id;
            $_SESSION['customer'] = $row['customer'];
            $_SESSION['i_name'] = $row['i_name'];
            $_SESSION['app_name'] = 'IRCMS';
            $_SESSION['company_name'] = $entity;
            $_SESSION['last_token'] = $token;
            $_SESSION['company'] = $entity;
            $_SESSION['vat_reg_no'] = $settings['VAT'];
            $_SESSION['vat_invpice_serial'] = $settings['invoice_prefix'];
            $_SESSION['vat_applicable'] = $vat_client;
            $_SESSION['address1'] = 'Eastern Province';

            $role_id = (int)$row['role_id'];
            $perm_stmt = $conn->prepare("SELECT act_id FROM group_activity_map WHERE group_id = ? AND status = 1");
            $perm_stmt->bind_param('i', $role_id);
            $perm_stmt->execute();
            $perm_res = $perm_stmt->get_result();
            $permissions = [];
            while ($r = $perm_res->fetch_assoc()) { $permissions[] = $r['act_id']; }
            $perm_stmt->close();
            $_SESSION['permissions'] = $permissions;

            $log_stmt = $con->prepare("INSERT INTO user_log (usr_id, module, action, detail) VALUES (?, '0', 'Login', ?)");
            $detail = 'Login from ' . $ip;
            $log_stmt->bind_param('is', $user_id, $detail);
            $log_stmt->execute();
            $log_stmt->close();

            $cookie_name = 'DR' . $user_id;
            $encrypted_token = $_COOKIE[$cookie_name] ?? null;
            $decryption = null;
            if ($encrypted_token) {
                $decryption = openssl_decrypt($encrypted_token, 'AES-128-CTR', 'kiritharan100@gmail.com', 0, '1234567891011121');
            }
            $device_stmt = $con->prepare("SELECT 1 FROM user_device WHERE pf_no = ? AND token = ? LIMIT 1");
            $device_stmt->bind_param('ss', $row['username'], $decryption);
            $device_stmt->execute();
            $device_res = $device_stmt->get_result();
            $device_valid = $device_res && $device_res->num_rows === 1;
            $device_stmt->close();

            if (!$encrypted_token || !$device_valid) {
                $new_token = (string)random_int(1000, 9999);
                $dr_stmt = $con->prepare("UPDATE user_license SET dr_token = ? WHERE usr_id = ?");
                $dr_stmt->bind_param('si', $new_token, $user_id);
                $dr_stmt->execute();
                $dr_stmt->close();
                $sms_to = $settings['admin_device_approval'] ? $settings['gm_mobile'] : $row['username'];
                $sms_msg = $settings['admin_device_approval']
                    ? 'User ' . $row['i_name'] . ' is accessing from a new device. Share this token if valid: ' . $new_token
                    : 'Your device registration token for IRCMS: ' . $new_token;
                // Use new gateway (sms_type DEVICE or DEVICE_ADMIN)
                $sms_type = "Device Registration";
                $smsHelper->sendSMS(0, $sms_to, $sms_msg, $sms_type);
                echo "<script>window.location.href = 'device_registration.php?ip=$ip';</script>";
                exit;
            }

            $client_sql = "SELECT client_registration.md5_client FROM client_registration 
                            INNER JOIN user_location ON user_location.location_id = client_registration.c_id
                            WHERE user_location.usr_id = ? AND client_registration.user_license = '1'";
            $client_stmt = $con->prepare($client_sql);
            $client_stmt->bind_param('i', $user_id);
            $client_stmt->execute();
            $client_res = $client_stmt->get_result();
            if ($client_res && $client_res->num_rows === 1) {
                $last_client = $client_res->fetch_assoc()['md5_client'];
                setcookie('client_cook', $last_client, time() + (86400 * 180), '/');
            }
            $client_stmt->close();
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
        } else {
            $fail_stmt = $con->prepare("INSERT INTO login_attempts (ip_address, attempt_time, try_for) VALUES (?, NOW(), 'login')");
            $fail_stmt->bind_param('s', $ip);
            $fail_stmt->execute();
            $fail_stmt->close();
            usleep(random_int(50000,150000));
            echo "<script>alert('Username or Password is incorrect');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Secure Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(to right, #00695c, #26a69a);
        min-height: 100vh;
    }

    .login-wrapper {
        max-width: 400px;
        margin: auto;
    }

    .card {
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .form-control:focus {
        box-shadow: none;
        border-color: #26a69a;
    }

    .login-logo img {
        max-width: 150px;
    }

    .btn-success {
        background-color: #26a69a;
        border: none;
    }

    .btn-success:hover {
        background-color: #1f8f82;
    }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center">

    <div class="login-wrapper w-100 px-3">
        <div class="card p-4">
            <div class="text-center login-logo mb-3">
                <img src="img/gov.png" alt="Logo" style='height:80px;'>
            </div>
            <h1 class="text-center text-success " style=''>
                <b> IRCMS </b>
            </h1>

            <h5 class="text-center text-success mb-3">

                <?php echo isset($entity) ? htmlspecialchars($entity) : 'Department of Land Administration<br> Eastern Province'; ?>
            </h5>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">User Name</label>
                    <input type="text" class="form-control" name="username" id="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" id="password" required>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="showPassword" onclick="togglePassword()">
                        <label class="form-check-label " for="showPassword">Show Password</label>
                    </div>
                </div>
                <button type="submit" name="LoginSubmit" class="btn btn-success w-100">Login</button>
            </form>
            <div class="card-footer text-center mt-3 border-0 bg-transparent">
                <a href="reset_request.php" class="btn btn-outline-primary btn-sm me-2">Forgot Password?</a>
                <a href="setup_password.php" class="btn btn-outline-primary btn-sm">New User?</a>
            </div>
        </div>
        <div class="text-center text-white mt-3">
            <small>
                Solution by
                <a href="https://dtecstudio.com/" target="_blank" class="text-white text-decoration-underline">
                    <img src="https://dtecstudio.com/img/logo.png" width="80" alt="DtecStudio">
                </a>
            </small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Show Password Toggle Script -->
    <script>
    function togglePassword() {
        const pwd = document.getElementById("password");
        pwd.type = pwd.type === "password" ? "text" : "password";
    }
    </script>
    </script>
</body>

</html>