<?php
/**
 * Minimal Approval Test (No Email)
 * TAU-UREO Portal
 */

// Start session
session_start();

// Simulate login for testing
$_SESSION['user_id'] = 1;

// Simulate POST
$_POST['queue_number'] = 'UREO-0001';
$_POST['review_type'] = 'expedited';

echo "<h2>Minimal Approval Test</h2>";

try {
    require_once 'config/config.php';
    require_once 'includes/functions.php';
    
    echo "<p style='color: green;'>✅ Includes loaded</p>";
    
    $conn = getDBConnection();
    echo "<p style='color: green;'>✅ Database connected</p>";
    
    // Get application
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $_POST['queue_number']);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if ($application) {
        echo "<p style='color: green;'>✅ Application found: " . $application['queue_number'] . "</p>";
        echo "<p>Current status: " . $application['current_status'] . "</p>";
        
        // Test status update
        $new_status = 'CATEGORY_FORMS_REQUIRED';
        $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
        $update_stmt->bind_param("sss", $new_status, $_POST['review_type'], $_POST['queue_number']);
        
        if ($update_stmt->execute()) {
            echo "<p style='color: green;'>✅ Status update successful</p>";
            echo "<p>New status: $new_status</p>";
        } else {
            echo "<p style='color: red;'>❌ Status update failed</p>";
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
