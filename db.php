<?php
$con = $conn = new mysqli("localhost", "root", "", "land_administration");
// $conn = $con = new mysqli("localhost", "trincomp_kiri", "kiritharan100@gmail.com", "trincomp_am_amparampcs");
mysqli_set_charset($con, "utf8mb4");
/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
if (!$con->set_charset("utf8")) {
   // printf("Error loading character set utf8: %s\n", $con->error);
    exit();
} else {
    
}

// Encryption key used for ID obfuscation. Change this to a strong random secret in production.
if (!defined('APP_SECRET')) {
    // NOTE: Replace the string below with a strong random passphrase and keep it secret.
    define('APP_SECRET', 'dep_land_admin_2024_secure_key');
}

if (!function_exists('encrypt_id')) {
    /**
     * Encrypt an integer ID into a URL-safe token.
     * Returns a short URL-safe string.
     */
    function encrypt_id($id) {
        $id = (string)$id;
        $key = hash('sha256', APP_SECRET, true); // 32 bytes key
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($id, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv . $cipher, APP_SECRET, true);
        $token = base64_encode($iv . $hmac . $cipher);
        // make URL safe
        return rtrim(strtr($token, '+/', '-_'), '=');
    }
}

if (!function_exists('decrypt_id')) {
    /**
     * Decrypt a token produced by encrypt_id() and return the original ID string.
     * Returns the ID string on success or false on failure.
     */
    function decrypt_id($token) {
        if (empty($token)) return false;
        // restore base64 padding
        $b64 = strtr($token, '-_', '+/');
        $mod4 = strlen($b64) % 4;
        if ($mod4) { $b64 .= str_repeat('=', 4 - $mod4); }
        $data = base64_decode($b64, true);
        if ($data === false || strlen($data) < 48) return false; // iv(16) + hmac(32) minimal
        $iv = substr($data, 0, 16);
        $hmac = substr($data, 16, 32);
        $cipher = substr($data, 48);
        $calc = hash_hmac('sha256', $iv . $cipher, APP_SECRET, true);
        if (!hash_equals($calc, $hmac)) return false;
        $key = hash('sha256', APP_SECRET, true);
        $id = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($id === false) return false;
        return $id;
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($p) {
        return !empty($_SESSION['permissions']) && in_array($p, $_SESSION['permissions']);
    }
}

 
if (!function_exists('UserLog')) {
    function UserLog($module, $action, $detail, $ben_id = null, $type = null) {

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        global $con;

        // Resolve location_id from cookie
        if (isset($_COOKIE['client_cook'])) {
            $selected_client = $_COOKIE['client_cook'];
            $sel_query = "SELECT c_id FROM client_registration WHERE md5_client='$selected_client'";
            $result = mysqli_query($con, $sel_query);
            $row = mysqli_fetch_assoc($result);
            $location = $row['c_id'] ?? '0';
        } else {
            $location = '0';
        }

        $usr_id = $_SESSION['user_id'];

        // Sanitize
        $module = mysqli_real_escape_string($con, $module);
        $action = mysqli_real_escape_string($con, $action);
        $detail = mysqli_real_escape_string($con, $detail);

        // If ben_id is null, store NULL in DB
        $ben_id_sql = $ben_id !== null ? "'" . intval($ben_id) . "'" : "NULL";
        if ($type !== null) {
            $type = mysqli_real_escape_string($con, $type);
        } else {
            $type = '';
        }

        // Insert
        $sql = "
            INSERT INTO user_log (usr_id, module, action, detail, location, ben_id, lease_type)
            VALUES ('$usr_id', '$module', '$action', '$detail', '$location', $ben_id_sql, '$type')
        ";

        if (mysqli_query($con, $sql)) {
            return true;
        } else {
            echo "Error: " . $sql . "<br>" . mysqli_error($con);
            return false;
        }
    }
}


    
// if (!function_exists('UserLog')) {
//     function UserLog($module, $action, $detail) {
//         if (session_status() == PHP_SESSION_NONE) {
//             if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
//         }
//         global $con;  
//         if(isset($_COOKIE['client_cook'])){
//             $selected_client= $_COOKIE['client_cook'];
//             $sel_query="SELECT c_id from client_registration where md5_client='$selected_client'";
//             $result = mysqli_query($con,$sel_query);
//             $row = mysqli_fetch_assoc($result); 
//             $location = $row['c_id'];
//         }else {
//             $location = '0';
//         }
//         $usr_id = $_SESSION['user_id'];
//         $module = mysqli_real_escape_string($con, $module);
//         $action = mysqli_real_escape_string($con, $action);
//         $detail = mysqli_real_escape_string($con, $detail); 
//         $sql = "INSERT INTO `user_log` (`usr_id`, `module`, `action`, `detail`,location) 
//                 VALUES ('$usr_id', '$module', '$action', '$detail','$location')";
//         if (mysqli_query($con, $sql)) {
//             return true; 
//         } else {
//             echo "Error: " . $sql . "<br>" . mysqli_error($con);
//             return false; 
//         }
//     }
// }
// ?>