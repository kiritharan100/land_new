<?php
session_start();
include '../../db.php';


// Set content type for JSON response
header('Content-Type: application/json');

// Ensure required columns exist (defensive, in case migrations not applied)
function ensure_column_exists($con, $table, $column, $definition){
    $tableEsc = mysqli_real_escape_string($con, $table);
    $colEsc = mysqli_real_escape_string($con, $column);
    $chk = mysqli_query($con, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='".$tableEsc."' AND COLUMN_NAME='".$colEsc."' LIMIT 1");
    if($chk && mysqli_num_rows($chk) === 0){
        @mysqli_query($con, "ALTER TABLE `".$tableEsc."` ADD COLUMN `".$colEsc."` " . $definition);
    }
}
// discount columns used by logic
ensure_column_exists($con, 'lease_schedules', 'discount_apply', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
ensure_column_exists($con, 'lease_payments', 'discount_apply', 'DECIMAL(12,2) NOT NULL DEFAULT 0');

if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        if (empty($_POST['lease_id']) || empty($_POST['payment_date']) || empty($_POST['amount'])) {
            throw new Exception("Missing required fields");
        }
        

        if(isset($_COOKIE['client_cook'])){
            $selected_client = $_COOKIE['client_cook'];
            $sel_query = "SELECT c_id from client_registration where md5_client='$selected_client'";
            $result = mysqli_query($con, $sel_query);
            $row = mysqli_fetch_assoc($result); 
            $location_id = $row['c_id'];
        } else {
            $location_id = 0;
        }


        $lease_id = intval($_POST['lease_id']);
        $payment_date = $_POST['payment_date'];
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $reference_number = $_POST['reference_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($amount <= 0) {
            throw new Exception("Payment amount must be greater than zero");
        }
        
        // Generate receipt number
        $receipt_number = "RCPT-" . date('Ymd-His') . "-" . rand(100, 999);
        $user_id = $_SESSION['user_id'] ?? 1;
        
        // Find the appropriate schedule based on payment_date within start_date and end_date range
        $schedule_check = "SELECT schedule_id, start_date, end_date, schedule_year FROM lease_schedules WHERE lease_id = ? AND ? BETWEEN start_date AND end_date LIMIT 1";
        $stmt_check = $con->prepare($schedule_check);
        $stmt_check->bind_param("is", $lease_id, $payment_date);
        $stmt_check->execute();
        $schedule_result = $stmt_check->get_result();
        
        $schedule_id = null;
        $schedule_info = null;
        if ($schedule_result->num_rows > 0) {
            $schedule_row = $schedule_result->fetch_assoc();
            $schedule_id = $schedule_row['schedule_id'];
            $schedule_info = $schedule_row;
        } else {
            // No exact-range schedule found: pick next upcoming schedule (earliest start_date after payment_date)
            $next_sql = "SELECT schedule_id, start_date, end_date, schedule_year FROM lease_schedules WHERE lease_id = ? AND start_date > ? ORDER BY start_date ASC LIMIT 1";
            if ($stmt_next = $con->prepare($next_sql)){
                $stmt_next->bind_param("is", $lease_id, $payment_date);
                $stmt_next->execute();
                $res_next = $stmt_next->get_result();
                if ($res_next && $res_next->num_rows > 0){
                    $schedule_row = $res_next->fetch_assoc();
                    $schedule_id = $schedule_row['schedule_id'];
                    $schedule_info = $schedule_row;
                }
                $stmt_next->close();
            }
            // If still not found, fallback to payment year (legacy behavior)
            if (!$schedule_id){
                $payment_year = date('Y', strtotime($payment_date));
                $schedule_check_year = "SELECT schedule_id, start_date, end_date, schedule_year FROM lease_schedules WHERE lease_id = ? AND schedule_year = ? LIMIT 1";
                $stmt_check_year = $con->prepare($schedule_check_year);
                $stmt_check_year->bind_param("ii", $lease_id, $payment_year);
                $stmt_check_year->execute();
                $schedule_result_year = $stmt_check_year->get_result();
                if ($schedule_result_year->num_rows > 0) {
                    $schedule_row = $schedule_result_year->fetch_assoc();
                    $schedule_id = $schedule_row['schedule_id'];
                    $schedule_info = $schedule_row;
                }
            }
        }
        
        // We require a schedule to allocate breakdown
        if (!$schedule_id) {
            throw new Exception("Failed to find matching schedule for payment date.");
        }

        // Fetch current schedule + aggregate penalty + premium status BEFORE inserting payment
        $check_sql = "SELECT ls.schedule_id, ls.start_date, ls.end_date, ls.schedule_year, ls.annual_amount, ls.paid_rent, ls.panalty_paid, ls.total_paid, ls.panalty, ls.premium, ls.premium_paid, ls.discount_apply
                  FROM lease_schedules ls WHERE ls.schedule_id = " . intval($schedule_id) . " LIMIT 1";
        $check_result = mysqli_query($con, $check_sql);
        if(!$check_result){ throw new Exception("Failed to load schedule for allocation: " . mysqli_error($con)); }
        $current_data = mysqli_fetch_assoc($check_result);
        if(!$current_data){ throw new Exception("Schedule data not found for allocation."); }

        // Aggregate penalties up to this schedule's end date
        $penalty_sql = "SELECT SUM(COALESCE(panalty,0)) AS total_penalty, SUM(COALESCE(panalty_paid,0)) AS total_penalty_paid
                        FROM lease_schedules WHERE lease_id=" . intval($lease_id) . " AND end_date <= '" . $current_data['end_date'] . "'";
        $penalty_result = mysqli_query($con, $penalty_sql);
        $penalty_data = $penalty_result ? mysqli_fetch_assoc($penalty_result) : ['total_penalty'=>0,'total_penalty_paid'=>0];
        $total_penalty = floatval($penalty_data['total_penalty'] ?? 0);
        $total_penalty_paid = floatval($penalty_data['total_penalty_paid'] ?? 0);
        $total_outstanding_penalty = max(0, $total_penalty - $total_penalty_paid);

        $current_premium        = floatval($current_data['premium'] ?? 0);
        $current_premium_paid   = floatval($current_data['premium_paid'] ?? 0);
        $premium_outstanding    = max(0, $current_premium - $current_premium_paid);
        $current_schedule_start = $current_data['start_date'];
        $current_schedule_end   = $current_data['end_date'];
        $current_annual_amount  = floatval($current_data['annual_amount']);
        $current_rent_paid_so_far = floatval($current_data['paid_rent']);
        $existing_discount_applied = floatval($current_data['discount_apply'] ?? 0);

        // Find next upcoming schedule (for early payment discount targeting next period)
        $next_schedule_start = null; $next_schedule_end = null; $next_annual_amount = null; $has_next_schedule = false;
        $q_next = "SELECT schedule_id, start_date, end_date, schedule_year, annual_amount FROM lease_schedules WHERE lease_id=" . intval($lease_id) . " AND start_date > '" . mysqli_real_escape_string($con, $current_schedule_start) . "' ORDER BY start_date ASC LIMIT 1";
        if ($rsn = mysqli_query($con, $q_next)){
            if ($rowN = mysqli_fetch_assoc($rsn)){
                $next_schedule_start = $rowN['start_date'];
                $next_schedule_end   = $rowN['end_date'];
                $next_annual_amount  = floatval($rowN['annual_amount']);
                $has_next_schedule   = true;
            }
        }

        // Load all schedules for this lease for discount eligibility checks & prior outstanding settlement
        $all_sched_sql = "SELECT schedule_id, start_date, end_date, annual_amount, paid_rent, panalty, panalty_paid, premium, premium_paid, schedule_year FROM lease_schedules WHERE lease_id=" . intval($lease_id) . " ORDER BY schedule_year";
        $all_sched_rs = mysqli_query($con, $all_sched_sql);
        $prior_schedules = [];
        $future_schedules = [];
        $current_schedule_year = $current_data['schedule_year'];
        if($all_sched_rs){
            while($rowS = mysqli_fetch_assoc($all_sched_rs)){
                if($rowS['schedule_id'] == $schedule_id){ continue; }
                if(strtotime($rowS['end_date']) < strtotime($current_schedule_start)){
                    $prior_schedules[] = $rowS; // ended before current start date
                } else {
                    $future_schedules[] = $rowS; // current or future; current excluded above
                }
            }
        }

        // Compute prior outstanding BEFORE applying this payment (for discount logic)
        $prior_outstanding_rent = 0.0;
        $prior_outstanding_penalty = 0.0;
        $prior_outstanding_premium = 0.0;
        foreach($prior_schedules as $ps){
            $prior_outstanding_rent     += max(0, floatval($ps['annual_amount']) - floatval($ps['paid_rent']));
            $prior_outstanding_penalty  += max(0, floatval($ps['panalty']) - floatval($ps['panalty_paid']));
            $prior_outstanding_premium  += max(0, floatval($ps['premium']) - floatval($ps['premium_paid']));
        }

        $remaining_payment = $amount;
        $premium_payment_now = 0; $penalty_payment = 0; $rent_payment_current = 0; $rent_payment_prior = 0;
        // 1) Premium first
        if($premium_outstanding > 0 && $remaining_payment > 0){
            $premium_payment_now = min($premium_outstanding, $remaining_payment);
            $remaining_payment -= $premium_payment_now;
        }
        // 2) Penalty next (aggregate outstanding up to end date)
        if($total_outstanding_penalty > 0 && $remaining_payment > 0){
            $penalty_payment = min($total_outstanding_penalty, $remaining_payment);
            $remaining_payment -= $penalty_payment;
        }

        // Per business rule: record full penalty payment in current period only (no back-allocation)
        $penalty_alloc_to_current = $penalty_payment;

        // Do not allocate rent to prior schedules; record in current schedule only.
        // 3) Remaining goes to rent
        // Remaining rent goes to current schedule
        $rent_payment_current = $remaining_payment; // may be zero
        $remaining_payment = 0;

        // Discount logic (10%) eligibility evaluation
        $discount_rate = 0.10; $discount_amount = 0.0; $discount_applied = false; $discount_target = 'current';
        $payment_ts  = strtotime($payment_date);
        $current_deadline_ts = strtotime($current_schedule_start . ' +30 days');
        $within_window_current = ($payment_ts <= $current_deadline_ts);
        $next_deadline_ts = $has_next_schedule ? strtotime($next_schedule_start . ' +30 days') : null;
        $within_window_next = $has_next_schedule ? ($payment_ts <= $next_deadline_ts) : false; // before or on 30-day cutoff for next period
        // Prior outstanding after allocations must be zero for premium, penalty, rent
        $prior_outstanding_penalty_after = 0.0; $prior_outstanding_premium_after = 0.0;
        foreach($prior_schedules as $ps){
            $prior_outstanding_penalty_after += max(0, floatval($ps['panalty']) - floatval($ps['panalty_paid']));
            $prior_outstanding_premium_after += max(0, floatval($ps['premium']) - floatval($ps['premium_paid']));
        }
        // Note: Penalty payment we attributed only to current schedule; but aggregate outstanding penalty already reduced by penalty_payment
        // Recompute aggregate penalty outstanding up to prior schedules end dates (excluding current schedule)
        $agg_pen_sql = "SELECT SUM(GREATEST(panalty - panalty_paid,0)) AS pen_out FROM lease_schedules WHERE lease_id=" . intval($lease_id) . " AND end_date < '" . mysqli_real_escape_string($con,$current_schedule_start) . "'";
        $agg_pen_rs = mysqli_query($con, $agg_pen_sql); if($agg_pen_rs && ($ap = mysqli_fetch_assoc($agg_pen_rs))){ $prior_outstanding_penalty_after = floatval($ap['pen_out'] ?? 0); }

        // Compute prospective total rent paid for current schedule after this payment
        $prospective_current_paid_rent = $current_rent_paid_so_far + $rent_payment_current;
        $target_min_paid_for_discount_current = $current_annual_amount * (1 - $discount_rate); // annual - 10%
        if($existing_discount_applied <= 0){
            // Case A: Discount for current period within its 30-day window
            if($within_window_current && $prior_outstanding_rent == 0 && $prior_outstanding_penalty_after == 0 && $prior_outstanding_premium_after == 0){
                if($prospective_current_paid_rent >= $target_min_paid_for_discount_current){
                    $discount_amount = $current_annual_amount * $discount_rate;
                    $discount_applied = true;
                    $discount_target = 'current';
                }
            }
            // Case B: Early payment toward next period: payment before/within next window, record discount in current row
            if(!$discount_applied && $has_next_schedule && $within_window_next){
                // Require current period to be fully settled (rent) after this payment and no prior outstanding elsewhere
                $current_fully_settled = ($prospective_current_paid_rent >= $current_annual_amount);
                if($current_fully_settled && $prior_outstanding_rent == 0 && $prior_outstanding_penalty_after == 0 && $prior_outstanding_premium_after == 0){
                    $discount_amount = $next_annual_amount * $discount_rate;
                    $discount_applied = true;
                    $discount_target = 'next-recorded-in-current';
                }
            }
        }

        // Insert payment with breakdown columns
        $discount_apply = 0; // placeholder (no discount logic yet)
        $payment_type = 'mixed';
                $payment_sql = "INSERT INTO lease_payments (
                            lease_id, location_id, schedule_id, payment_date, amount,
                            rent_paid, panalty_paid, premium_paid, discount_apply, payment_type,
                            receipt_number, payment_method, reference_number, notes, created_by
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $con->prepare($payment_sql);
        if(!$stmt){ throw new Exception("Database error preparing payment: " . $con->error); }
        // Prepare variables for bind_param (cannot pass expressions by reference)
        $rent_paid_total = $rent_payment_prior + $rent_payment_current; // prior remains 0 under current rule
        $discount_to_apply = ($discount_applied ? $discount_amount : 0.0);
        $stmt->bind_param(
            'iiisdddddsssssi',
            $lease_id, $location_id, $schedule_id, $payment_date, $amount,
            $rent_paid_total, $penalty_payment, $premium_payment_now, $discount_to_apply, $payment_type,
            $receipt_number, $payment_method, $reference_number, $notes, $user_id
        );
        if(!$stmt->execute()){ throw new Exception("Failed to insert payment: " . $stmt->error); }

        // Update schedule aggregates (rent/penalty/premium totals)
        $new_panalty_paid = floatval($current_data['panalty_paid'] ?? 0) + $penalty_alloc_to_current;
        $new_paid_rent = floatval($current_data['paid_rent'] ?? 0) + $rent_payment_current; // current schedule only
        $total_alloc_current = $rent_payment_current + $penalty_alloc_to_current + $premium_payment_now;
        $new_total_paid = floatval($current_data['total_paid'] ?? 0) + $total_alloc_current;
        $new_premium_paid = $current_premium_paid + $premium_payment_now;
        $update_sql = "UPDATE lease_schedules SET "
            . "paid_rent=" . $new_paid_rent . ", "
            . "panalty_paid=" . $new_panalty_paid . ", "
            . "premium_paid=" . $new_premium_paid . ", "
            . "total_paid=" . $new_total_paid . ", "
            . "discount_apply=" . ($discount_applied ? $existing_discount_applied + $discount_amount : $existing_discount_applied) . " "
            . "WHERE schedule_id=" . intval($schedule_id);
        if(!mysqli_query($con, $update_sql)){
            throw new Exception("Failed to update schedule after payment: " . mysqli_error($con));
        }

        // Prepare response breakdown
        $response['payment_breakdown'] = [
            'total_payment' => number_format($amount,2),
            'premium_payment' => number_format($premium_payment_now,2),
            'penalty_payment' => number_format($penalty_payment,2),
            'rent_payment_prior' => number_format($rent_payment_prior,2),
            'rent_payment_current' => number_format($rent_payment_current,2),
            'discount_applied' => $discount_applied ? 'YES' : 'NO',
            'discount_amount' => number_format($discount_amount,2),
            'premium_outstanding_before' => number_format($premium_outstanding,2),
            'penalty_outstanding_before' => number_format($total_outstanding_penalty,2),
            'prior_rent_outstanding_before' => number_format($prior_outstanding_rent,2)
        ];
        $response['schedule_update'] = "Schedule ID: $schedule_id updated. Rent Current +" . number_format($rent_payment_current,2) . ", Rent Prior +" . number_format($rent_payment_prior,2) . ", Penalty +" . number_format($penalty_payment,2) . ", Premium +" . number_format($premium_payment_now,2) . ($discount_applied ? "; Discount=" . number_format($discount_amount,2) . " (" . $discount_target . ")" : "");
        if($discount_applied){ $response['message_extra'] = 'Discount applied (10%).'; }
        
        // Continue with penalty recalculation below
        
        // Log the action
        if (function_exists('UserLog')) {
            UserLog('Lease Management', 'Record Payment', "Payment recorded for lease ID: $lease_id, Amount: $amount, Receipt: $receipt_number");
        }
        
        // Trigger penalty calculation for this specific lease after payment
        try {
            // Small delay to ensure prior writes are fully visible to penalty calc
            usleep(800000); // ~0.8 second; adjust if needed
            // Set the lease_id in REQUEST superglobal for cal_panalty.php
            $_REQUEST['lease_id'] = $lease_id;
            
            // Capture output from penalty calculation
            ob_start();
            include '../cal_panalty.php';
            $penalty_result = ob_get_clean();
            
            $response['penalty_calc_status'] = "Penalty calculation completed: " . trim($penalty_result);
        } catch (Exception $penalty_error) {
            $response['penalty_calc_status'] = "Error in penalty calculation: " . $penalty_error->getMessage();
        }
        
        $response['success'] = true;
        $response['message'] = "Payment recorded successfully! Receipt: $receipt_number";
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'No data received']);
}
?>