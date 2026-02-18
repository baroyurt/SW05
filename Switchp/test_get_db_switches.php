<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...\n";

// Simulate the POST request
$_POST['action'] = 'get_db_switches';

// Include the necessary files
try {
    echo "Loading db.php...\n";
    require_once 'db.php';
    echo "DB loaded successfully\n";
    
    echo "Loading auth.php...\n";
    require_once 'auth.php';
    echo "Auth loaded successfully\n";
    
    // Check if we can connect
    echo "Testing database connection...\n";
    $test = $conn->query("SELECT 1");
    echo "Database connection OK\n";
    
    // Try to query switches
    echo "Querying switches table...\n";
    $stmt = $conn->prepare("SHOW COLUMNS FROM switches");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available columns: " . implode(', ', $columns) . "\n";
    
    // Check if vendor column exists
    echo "Checking for vendor column...\n";
    $checkVendor = $conn->query("SHOW COLUMNS FROM switches LIKE 'vendor'");
    $hasVendor = $checkVendor->rowCount() > 0;
    echo "Vendor column exists: " . ($hasVendor ? 'YES' : 'NO') . "\n";
    
    // Check if description column exists
    echo "Checking for description column...\n";
    $checkDesc = $conn->query("SHOW COLUMNS FROM switches LIKE 'description'");
    $hasDescription = $checkDesc->rowCount() > 0;
    echo "Description column exists: " . ($hasDescription ? 'YES' : 'NO') . "\n";
    
    // Build the query
    $vendorCol = $hasVendor ? 'vendor' : 'brand as vendor';
    $descCol = $hasDescription ? 'description' : 'NULL as description';
    
    echo "Vendor column query: $vendorCol\n";
    echo "Description column query: $descCol\n";
    
    // Try the actual query
    echo "Executing main query...\n";
    $stmt = $conn->prepare("SELECT id, name, ip, $vendorCol, model, $descCol, ports FROM switches ORDER BY name");
    $stmt->execute();
    $switches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query successful! Found " . count($switches) . " switches\n";
    
    if (count($switches) > 0) {
        echo "First switch:\n";
        print_r($switches[0]);
    }
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
