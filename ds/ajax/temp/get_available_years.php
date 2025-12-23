<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$location_id = intval($_GET['location_id'] ?? 0);

if ($location_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid location ID is required']);
    exit;
}

try {
    // Get distinct years from start_date column for the specific location
    $query = "SELECT DISTINCT YEAR(start_date) as year 
              FROM short_term_leases 
              WHERE location_id = ? 
              AND start_date IS NOT NULL
              AND (is_deleted IS NULL OR is_deleted = 0)
              ORDER BY year DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $years = [];
    $currentYear = date('Y');
    $defaultYear = null;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['year']) {
            $years[] = intval($row['year']);
            
            // Set default year: current year if exists, otherwise most recent year
            if ($row['year'] == $currentYear) {
                $defaultYear = intval($row['year']);
            } else if ($defaultYear === null) {
                $defaultYear = intval($row['year']); // Most recent year
            }
        }
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $years,
        'default_year' => $defaultYear,
        'message' => 'Available years loaded successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_available_years.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load available years: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>