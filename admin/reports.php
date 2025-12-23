 <?php include 'header.php'; 
 checkPermission(15);
 ?>
<style>
.report-item { 
    padding: 12px; 
    border-bottom: 1px solid #e5e5e5; 
    cursor: pointer; 
}
.report-item:hover { background: #f7f7f7; }
.report-title { font-size: 16px; font-weight: 600; }
.report-desc { font-size: 13px; color: #666; }
.report-form { background: #fafafa; padding: 15px; border-left: 3px solid #007bff; display: none; }
</style>

<div class="content-wrapper">
   <div class="container-fluid">

      <div class="row">
         <div class="col-sm-12 p-0">
            <div class="main-header">
               <h4><i class="fa fa-file"></i> Reports</h4> <?php $report_number = 01; ?>
            </div>
         </div>
      </div>

      <div class="card">
         <div class="card-block">

            <div class="report-item" data-target="#form_lease_payment">
               <div class="report-title"><b><?= sprintf('%02d', $report_number++); ?></b> Long Term Lease Payment</div>
               <div class="report-desc"> long-term lease Payment. </div>
            </div>
            <div id="form_lease_payment" class="report-form">
               <form method="GET" action="reports/long_term_lease_payment_summary.php" target="_blank" rel="noopener">
                  <div class="row">
                     <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value='<?= date("Y-m-01", strtotime("last month")) ?>' required>
                     </div>
                     <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value='<?= date("Y-m-t") ?>' required>
                     </div>
                     <div class="col-md-2">
                        <label>District</label>
                                    <select name="district" class="form-control" required>
                                       <option value="All">All</option>
                                       <option value="Batticaloa">Batticaloa</option>
                                       <option value="Ampara">Ampara</option>
                                       <option value="Trincomalee">Trincomalee</option>
                                    </select>
                     </div>

                     <div class="col-md-2">
                        <label>Lease Type</label>
                        <select name="lease_type" class="form-control" required>
                          <option value="All">All</option>
                          <option value="Agricultural">Agricultural</option>
                          <option value="Commercial">Commercial</option>
                          <option value="BOI Zones">BOI Zones</option>
                          <option value="Religious">Religious</option>
                          <option value="Educational">Educational</option>
                          <option value="Charitable">Charitable</option>
                          <option value="Other Purposes">Other Purposes</option>
                        </select>
                     </div>
                     <div class="col-md-2">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block">Open Report</button>
                     </div>
                  </div>
               </form>
            </div>


            <div class="report-item" data-target="#form_lease1">
               <div class="report-title"><b><?= sprintf('%02d', $report_number++); ?></b> Divisional Secretariat–wise  Long Term Lease Outstanding</div>
               <div class="report-desc">Generates a Divisional Secretariat–wise long-term lease outstanding report. </div>
            </div>
            <div id="form_lease1" class="report-form">
               <form method="GET" action="reports/long_term_lease_detail_arrears_ds.php" target="_blank" rel="noopener">
                  <div class="row">
                     <div class="col-md-3">
                        <label>As At Date</label>
                        <input type="date" name="as_at" class="form-control" value='<?= date("Y-m-d") ?>' required>
                     </div>
                     <div class="col-md-3">
                        <label>Lease Type</label>
                        <select name="lease_type" class="form-control" required>
                          <option value="All">All</option>
                          <option value="Agricultural">Agricultural</option>
                          <option value="Commercial">Commercial</option>
                          <option value="BOI Zones">BOI Zones</option>
                          <option value="Religious">Religious</option>
                          <option value="Educational">Educational</option>
                          <option value="Charitable">Charitable</option>
                          <option value="Other Purposes">Other Purposes</option>
                        </select>
                     </div>
                     <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block">Open Report</button>
                     </div>
                  </div>
               </form>
            </div>


            <div class="report-item" data-target="#form_lease2">
               <div class="report-title"><b><?= sprintf('%02d', $report_number++); ?></b> District–wise  Long Term Lease Outstanding</div>
               <div class="report-desc">Generates a Divisional Secretariat–wise long-term lease outstanding report. </div>
            </div>
            <div id="form_lease2" class="report-form">
               <form method="GET" action="reports/long_term_lease_detail_arrears_distrct.php" target="_blank" rel="noopener">
                  <div class="row">
                     <div class="col-md-3">
                        <label>As At Date</label>
                        <input type="date" name="as_at" class="form-control" value='<?= date("Y-m-d") ?>' required>
                     </div>
                     <div class="col-md-3">
                        <label>District</label>
                                    <select name="district" class="form-control" required>
                                       <option value="All">All</option>
                                       <option value="Batticaloa">Batticaloa</option>
                                       <option value="Ampara">Ampara</option>
                                       <option value="Trincomalee">Trincomalee</option>
                                    </select>
                     </div>
                     <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block">Open Report</button>
                     </div>
                  </div>
               </form>
            </div>



            <!-- Report 1 -->
            <div class="report-item" data-target="#form_lease">
               <div class="report-title"><b><?= sprintf('%02d', $report_number++); ?></b> Long Term Lease Payments Summary</div>
               <div class="report-desc"> List of payments within selected period.</div>
            </div>
            <div id="form_lease" class="report-form">
               <form method="GET" action="reports/long_term_lease_payments.php" target="_blank" rel="noopener">
                  <div class="row">
                     <div class="col-md-3">
                        <label>DS</label>

                          <select   name="c_id" class="form-control input-sm select2" style="width:300px;"  >
                                <option value="0">Select DS</option>
                                <?php 
                        $sel_query="SELECT client_registration.c_id,client_registration.md5_client, client_registration.client_name from client_registration 
                        Order by client_registration.client_name ASC";
                        $result = mysqli_query($con,$sel_query);
                         $rowcount=mysqli_num_rows($result);
                         while($row = mysqli_fetch_assoc($result)) { ?>
                                    <option value="<?php echo $row['md5_client']; ?>"
                                        <?php if ($client_cook == $row['md5_client']) { echo 'selected';  $count_client += 1; } ?>>
                                        <?php echo htmlspecialchars($row['client_name']); ?>
                                    </option>
                                <?php } ?>
                            </select> 
                      </div>
                     <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value='<?= date("Y-m-01", strtotime("last month")) ?>' required>
                     </div>
                     <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value='<?= date("Y-m-t") ?>' required>
                     </div>
                     <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block">Open Report</button>
                     </div>
                  </div>
               </form>
            </div>

            <!-- Report 2 -->
            <div class="report-item" data-target="#form_penalties">
               <div class="report-title"><?= sprintf('%02d', $report_number++); ?>. Long Term Lease Outstanding</div>
               <div class="report-desc">Outstanding amounts as at a given date.</div>
            </div>
            <div id="form_penalties" class="report-form">
               <form method="GET" action="reports/long_term_lease_detail_arrears.php" target="_blank" rel="noopener">
                  <div class="row">
                     <div class="col-md-3">
                        <label>DS</label>

                          <select   name="c_id" class="form-control input-sm select2" style="width:300px;"  >
                                <option value="0">Select DS</option>
                                <?php 
                        $sel_query="SELECT client_registration.c_id,client_registration.md5_client, client_registration.client_name from client_registration 
                        Order by client_registration.client_name ASC";
                        $result = mysqli_query($con,$sel_query);
                         $rowcount=mysqli_num_rows($result);
                         while($row = mysqli_fetch_assoc($result)) { ?>
                                    <option value="<?php echo $row['md5_client']; ?>"
                                        <?php if ($client_cook == $row['md5_client']) { echo 'selected';  $count_client += 1; } ?>>
                                        <?php echo htmlspecialchars($row['client_name']); ?>
                                    </option>
                                <?php } ?>
                            </select> 
                      </div>
                     <div class="col-md-3">
                        <label>As At Date</label>
                        <input type="date" name="as_at" class="form-control" value='<?= date("Y-m-d") ?>' required>
                     </div>
                     <div class="col-md-3">
                        <label>Lease Type</label>
                        <select name="lease_type" class="form-control" required>
                          <option value="All">All</option>
                          <option value="Agricultural">Agricultural</option>
                          <option value="Commercial">Commercial</option>
                          <option value="BOI Zones">BOI Zones</option>
                          <option value="Religious">Religious</option>
                          <option value="Educational">Educational</option>
                          <option value="Charitable">Charitable</option>
                          <option value="Other Purposes">Other Purposes</option>
                        </select>
                     </div>
                     <div class="col-md-3 d-flex align-items-end" style='margin-top:30px;'>
                        <button class="btn btn-primary">Open Report</button>
                     </div>
                  </div>
               </form>
            </div>

 

            <!-- Report 4 -->
            <div class="report-item" data-target="#form_beneficiary">
               <div class="report-title"><?= sprintf('%02d', $report_number++); ?>. Long Term Lease Payment Reminders</div>
               <div class="report-desc"> Payment remindes for for the lease </div>
            </div>
            <div id="form_beneficiary" class="report-form">
               <form method="GET" action="reports/payment_remindes.php" target="_blank" rel="noopener">
                  <div class="row">
                         <div class="col-md-3">
                        <label>DS</label>

                          <select   name="c_id" class="form-control input-sm select2" style="width:300px;"  >
                                <option value="0">Select DS</option>
                                <?php 
                        $sel_query="SELECT client_registration.c_id,client_registration.md5_client, client_registration.client_name from client_registration 
                        Order by client_registration.client_name ASC";
                        $result = mysqli_query($con,$sel_query);
                         $rowcount=mysqli_num_rows($result);
                         while($row = mysqli_fetch_assoc($result)) { ?>
                                    <option value="<?php echo $row['md5_client']; ?>"
                                        <?php if ($client_cook == $row['md5_client']) { echo 'selected';  $count_client += 1; } ?>>
                                        <?php echo htmlspecialchars($row['client_name']); ?>
                                    </option>
                                <?php } ?>
                            </select> 
                      </div>


                     <div class="col-md-4">
                        <label>Year</label>
                        <input type="number" name="year" class="form-control" value="<?= date('Y'); ?>">
                     </div>
                     <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block">Open Report</button>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </div>
</div>

<script>
document.querySelectorAll(".report-item").forEach(item => {
   item.addEventListener("click", function() {
      let target = this.getAttribute("data-target");

      // Hide all forms
      document.querySelectorAll(".report-form").forEach(f => f.style.display = "none");

      // Show selected form
      document.querySelector(target).style.display = "block";

      // Scroll into view for better UX
      document.querySelector(target).scrollIntoView({ behavior: "smooth", block: "start" });
   });
});
</script>

<?php include 'footer.php'; ?>

<script>
// Initialize Select2 for DS selector (safe if Select2 not loaded)
(function(){
   try {
      if (window.jQuery && jQuery().select2) {
         jQuery('select[name="c_id"].select2').select2({ width: '300px', placeholder: 'Select DS' });
      }
   } catch(e) { /* no-op */ }
})();
</script>
