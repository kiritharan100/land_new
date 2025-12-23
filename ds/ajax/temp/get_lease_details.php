<?php
require('../../db.php');
session_start();

if (empty($_SESSION['username'])) {
    echo '<div class="alert alert-danger">Authentication required</div>';
    exit;
}

$lease_id = intval($_GET['lease_id'] ?? 0);

if ($lease_id <= 0) {
    echo '<div class="alert alert-danger">Invalid lease ID</div>';
    exit;
}

try {
        $query = "SELECT stl.*, 
                lr.address as land_address,
                lr.lcg_plan_no, 
                lr.val_plan_no, 
                lr.survey_plan_no,
                lr.lcg_hectares,
                lr.latitude,
                lr.longitude,
                b.name as beneficiary_name, 
                b.nic_reg_no,
                b.telephone,
                b.email,
                b.address as beneficiary_address,
                gn.gn_name,
                gn.gn_no
            FROM short_term_leases stl
            LEFT JOIN short_term_land_registration lr ON stl.land_id = lr.land_id
            LEFT JOIN short_term_beneficiaries b ON stl.beneficiary_id = b.ben_id
            LEFT JOIN gn_division gn ON lr.gn_id = gn.gn_id
            WHERE stl.st_lease_id = ?";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $lease_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $lease = $result->fetch_assoc();
        
        // Get purpose name (since we're using static data)
        $purpose_names = [
            1 => 'Agricultural Use',
            2 => 'Residential Development', 
            3 => 'Commercial Development',
            4 => 'Industrial Use',
            5 => 'Recreational Use',
            6 => 'Conservation',
            7 => 'Government Use',
            8 => 'Educational Institution',
            9 => 'Religious Purpose',
            10 => 'Mixed Use Development'
        ];
        $purpose_name = $purpose_names[$lease['purpose_id']] ?? 'Unknown Purpose';
        
        // Format the dates
        $start_date = date('Y-m-d', strtotime($lease['start_date']));
        $end_date = date('Y-m-d', strtotime($lease['end_date']));
        $payment_due_date = date('Y-m-d', strtotime($lease['payment_due_date']));
        $created_date = date('Y-m-d H:i:s', strtotime($lease['created_on']));
        
        // Format currency
        $lease_amount = number_format($lease['lease_amount'], 2);
        $total_paid = number_format($lease['total_paid'], 2);
        $penalty_amount = number_format($lease['penalty_amount'], 2);
        $penalty_paid = number_format($lease['penalty_paid'], 2);
        
        // Format status badge
        $status_class = '';
        switch (strtoupper($lease['status'])) {
            case 'ACTIVE':
                $status_class = 'badge-success';
                break;
            case 'INACTIVE':
                $status_class = 'badge-secondary';
                break;
            case 'EXPIRED':
                $status_class = 'badge-warning';
                break;
            case 'CANCELLED':
                $status_class = 'badge-danger';
                break;
            default:
                $status_class = 'badge-info';
        }
        
        // Generate the HTML content
        echo '
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-primary">Lease Information</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <td><strong>Lease Number:</strong></td>
                        <td>' . htmlspecialchars($lease['lease_number']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Lease Year:</strong></td>
                        <td>' . htmlspecialchars($lease['lease_year']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Start Date:</strong></td>
                        <td>' . $start_date . '</td>
                    </tr>
                    <tr>
                        <td><strong>End Date:</strong></td>
                        <td>' . $end_date . '</td>
                    </tr>
                    <tr>
                        <td><strong>Purpose:</strong></td>
                        <td>' . htmlspecialchars($purpose_name) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span class="badge ' . $status_class . '">' . ucfirst($lease['status']) . '</span></td>
                    </tr>
                    <tr>
                        <td><strong>Auto Renewal:</strong></td>
                        <td>' . ($lease['auto_renew'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>') . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h5 class="text-primary">Financial Information</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <td><strong>Lease Amount:</strong></td>
                        <td>LKR ' . $lease_amount . '</td>
                    </tr>
                    <tr>
                        <td><strong>Payment Due Date:</strong></td>
                        <td>' . $payment_due_date . '</td>
                    </tr>
                    <tr>
                        <td><strong>Payment Status:</strong></td>
                        <td><span class="badge ' . ($lease['payment_status'] == 'paid' ? 'badge-success' : 'badge-warning') . '">' . ucfirst($lease['payment_status']) . '</span></td>
                    </tr>
                    <tr>
                        <td><strong>Total Paid:</strong></td>
                        <td>LKR ' . $total_paid . '</td>
                    </tr>
                    <tr>
                        <td><strong>Penalty Amount:</strong></td>
                        <td>LKR ' . $penalty_amount . '</td>
                    </tr>
                    <tr>
                        <td><strong>Penalty Paid:</strong></td>
                        <td>LKR ' . $penalty_paid . '</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <h5 class="text-primary">Land Registration Details</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td>' . htmlspecialchars($lease['land_address'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Plan Number:</strong></td>
                        <td>' . htmlspecialchars($lease['lcg_plan_no'] ?: $lease['val_plan_no'] ?: $lease['survey_plan_no'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Area (Hectares):</strong></td>
                        <td>' . ($lease['lcg_hectares'] ? number_format($lease['lcg_hectares'], 4) : 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>GN Division:</strong></td>
                        <td>' . htmlspecialchars($lease['gn_name'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Coordinates:</strong></td>
                        <td>' . ($lease['latitude'] && $lease['longitude'] ? $lease['latitude'] . ', ' . $lease['longitude'] : 'N/A') . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h5 class="text-primary">Beneficiary Details</h5>
                <table class="table table-bordered table-sm">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td>' . htmlspecialchars($lease['beneficiary_name'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>NIC Number:</strong></td>
                        <td>' . htmlspecialchars($lease['nic_reg_no'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Telephone:</strong></td>
                        <td>' . htmlspecialchars($lease['telephone'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td>' . htmlspecialchars($lease['email'] ?: 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td>' . htmlspecialchars($lease['beneficiary_address'] ?: 'N/A') . '</td>
                    </tr>
                </table>
            </div>
        </div>';
        
        if (!empty($lease['remarks'])) {
            echo '
            <div class="row mt-3">
                <div class="col-md-12">
                    <h5 class="text-primary">Special Conditions/Remarks</h5>
                    <div class="alert alert-info">
                        ' . nl2br(htmlspecialchars($lease['remarks'])) . '
                    </div>
                </div>
            </div>';
        }
        
        echo '
        <div class="row mt-3">
            <div class="col-md-12">
                <h6 class="text-muted">Created on: ' . $created_date . '</h6>
            </div>
        </div>';
        
    } else {
        echo '<div class="alert alert-warning">Lease not found</div>';
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in get_lease_details.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading lease details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>