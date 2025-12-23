<?php
include '../../db.php';
include '../../auth.php';
if (isset($_GET['lease_id'])) {
    $lease_id = $_GET['lease_id'];
    
    // Get lease details
    $lease_sql = "SELECT l.*, land.address, ben.name as beneficiary_name 
                  FROM leases l
                  LEFT JOIN land_registration land ON l.land_id = land.land_id
                  LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
                  WHERE l.lease_id = ?";
    $stmt = $con->prepare($lease_sql);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $lease = $stmt->get_result()->fetch_assoc();
    
    // Get schedules with new columns from lease_schedules
    $schedule_sql = "SELECT 
                        schedule_id, lease_id, schedule_year, due_date, start_date, end_date,
                        base_amount, annual_amount, panalty, paid_rent, total_paid, panalty_paid,
                        revision_number, is_revision_year, penalty_rate, status, created_on,
                        penalty_last_calc, penalty_updated_by, penalty_remarks
                     FROM lease_schedules
                     WHERE lease_id = ?
                     ORDER BY schedule_year";
    $stmt = $con->prepare($schedule_sql);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $schedules_result = $stmt->get_result();
    // fetch all schedules to be able to identify the last schedule
    $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
    
    // Get payments with year allocation
    $payment_sql = "SELECT lp.*, 
                           YEAR(lp.payment_date) as payment_year,
                           ls.schedule_year as allocated_year
                    FROM lease_payments lp 
                    LEFT JOIN lease_schedules ls ON lp.schedule_id = ls.schedule_id
                    WHERE lp.lease_id = ? 
                    ORDER BY lp.payment_date DESC";
    $stmt = $con->prepare($payment_sql);
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $payments = $stmt->get_result();
?>
<div class="row">
    <div class="col-6">
        <h6>Lease: <?= htmlspecialchars($lease['lease_number']) ?></h6>
        <p><strong>Lessee:</strong> <?= htmlspecialchars($lease['beneficiary_name']) ?> | 
           <strong>Land:</strong> <?= htmlspecialchars($lease['address']) ?></p>
    </div>
    <div class="col-6 text-right">
        <button class="btn btn-success" id="recordPaymentBtn">Record Payment</button>
        
        <?php 
            // Prefer encrypted id token if helper exists
            $print_token = isset($lease_id) ? (function_exists('encrypt_id') ? encrypt_id($lease_id) : $lease_id) : '';
        ?>
        <a class="btn btn-outline-primary" href="print_schedule.php?token=<?= urlencode($print_token) ?>" target="_blank" rel="noopener"> <i class="fa fa-print" aria-hidden="true"></i> Print Shedule</a>
</div>
        
</div>


<div class="row">
    <div class="col-12">
        <div class="mb-2">
            <strong>Approval Date:</strong> <?= htmlspecialchars($lease['approval_date'] ?? '-') ?> &nbsp;|
            <strong>Start Date:</strong> <?= htmlspecialchars($lease['start_date'] ?? '-') ?>
        </div>
        <h6>Payment Schedule</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="bg-light">
                    <tr>
                        <th class="text-center">#</th>
                          
                        <th class="text-center">Start Date</th>
                        <th class="text-center">End Date</th>
                        <th class="text-center">Annual Lease</th>
                        <th class="text-center">Paid Rent</th>
                        <th class="text-center">Balance Rent <br>payable</th>
                        <th class="text-center">Penalty</th>
                        <th class="text-center">Penalty Paid</th>
                        <th class="text-center">Balance Penalty <br>payable</th>
                        <th class="text-center">Total Payment</th>
                        <th class="text-center">Total Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 1;
                    // Get approval date and start date
                    $approval_date = isset($lease['approval_date']) ? new DateTime($lease['approval_date']) : null;
                    $lease_start_date = isset($lease['start_date']) ? new DateTime($lease['start_date']) : null;
                    $current_year = (int)date('Y');

                    // Running balances initialized to zero (previous balance can be loaded if available)
                    $prev_balance_rent = 0.0;
                    $prev_balance_penalty = 0.0;

                    $totalSchedules = count($schedules);
                    $i = 0;
                    while ($i < $totalSchedules):
                        $schedule = $schedules[$i];
                        $i++;
                        
                        // Use fields from lease_schedules directly
                        $due_date = !empty($schedule['due_date']) ? new DateTime($schedule['due_date']) : null;
                        $from_date = $schedule['start_date'];
                        $to_date = $schedule['end_date'];


                        // Fields from table: paid_rent, panalty, panalty_paid, total_paid
                        $paid_rent = isset($schedule['paid_rent']) ? (float)$schedule['paid_rent'] : 0.0;
                        $annual_amount = isset($schedule['annual_amount']) ? (float)$schedule['annual_amount'] : 0.0;

                        // Balance Rent = previous balance + annual_amount - paid_rent
                        $balance_rent = $prev_balance_rent + $annual_amount - $paid_rent;
                        $prev_balance_rent = $balance_rent; // carry forward

                        $penalty_total = isset($schedule['panalty']) ? (float)$schedule['panalty'] : 0.0; // note column name spelling
                        $penalty_paid = isset($schedule['panalty_paid']) ? (float)$schedule['panalty_paid'] : 0.0;

                        // Balance Penalty = previous balance penalty + penalty_total - penalty_paid
                        $balance_penalty = $prev_balance_penalty + $penalty_total - $penalty_paid;
                        $prev_balance_penalty = $balance_penalty; // carry forward

                        // Total payment for this schedule: rent + penalty paid + any reported total_paid
                        $total_payment = $paid_rent + $penalty_paid  ;

                        // Total outstanding: remaining rent + remaining penalty
                        $total_outstanding = $balance_rent + max(0, $balance_penalty);

                       
                        $today = date('Y-m-d');
                        if($schedule['end_date'] < $today  ){
                            $status = 'paid';
                            $status1 = '<i class="fa fa-check" aria-hidden="true"></i>';
                        } else if ($schedule['end_date'] < $today && $total_outstanding > 0) {
                            $status = 'overdue';
                        } else {
                            $status = 'pending';
                            $status1 = 'Pending';
                        }

                         
                                 
                    ?>
                        <tr class="<?= $status == 'paid' ? 'table-success' : 
                                    ($status == 'overdue' ? 'table-danger' : '') ?>">
                            <td class="text-center"><?= $count ?></td>
                            
                                 <td class="text-center"><?= $schedule['start_date'] ?></td>
                            <td class="text-center"><?= $schedule['end_date'] ?></td>
                            <td class="text-right"><?= number_format($schedule['annual_amount'], 2) ?></td>
                            <td class="text-right"><?= number_format($paid_rent, 2) ?></td>
                            <td class="text-right"><?= number_format($balance_rent, 2) ?></td>
                            <td class="text-right"><?= number_format($penalty_total, 2) ?></td>
                            <td class="text-right"><?= number_format($penalty_paid, 2) ?></td>
                            <td class="text-right"><?= number_format($balance_penalty, 2) ?></td>
                            <td class="text-right"><strong><?= number_format($total_payment, 2) ?></strong></td>
                            <td class="text-right"><strong><?= number_format($total_outstanding, 2) ?></strong></td>
                             
                            <td>
                                <span class="badge badge-<?= 
                                    $status == 'paid' ? 'success' : 
                                    ($status == 'overdue' ? 'danger' : 'warning')
                                ?>">
                                    <?= ucfirst($status1) ?>
                                </span>
                            </td>
                        </tr>
                    <?php
                    $count++;
                 endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-16">
        <h6>Payment History</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" style="width:60vw;">
                <thead class="bg-light">
                    <tr>
                        <th>Date</th>
                        <th>Receipt No</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Payment Year</th>
                        <th>Allocated Year</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $payment['payment_date'] ?></td>
                            <td><?= htmlspecialchars($payment['receipt_number']) ?></td>
                            <td>LKR <?= number_format($payment['amount'], 2) ?></td>
                            <td><span class="badge badge-<?= $payment['payment_type'] == 'rent' ? 'primary' : 'warning' ?>"><?= ucfirst($payment['payment_type']) ?></span></td>
                            <td><?= $payment['payment_year'] ?></td>
                            <td><?= $payment['allocated_year'] ?? 'Not Allocated' ?></td>
                            <td><?= ucfirst($payment['payment_method']) ?></td>
                            <td><?= htmlspecialchars($payment['reference_number']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($payments->num_rows == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No payments recorded</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<script>
// Print the schedule in a new window optimized for landscape printing with reduced margins
document.addEventListener('DOMContentLoaded', function(){
    var printBtn = document.getElementById('printScheduleBtn');
    if(!printBtn) return;
    printBtn.addEventListener('click', function(e){
        e.preventDefault();
        // Clone the schedule container
        var content = document.querySelector('.row');
        if(!content) {
            alert('Nothing to print');
            return;
        }

        // Build a minimal HTML document with print styles
        var html = '<!doctype html><html><head><meta charset="utf-8">';
        html += '<title>Print Schedule</title>';
        // Print styles: A4 landscape, reduced margins, simple fonts
        html += '<style>';
        html += '@page { size: A4 landscape; margin: 8mm; }\n';
        html += 'html, body { width: 100%; height: 100%; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }\n';
        html += '.no-print { display: none !important; }\n';
        html += 'table { border-collapse: collapse; width: 100%; }\n';
        html += 'table, th, td { border: 1px solid #333; }\n';
        html += 'th, td { padding: 6px 8px; font-size: 12px; }\n';
        html += '.text-right { text-align: right; }\n';
        html += '.text-center { text-align: center; }\n';
        html += '</style>';
        html += '</head><body>';

        // Clone visible body content: we want the main container only
        var container = document.querySelector('body').cloneNode(true);
        // Remove scripts and buttons not needed
        // Instead, we'll extract the main lease/schedule section by selecting the first .row blocks above
        var rows = document.querySelectorAll('body > .row, body .row');
        // Find the largest row (heuristic) to print
        var mainHtml = '';
        if(rows && rows.length){
            // Concatenate the rows that contain 'Payment Schedule' or lease header
            for(var i=0;i<rows.length;i++){
                var r = rows[i];
                if(r.innerText && (r.innerText.indexOf('Payment Schedule') !== -1 || r.innerText.indexOf('Payment History') !== -1 || r.innerText.indexOf('Lease:') !== -1)){
                    mainHtml += r.outerHTML;
                }
            }
        }
        if(!mainHtml) mainHtml = content.outerHTML;

        html += '<div>' + mainHtml + '</div>';
        html += '<script>window.onload = function(){ setTimeout(function(){ window.print(); /*window.close();*/ }, 300); }<\/script>';
        html += '</body></html>';

        var printWindow = window.open('', '_blank', 'toolbar=0,location=0,menubar=0');
        if(!printWindow){
            alert('Please allow popups for this site to enable printing.');
            return;
        }
        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
    });
});
</script>