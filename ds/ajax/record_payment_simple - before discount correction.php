<?php
session_start();
include '../../db.php';

header('Content-Type: application/json');

// Ensure required columns exist
function ensure_column_exists($con, $table, $column, $definition){
    $tableEsc = mysqli_real_escape_string($con, $table);
    $colEsc   = mysqli_real_escape_string($con, $column);
    $chk = mysqli_query(
        $con,
        "SELECT 1 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
           AND TABLE_NAME='$tableEsc' 
           AND COLUMN_NAME='$colEsc' 
         LIMIT 1"
    );
    if($chk && mysqli_num_rows($chk) === 0){
        @mysqli_query($con, "ALTER TABLE `$tableEsc` ADD COLUMN `$colEsc` $definition");
    }
}

ensure_column_exists($con, 'lease_schedules', 'discount_apply', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
ensure_column_exists($con, 'lease_payments', 'discount_apply', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
ensure_column_exists($con, 'lease_payments', 'current_year_payment', 'DECIMAL(12,2) NOT NULL DEFAULT 0');

if ($_POST) {

    $response = ['success' => false, 'message' => ''];

    try {

        if (empty($_POST['lease_id']) || empty($_POST['payment_date']) || empty($_POST['amount'])) {
            throw new Exception("Missing required fields");
        }

        if(isset($_COOKIE['client_cook'])){
            $selected_client = $_COOKIE['client_cook'];
            $sel_query = "SELECT c_id FROM client_registration WHERE md5_client='$selected_client'";
            $result    = mysqli_query($con, $sel_query);
            $row       = mysqli_fetch_assoc($result); 
            $location_id = $row['c_id'];
        } else {
            $location_id = 0;
        }

        $lease_id         = intval($_POST['lease_id']);
        $lease_type_id    = intval($_POST['lease_type_id']);
        $payment_date     = $_POST['payment_date'];
        $amount           = floatval($_POST['amount']);
        $payment_method   = $_POST['payment_method'] ?? 'cash';
        $reference_number = $_POST['reference_number'] ?? '';
        $notes            = $_POST['notes'] ?? '';
        $ben_id           = intval($_POST['ben_id'] ?? 0);

              // Hidden Fields
        $payment_sms  = intval($_POST['payment_sms'] ?? 0);
        $sms_language = trim($_POST['sms_language'] ?? 'English');
        $telephone    = trim($_POST['telephone'] ?? '');

        

        if ($amount <= 0) {
            throw new Exception("Payment amount must be greater than zero");
        }

        $receipt_number = "RCPT-" . date('Ymd-His') . "-" . rand(100,999);
        $user_id        = $_SESSION['user_id'] ?? 1;


        // =====================================================
        // FIND CURRENT SCHEDULE
        // =====================================================

        $schedule_check = "SELECT schedule_id, start_date, end_date, schedule_year 
                           FROM lease_schedules 
                           WHERE lease_id = ? AND ? BETWEEN start_date AND end_date 
                           LIMIT 1";
        $stmt_check = $con->prepare($schedule_check);
        $stmt_check->bind_param("is", $lease_id, $payment_date);
        $stmt_check->execute();
        $schedule_result = $stmt_check->get_result();

        if ($schedule_result->num_rows > 0) {
            $curSched = $schedule_result->fetch_assoc();
            $schedule_id = $curSched['schedule_id'];
        } else {
            $next_sql = "SELECT schedule_id, start_date 
                         FROM lease_schedules 
                         WHERE lease_id=? AND start_date > ? 
                         ORDER BY start_date ASC LIMIT 1";
            $stmt_n = $con->prepare($next_sql);
            $stmt_n->bind_param("is",$lease_id,$payment_date);
            $stmt_n->execute();
            $next_rs = $stmt_n->get_result();

            if($next_rs->num_rows>0){
                $curSched = $next_rs->fetch_assoc();
                $schedule_id = $curSched['schedule_id'];
            }
        }

        if(!$schedule_id){
            throw new Exception("Cannot find matching lease schedule");
        }


        // =====================================================
        // LOAD SCHEDULE DATA
        // =====================================================

        $cur = mysqli_fetch_assoc(
            mysqli_query($con, "SELECT * FROM lease_schedules WHERE schedule_id=$schedule_id")
        );

        $current_annual_amount    = floatval($cur['annual_amount']);
        $current_rent_paid_so_far = floatval($cur['paid_rent']);
        $current_schedule_start   = $cur['start_date'];
        $current_schedule_end     = $cur['end_date'];
        $existing_discount_applied= floatval($cur['discount_apply']);


        // =====================================================
        // FIND NEXT SCHEDULE
        // =====================================================

        $nxrs = mysqli_query($con,
            "SELECT schedule_id, annual_amount
             FROM lease_schedules
             WHERE lease_id=$lease_id 
               AND start_date > '$current_schedule_start'
             ORDER BY start_date ASC LIMIT 1"
        );

        $has_next_schedule = false;
        $next_schedule_id = null;
        $next_annual_amount = 0;

        if($nxrs && mysqli_num_rows($nxrs)>0){
            $nx = mysqli_fetch_assoc($nxrs);
            $has_next_schedule = true;
            $next_schedule_id  = $nx['schedule_id'];
            $next_annual_amount= floatval($nx['annual_amount']);
        }


        // =====================================================
        //   FIXED ARREARS CHECK (CUMULATIVE)
        // =====================================================

        $arrears_sql = "
            SELECT 
                SUM(COALESCE(annual_amount,0))  AS sum_annual,
                SUM(COALESCE(paid_rent,0))      AS sum_paid_rent,
                SUM(COALESCE(panalty,0))        AS sum_pen,
                SUM(COALESCE(panalty_paid,0))   AS sum_pen_paid,
                SUM(COALESCE(premium,0))        AS sum_prem,
                SUM(COALESCE(premium_paid,0))   AS sum_prem_paid
            FROM lease_schedules
            WHERE lease_id = $lease_id
              AND end_date < '$current_schedule_start'";

        $ar = mysqli_fetch_assoc(mysqli_query($con,$arrears_sql));

        $sum_annual      = floatval($ar['sum_annual'] ?? 0);
        $sum_paid_rent   = floatval($ar['sum_paid_rent'] ?? 0);
        $sum_pen         = floatval($ar['sum_pen'] ?? 0);
        $sum_pen_paid    = floatval($ar['sum_pen_paid'] ?? 0);
        $sum_prem        = floatval($ar['sum_prem'] ?? 0);
        $sum_prem_paid   = floatval($ar['sum_prem_paid'] ?? 0);

        $outstanding_before = max(0, $sum_annual - $sum_paid_rent)
                            + max(0, $sum_pen    - $sum_pen_paid)
                            + max(0, $sum_prem   - $sum_prem_paid);

        $no_arrears_before_start = ($outstanding_before < 0.005);


        // =====================================================
        // PAYMENT ALLOCATION: premium → penalty → rent(current)
        // =====================================================

        $remaining_payment = $amount;

        // Premium
 

        // $cur_premium       = floatval($cur['premium']);
        // $cur_premium_paid  = floatval($cur['premium_paid']);
        $cur_premium =$sum_prem ;
        $cur_premium_paid =  $sum_prem_paid;
        $premium_outstanding = max(0,$cur_premium - $cur_premium_paid);
        $premium_payment_now = min($remaining_payment,$premium_outstanding);
        $remaining_payment -= $premium_payment_now;

        // Penalty
        $pen_rs = mysqli_fetch_assoc(mysqli_query(
            $con,"SELECT 
                    SUM(COALESCE(panalty,0)) AS p, 
                    SUM(COALESCE(panalty_paid,0)) AS pp
                  FROM lease_schedules
                  WHERE lease_id=$lease_id AND end_date <= '{$cur['end_date']}'"
        ));
        $out_pen = max(0,$pen_rs['p'] - $pen_rs['pp']);
        $penalty_payment = min($remaining_payment,$out_pen);
        $remaining_payment -= $penalty_payment;

        // Rent (current)
        $rent_payment_current = $remaining_payment;
        $remaining_payment = 0;


        // =====================================================
        // DISCOUNT LOGIC
        // =====================================================


        $sql = "SELECT * FROM lease_master WHERE lease_type_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $lease_type_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $discount_rate = floatval($row['discount_rate']/100); 
        } else {
            $discount_rate = 0.00;  // default if not found
        }


        // $discount_rate = 0.10;
        $discount_amount = 0;
        $discount_applied = false;

        $payment_ts = strtotime($payment_date);
        $deadline_ts = strtotime($current_schedule_start." +30 days");
        $within_window = ($payment_ts <= $deadline_ts);

        $prospective_rent = $current_rent_paid_so_far + $rent_payment_current;
        $min_for_discount = $current_annual_amount * 0.90;

        if($existing_discount_applied == 0){

            // CASE A: discount for current period
            if($within_window && $no_arrears_before_start && $prospective_rent >= $min_for_discount){
                $discount_amount = $current_annual_amount * $discount_rate;
                $discount_applied = true;
            }

            // CASE B: next-period early discount
            if(!$discount_applied && $has_next_schedule && $within_window){
                if($prospective_rent >= $current_annual_amount && $no_arrears_before_start){
                    $discount_amount = $next_annual_amount * $discount_rate;
                    $discount_applied = true;
                }
            }
        }


        // =====================================================
        // RENT REALLOCATION IF DISCOUNT APPLIED
        // =====================================================

        $rent_payment_next = 0;

        if($discount_applied){

            $max_current_rent = $current_annual_amount - $discount_amount;
            $new_total_rent   = $current_rent_paid_so_far + $rent_payment_current;

            if($new_total_rent > $max_current_rent && $has_next_schedule){

                $rent_payment_next = $new_total_rent - $max_current_rent;
                $rent_payment_current -= $rent_payment_next;

                if($rent_payment_current < 0){
                    $rent_payment_next += $rent_payment_current;
                    $rent_payment_current = 0;
                }
            }
        }


        // =====================================================
        // INSERT PAYMENT
        // =====================================================

        $current_year_payment = $rent_payment_current;  // NEW RULE

        $payment_sql = "INSERT INTO lease_payments (
                lease_id, location_id, schedule_id, payment_date, amount,
                rent_paid, panalty_paid, premium_paid, discount_apply,
                current_year_payment, payment_type,
                receipt_number, payment_method, reference_number, notes, created_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $con->prepare($payment_sql);

        $total_rent_paid   = $rent_payment_current + $rent_payment_next;
        $discount_to_apply = $discount_applied ? $discount_amount : 0;
        $payment_type      = 'mixed';

        $stmt->bind_param(
            'iiisddddddsssssi',
            $lease_id, $location_id, $schedule_id, $payment_date, $amount,
            $total_rent_paid, $penalty_payment, $premium_payment_now,
            $discount_to_apply, $current_year_payment,
            $payment_type,
            $receipt_number, $payment_method, $reference_number,
            $notes, $user_id
        );

        $stmt->execute();


        // =====================================================
        // UPDATE CURRENT SCHEDULE
        // =====================================================

        mysqli_query($con,
            "UPDATE lease_schedules SET 
                paid_rent     = paid_rent + $rent_payment_current,
                panalty_paid  = panalty_paid + $penalty_payment,
                premium_paid  = premium_paid + $premium_payment_now,
                total_paid    = total_paid + ".($rent_payment_current+$penalty_payment+$premium_payment_now).",
                discount_apply= discount_apply + $discount_to_apply
             WHERE schedule_id=$schedule_id"
        );

        // Next schedule advance
        if($rent_payment_next > 0 && $has_next_schedule){
            mysqli_query($con,
                "UPDATE lease_schedules SET 
                    paid_rent = paid_rent + $rent_payment_next,
                    total_paid= total_paid + $rent_payment_next
                 WHERE schedule_id=$next_schedule_id"
            );
        }




        /* ---------------------------------------------------------
   SMS SENDING (FOLLOWING EXACT SAMPLE FORMAT)
--------------------------------------------------------- */
if ($payment_sms == 1) {

    // Validate telephone number (only 10 digits allowed)
    if (preg_match('/^[0-9]{10}$/', $telephone)) {

        // Load SMS template based on payment SMS
        $tpl_sql = "SELECT english_sms, tamil_sms, sinhala_sms 
                    FROM sms_templates 
                    WHERE sms_name = 'Payment' AND status = 1 LIMIT 1";
        $tpl_stmt = $con->prepare($tpl_sql);
        $tpl_stmt->execute();
        $tpl = $tpl_stmt->get_result()->fetch_assoc();

        if ($tpl) {

            // Select language SMS
            if ($sms_language === 'Tamil') {
                $message = $tpl['tamil_sms'];
            } elseif ($sms_language === 'Sinhala') {
                $message = $tpl['sinhala_sms'];
            } else {
                $message = $tpl['english_sms'];
            }

            // Replace placeholders
            $message = str_replace("@Receipt_no", $reference_number, $message);
            $message = str_replace("@paid_amount", number_format($amount, 2), $message);

            // EXACT SAME FORMAT AS YOUR SAMPLE
            require_once __DIR__ . '/../../sms_helper.php';
            $sms = new SMS_Helper();

            $sms_type = 'Payment SMS';

            // USE EXACT SAMPLE METHOD CALL
            $result = $sms->sendSMS(
                $lease_id,
                $telephone,
                $message,
                $sms_type
            );

            // If SMS fails → only warning, not error
            if (!$result['success']) {
                $response['sms_warning'] = "SMS failed: " . $result['comment'];
            }
        }
    }
}



        // =====================================================
        // RESPONSE
        // =====================================================
        //-------------------------------------------------------------
        // USER LOG FOR PAYMENT ENTRY
        //-------------------------------------------------------------
            $log_detail  = "Lease ID=$lease_id"; 
            $log_detail .= " | Amount=" . number_format($amount, 2);
            $log_detail .= " | Rec No=$reference_number";
            $log_detail .= " | Method=$payment_method";
            $log_detail .= " | Date=$payment_date";
            $log_detail .= " | Discount=" . ($discount_applied ? "YES" : "NO");

            UserLog("2", "LTL New Payment", $log_detail, $ben_id);


        $response['success'] = true;
        $response['discount'] = $discount_applied ? "YES" : "NO";
        $response['discount_amount'] = number_format($discount_amount,2);
        $response['message'] = "Payment saved successfully";

    } catch (Exception $e){
        $response['success']=false;
        $response['message']="Error: ".$e->getMessage();
    }

    echo json_encode($response);
    exit;
}

echo json_encode(['success'=>false,'message'=>'No data received']);
?>