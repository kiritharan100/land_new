<?php
/**
 * -----------------------------------------
 * Lease Penalty Calculation (TEST PURPOSE)
 * -----------------------------------------
 * Uses: lease_schedules
 * Safe for PHP 8+
 */

date_default_timezone_set('Asia/Colombo');

/* ==============================
   SAFE QUERY HELPER
   ============================== */
function safeQuery(mysqli $con, string $sql)
{
    $res = mysqli_query($con, $sql);
    if (!$res) {
        die(
            "<pre style='color:red'>
SQL ERROR:
" . mysqli_error($con) . "

QUERY:
$sql
</pre>"
        );
    }
    return $res;
}

/* ==============================
   MAIN FUNCTION
   ============================== */
function calculateLeasePenaltyTest(mysqli $con, int $lease_id): array
{
    $today = date('Y-m-d');

    /* ==============================
       1. Load Lease Details
       ============================== */
    $leaseSql = "
        SELECT 
            l.lease_id,
            l.valuation_date,
            lm.penalty_rate
        FROM leases l
        LEFT JOIN lease_master lm 
            ON l.lease_type_id = lm.lease_type_id
        WHERE l.lease_id = '$lease_id'
    ";

    $leaseRes = safeQuery($con, $leaseSql);

    if (mysqli_num_rows($leaseRes) === 0) {
        return ['status' => false, 'msg' => 'Invalid lease ID'];
    }

    $lease = mysqli_fetch_assoc($leaseRes);
    $valuation_date = $lease['valuation_date'];
    $penalty_rate   = (float)$lease['penalty_rate'];

    /* ==============================
       2. Validation Rules
       ============================== */
    if (empty($valuation_date) || $valuation_date === '0000-00-00') {

        safeQuery($con, "
            UPDATE lease_schedules
            SET panalty = 0,
                penalty_last_calc = NULL,
                penalty_remarks = 'No valuation date'
            WHERE lease_id = '$lease_id'
        ");

        return ['status' => true, 'msg' => 'Penalty reset – no valuation date'];
    }

    if ($penalty_rate <= 0) {

        safeQuery($con, "
            UPDATE lease_schedules
            SET panalty = 0,
                penalty_last_calc = NULL,
                penalty_remarks = '0% penalty rate'
            WHERE lease_id = '$lease_id'
        ");

        return ['status' => true, 'msg' => 'Penalty reset – 0% penalty rate'];
    }

    /* ==============================
       3. Reset Previous Penalties
       ============================== */
    safeQuery($con, "
        UPDATE lease_schedules
        SET panalty = 0
        WHERE lease_id = '$lease_id'
    ");

    /* ==============================
       4. Fetch Schedules (SAFE)
       ============================== */
    $scheduleSql = "
        SELECT 
            ls.schedule_id,
            ls.start_date,
            ls.end_date,
            ls.premium,
            ls.annual_amount,

            IFNULL(SUM(lp.rent_paid),0) AS rent_paid,
            IFNULL(SUM(lp.premium_paid),0) AS premium_paid,
            IFNULL(SUM(lp.discount_apply),0) AS discount_apply,
            IFNULL(SUM(w.write_off_amount),0) AS write_off_amount

        FROM lease_schedules ls
        LEFT JOIN lease_payments lp 
            ON ls.schedule_id = lp.schedule_id AND lp.status = 1
        LEFT JOIN ltl_write_off w 
            ON ls.schedule_id = w.schedule_id AND w.status = 1

        WHERE ls.lease_id = '$lease_id'
          AND DATE_ADD(ls.start_date, INTERVAL 30 DAY) < '$today'
          AND ls.status = 1

        GROUP BY ls.schedule_id
        ORDER BY ls.start_date
    ";

    $scheduleRes = safeQuery($con, $scheduleSql);

    /* ==============================
       5. Penalty Calculation
       ============================== */
    $cumulative_outstanding = 0;
    $penalty_year = 0;

    while ($row = mysqli_fetch_assoc($scheduleRes)) {

        $previous_outstanding = max(0, $cumulative_outstanding);

        $cumulative_outstanding += (
            $row['annual_amount']
            + $row['premium']
            - $row['rent_paid']
            - $row['premium_paid']
            - $row['discount_apply']
        );

        if ($row['end_date'] > $valuation_date && $penalty_year > 0) {

            $penalty_amount =
                ($previous_outstanding * ($penalty_rate / 100))
                - $row['write_off_amount'];

            if ($penalty_amount < 0) {
                $penalty_amount = 0;
            }

            safeQuery($con, "
                UPDATE lease_schedules
                SET panalty = '$penalty_amount',
                    penalty_last_calc = '$today',
                    penalty_remarks = 'Penalty calculated (TEST) on $today'
                WHERE schedule_id = '{$row['schedule_id']}'
            ");
        }

        if ($row['end_date'] > $valuation_date) {
            $penalty_year++;
        }
    }

    return [
        'status' => true,
        'msg' => 'Penalty calculated successfully (TEST)',
        'lease_id' => $lease_id
    ];
}