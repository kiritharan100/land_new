<?php
include '../db.php'; // Ensure the DB connection is included here.

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $salary_number = $_POST['salary_number'];
    $name = $_POST['name'];
    $nic_no = $_POST['nic_no'];
    $designation = $_POST['designation'];
    $location = $_POST['location'];

    // Validate required fields
    if (empty($salary_number) || empty($name) || empty($nic_no) || empty($designation) || empty($location)) {
        echo "All fields are required.";
        exit;
    }

    // Insert query
    $stmt = $con->prepare("INSERT INTO emp_list (salary_number, name, nic_no, designation, location, status) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("sssss", $salary_number, $name, $nic_no, $designation, $location);

    if ($stmt->execute()) {
        echo "Staff added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>
