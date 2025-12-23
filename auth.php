<?php 
 
 $conn1 = new mysqli("localhost", "root", "", "land_administration");
// $conn1 = $con = new mysqli("localhost", "trincomp_kiri", "kiritharan100@gmail.com", "trincomp_am_amparampcs");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
   if(empty($_SESSION['username'])) {
      session_unset();     
      session_destroy(); 
      header("Location: login.php?msg=true");
      exit;
   }

   
    if (!isset($_SESSION['permissions'])) {
    header("Location: login.php");
    exit;
    }

 

   
   
   
     $user = $_SESSION['username'];
     $sel_query="SELECT * from user_license where username='$user'";
     $result = mysqli_query($conn1,$sel_query);
     
     
     $user_row = mysqli_fetch_assoc($result);
     $user_id = $user_row['usr_id'];
     $rights= $user_row['user_rights'];
     
     if($_SESSION['last_token'] <> $user_row['last_token']){
          header("Location: login.php?multiple_sign_in");
         exit;
     }
     
     
 $cookie_name = "DR" . $user_id;
      $username = $user_row['username'];
      if (isset($_COOKIE[$cookie_name])) {
        $encrypted_token = $_COOKIE[$cookie_name];

        // Decrypt token
        $ciphering = "AES-128-CTR";
        $encryption_iv = '1234567891011121';
        $encryption_key = "kiritharan100@gmail.com";
        $options = 0;

        $decrypted_token = openssl_decrypt(
            $encrypted_token,
            $ciphering,
            $encryption_key,
            $options,
            $encryption_iv
        );

        // Check if token matches for this username
        // $check_sql = "SELECT 1 FROM user_device WHERE pf_no = '$username' AND token = '$decrypted_token'";
        // $check_result = mysqli_query($con, $check_sql);
$check_sql = $conn1->prepare("SELECT 1 FROM user_device WHERE pf_no = ? AND token = ?");
$check_sql->bind_param("ss", $username, $decrypted_token);
$check_sql->execute();
$check_result = $check_sql->get_result();

        if (mysqli_num_rows($check_result) === 0) {
            // ❌ Invalid device - redirect to login
            header("Location: login.php?reg_faild");
            exit;
        }
        // ✅ Device is valid – do nothing
    } else {
        // ❌ No cookie - redirect to login
        header("Location: login.php?reg_faild");
        exit;
    }
    
    
   
   $timeout = 12600; // 30 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && time() - $_SESSION['LAST_ACTIVITY'] > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=true");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();






   date_default_timezone_set('Asia/Colombo');


if (!function_exists('sendSMS')) {
    function sendSMS($to, $message) {
        // Global credentials
        $user_id   = 290;
        $api_key   = 'a52147cf-ec5b-451d-b4c0-058c030a6d21';
        $sender_id = 'DtecStudio';
        $url       = "https://smslenz.lk/api/send-sms";

        $data = [
            'user_id'   => $user_id,
            'api_key'   => $api_key,
            'sender_id' => $sender_id,
            'contact'   => $to,
            'message'   => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $timestamp = date("Y-m-d H:i:s");

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $logMessage = "[$timestamp] ERROR sending to $to: $error\n";
            file_put_contents("sms_log.txt", $logMessage, FILE_APPEND);
            curl_close($ch);
            return ['success' => false, 'error' => $error];
        }

        curl_close($ch);

        // Log the response
        $logMessage = "[$timestamp] SMS sent to $to: $response\n";
        file_put_contents("sms_log.txt", $logMessage, FILE_APPEND);

        return ['success' => true, 'response' => $response];
    }
}
mysqli_set_charset($con, "utf8mb4");
?>