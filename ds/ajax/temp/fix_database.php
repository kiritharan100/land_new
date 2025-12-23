<?php
include '../../db.php';

echo "<h3>Fixing Database Structure</h3>";

try {
    // First, check current structure
    $result = $con->query("DESCRIBE lease_payments");
    if ($result) {
        echo "<h4>Current lease_payments structure:</h4>";
        while ($row = $result->fetch_assoc()) {
            echo "Field: " . $row['Field'] . " | Type: " . $row['Type'] . " | Null: " . $row['Null'] . "<br>";
        }
    }
    
    // Drop foreign key constraint if it exists
    echo "<h4>Removing foreign key constraint...</h4>";
    $drop_fk_sql = "ALTER TABLE lease_payments DROP FOREIGN KEY lease_payments_ibfk_2";
    if ($con->query($drop_fk_sql)) {
        echo "✓ Foreign key constraint removed<br>";
    } else {
        echo "ℹ Foreign key constraint not found or already removed: " . $con->error . "<br>";
    }
    
    // Make schedule_id nullable
    echo "<h4>Making schedule_id nullable...</h4>";
    $modify_sql = "ALTER TABLE lease_payments MODIFY COLUMN schedule_id INT NULL";
    if ($con->query($modify_sql)) {
        echo "✓ schedule_id is now nullable<br>";
    } else {
        echo "✗ Error modifying schedule_id: " . $con->error . "<br>";
    }
    
    // Add foreign key constraint back with proper settings
    echo "<h4>Adding back foreign key constraint with proper settings...</h4>";
    $add_fk_sql = "ALTER TABLE lease_payments 
                   ADD CONSTRAINT fk_lease_payments_schedule 
                   FOREIGN KEY (schedule_id) REFERENCES lease_schedules(schedule_id) 
                   ON DELETE SET NULL ON UPDATE CASCADE";
    if ($con->query($add_fk_sql)) {
        echo "✓ Foreign key constraint added with ON DELETE SET NULL<br>";
    } else {
        echo "ℹ Could not add foreign key (table may not exist): " . $con->error . "<br>";
    }
    
    // Test insert without schedule_id
    echo "<h4>Testing payment insert...</h4>";
    $test_sql = "INSERT INTO lease_payments (lease_id, payment_date, amount, payment_type, receipt_number, payment_method, created_by) 
                 VALUES (1, CURDATE(), 1000.00, 'rent', 'TEST-001', 'cash', 1)";
    if ($con->query($test_sql)) {
        echo "✓ Test payment inserted successfully<br>";
        // Remove test record
        $con->query("DELETE FROM lease_payments WHERE receipt_number = 'TEST-001'");
        echo "✓ Test record cleaned up<br>";
    } else {
        echo "✗ Test payment failed: " . $con->error . "<br>";
    }
    
    echo "<h4>Database structure fixed!</h4>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>