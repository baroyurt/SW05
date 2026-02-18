<?php
/**
 * test_update_validation.php
 * 
 * Quick validation script to check if update_database.php is properly structured
 * This does NOT execute database changes, only validates syntax
 */

echo "=== Database Update Script Validation ===\n\n";

// Check if update_database.php exists
$updateFile = __DIR__ . '/update_database.php';
if (!file_exists($updateFile)) {
    echo "❌ ERROR: update_database.php not found\n";
    exit(1);
}

echo "✓ update_database.php exists\n";

// Read and parse the file
$content = file_get_contents($updateFile);

// Check for required functions
$requiredFunctions = ['tableExists', 'columnExists', 'indexExists', 'fkExists', 'enumHasValue'];
foreach ($requiredFunctions as $func) {
    if (strpos($content, "function $func") !== false) {
        echo "✓ Function $func() found\n";
    } else {
        echo "❌ ERROR: Function $func() not found\n";
        exit(1);
    }
}

// Check for SNMP-related updates
if (strpos($content, 'snmp_config') !== false) {
    echo "✓ SNMP config table creation found\n";
} else {
    echo "❌ ERROR: SNMP config table creation not found\n";
    exit(1);
}

if (strpos($content, 'EDGE-SW35') !== false) {
    echo "✓ EDGE-SW35 switch insertion found\n";
} else {
    echo "❌ ERROR: EDGE-SW35 switch insertion not found\n";
    exit(1);
}

// Check for backup table cleanup
if (strpos($content, 'ports_backup') !== false && strpos($content, 'DROP TABLE') !== false) {
    echo "✓ Backup table cleanup found\n";
} else {
    echo "❌ ERROR: Backup table cleanup not found\n";
    exit(1);
}

// Validate PHP syntax
$output = [];
$returnCode = 0;
exec("php -l " . escapeshellarg($updateFile) . " 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    echo "✓ PHP syntax is valid\n";
} else {
    echo "❌ ERROR: PHP syntax error:\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "\n=== All Validation Checks Passed ===\n";
echo "\nTo apply database updates, run:\n";
echo "php update_database.php\n";
echo "or visit: http://your-server/test 2/Switchp/update_database.php\n";
