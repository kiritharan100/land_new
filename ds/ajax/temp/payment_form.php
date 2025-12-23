<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include '../../db.php';

// Set content type for JSON responses
header('Content-Type: text/html');

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
?>
<form id="paymentForm">
    <input type="hidden" name="lease_id" value="<?= $lease_id ?>">
    
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number']) ?> - <?= htmlspecialchars($lease['beneficiary_name']) ?><br>
                <strong>Land:</strong> <?= htmlspecialchars($lease['address']) ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="payment_date">Payment Date *</label>
                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                       value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select class="form-control" id="payment_method" name="payment_method" required>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="online">Online Payment</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="reference_number">Reference Number</label>
                <input type="text" class="form-control" id="reference_number" name="reference_number" 
                       placeholder="Cheque number, bank reference, etc.">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="amount">Amount (LKR) *</label>
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                       required placeholder="Enter payment amount">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" 
                          placeholder="Any additional notes..."></textarea>
            </div>
        </div>
    </div>

 

    <div class="row">
        <div class="col-md-12 text-right">
            <button type="submit" class="btn btn-success">
                <i class="fa fa-save"></i> Record Payment
            </button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
    </div>
</form>

<script>
function calculateOutstanding() {
    const leaseId = <?= $lease_id ?>;
    const paymentDate = $('#payment_date').val();
    
    if (!paymentDate) {
        alert('Please select payment date first');
        return;
    }
    
    $('#calculationResult').html('Calculating...').show();
    
    $.ajax({
        url: 'ajax/calculate_outstanding.php',
        type: 'GET',
        data: { lease_id: leaseId, payment_date: paymentDate },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#calculationResult').html(`
                    <strong>Outstanding Calculation:</strong><br>
                    Rent: LKR ${response.outstanding_rent.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                    Penalty: LKR ${response.penalty_amount.toLocaleString('en-US', {minimumFractionDigits: 2})}<br>
                    <strong>Total Due: LKR ${response.total_due.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                `);
                $('#amount').val(response.total_due);
            } else {
                $('#calculationResult').html('Error calculating outstanding amount');
            }
        },
        error: function() {
            $('#calculationResult').html('Error calculating outstanding amount');
        }
    });
}

$('#paymentForm').on('submit', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: 'Recording Payment',
        text: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/record_payment_simple.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                Swal.fire({
                    title: 'Success!',
                    text: response.message,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#paymentModal').modal('hide');
                    location.reload(); // Refresh to show updated data
                });
            } else {
                Swal.fire({
                    title: 'Error!', 
                    text: response.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            console.log('AJAX Error:', xhr.responseText);
            Swal.fire({
                title: 'Error!',
                text: 'Network error occurred. Check console for details.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
});
</script>
<?php } ?>