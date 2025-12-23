<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include '../../db.php';

// Set content type for JSON response
header('Content-Type: application/json');

if (isset($_GET['lease_id']) && isset($_GET['payment_date'])) {
    $lease_id = $_GET['lease_id'];
    $payment_date = $_GET['payment_date'];
    
    $response = ['success' => false, 'outstanding_rent' => 0, 'penalty_amount' => 0, 'total_due' => 0];
    
    try {
        // Use running-balance logic consistent with view_schedule.php
        $as_of = new DateTime($payment_date);

        // Fetch schedules and available fields (panalty, panalty_paid, paid_rent, total_paid)
        $schedule_sql = "SELECT schedule_id, schedule_year, start_date, end_date, annual_amount,
                                panalty, panalty_paid, paid_rent, total_paid
                         FROM lease_schedules
                         WHERE lease_id = ?
                         ORDER BY schedule_year";

        $stmt = $con->prepare($schedule_sql);
        $stmt->bind_param("i", $lease_id);
        $stmt->execute();
        $schedules_res = $stmt->get_result();
        $schedules = $schedules_res->fetch_all(MYSQLI_ASSOC);

        $outstanding_rent_payable = 0.0; // payable as of $as_of (end_date <= as_of)
        $penalty_payable = 0.0; // penalty payable as of $as_of

        $prev_balance_rent = 0.0;
        $prev_balance_penalty = 0.0;

        $details = [];

        foreach ($schedules as $schedule) {
            $paid_rent = isset($schedule['paid_rent']) ? (float)$schedule['paid_rent'] : 0.0;
            $annual_amount = isset($schedule['annual_amount']) ? (float)$schedule['annual_amount'] : 0.0;

            // Running balance rent
            $balance_rent = $prev_balance_rent + $annual_amount - $paid_rent;
            $prev_balance_rent = $balance_rent;

            $penalty_total = isset($schedule['panalty']) ? (float)$schedule['panalty'] : 0.0;
            $penalty_paid = isset($schedule['panalty_paid']) ? (float)$schedule['panalty_paid'] : 0.0;

            // Running balance penalty
            $balance_penalty = $prev_balance_penalty + $penalty_total - $penalty_paid;
            $prev_balance_penalty = $balance_penalty;

            // Determine if this schedule is payable as of $as_of (end_date <= as_of)
            $is_payable = false;
            if (!empty($schedule['end_date'])) {
                try {
                    $end_dt = new DateTime($schedule['end_date']);
                    if ($end_dt <= $as_of) $is_payable = true;
                } catch (Exception $e) { $is_payable = false; }
            }

            if ($is_payable) {
                if ($balance_rent > 0) $outstanding_rent_payable += $balance_rent;
                if ($balance_penalty > 0) $penalty_payable += $balance_penalty;
            }

            $details[] = [
                'schedule_id' => $schedule['schedule_id'],
                'schedule_year' => $schedule['schedule_year'],
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'annual_amount' => $annual_amount,
                'paid_rent' => $paid_rent,
                'balance_rent' => $balance_rent,
                'penalty_total' => $penalty_total,
                'penalty_paid' => $penalty_paid,
                'balance_penalty' => $balance_penalty
            ];
        }

        // Last running balances after processing all schedules
        $last_running_rent = $prev_balance_rent;
        $last_running_penalty = $prev_balance_penalty;

        $outstanding_rent = $outstanding_rent_payable; // payable as of date
        $total_penalty = $penalty_payable;

        // Total outstanding overall (final running balances if positive)
        $total_outstanding = 0.0;
        if ($last_running_rent > 0) $total_outstanding += $last_running_rent;
        if ($last_running_penalty > 0) $total_outstanding += $last_running_penalty;
        
        $response['success'] = true;
        $response['outstanding_rent'] = $outstanding_rent;
        $response['penalty_amount'] = $total_penalty;
        $response['total_due'] = $outstanding_rent + $total_penalty;
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
}
?>