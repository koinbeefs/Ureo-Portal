<!DOCTYPE html>
<html>
<head>
    <title>Approval Process Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .log { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>🔍 Approval Process Debug Log</h1>
    
    <?php
    function log_msg($msg, $type = 'info') {
        $class = $type;
        echo "<div class='log $class'>" . htmlspecialchars($msg) . "</div>";
    }
    
    log_msg("Starting approval process debug...", 'info');
    
    try {
        // Start session
        session_start();
        $_SESSION['user_id'] = 1;
        log_msg("Session started with user_id: " . $_SESSION['user_id'], 'success');
        
        // Load required files
        require_once 'config/config.php';
        require_once 'includes/functions.php';
        require_once 'includes/email-template-functions.php';
        log_msg("Required files loaded successfully", 'success');
        
        // Simulate POST data
        $_POST['queue_number'] = 'UREO-0001';
        $_POST['review_type'] = 'expedited';
        
        $queue_number = $_POST['queue_number'];
        $review_type = $_POST['review_type'];
        
        log_msg("Queue Number: $queue_number", 'info');
        log_msg("Review Type: $review_type", 'info');
        
        // Validate review type
        $valid_review_types = ['exempt', 'expedited', 'full'];
        if (!in_array($review_type, $valid_review_types)) {
            throw new Exception("Invalid review type");
        }
        log_msg("Review type validation: PASSED", 'success');
        
        // Database connection
        $conn = getDBConnection();
        log_msg("Database connection: ESTABLISHED", 'success');
        
        // Get application
        $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
        $app_stmt->bind_param("s", $queue_number);
        $app_stmt->execute();
        $application = $app_stmt->get_result()->fetch_assoc();
        
        if (!$application) {
            throw new Exception("Application not found");
        }
        log_msg("Application found: " . $application['applicant_name'], 'success');
        log_msg("Current status: " . $application['current_status'], 'info');
        
        // Check valid status
        $valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
        if (!in_array($application['current_status'], $valid_statuses)) {
            throw new Exception("Invalid status: " . $application['current_status']);
        }
        log_msg("Status validation: PASSED", 'success');
        
        // Determine new status
        $new_status = ($review_type === 'exempt') ? 'UREC_REVIEW_REQUIRED' : 'CATEGORY_FORMS_REQUIRED';
        log_msg("New status will be: $new_status", 'info');
        
        // Update application
        $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
        $update_stmt->bind_param("sss", $new_status, $review_type, $queue_number);
        $update_result = $update_stmt->execute();
        log_msg("Application update: " . ($update_result ? "SUCCESS" : "FAILED"), $update_result ? 'success' : 'error');
        
        // Add status history
        $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $notes = "Application approved for " . ucfirst($review_type) . " Review";
        $changed_by_type = 'staff';
        $history_stmt->bind_param("ssssss", $queue_number, $application['current_status'], $new_status, $_SESSION['user_id'], $changed_by_type, $notes);
        $history_result = $history_stmt->execute();
        log_msg("Status history insert: " . ($history_result ? "SUCCESS" : "FAILED"), $history_result ? 'success' : 'error');
        
        if (!$history_result) {
            log_msg("History error: " . $history_stmt->error, 'error');
        }
        
        // Category forms workflow (if not exempt)
        if ($review_type !== 'exempt') {
            log_msg("Starting category forms workflow...", 'info');
            
            // Get QF02 data
            $form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
            $form_stmt->bind_param("s", $queue_number);
            $form_stmt->execute();
            $qf02_data = $form_stmt->get_result()->fetch_assoc();
            
            if (!$qf02_data) {
                log_msg("WARNING: QF02 form data not found", 'error');
            } else {
                log_msg("QF02 form data: FOUND", 'success');
                
                // Create category form
                $category_form_data = [
                    'category' => 'human',
                    'review_type' => $review_type,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $form_data_json = json_encode($category_form_data);
                $insert_stmt = $conn->prepare("INSERT INTO category_forms (queue_number, category, review_type, form_data) VALUES (?, 'human', ?, ?)");
                $insert_stmt->bind_param("sss", $queue_number, $review_type, $form_data_json);
                $insert_result = $insert_stmt->execute();
                log_msg("Category form insert: " . ($insert_result ? "SUCCESS" : "FAILED"), $insert_result ? 'success' : 'error');
                
                if ($insert_result) {
                    log_msg("Category form data: " . json_encode($category_form_data), 'info');
                }
            }
        }
        
        closeDBConnection($conn);
        log_msg("Database connection: CLOSED", 'success');
        log_msg("Approval process: COMPLETED SUCCESSFULLY", 'success');
        
    } catch (Exception $e) {
        log_msg("FATAL ERROR: " . $e->getMessage(), 'error');
        log_msg("Error on line: " . $e->getLine(), 'error');
    }
    ?>
    
    <h2>📋 Summary</h2>
    <p>This debug shows exactly what happens during the approval process. Check for any ERROR messages above.</p>
    
</body>
</html>
