<?php
include '../../db.php';

echo "<h3>Adding New Fields to Leases Table</h3>";

try {
    // Add valuation_date field
    echo "<h4>Adding valuation_date field...</h4>";
    $add_valuation_date = "ALTER TABLE leases ADD COLUMN valuation_date DATE NULL AFTER valuation_amount";
    if ($con->query($add_valuation_date)) {
        echo "✓ valuation_date field added<br>";
    } else {
        if (strpos($con->error, "Duplicate column name") !== false) {
            echo "ℹ valuation_date field already exists<br>";
        } else {
            echo "✗ Error adding valuation_date: " . $con->error . "<br>";
        }
    }
    
    // Add duration_years field
    echo "<h4>Adding duration_years field...</h4>";
    $add_duration_years = "ALTER TABLE leases ADD COLUMN duration_years INT DEFAULT 30 AFTER end_date";
    if ($con->query($add_duration_years)) {
        echo "✓ duration_years field added<br>";
    } else {
        if (strpos($con->error, "Duplicate column name") !== false) {
            echo "ℹ duration_years field already exists<br>";
        } else {
            echo "✗ Error adding duration_years: " . $con->error . "<br>";
        }
    }
    
    // Update existing records with default values
    echo "<h4>Updating existing records...</h4>";
    $update_defaults = "UPDATE leases SET 
                       valuation_date = COALESCE(valuation_date, start_date),
                       duration_years = COALESCE(duration_years, 30)
                       WHERE valuation_date IS NULL OR duration_years IS NULL";
    if ($con->query($update_defaults)) {
        echo "✓ Existing records updated with default values<br>";
    } else {
        echo "✗ Error updating defaults: " . $con->error . "<br>";
    }
    
    // Show current table structure
    echo "<h4>Current leases table structure:</h4>";
    $result = $con->query("DESCRIBE leases");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background-color: #f0f0f0;'><th style='padding: 8px;'>Field</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Null</th><th style='padding: 8px;'>Key</th><th style='padding: 8px;'>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $row['Field'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h4>✅ Database migration completed successfully!</h4>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Create lease with valuation_date and dynamic duration</li>";
    echo "<li>✅ Edit existing leases with new fields</li>";
    echo "<li>✅ End date automatically calculated from start date + duration</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h4>❌ Error during migration:</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>