<?php
include 'header.php';
if (!function_exists('checkPermission')) {
    function checkPermission($act_id) {
        if (!isset($_SESSION)) session_start();
        if (!isset($_SESSION['permissions']) || !in_array($act_id, $_SESSION['permissions'])) {
            echo '<div class="content-wrapper"><div class="container-fluid"><div class="alert alert-danger mt-4">Access Denied</div></div></div>';
            include 'footer.php';
            exit;
        }
    }
}
checkPermission(24);
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4><i class="fa fa-money-bill"></i> Manage Payment Category</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header text-right">
                        <button class="btn btn-primary btn-sm" id="btnAddCat">
                            <i class="fa fa-plus"></i> Add Category
                        </button>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="catTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="catForm">
                <div class="modal-body">
                    <input type="hidden" name="cat_id" id="cat_id">
                    <div class="form-group">
                        <label for="payment_name">Payment Name *</label>
                        <input type="text" class="form-control" name="payment_name" id="payment_name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select class="form-control" name="category" id="category" required>
                            <option value="">Select</option>
                            <option value="Department income">Department income</option>
                            <option value="Non department income">Non department income</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="catSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function() {
    var table = $('#catTable').DataTable({
        ajax: {
            url: 'ajax/payment_category_create.php',
            data: {
                action: 'list'
            },
            dataSrc: function(json) {
                if (!json.success) {
                    Swal.fire('Error', json.message || 'Failed to load data', 'error');
                    return [];
                }
                return json.data || [];
            }
        },
        columns: [{
                data: 'cat_id'
            },
            {
                data: 'payment_name'
            },
            {
                data: 'category'
            },
            {
                data: 'starus_label'
            },
            {
                data: null,
                render: function(row) {
                    var btns =
                        '<button class="btn btn-sm btn-outline-primary edit-cat" data-id="' +
                        row.cat_id + '"><i class="fa fa-edit"></i></button> ';
                    btns += '<button class="btn btn-sm btn-outline-danger del-cat" data-id="' +
                        row.cat_id + '"><i class="fa fa-trash"></i></button>';
                    return btns;
                },
                orderable: false,
                searchable: false
            }
        ]
    });

    $('#btnAddCat').on('click', function() {
        $('#cat_id').val('');
        $('#payment_name').val('');
        $('#category').val('');
        $('#status').val('1');
        $('#catModalTitle').text('Add Category');
        $('#catModal').modal('show');
    });

    $('#catTable').on('click', '.edit-cat', function() {
        var row = table.row($(this).closest('tr')).data();
        $('#cat_id').val(row.cat_id);
        $('#payment_name').val(row.payment_name);
        $('#category').val(row.category);
        $('#status').val(row.starus);
        $('#catModalTitle').text('Edit Category');
        $('#catModal').modal('show');
    });

    $('#catTable').on('click', '.del-cat', function() {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'warning',
            title: 'Delete?',
            text: 'This will mark the category inactive.',
            showCancelButton: true
        }).then(function(res) {
            if (!res.isConfirmed) return;
            $.post('ajax/payment_category_create.php', {
                action: 'delete',
                cat_id: id
            }, function(resp) {
                if (resp && resp.success) {
                    table.ajax.reload();
                    Swal.fire('Done', resp.message || 'Deleted', 'success');
                } else {
                    Swal.fire('Error', (resp && resp.message) || 'Delete failed',
                        'error');
                }
            }, 'json');
        });
    });

    $('#catForm').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serializeArray();
        var id = $('#cat_id').val();
        formData.push({
            name: 'action',
            value: id ? 'update' : 'create'
        });
        $.post('ajax/payment_category_create.php', formData, function(resp) {
            if (resp && resp.success) {
                $('#catModal').modal('hide');
                table.ajax.reload();
                Swal.fire('Success', resp.message || 'Saved', 'success');
            } else {
                Swal.fire('Error', (resp && resp.message) || 'Save failed', 'error');
            }
        }, 'json');
    });
});
</script>

<?php include 'footer.php'; ?>