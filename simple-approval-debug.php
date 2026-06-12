<?php
/**
 * Simple Approval Debug Log
 */

echo "<h2>🔍 Approval Process Debug</h2>";

// Start logging
$log = [];
$log[] = "=== APPROVAL DEBUG START ===";

// Simulate session
session_start();
$_SESSION['user_id'] = 1;

// Simulate POST data
$_POST['queue_number'] = 'UREO-0001';
$_POST['review_type'] = 'expedited';

$log[] = "POST queue_number: " . $_POST['queue_number'];
$log[] = "POST review_type: " . $_POST['review_type'];
$log[] = "Session user_id: " . $_SESSION['user_id'];

try {
    require_once 'config/config.php';
    require_once 'includes/functions.php';
    require_once 'includes/email-template-functions.php';
    
    $log[] = "Files loaded successfully";
    
    $queue_number = $_POST['queue_number'];
    $review_type = $_POST['review_type'];
    
    // Validate
    $valid_review_types = ['exempt', 'expedited', 'full'];
    if (!in_array($review_type, $valid_review_types)) {
        $log[] = "ERROR: Invalid review type";
        throw new Exception("Invalid review type");
    }
    $log[] = "Review type validation: PASSED";
    
    // Database connection
    $conn = getDBConnection();
    $log[] = "Database connection: SUCCESS";
    
    // Get application
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        $log[] = "ERROR: Application not found";
        throw new Exception("Application not found");
    }
    
    $log[] = "Application found: " . $application['applicant_name'];
    $log[] = "Current status: " . $application['current_status'];
    
    // Check valid status
    $valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
    if (!in_array($application['current_status'], $valid_statuses)) {
        $log[] = "ERROR: Invalid status - " . $application['current_status'];
        throw new Exception("Invalid application status");
    }
    $log[] = "Status validation: PASSED";
    
    // Determine new status
    $new_status = ($review_type === 'exempt') ? 'UREC_REVIEW_REQUIRED' : 'CATEGORY_FORMS_REQUIRED';
    $log[] = "New status will be: " . $new_status;
    
    // Update application
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
    $update_stmt->bind_param("sss", $new_status, $review_type, $queue_number);
    $update_result = $update_stmt->execute();
    $log[] = "Application update: " . ($update_result ? "SUCCESS" : "FAILED");
    
    // Add status history
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $notes = "Application approved for " . ucfirst($review_type) . " Review";
    $history_stmt->bind_param("ssssis", $queue_number, $application['current_status'], $new_status, $_SESSION['user_id'], 'staff', $notes);
    $history_result = $history_stmt->execute();
    $log[] = "Status history: " . ($history_result ? "SUCCESS" : "FAILED");
    
    if ($history_result) {
        $history_id = $conn->insert_id;
        $log[] = "History record ID: " . $history_id;
    }
    
    // Category forms workflow
    if ($review_type !== 'exempt') {
        $log[] = "Starting category forms workflow...";
        
        // Get QF02 data
        $form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
        $form_stmt->bind_param("s", $queue_number);
        $form_stmt->execute();
        $qf02_data = $form_stmt->get_result()->fetch_assoc();
        
        if (!$qf02_data) {
            $log[] = "WARNING: QF02 form data not found";
        } else {
            $log[] = "QF02 form data: FOUND";
            
            // Create category form
            $category_form_data = [
                'category' => 'human',
                'review_type' => $review_type,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $insert_stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data) VALUES (?, 'category_form', ?)");
            $insert_stmt->bind_param("ss", $queue_number, json_encode($category_form_data));
            $insert_result = $insert_stmt->execute();
            $log[] = "Category form insert: " . ($insert_result ? "SUCCESS" : "FAILED");
            
            if ($insert_result) {
                $form_id = $conn->insert_id;
                $log[] = "Category form ID: " . $form_id;
                $log[] = "Category form data: " . json_encode($category_form_data);
            }
        }
    }
    
    closeDBConnection($conn);
    $log[] = "Database connection: CLOSED";
    
    $log[] = "=== APPROVAL DEBUG END ===";
    
} catch (Exception $e) {
    $log[] = "FATAL ERROR: " . $e->getMessage();
    $log[] = "Error line: " . $e->getLine();
}

// Display log
echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace;'>";
foreach ($log as $entry) {
    echo htmlspecialchars($entry) . "<br>";
}
echo "</div>";

// Save log to file
file_put_contents('approval_debug_log.txt', implode("\n", $log));
echo "<p><strong>Log saved to: approval_debug_log.txt</strong></p>";

?>
