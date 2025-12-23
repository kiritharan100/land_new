<?php include 'header.php'; ?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4><i class="fa fa-list"></i> Land Usage Purposes Management</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header" align='right'>
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPurposeModal">
                            <i class="fa fa-plus"></i> Add New Purpose
                        </button>
                        <button id="exportPurposesBtn" class="btn btn-success btn-sm">
                            <i class="fa fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="purposesTable">
                                <thead>
                                    <tr>
                                        <th>Purpose ID</th>
                                        <th>Purpose Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created On</th>
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

<!-- Add Purpose Modal -->
<div class="modal fade" id="addPurposeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Land Usage Purpose</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addPurposeForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="purpose_name">Purpose Name *</label>
                                <input type="text" class="form-control" id="purpose_name" name="purpose_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="is_active">Status *</label>
                                <select class="form-control" id="is_active" name="is_active" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="purpose_description">Description</label>
                                <textarea class="form-control" id="purpose_description" name="purpose_description" rows="3" placeholder="Enter detailed description of the land usage purpose"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Purpose</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Purpose Modal -->
<div class="modal fade" id="editPurposeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Land Usage Purpose</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editPurposeForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_purpose_id" name="purpose_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_purpose_name">Purpose Name *</label>
                                <input type="text" class="form-control" id="edit_purpose_name" name="purpose_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_is_active">Status *</label>
                                <select class="form-control" id="edit_is_active" name="is_active" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_purpose_description">Description</label>
                                <textarea class="form-control" id="edit_purpose_description" name="purpose_description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Purpose</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    // Debug: Check login status
    console.log('Location ID:', '<?php echo isset($location_id) ? $location_id : "NOT SET"; ?>');
    console.log('Session Username:', '<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : "NOT SET"; ?>');
    
    // Check if location_id is set
    var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
    if (!locationId) {
        Swal.fire({
            icon: 'warning',
            title: 'No Location Selected',
            text: 'Please select a client/location first from the top menu before managing land usage purposes.',
            confirmButtonText: 'OK'
        });
        return; // Don't initialize DataTable
    }
    
    // Test session with debug endpoint
    $.get('ajax/debug_session.php', function(data) {
        console.log('Session Debug Info:', data);
    }).fail(function(xhr) {
        console.error('Session debug failed:', xhr.responseText);
    });
    
    // Initialize DataTable
    var table = $('#purposesTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        ajax: {
            url: 'ajax/fetch_land_usage_purposes.php',
            data: { location_id: locationId },
            error: function(xhr, error, code) {
                console.error('DataTable Ajax Error:', {
                    xhr: xhr,
                    error: error,
                    code: code,
                    responseText: xhr.responseText
                });
                
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Load Data',
                    html: 'Error loading table data:<br><small>' + (xhr.responseText || error) + '</small>',
                    width: '600px'
                });
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 0 }, // Purpose ID
            { data: 1 }, // Purpose Name
            { data: 2 }, // Description
            { data: 3 }, // Status
            { data: 4 }, // Created By
            { data: 5 }, // Created On
            { data: 6, orderable: false, searchable: false }  // Actions
        ]
    });

    // Add Purpose Form Submit
    $('#addPurposeForm').on('submit', function(e) {
        e.preventDefault();
        
        // Check if user is logged in by checking if location_id exists
        var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
        if (!locationId) {
            Swal.fire('Error', 'Please select a client/location first from the top menu.', 'error');
            return false;
        }
        
        var formData = $(this).serialize() + '&action=add&location_id=' + locationId;
        console.log('Submitting form data:', formData);
        
        $.ajax({
            url: 'ajax/manage_land_usage_purposes.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                console.log('Sending request to save purpose...');
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    $('#addPurposeModal').modal('hide');
                    $('#addPurposeForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    status_code: xhr.status
                });
                
                var errorMessage = 'Failed to save purpose';
                var debugInfo = '';
                
                // Try to get the actual error message from server
                if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                        
                        // Add debug information if available
                        if (errorResponse.debug) {
                            debugInfo = '<br><small>Debug: ' + JSON.stringify(errorResponse.debug) + '</small>';
                        }
                        if (errorResponse.error_type) {
                            debugInfo += '<br><small>Error Type: ' + errorResponse.error_type + '</small>';
                        }
                    } catch (e) {
                        // If it's not JSON, show the raw response
                        errorMessage = 'Server error: ' + xhr.responseText.substring(0, 500);
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: errorMessage + debugInfo,
                    width: '600px'
                });
            }
        });
    });

    // Edit Purpose Form Submit
    $('#editPurposeForm').on('submit', function(e) {
        e.preventDefault();
        
        var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
        if (!locationId) {
            Swal.fire('Error', 'Please select a client/location first from the top menu.', 'error');
            return false;
        }
        
        $.ajax({
            url: 'ajax/manage_land_usage_purposes.php',
            type: 'POST',
            data: $(this).serialize() + '&action=edit&location_id=' + locationId,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    $('#editPurposeModal').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update purpose', 'error');
            }
        });
    });

    // Export button
    $('#exportPurposesBtn').on('click', function(){
        var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
        if (!locationId) {
            Swal.fire('Error', 'Please select a client/location first from the top menu.', 'error');
            return false;
        }
        
        var url = 'ajax/export_land_usage_purposes.php?location_id=' + locationId;
        window.location = url;
    });
});

// Edit Purpose Function
function editPurpose(purposeId) {
    $.ajax({
        url: 'ajax/get_land_usage_purpose.php',
        type: 'GET',
        data: { purpose_id: purposeId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var purpose = response.data;
                $('#edit_purpose_id').val(purpose.purpose_id);
                $('#edit_purpose_name').val(purpose.purpose_name);
                $('#edit_purpose_description').val(purpose.purpose_description);
                $('#edit_is_active').val(purpose.is_active);
                $('#editPurposeModal').modal('show');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to load purpose details', 'error');
        }
    });
}

// Delete Purpose Function
function deletePurpose(purposeId) {
    var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
    if (!locationId) {
        Swal.fire('Error', 'Please select a client/location first from the top menu.', 'error');
        return false;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/manage_land_usage_purposes.php',
                type: 'POST',
                data: { 
                    action: 'delete', 
                    purpose_id: purposeId,
                    location_id: locationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deleted!', response.message, 'success');
                        $('#purposesTable').DataTable().ajax.reload();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete purpose', 'error');
                }
            });
        }
    });
}
</script>

<?php include 'footer.php'; ?>