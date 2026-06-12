<?php
/**
 * Simple Approval Test (No Transaction)
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';
$review_type = 'expedited';

echo "<h2>Simple Approval Test (No Transaction)</h2>";

try {
    $conn = getDBConnection();
    
    echo "<h4>Step 1: Check Application Status</h4>";
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        echo "<p style='color: red;'>❌ Application not found</p>";
        exit();
    }
    echo "<p>✅ Current Status: " . $application['current_status'] . "</p>";
    
    echo "<h4>Step 2: Check for Active Locks</h4>";
    // Check if there are any active transactions or locks
    $lock_check = $conn->query("SHOW PROCESSLIST");
    $processes = $lock_check->fetch_all(MYSQLI_ASSOC);
    
    $active_processes = 0;
    foreach ($processes as $process) {
        if (!empty($process['Info']) && (strpos($process['Info'], 'UPDATE') !== false || strpos($process['Info'], 'INSERT') !== false)) {
            $active_processes++;
        }
    }
    
    echo "<p>Active database processes: $active_processes</p>";
    
    if ($active_processes > 0) {
        echo "<p style='color: orange;'>⚠️ There are active database processes that might cause locks</p>";
        echo "<pre>";
        foreach ($processes as $process) {
            if (!empty($process['Info'])) {
                echo "Process " . $process['Id'] . ": " . $process['Info'] . "\n";
            }
        }
        echo "</pre>";
    }
    
    echo "<h4>Step 3: Simple Status Update (No Transaction)</h4>";
    
    // Try the update without transaction first
    $new_status = 'CATEGORY_FORMS_REQUIRED';
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
    $update_stmt->bind_param("sss", $new_status, $review_type, $queue_number);
    
    $start_time = microtime(true);
    $result = $update_stmt->execute();
    $end_time = microtime(true);
    
    if ($result) {
        echo "<p>✅ Status update successful in " . number_format($end_time - $start_time, 3) . " seconds</p>";
        echo "<p>New status: $new_status</p>";
    } else {
        echo "<p style='color: red;'>❌ Status update failed</p>";
        echo "<p>Error: " . $conn->error . "</p>";
    }
    
    echo "<h4>Step 4: Check Final Status</h4>";
    $check_stmt = $conn->prepare("SELECT current_status, category FROM applications WHERE queue_number = ?");
    $check_stmt->bind_param("s", $queue_number);
    $check_stmt->execute();
    $final_status = $check_stmt->get_result()->fetch_assoc();
    
    echo "<p>Final Status: " . $final_status['current_status'] . "</p>";
    echo "<p>Category: " . $final_status['category'] . "</p>";
    
    closeDBConnection($conn);
    
    echo "<h3>✅ Simple Test Completed</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during simple test</h3>";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack Trace:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

?>
