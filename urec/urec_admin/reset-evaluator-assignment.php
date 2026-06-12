<?php
/**
 * Reset Evaluator Assignment - UREC Admin
 * TAU-UREO Portal
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get queue number from URL
$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    header("Location: assign-application.php");
    exit();
}

// Check if user is chairperson (UREC header will handle basic auth)
$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

if (!$is_chairperson) {
    $_SESSION['error_message'] = "You must be a chairperson to reset evaluator assignments.";
    header("Location: ../dashboard.php");
    exit();
}

$conn = getDBConnection();

// Get application details
$app_sql = "SELECT * FROM applications WHERE queue_number = ? AND urec_committee_id = ?";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("si", $queue_number, $committee_id);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    $_SESSION['error_message'] = "Application not found or not assigned to your committee.";
    header("Location: assign-application.php");
    exit();
}

// Handle reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if ($confirm !== 'RESET') {
        $error = "Please type 'RESET' to confirm the action";
    } else {
        try {
            // Store current evaluator for logging
            $previous_evaluator = $application['urec_reviewed_by'];
            $previous_status = $application['current_status'];
            
            // Reset evaluator assignment from applications table
            $update_sql = "UPDATE applications SET urec_reviewed_by = NULL, urec_review_notes = NULL WHERE queue_number = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("s", $queue_number);
            $stmt->execute();
            
            // Change status back to assignment stage
            $status_update_sql = "UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = ?";
            $status_stmt = $conn->prepare($status_update_sql);
            $assign_status = STATUS_ASSIGNING_UREC_EVALUATOR;
            $status_stmt->bind_param("ss", $assign_status, $queue_number);
            $status_stmt->execute();
            
            // Add to status history
            $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'staff', ?)";
            $history_stmt = $conn->prepare($history_sql);
            $history_notes = "Reset evaluator assignment. Previous evaluator: $previous_evaluator. Reason: $reason";
            $history_stmt->bind_param("sssis", $queue_number, $previous_status, $assign_status, $user_id, $history_notes);
            $history_stmt->execute();
            
            // Log the reset action
            logStaffActivity($user_id, $queue_number, 'other', "Reset evaluator assignment. Previous evaluator: $previous_evaluator");
            
            $_SESSION['success_message'] = "All evaluator assignments have been reset successfully. Application is now available for reassignment.";
            header("Location: assign-application.php");
            exit();
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Reset Evaluator Assignment';
$active_menu = 'assignments';

require_once __DIR__ . '/../../includes_urec/header.php';
?>

<div class="main-content">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-arrow-clockwise text-warning me-2"></i>
                Reset Evaluator Assignment
            </h2>
            <p class="text-muted small mb-0">Clear current evaluator assignment for reassignment</p>
        </div>
        <div class="text-end">
            <span class="badge bg-warning rounded-pill px-3 py-2"><?php echo htmlspecialchars($queue_number); ?></span>
        </div>
    </div>

    <!-- Application Overview -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 20px;">
        <div class="card-body">
            <h6 class="card-title mb-4">Application Overview</h6>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Applicant:</strong> <?php echo htmlspecialchars($application['applicant_name']); ?></p>
                    <p><strong>Research Title:</strong> <?php echo htmlspecialchars($application['research_title']); ?></p>
                    <p><strong>Current Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($application['current_status']); ?></span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>UREC Committee:</strong> <?php echo htmlspecialchars($application['urec_committee_id']); ?></p>
                    <p><strong>Current Evaluator:</strong> 
                        <?php if ($application['urec_reviewed_by']): ?>
                            <span class="badge bg-info">ID: <?php echo $application['urec_reviewed_by']; ?></span>
                        <?php else: ?>
                            <span class="text-muted">No evaluator assigned</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Submitted:</strong> <?php echo formatDate($application['submission_timestamp']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Warning Section -->
    <div class="card border-0 border-danger shadow-sm mb-4" style="border-radius: 20px;">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <i class="bi bi-exclamation-triangle-fill text-danger display-6"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="card-title text-danger">⚠️ Important - This action cannot be undone</h6>
                    <p class="mb-3">Resetting the evaluator assignment will:</p>
                    <ul class="mb-3">
                        <li>Remove the current evaluator from this application</li>
                        <li>Clear any evaluator review notes</li>
                        <li>Change application status back to "Assignment Stage"</li>
                        <li>Allow reassignment to a different evaluator</li>
                        <li>Log this action in the application history</li>
                    </ul>
                    <p class="text-muted small">The application will appear again in the assignment list for reassignment to evaluators.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Form -->
    <div class="card border-0 shadow-sm" style="border-radius: 20px;">
        <div class="card-body">
            <h6 class="card-title mb-4">Confirm Evaluator Assignment Reset</h6>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Reset <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" required 
                                      placeholder="Please explain why you need to reset the evaluator assignment..."></textarea>
                            <small class="text-muted">This reason will be logged in the application history for audit purposes.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm" class="form-label">Type 'RESET' to confirm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="confirm" name="confirm" required 
                                   placeholder="Type RESET in all caps">
                            <small class="text-muted">This prevents accidental resets.</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title small">Next Steps After Reset</h6>
                                <ol class="small mb-0">
                                    <li>Application returns to assignment list</li>
                                    <li>Select new evaluator(s)</li>
                                    <li>Reassign to evaluator(s)</li>
                                    <li>Evaluation continues</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="assign-application.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Assignments
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Evaluator Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes_urec/footer.php'; ?>
