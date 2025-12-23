<?php
include '../db.php'; // Ensure the DB connection is included here.

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emp_id = $_POST['emp_id'];
    $salary_number = $_POST['salary_number'];
    $name = $_POST['name'];
    $nic_no = $_POST['nic_no'];
    $designation = $_POST['designation'];
    $location = $_POST['location'];
    $status = $_POST['status']; // Retrieve the status field
    
    

    // Validate required fields
    if (empty($emp_id) || empty($salary_number) || empty($name) || empty($nic_no) || empty($designation) || empty($location)) {
        echo "All fields are required.";
        exit;
    }

    // Update query
    
    if($status =='0'){
            $stmt = $con->prepare("UPDATE emp_list SET salary_number = ?, name = ?, nic_no = ?, designation = ?, location = ?, status = ? ,password= '' WHERE emp_id = ?");
    $stmt->bind_param("sssssii", $salary_number, $name, $nic_no, $designation, $location, $status, $emp_id);
    
    }else{
          $stmt = $con->prepare("UPDATE emp_list SET salary_number = ?, name = ?, nic_no = ?, designation = ?, location = ?, status = ? WHERE emp_id = ?");
    $stmt->bind_param("sssssii", $salary_number, $name, $nic_no, $designation, $location, $status, $emp_id);  
    }


    if ($stmt->execute()) {
        echo "Staff updated successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>
