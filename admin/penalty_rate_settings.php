<?php
session_start();
if (empty($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';
require_once 'header.php';

// Get penalty rates data directly
$penalty_rates = [];
if (isset($location_id) && $location_id) {
    $sql = "SELECT ps.*, ul.i_name as created_by_name 
            FROM short_term_penalty_settings ps 
            LEFT JOIN user_license ul ON ps.created_by = ul.usr_id 
            WHERE ps.location_id = ? 
            ORDER BY ps.effective_from DESC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $location_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $penalty_rates[] = $row;
        }
        $stmt->close();
    }
}
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4><i class="fa fa-percent"></i> Penalty Rate Settings</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header" align='right'>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addPenaltyRate()">
                            <i class="fa fa-plus"></i> Add New Rate
                        </button>
                        <button id="exportRatesBtn" class="btn btn-success btn-sm">
                            <i class="fa fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                    <div class="card-block">
                        <?php if (!isset($location_id) || !$location_id): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle"></i>
                                Please select a client/location first from the top menu to manage penalty rate settings.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="example">
                                <thead>
                                    <tr>
                                        <th>Rate ID</th>
                                        <th>Rate Type</th>
                                        <th>Rate (%)</th>
                                        <th>Effective Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($penalty_rates)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <i class="fa fa-info-circle"></i> No penalty rates configured yet.
                                                <br><small>Click "Add Penalty Rate" to create your first rate setting.</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($penalty_rates as $rate): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rate['setting_id']); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $rate['penalty_type']))); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($rate['penalty_rate'], 2); ?>%</td>
                                                <td><?php echo date('Y-m-d', strtotime($rate['effective_from'])); ?></td>
                                                <td>
                                                    <?php if ($rate['effective_to']): ?>
                                                        <?php echo date('Y-m-d', strtotime($rate['effective_to'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Ongoing</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($rate['is_active']): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($rate['created_by_name'] ?: 'System'); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($rate['created_on'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                onclick="editPenaltyRate(<?php echo $rate['setting_id']; ?>)" 
                                                                title="Edit">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="deletePenaltyRate(<?php echo $rate['setting_id']; ?>)" 
                                                                title="Delete">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Penalty Rate Modal -->
<div class="modal fade" id="addRateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Penalty Rate</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addRateForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="penalty_type">Rate Type *</label>
                                <select class="form-control" id="penalty_type" name="penalty_type" required>
                                    <option value="">Select Rate Type</option>
                                    <option value="monthly">Monthly Penalty</option>
                                    <option value="annual">Annual Penalty</option>
                                    <option value="late_payment">Late Payment Penalty</option>
                                    <option value="default">Default Penalty</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="penalty_rate">Rate Percentage (%) *</label>
                                <input type="number" class="form-control" id="penalty_rate" name="penalty_rate" 
                                       min="0" max="100" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="effective_from">Effective Date *</label>
                                <input type="date" class="form-control" id="effective_from" name="effective_from" required>
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="effective_to">End Date</label>
                                <input type="date" class="form-control" id="effective_to" name="effective_to">
                                <small class="form-text text-muted">Leave empty if rate is ongoing</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                         placeholder="Enter detailed description of the penalty rate"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Penalty Rate Modal -->
<div class="modal fade" id="editRateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Penalty Rate</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editRateForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_rate_id" name="rate_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_penalty_type">Rate Type *</label>
                                <select class="form-control" id="edit_penalty_type" name="penalty_type" required>
                                    <option value="">Select Rate Type</option>
                                    <option value="monthly">Monthly Penalty</option>
                                    <option value="annual">Annual Penalty</option>
                                    <option value="late_payment">Late Payment Penalty</option>
                                    <option value="default">Default Penalty</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_penalty_rate">Rate Percentage (%) *</label>
                                <input type="number" class="form-control" id="edit_penalty_rate" name="penalty_rate" 
                                       min="0" max="100" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_effective_from">Effective Date *</label>
                                <input type="date" class="form-control" id="edit_effective_from" name="effective_from" required>
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_effective_to">End Date</label>
                                <input type="date" class="form-control" id="edit_effective_to" name="effective_to">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_description">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Rate</button>
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
        console.log('No location selected');
    }
    
    // Add Rate Form Submit
    $('#addRateForm').on('submit', function(e) {
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
            url: 'ajax/manage_penalty_rates.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                console.log('Sending request to save penalty rate...');
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    Swal.fire('Success', response.message, 'success').then(function() {
                        window.location.reload();
                    });
                    $('#addRateModal').modal('hide');
                    $('#addRateForm')[0].reset();
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
                
                var errorMessage = 'Failed to save penalty rate';
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

    // Edit Rate Form Submit
    $('#editRateForm').on('submit', function(e) {
        e.preventDefault();
        
        var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
        if (!locationId) {
            Swal.fire('Error', 'Please select a client/location first from the top menu.', 'error');
            return false;
        }
        
        var formData = $(this).serialize() + '&action=edit&location_id=' + locationId;
        console.log('Submitting edit form data:', formData);
        
        $.ajax({
            url: 'ajax/manage_penalty_rates.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                console.log('Sending request to update penalty rate...');
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    Swal.fire('Success', response.message, 'success').then(function() {
                        window.location.reload();
                    });
                    $('#editRateModal').modal('hide');
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
                
                var errorMessage = 'Failed to update penalty rate';
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

    // Export button
    $('#exportRatesBtn').on('click', function(){
        var locationId = '<?php echo isset($location_id) && $location_id ? $location_id : ""; ?>';
        if (!locationId) {
            Swal.fire('Error', 'Please select a client/location first from the top menu.', 'error');
            return false;
        }
        
        var url = 'ajax/export_penalty_rates.php?location_id=' + locationId;
        window.location = url;
    });
});

function addPenaltyRate() {
    var locationId = '<?php echo isset($location_id) ? $location_id : ""; ?>';
    if (!locationId) {
        Swal.fire({
            icon: 'warning',
            title: 'No Location Selected',
            text: 'Please select a client/location first from the top menu.'
        });
        return false;
    }
    $('#addRateModal').modal('show');
}

// Edit Rate Function
function editPenaltyRate(rateId) {
    var locationId = '<?php echo isset($location_id) ? $location_id : ""; ?>';
    if (!locationId) {
        Swal.fire({
            icon: 'warning',
            title: 'No Location Selected', 
            text: 'Please select a client/location first from the top menu.'
        });
        return false;
    }
    
    $.ajax({
        url: 'ajax/get_penalty_rate.php',
        type: 'GET',
        data: { rate_id: rateId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var rate = response.data;
                $('#edit_rate_id').val(rate.setting_id);
                $('#edit_penalty_type').val(rate.penalty_type);
                $('#edit_penalty_rate').val(rate.penalty_rate);
                $('#edit_effective_from').val(rate.effective_from);
                $('#edit_effective_to').val(rate.effective_to);
                $('#edit_description').val(rate.description);
                $('#edit_is_active').val(rate.is_active);
                $('#editRateModal').modal('show');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to load penalty rate details', 'error');
        }
    });
}

// Delete Rate Function
function deletePenaltyRate(rateId) {
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
                url: 'ajax/manage_penalty_rates.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    rate_id: rateId,
                    location_id: locationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deleted!', response.message, 'success').then(function() {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete penalty rate', 'error');
                }
            });
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>