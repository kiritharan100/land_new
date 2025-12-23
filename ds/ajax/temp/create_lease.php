<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include '../../db.php';

// Set content type for JSON response
header('Content-Type: application/json');

if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get location_id from session or cookie
        if(isset($_COOKIE['client_cook'])){
            $selected_client = $_COOKIE['client_cook'];
            $sel_query = "SELECT c_id from client_registration where md5_client='$selected_client'";
            $result = mysqli_query($con, $sel_query);
            $row = mysqli_fetch_assoc($result); 
            $location_id = $row['c_id'];
        } else {
            $location_id = 0;
        }
        
        $land_id = (int)($_POST['land_id'] ?? 0);
        $beneficiary_id = $_POST['beneficiary_id'];
        $valuation_amount = $_POST['valuation_amount'];
        $valuation_date = $_POST['valuation_date'];
        $annual_rent_percentage = $_POST['annual_rent_percentage'];
        $revision_period = $_POST['revision_period'];
        $revision_percentage = $_POST['revision_percentage'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $duration_years = $_POST['duration_years'];
        $lease_type_id = isset($_POST['lease_type_id']) ? (int)$_POST['lease_type_id'] : 0;
        $type_of_project = isset($_POST['type_of_project']) ? mysqli_real_escape_string($con, $_POST['type_of_project']) : '';
        $name_of_the_project = isset($_POST['name_of_the_project']) ? mysqli_real_escape_string($con, $_POST['name_of_the_project']) : '';
        
        // Lease number and file number: accept both from form; fallback to generated values
        $lease_number = isset($_POST['lease_number']) && trim($_POST['lease_number']) !== '' ? mysqli_real_escape_string($con, $_POST['lease_number']) : "LEASE-" . date('Ymd-His');
        $file_number = isset($_POST['file_number']) && trim($_POST['file_number']) !== '' ? mysqli_real_escape_string($con, $_POST['file_number']) : $lease_number;
        
        // Calculate initial annual rent
        $initial_annual_rent = $valuation_amount * ($annual_rent_percentage / 100);
        
        // Insert lease (including lease_type and project fields)
        $land_id = (int)$land_id;
        $beneficiary_id = (int)$beneficiary_id;
        $location_id = (int)$location_id;
        $valuation_amount = floatval($valuation_amount);
        $annual_rent_percentage = floatval($annual_rent_percentage);
        $revision_period = (int)$revision_period;
        $revision_percentage = floatval($revision_percentage);
        $duration_years = (int)$duration_years;
        $lease_type_id = (int)$lease_type_id;

        $lease_number_esc = mysqli_real_escape_string($con, $lease_number);
        $valuation_date_esc = mysqli_real_escape_string($con, $valuation_date);
        $start_date_esc = mysqli_real_escape_string($con, $start_date);
        $end_date_esc = mysqli_real_escape_string($con, $end_date);

        $file_number_esc = mysqli_real_escape_string($con, $file_number);

        $sql = "INSERT INTO leases (land_id, beneficiary_id, location_id, lease_number, file_number, valuation_amount, valuation_date, annual_rent_percentage, revision_period, revision_percentage, start_date, end_date, duration_years, lease_type_id, type_of_project, name_of_the_project, created_by, status, created_on)
            VALUES ($land_id, $beneficiary_id, $location_id, '$lease_number_esc', '$file_number_esc', $valuation_amount, '$valuation_date_esc', $annual_rent_percentage, $revision_period, $revision_percentage, '$start_date_esc', '$end_date_esc', $duration_years, $lease_type_id, '$type_of_project', '$name_of_the_project', {$_SESSION['user_id']}, 'active', NOW())";

        if ($con->query($sql)) {
            $lease_id = $con->insert_id;
            // Generate schedules automatically
            generateLeaseSchedules($lease_id, $initial_annual_rent, $revision_period, $revision_percentage, $start_date, $duration_years);
              
            // Run penalty calculation for the new lease (silently) to initialize penalties
                try {
                    // Ensure we don't pollute JSON output - buffer include
                    $_REQUEST['lease_id'] = $lease_id;
                    ob_start();
                    include __DIR__ . '/../cal_panalty.php';
                    ob_end_clean();
                } catch (Exception $e) {
                    // non-fatal: log and continue
                    UserLog('Lease Management', 'Penalty Calc Failed', "LeaseID: $lease_id - " . $e->getMessage());
                }


            UserLog('Lease Management', 'Create Lease', "Created new lease: $lease_number");
            $response['success'] = true;
            $response['message'] = 'Lease created successfully!';
        } else {
            throw new Exception("Error creating lease: " . $con->error . " SQL: " . $sql);
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
}

function generateLeaseSchedules($lease_id, $initial_rent, $revision_period, $revision_percentage, $start_date, $duration_years = 30) {
    global $con;
    
    try {
        $start_year = date('Y', strtotime($start_date));
        $duration = $duration_years; // Use dynamic duration
        
        $current_rent = $initial_rent;
        $revision_number = 0;
        
        for ($year = 0; $year < $duration; $year++) {
            $schedule_year = $start_year + $year;
            
            // Calculate start_date and end_date for this schedule year
            $year_start_date = date('Y-m-d', strtotime($start_date . " + $year years"));
            $year_end_date = date('Y-m-d', strtotime($year_start_date . " + 1 year - 1 day"));
            $due_date = date('Y-m-d', strtotime("$schedule_year-03-31"));
            
            // Check if revision year
            $is_revision_year = ($year > 0 && $year % $revision_period == 0);
            
            if ($is_revision_year) {
                $revision_number++;
                $current_rent = $current_rent * (1 + ($revision_percentage / 100));
            }
            
            $sql = "INSERT INTO lease_schedules (
                lease_id, schedule_year, start_date, end_date, due_date, base_amount, annual_amount, 
                revision_number, is_revision_year, status, created_on
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $con->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparing schedule statement: " . $con->error);
            }
            
            $stmt->bind_param("iisssdiss", 
                $lease_id, $schedule_year, $year_start_date, $year_end_date, $due_date, $initial_rent, $current_rent,
                $revision_number, $is_revision_year
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating schedule for year $schedule_year: " . $stmt->error);
            }
        }
    } catch (Exception $e) {
        throw new Exception("Schedule generation failed: " . $e->getMessage());
    }
}
?>