<?php
/**
 * Reset Evaluator Assignment
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

// Get application details
$app_sql = "SELECT * FROM applications WHERE queue_number = ?";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    die("Application not found");
}

// Handle reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if ($confirm !== 'RESET') {
        $error = "Please type 'RESET' to confirm the action";
    } else {
        try {
            $conn->begin_transaction();
            
            // Store current evaluator for logging
            $previous_evaluator = $application['urec_reviewed_by'];
            
            // Reset evaluator assignment
            $update_sql = "UPDATE applications SET urec_reviewed_by = NULL, urec_review_notes = NULL WHERE queue_number = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("s", $queue_number);
            $stmt->execute();
            
            // Add to status history
            $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'staff', ?)";
            $history_stmt = $conn->prepare($history_sql);
            $history_notes = "Reset evaluator assignment. Previous evaluator: $previous_evaluator. Reason: $reason";
            $history_stmt->bind_param("sssis", $queue_number, $application['current_status'], $application['current_status'], $staff_id, $history_notes);
            $history_stmt->execute();
            
            // Log the reset action
            logStaffActivity($staff_id, $queue_number, 'evaluator_reset', "Reset evaluator assignment. Previous evaluator: $previous_evaluator");
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Evaluator assignment has been reset successfully.";
            header("Location: view-application.php?queue=" . urlencode($queue_number));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Reset Evaluator Assignment';
require_once '../includes_urec/header.php';
?>

<div class="main-container">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Reset Evaluator Assignment</h4>
                <p class="text-muted mb-0">Clear current evaluator assignment for reassignment</p>
            </div>
            <div>
                <span class="badge bg-warning"><?php echo htmlspecialchars($queue_number); ?></span>
            </div>
        </div>
    </div>

    <div class="content-area">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-modern">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Application Overview -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Application Overview</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($application['applicant_name']); ?></p>
                        <p><strong>Research Title:</strong> <?php echo htmlspecialchars($application['research_title']); ?></p>
                        <p><strong>Current Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($application['current_status']); ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>UREC Committee:</strong> <?php echo htmlspecialchars($application['urec_committee_id'] ?? 'Not assigned'); ?></p>
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
        <div class="card border-0 border-danger shadow-sm mb-4">
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
                            <li>Allow reassignment to a different evaluator</li>
                            <li>Log this action in the application history</li>
                        </ul>
                        <p class="text-muted small">The application will remain in its current status and can be reassigned to evaluators through the normal committee assignment process.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reset Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-4">Confirm Evaluator Assignment Reset</h6>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for Reset <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" required placeholder="Please explain why you need to reset the evaluator assignment..."></textarea>
                                <small class="text-muted">This reason will be logged in the application history for audit purposes.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm" class="form-label">Type 'RESET' to confirm <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="confirm" name="confirm" required placeholder="Type RESET in all caps">
                                <small class="text-muted">This prevents accidental resets.</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title small">Next Steps After Reset</h6>
                                    <ol class="small mb-0">
                                        <li>Go to Committee Assignment page</li>
                                        <li>Select the same committee</li>
                                        <li>Choose new evaluator(s)</li>
                                        <li>Forward to committee</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view-application.php?queue=<?php echo urlencode($queue_number); ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Application
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset Evaluator Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes_urec/footer.php'; ?>
