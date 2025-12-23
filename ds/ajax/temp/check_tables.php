<?php
include '../../db.php';

// Check if lease_payments table exists and show its structure
try {
    $result = $con->query("DESCRIBE lease_payments");
    if ($result) {
        echo "<h3>lease_payments table structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error checking lease_payments table: " . $e->getMessage() . "</p>";
    
    // Try to create the table if it doesn't exist
    $create_sql = "CREATE TABLE IF NOT EXISTS lease_payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        lease_id INT NOT NULL,
        schedule_id INT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_type ENUM('rent', 'penalty', 'both') DEFAULT 'rent',
        receipt_number VARCHAR(50) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'cash',
        reference_number VARCHAR(100) DEFAULT '',
        notes TEXT DEFAULT '',
        created_by INT DEFAULT 1,
        created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($con->query($create_sql)) {
        echo "<p>lease_payments table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . $con->error . "</p>";
    }
}

// Check if lease_schedules table exists
try {
    $result = $con->query("DESCRIBE lease_schedules");
    if ($result) {
        echo "<h3>lease_schedules table structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error checking lease_schedules table: " . $e->getMessage() . "</p>";
}

// Check if leases table exists
try {
    $result = $con->query("DESCRIBE leases");
    if ($result) {
        echo "<h3>leases table structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error checking leases table: " . $e->getMessage() . "</p>";
}
?>