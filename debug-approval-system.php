<?php
/**
 * Simple Approval Test
 * TAU-UREO Portal
 */

// Start session first
session_start();

echo "<h2>Simple Approval Test</h2>";

// Check if required files exist
$required_files = [
    'config/config.php',
    'includes/functions.php',
    'includes/email-template-functions.php'
];

echo "<h3>Required Files Check</h3>";
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file NOT found</p>";
    }
}

// Check session
echo "<h3>Session Check</h3>";
if (session_status() === PHP_SESSION_NONE) {
    echo "<p style='color: orange;'>⚠️ No session started</p>";
} elseif (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>✅ Session active</p>";
    if (isset($_SESSION['user_id'])) {
        echo "<p style='color: green;'>✅ User logged in: " . $_SESSION['user_id'] . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Session active but no user_id</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Session disabled</p>";
}

// Test database connection
echo "<h3>Database Connection Test</h3>";
try {
    require_once 'config/config.php';
    require_once 'includes/functions.php';
    
    $conn = getDBConnection();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Test query
    $result = $conn->query("SELECT 1");
    echo "<p style='color: green;'>✅ Test query successful</p>";
    
    closeDBConnection($conn);
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test POST simulation
echo "<h3>POST Simulation Test</h3>";
$_POST['queue_number'] = 'UREO-0001';
$_POST['review_type'] = 'expedited';

echo "<p>Simulating POST data:</p>";
echo "<pre>";
echo "Queue Number: " . $_POST['queue_number'] . "\n";
echo "Review Type: " . $_POST['review_type'] . "\n";
echo "</pre>";

echo "<h3>Next Steps</h3>";
echo "<p>If all checks pass above, the issue might be:</p>";
echo "<ul>";
echo "<li>Missing email template functions</li>";
echo "<li>Missing vendor libraries</li>";
echo "<li>Permission issues</li>";
echo "<li>Session timeout</li>";
echo "</ul>";

?>
