<?php
/**
 * Full Approval Test with Session
 * TAU-UREO Portal
 */

// Start session and simulate login
session_start();
$_SESSION['user_id'] = 1;

// Simulate POST data
$_POST['queue_number'] = 'UREO-0001';
$_POST['review_type'] = 'expedited';

echo "<h2>Full Approval Test with Session</h2>";

try {
    // Include all required files
    require_once 'config/config.php';
    require_once 'includes/functions.php';
    require_once 'includes/email-template-functions.php';
    
    echo "<p style='color: green;'>✅ All includes loaded</p>";
    
    // Test requireLogin function
    if (function_exists('requireLogin')) {
        echo "<p style='color: green;'>✅ requireLogin function exists</p>";
    } else {
        echo "<p style='color: red;'>❌ requireLogin function missing</p>";
    }
    
    // Test checkSessionTimeout function
    if (function_exists('checkSessionTimeout')) {
        echo "<p style='color: green;'>✅ checkSessionTimeout function exists</p>";
    } else {
        echo "<p style='color: red;'>❌ checkSessionTimeout function missing</p>";
    }
    
    // Now test the actual approval process
    echo "<h3>Testing Approval Process</h3>";
    
    // This should work since we have session
    requireLogin();
    checkSessionTimeout();
    echo "<p style='color: green;'>✅ Login checks passed</p>";
    
    // Test database operations
    $conn = getDBConnection();
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $_POST['queue_number']);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if ($application) {
        echo "<p style='color: green;'>✅ Application found</p>";
        echo "<p>Current Status: " . $application['current_status'] . "</p>";
        
        // Test status update
        $new_status = 'CATEGORY_FORMS_REQUIRED';
        $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
        $update_stmt->bind_param("sss", $new_status, $_POST['review_type'], $_POST['queue_number']);
        
        if ($update_stmt->execute()) {
            echo "<p style='color: green;'>✅ Status update successful</p>";
            echo "<p>✅ Approval process core functionality works!</p>";
        } else {
            echo "<p style='color: red;'>❌ Status update failed: " . $conn->error . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Application not found</p>";
    }
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

?>
