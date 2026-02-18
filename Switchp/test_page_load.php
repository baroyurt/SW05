<?php
// Test script to diagnose snmp_admin.php 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...\n";

// Test 1: Check if db.php exists and loads
echo "\n1. Testing db.php...\n";
if (file_exists('db.php')) {
    echo "   db.php file exists\n";
    try {
        require_once 'db.php';
        echo "   db.php loaded successfully\n";
        if (isset($conn)) {
            echo "   Connection object exists\n";
            if ($conn->ping()) {
                echo "   ✓ Database connection is working\n";
            } else {
                echo "   ✗ Database ping failed\n";
            }
        } else {
            echo "   ✗ Connection object not set\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error loading db.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ db.php file not found\n";
}

// Test 2: Check if auth.php exists and loads
echo "\n2. Testing auth.php...\n";
if (file_exists('auth.php')) {
    echo "   auth.php file exists\n";
    try {
        require_once 'auth.php';
        echo "   auth.php loaded successfully\n";
        if (class_exists('Auth')) {
            echo "   ✓ Auth class exists\n";
        } else {
            echo "   ✗ Auth class not found\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error loading auth.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ auth.php file not found\n";
}

// Test 3: Check session
echo "\n3. Testing session...\n";
try {
    session_start();
    echo "   ✓ Session started successfully\n";
} catch (Exception $e) {
    echo "   ✗ Session error: " . $e->getMessage() . "\n";
}

// Test 4: Test Auth instantiation
echo "\n4. Testing Auth instantiation...\n";
try {
    if (isset($conn) && class_exists('Auth')) {
        $auth = new Auth($conn);
        echo "   ✓ Auth object created\n";
        echo "   Login status: " . ($auth->isLoggedIn() ? 'Logged in' : 'Not logged in') . "\n";
    } else {
        echo "   ✗ Cannot create Auth object (missing prerequisites)\n";
    }
} catch (Exception $e) {
    echo "   ✗ Auth instantiation error: " . $e->getMessage() . "\n";
}

// Test 5: Check config path
echo "\n5. Testing config path...\n";
$configPath = '../snmp_worker/config/config.yml';
echo "   Config path: $configPath\n";
if (file_exists($configPath)) {
    echo "   ✓ Config file exists\n";
    if (is_readable($configPath)) {
        echo "   ✓ Config file is readable\n";
    } else {
        echo "   ✗ Config file is not readable\n";
    }
} else {
    echo "   ℹ Config file doesn't exist (will use defaults)\n";
}

echo "\nTest complete!\n";
?>
