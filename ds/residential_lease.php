<?php
include 'header.php';
checkPermission(12);

// Fetch residential lease applicants (no joins; pending placeholders for other data)
$rows = [];
if (isset($con)) {
    $q = "SELECT rl_ben_id, md5_ben_id, name, name_tamil, name_sinhala, address, address_tamil, address_sinhala, district,
                 ds_division_text, gn_division_text, nic_reg_no, dob, nationality, telephone, email, language, created_on
          FROM rl_beneficiaries
          WHERE status = 1
          ORDER BY rl_ben_id DESC";
    $res = mysqli_query($con, $q);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4>Residential Lease Register</h4>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" align="right">
                <?php if (hasPermission(13)): ?>
                <button type='button' class="btn btn-primary float-right" data-toggle="modal" data-target="#rlBenModal">
                    Add Lease Application
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table id="rlBenTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Telephone</th>
                            <th>Address</th>
                            <th>DS Division</th>
                            <th>GN Division</th>
                            <th>Lease Number</th>
                            <th>File Number</th>
                            <th>Remind Date</th>
                            <th class="text-right">Outstanding</th>
                            <th width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; foreach ($rows as $row): ?>
                        <tr>
                            <td><?= $count ?></td>
                            <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['telephone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['address'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['ds_division_text'] ?? 'Pending') ?></td>
                            <td><?= htmlspecialchars($row['gn_division_text'] ?? 'Pending') ?></td>
                            <td><span class="text-muted">Pending</span></td>
                            <td><span class="text-muted">Pending</span></td>
                            <td><span class="text-muted">Pending</span></td>
                            <td class="text-right"><span class="text-muted">Pending</span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary rl-edit"
                                    data-id="<?= (int)$row['rl_ben_id'] ?>">
                                    <i class="fa fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php $count++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="rlBenModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="rlBenForm" class='processing_form'>
                <div class="modal-header">
                    <h5 class="modal-title">Add Lease Application</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="rl_ben_id" id="rl_ben_id">

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Name in English</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Name in Tamil</label>
                            <input type="text" name="name_tamil" id="name_tamil" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Name in Sinhala</label>
                            <input type="text" name="name_sinhala" id="name_sinhala" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Address - English</label>
                            <textarea name="address" id="address" class="form-control"></textarea>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Address - Tamil</label>
                            <textarea name="address_tamil" id="address_tamil" class="form-control"></textarea>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Address - Sinhala</label>
                            <textarea name="address_sinhala" id="address_sinhala" class="form-control"></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>District</label>
                            <select name="district" id="district" class="form-control">
                                <option value="Ampara">Ampara</option>
                                <option value="Anuradhapura">Anuradhapura</option>
                                <option value="Badulla">Badulla</option>
                                <option value="Batticaloa">Batticaloa</option>
                                <option value="Colombo">Colombo</option>
                                <option value="Galle">Galle</option>
                                <option value="Gampaha">Gampaha</option>
                                <option value="Hambantota">Hambantota</option>
                                <option value="Jaffna">Jaffna</option>
                                <option value="Kalutara">Kalutara</option>
                                <option value="Kandy">Kandy</option>
                                <option value="Kegalle">Kegalle</option>
                                <option value="Kilinochchi">Kilinochchi</option>
                                <option value="Kurunegala">Kurunegala</option>
                                <option value="Mannar">Mannar</option>
                                <option value="Matale">Matale</option>
                                <option value="Matara">Matara</option>
                                <option value="Monaragala">Monaragala</option>
                                <option value="Mullaitivu">Mullaitivu</option>
                                <option value="Nuwara Eliya">Nuwara Eliya</option>
                                <option value="Polonnaruwa">Polonnaruwa</option>
                                <option value="Puttalam">Puttalam</option>
                                <option value="Ratnapura">Ratnapura</option>
                                <option value="Trincomalee" selected>Trincomalee</option>
                                <option value="Vavuniya">Vavuniya</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>DS Division</label>
                            <select name="ds_division_id" id="ds_division_id" class="form-control">
                                <option value="">Select DS Division</option>
                                <?php
                  $dsq = mysqli_query($con,"SELECT c_id, client_name FROM client_registration ORDER BY client_name");
                  while($ds = mysqli_fetch_assoc($dsq)){
                      echo "<option value='{$ds['c_id']}'>{$ds['client_name']}</option>";
                  }
                ?>
                            </select>
                            <input type="text" name="ds_division_text" id="ds_division_text" class="form-control mt-2"
                                placeholder="Enter DS Division (if not in list)" style="display:none;">
                        </div>
                        <div class="form-group col-md-4">
                            <label>GN Division</label>
                            <select name="gn_division_id" id="gn_division_id" class="form-control">
                                <option value="">Select GN Division</option>
                            </select>
                            <input type="text" name="gn_division_text" id="gn_division_text" class="form-control mt-2"
                                placeholder="Enter GN Division (if not in list)" style="display:none;">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>NIC Number / Registration No</label>
                            <input type="text" name="nic_reg_no" id="nic_reg_no" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Nationality</label>
                            <select name="nationality" id="nationality" class="form-control" required>
                                <option value="">-- Select Nationality --</option>
                                <option value="Sinhalese">Sinhalese</option>
                                <option value="Sri Lankan Tamil">Sri Lankan Tamil</option>
                                <option value="Sri Lankan Muslims">Sri Lankan Muslims</option>
                                <option value="Indian Tamil">Indian Tamil</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Telephone</label>
                            <input type="text" name="telephone" id="telephone" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Email</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Preferred Language</label>
                            <select name="language" id="language" class="form-control">
                                <option value="English" selected>English</option>
                                <option value="Tamil">Tamil</option>
                                <option value="Sinhala">Sinhala</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success processing_btn"><i class="bi bi-save"></i>
                        Save</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#rlBenTable').DataTable({
        processing: false,
        serverSide: false,
        pageLength: 25,
        order: [
            [0, 'asc']
        ]
    });

    // Submit form (create/update)
    $("#rlBenForm").on("submit", function(e) {
        e.preventDefault();
        var form = this;
        // If DS/GN are free-text (select2 tag), copy to text fields for backend
        var dsVal = $('#ds_division_id').val();
        if (dsVal && !/^\d+$/.test(dsVal)) {
            $('#ds_division_text').val(dsVal);
        } else if (!dsVal) {
            $('#ds_division_text').val('');
        }
        var gnVal = $('#gn_division_id').val();
        if (gnVal && !/^\d+$/.test(gnVal)) {
            $('#gn_division_text').val(gnVal);
        } else if (!gnVal) {
            $('#gn_division_text').val('');
        }
        var btn = form.querySelector(".processing_btn");
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        }
        $.ajax({
            url: "ajax_residential_lease/save_rl_beneficiary.php",
            method: "POST",
            data: $(form).serialize(),
            dataType: "json",
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire("Success", resp.message || "Saved", "success").then(
                        function() {
                            window.location.reload();
                        });
                } else {
                    Swal.fire("Error", (resp && resp.message) || "Failed to save", "error");
                }
            },
            error: function() {
                Swal.fire("Error", "Server error", "error");
            },
            complete: function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save"></i> Save';
                }
            }
        });
    });

    // Edit existing
    $(document).on("click", ".rl-edit", function() {
        var id = $(this).data("id");
        if (!id) return;
        $.ajax({
            url: "ajax_residential_lease/get_rl_beneficiary.php",
            method: "POST",
            data: {
                rl_ben_id: id
            },
            dataType: "json",
            success: function(data) {
                $("#rl_ben_id").val(data.rl_ben_id);
                $("#name").val(data.name);
                $("#name_tamil").val(data.name_tamil);
                $("#name_sinhala").val(data.name_sinhala);
                $("#address").val(data.address);
                $("#address_tamil").val(data.address_tamil);
                $("#address_sinhala").val(data.address_sinhala);
                $("#district").val(data.district || 'Trincomalee');
                // DS/GN population with select2 behavior similar to LTL
                if (data.ds_division_text && !data.ds_division_id) {
                    try {
                        $('#ds_division_id').select2('destroy');
                    } catch (e) {}
                    $('#ds_division_id').select2({
                        width: '100%',
                        dropdownParent: $('#rlBenModal'),
                        tags: true,
                        tokenSeparators: [',']
                    });
                    var val = data.ds_division_text;
                    var newOption = new Option(val, val, true, true);
                    $('#ds_division_id').append(newOption).trigger('change');
                    $('#ds_division_text').hide();
                    if (data.gn_division_text) {
                        try {
                            $('#gn_division_id').select2('destroy');
                        } catch (e) {}
                        $('#gn_division_id').select2({
                            width: '100%',
                            dropdownParent: $('#rlBenModal'),
                            tags: true,
                            tokenSeparators: [',']
                        });
                        var gval = data.gn_division_text;
                        var gOption = new Option(gval, gval, true, true);
                        $('#gn_division_id').append(gOption).trigger('change');
                        $('#gn_division_text').hide();
                    } else if (data.gn_division_id) {
                        $('#gn_division_id').val(data.gn_division_id).trigger('change');
                        $('#gn_division_id').show();
                        $('#gn_division_text').hide();
                    } else {
                        $('#gn_division_id').hide();
                        $('#gn_division_text').show();
                        $('#gn_division_text').val('');
                    }
                } else if (data.ds_division_id) {
                    $('#ds_division_id').val(data.ds_division_id).trigger('change');
                    $('#ds_division_id').show();
                    $('#ds_division_text').hide();
                    $.get("ajax/get_gn_divisions.php", {
                        c_id: data.ds_division_id
                    }, function(gnData) {
                        $('#gn_division_id').html(gnData);
                        $('#gn_division_id').select2({
                            width: '100%',
                            dropdownParent: $('#rlBenModal')
                        });
                        if (data.gn_division_id) {
                            $('#gn_division_id').val(data.gn_division_id).trigger(
                                'change');
                            $('#gn_division_id').show();
                            $('#gn_division_text').hide();
                        } else {
                            $('#gn_division_text').val(data.gn_division_text);
                            $('#gn_division_id').hide();
                            $('#gn_division_text').show();
                        }
                    });
                } else {
                    $('#ds_division_id').prop('disabled', true);
                    $('#ds_division_text').show();
                    $('#gn_division_id').prop('disabled', true);
                    $('#gn_division_text').show();
                    $('#ds_division_text').val(data.ds_division_text);
                    $('#gn_division_text').val(data.gn_division_text);
                }
                $("#nic_reg_no").val(data.nic_reg_no);
                $("#dob").val(data.dob);
                $("#nationality").val(data.nationality);
                $("#telephone").val(data.telephone);
                $("#email").val(data.email);
                $("#language").val(data.language || 'English');
                $("#rlBenModal").modal("show");
            },
            error: function() {
                Swal.fire("Error", "Failed to load beneficiary", "error");
            }
        });
    });

    $('#rlBenModal').on('hidden.bs.modal', function() {
        $('#rlBenForm')[0].reset();
        $('#rl_ben_id').val('');
        $('#district').val('Trincomalee');
        $('#language').val('English');
        $('#ds_division_id').val('').trigger('change');
        $('#ds_division_text').val('').hide();
        $('#ds_division_id').prop('disabled', false).show();
        $('#gn_division_id').val('').trigger('change');
        $('#gn_division_text').val('').hide();
        $('#gn_division_id').prop('disabled', false).show().html(
            '<option value=\"\">Select GN Division</option>');
    });

    // DS Division change -> load GN list or allow free text (tags)
    $('#ds_division_id').on('change', function() {
        var dsId = $(this).val();
        if (!dsId) {
            $('#gn_division_id').html('<option value=\"\">Select GN Division</option>');
            $('#gn_division_text').val('');
            $('#gn_division_id').hide();
            $('#gn_division_text').show();
            return;
        }
        if (/^\\d+$/.test(dsId)) {
            $.get("ajax/get_gn_divisions.php", {
                c_id: dsId
            }, function(gnData) {
                $('#gn_division_id').html(gnData);
                $('#gn_division_id').show();
                $('#gn_division_text').hide();
                if (window.jQuery && $('#gn_division_id').data('select2')) {
                    $('#gn_division_id').select2({
                        width: '100%',
                        dropdownParent: $('#rlBenModal')
                    });
                }
            });
        } else {
            // custom DS -> allow GN free text
            $('#gn_division_id').hide();
            $('#gn_division_text').val('').show();
        }
    });

    // Enable select2 on District/DS/GN like LTL; DS/GN allow tags for custom entries
    if (window.jQuery) {
        $('#district').select2({
            width: '100%',
            dropdownParent: $('#rlBenModal')
        });
        $('#ds_division_id').select2({
            width: '100%',
            dropdownParent: $('#rlBenModal'),
            tags: true,
            tokenSeparators: [',']
        });
        $('#gn_division_id').select2({
            width: '100%',
            dropdownParent: $('#rlBenModal'),
            tags: true,
            tokenSeparators: [',']
        });
    }
});
</script>

<?php include 'footer.php'; ?>