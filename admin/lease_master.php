<?php include 'header.php'; 
checkPermission(14);
?>



<div class="content-wrapper">
    <div class="container-fluid">


        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4>Lease Master Setup</h4>
                </div>
            </div>
        </div>






        <div class="card">
            <div class="card-header" align="right">

                <button class="btn btn-primary float-right" id="addLeaseBtn" data-toggle="modal"
                    data-target="#leaseModal">
                    Add Lease Type
                </button>


            </div>



            <div class="card-body">
                <table class="table table-bordered" id="leaseTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Lease Type</th>
                            <th>Purpose</th>
                            <th>Base Rent %</th>
                            <th>Economy Rate %</th>
                            <th>Economy Valuvation</th>
                            <th>Duration</th>
                            <th>Revision</th>
                            <th>Penalty %</th>
                            <th>Waiver</th>
                            <th>Effective From</th>

                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>


<!-- Add/Edit Modal -->
<div class="modal fade" id="leaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="leaseForm" class='processing_form'>
                <div class="modal-header">
                    <h5 class="modal-title">Lease Setup</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">

                    <input type="hidden" name="lease_type_id" id="lease_type_id">

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Lease Type Name</label>
                            <input type="text" name="lease_type_name" id="lease_type_name" class="form-control"
                                required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Land Use Purpose</label>
                            <select name="purpose" id="purpose" class="form-control" required>
                                <option value="">-- Select Purpose --</option>
                                <option value="Residential">Residential</option>
                                <option value="Agricultural">Agricultural</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Society">Society</option>
                                <option value="BOI Zones">BOI Zones</option>
                                <option value="Religious">Religious</option>
                                <option value="Educational">Educational</option>
                                <option value="Charitable">Charitable</option>
                                <option value="Other Purposes">Other Purposes</option>
                            </select>
                        </div>


                        <div class="form-group col-md-3">
                            <label>Base Rent %</label>
                            <input type="number" step="0.01" name="base_rent_percent" id="base_rent_percent"
                                class="form-control" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Economy Rate %</label>
                            <input type="number" step="0.01" name="economy_rate" id="economy_rate" class="form-control">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Economy Valuvation</label>
                            <input type="number" step="0.01" name="economy_valuvation" id="economy_valuvation"
                                class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Duration (Years)</label>
                            <input type="number" name="duration_years" id="duration_years" class="form-control"
                                required>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Revision Interval</label>
                            <input type="number" name="revision_interval" id="revision_interval" class="form-control">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Revision %</label>
                            <input type="number" step="0.01" name="revision_increase_percent"
                                id="revision_increase_percent" class="form-control">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Penalty %</label>
                            <input type="number" step="0.01" name="penalty_rate" id="penalty_rate" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Allow Waiver</label>
                            <select name="allow_interest_waiver" id="allow_interest_waiver" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Effective From</label>
                            <input type="date" name="effective_from" id="effective_from" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Discount Percentage</label>
                            <input type="number" step="0.01" name="discount_rate" id="discount_rate"
                                class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Premium Times</label>
                            <input type="number" name="premium_times" id="premium_times" class="form-control" min="0">
                        </div>
                    </div>



                    <div align="center">

                        <button type="button" class="btn btn-danger" data-dismiss="modal" style="margin-top:20px;">
                            <i class="fa fa-times"></i> Close
                        </button>


                        <button type="submit" class="btn btn-success processing" style="margin-top:20px;">
                            <i class="bi bi-save"></i> Save
                        </button>


                    </div>

                    <br><br>
                </div>
                <!-- <div class="modal-footer" style='border-top:none; !important; '> -->

        </div>

        </form>
    </div>
</div>
</div>



<?php include 'footer.php'; ?>

<script>
$(document).ready(function() {

    // Load Data
    function loadLease() {
        $.ajax({
            url: "ajax/fetch_lease_master.php",
            method: "GET",
            success: function(data) {
                $("#leaseTable tbody").html(data);
            }
        });
    }
    loadLease();

    // Clear modal fields for Add
    $("#addLeaseBtn").on("click", function() {
        $("#leaseForm")[0].reset();
        $("#lease_type_id").val("");
        $("#purpose").val("");
    });

    // Save Data
    $("#leaseForm").on("submit", function(e) {
        e.preventDefault();
        $.ajax({
            url: "ajax/save_lease_master.php",
            method: "POST",
            data: $(this).serialize(),
            success: function(response) {
                alert(response);
                $("#leaseModal").modal("hide");
                location.reload();
                loadLease();
            }
        });
    });

    // Edit
    $(document).on("click", ".editBtn", function() {
        var id = $(this).data("id");
        $.ajax({
            url: "ajax/get_lease_master.php",
            method: "POST",
            data: {
                lease_type_id: id
            },
            dataType: "json",
            success: function(data) {
                $("#lease_type_id").val(data.lease_type_id);
                $("#lease_type_name").val(data.lease_type_name);
                $("#base_rent_percent").val(data.base_rent_percent);
                $("#economy_rate").val(data.economy_rate);
                $("#economy_valuvation").val(data.economy_valuvation);
                $("#duration_years").val(data.duration_years);
                $("#revision_interval").val(data.revision_interval);
                $("#revision_increase_percent").val(data.revision_increase_percent);
                $("#penalty_rate").val(data.penalty_rate);
                $("#allow_interest_waiver").val(data.allow_interest_waiver);
                $("#effective_from").val(data.effective_from);
                $("#discount_rate").val(data.discount_rate);
                $("#premium_times").val(data.premium_times);
                $("#purpose").val(data.purpose);
                $("#leaseModal").modal("show");
            }
        });
    });

});
</script>