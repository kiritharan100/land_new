<?php
 
require('../db.php');
require('header.php');
checkPermission(3);
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header">
            <h2>Manage GN Divisions</h2>
        </div>
        <div class="card">
            <div class="card-block">
                <div align='right'>
                     <button type='button' id="exportButton" filename='<?php echo "GN_LIST";?>.xlsx' class="btn btn-success"><i class="ti-cloud-down"></i> Export</button>
                      <button class="btn btn-success" data-toggle="modal" data-target="#addGnModal">+ Add New GN Division</button></div>
                <hr>
                <table id='example' class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>GN ID</th>
                            <th>GN Name</th>
                            <th>GN No</th>
                            <th>GN Code</th>
                            <th>DS Division</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = mysqli_query($con, "
                            SELECT c.c_id,g.gn_id, g.gn_name, g.gn_no, g.gn_code, c.client_name 
                            FROM gn_division g
                            LEFT JOIN client_registration c ON g.c_id = c.c_id
                        ");
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>
                                <td>{$row['gn_id']}</td>
                                <td>{$row['gn_name']}</td>
                                <td>{$row['gn_no']}</td>
                                <td>{$row['gn_code']}</td>
                                <td>{$row['client_name']}</td>
                                <td>
                                <button class='btn btn-sm btn-success editBtn' 
                                    data-gn_id='{$row['gn_id']}'
                                    data-gn_name='{$row['gn_name']}'
                                    data-gn_no='{$row['gn_no']}'
                                    data-gn_code='{$row['gn_code']}'
                                    data-c_id='{$row['c_id']}'
                                    data-toggle='modal' 
                                    data-target='#editGnModal'>
                                    Edit
                                </button>
                                </td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

 

<script>
$(document).ready(function() {
    var table = $('#example').DataTable({
        "pageLength": 100
    });

    // Add Select2 to Add modal on show
    $('#addGnModal').on('shown.bs.modal', function () {
        $('#add_c_id').select2({
            dropdownParent: $('#addGnModal')
        });
    });

    // Add Select2 to Edit modal on show
    $('#editGnModal').on('shown.bs.modal', function () {
        $('#modal_c_id').select2({
            dropdownParent: $('#editGnModal')
        });
    });

    // Use event delegation for edit buttons
    $('#example tbody').on('click', '.editBtn', function() {
        $('#modal_gn_id').val($(this).data('gn_id'));
        $('#modal_gn_name').val($(this).data('gn_name'));
        $('#modal_gn_no').val($(this).data('gn_no'));
        $('#modal_gn_code').val($(this).data('gn_code'));
        $('#modal_c_id').val($(this).data('c_id')).trigger('change');
    });
});
</script>

<?php include 'footer.php'; ?>


<!-- GN Division Edit Modal -->
<div class="modal fade" id="editGnModal" tabindex="-1" role="dialog" aria-labelledby="editGnModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editGnForm" method="post" action="edit_gn_division.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editGnModalLabel">Edit GN Division</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="gn_id" id="modal_gn_id">
          <div class="form-group">
            <label for="modal_gn_name">GN Name</label>
            <input type="text" class="form-control" name="gn_name" id="modal_gn_name" required>
          </div>
          <div class="form-group">
            <label for="modal_gn_no">GN No</label>
            <input type="text" class="form-control" name="gn_no" id="modal_gn_no">
          </div>
          <div class="form-group">
            <label for="modal_gn_code">GN Code</label>
            <input type="text" class="form-control" name="gn_code" id="modal_gn_code">
          </div>
          <div class="form-group">
            <label for="modal_c_id">DS Division</label>
            <select class="form-control" name="c_id" id="modal_c_id" style="width:100%;">
                <?php
                $client_res = mysqli_query($con, "SELECT c_id, client_name FROM client_registration");
                while ($client = mysqli_fetch_assoc($client_res)) {
                    echo "<option value='{$client['c_id']}'>{$client['client_name']}</option>";
                }
                ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Add GN Division Modal -->
<div class="modal fade" id="addGnModal" tabindex="-1" role="dialog" aria-labelledby="addGnModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addGnForm" method="post" action="add_gn_division.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addGnModalLabel">Add New GN Division</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="add_gn_name">GN Name</label>
            <input type="text" class="form-control" name="gn_name" id="add_gn_name" required>
          </div>
          <div class="form-group">
            <label for="add_gn_no">GN No</label>
            <input type="text" class="form-control" name="gn_no" id="add_gn_no">
          </div>
          <div class="form-group">
            <label for="add_gn_code">GN Code</label>
            <input type="text" class="form-control" name="gn_code" id="add_gn_code">
          </div>
          <div class="form-group">
            <label for="add_c_id">DS Division</label>
            <select class="form-control" name="c_id" id="add_c_id" style="width:100%;">
                <?php
                $client_res = mysqli_query($con, "SELECT c_id, client_name FROM client_registration");
                while ($client = mysqli_fetch_assoc($client_res)) {
                    echo "<option value='{$client['c_id']}'>{$client['client_name']}</option>";
                }
                ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Add GN Division</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>