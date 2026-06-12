<?php
declare(strict_types=1);
/**
 * UREC Request Revision
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check (handled by header, but we need variables here)
$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';

$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ? AND urec_reviewed_by = ?");
$app_stmt->bind_param("si", $queue_number, $user_id);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: dashboard.php?error=not_found");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $revision_notes = $_POST['revision_notes'] ?? '';
    $revision_deadline = $_POST['revision_deadline'] ?? '';
    
    if (empty($revision_notes)) {
        $error = "Please provide revision notes.";
    } elseif (empty($revision_deadline)) {
        $error = "Please specify a revision deadline.";
    } else {
        try {
            $conn->begin_transaction();
            
            // Update application status
            $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, urec_review_notes = ?, urec_revision_deadline = ?, urec_decision_date = NOW(), last_updated = NOW() WHERE queue_number = ?");
            $revision_status = STATUS_REVISION_REQUIRED;
            $update_stmt->bind_param("ssss", $revision_status, $revision_notes, $revision_deadline, $queue_number);
            $update_stmt->execute();
            
            // Log activity
            logStaffActivity($user_id, $queue_number, 'other', 'Requested revisions from applicant');
            
            // Add to status history
            $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, urec_committee_id) VALUES (?, ?, ?, ?, 'urec', ?, ?)");
            $history_notes = "Revisions requested: " . $revision_notes;
            $history_stmt->bind_param("sssis", $queue_number, $application['current_status'], $revision_status, $user_id, $history_notes, $committee_id);
            $history_stmt->execute();
            
            // Send email notification to applicant
            $applicant_subject = "Revisions Requested - " . $queue_number;
            $applicant_body = "Dear " . htmlspecialchars($application['applicant_name']) . ",\n\n";
            $applicant_body .= "The UREC committee has reviewed your application and requires revisions.\n\n";
            $applicant_body .= "Application Details:\n";
            $applicant_body .= "Queue Number: " . htmlspecialchars($queue_number) . "\n";
            $applicant_body .= "Review Committee: " . htmlspecialchars($committee_designation) . "\n";
            $applicant_body .= "Revision Deadline: " . htmlspecialchars($revision_deadline) . "\n\n";
            $applicant_body .= "Revision Notes:\n" . htmlspecialchars($revision_notes) . "\n\n";
            $applicant_body .= "Please log in to your portal to submit the required revisions.\n\n";
            $applicant_body .= "Best regards,\nTAU-UREO Portal";
            
            sendEmail($application['applicant_email'], $applicant_subject, $applicant_body, $queue_number, 'revisions_requested');
            
            // Create system message for applicant
            $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'revision', ?, ?)");
            $msg_subject = "Revisions Required - " . $queue_number;
            $msg_body = "Your application requires revisions before approval.\n\nDeadline: " . htmlspecialchars($revision_deadline) . "\n\nPlease review the revision notes and submit the required changes.";
            $msg_stmt->bind_param("sss", $queue_number, $msg_subject, $msg_body);
            $msg_stmt->execute();
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Revision request sent successfully.";
            header("Location: view-application.php?queue=" . urlencode($queue_number));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error processing revision request: " . $e->getMessage();
        }
    }
}

$page_title = 'Request Revisions';
$active_menu = 'review';

require_once __DIR__ . '/../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm" style="border-radius: 20px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="flex-grow-1">
                            <h2 class="fw-bold text-dark mb-1">
                                <i class="bi bi-pencil-square text-warning me-2"></i>Request Revisions
                            </h2>
                            <p class="text-muted small mb-0">
                                Application: <strong><?php echo htmlspecialchars($queue_number); ?></strong>
                            </p>
                        </div>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-modern">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Revision Notes</label>
                            <textarea name="revision_notes" class="form-control" rows="8" required
                                placeholder="Please specify the revisions required for this application. Be clear and specific about what needs to be addressed."><?php echo isset($_POST['revision_notes']) ? htmlspecialchars($_POST['revision_notes']) : ''; ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Revision Deadline</label>
                            <input type="date" name="revision_deadline" class="form-control" required
                                value="<?php echo isset($_POST['revision_deadline']) ? htmlspecialchars($_POST['revision_deadline']) : date('Y-m-d', strtotime('+2 weeks')); ?>"
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            <div class="form-text">Specify the deadline by which the applicant should submit revisions.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-send me-2"></i>Send Revision Request
                            </button>
                            <a href="view-application.php?queue=<?php echo urlencode($queue_number); ?>" class="btn btn-light border">
                                <i class="bi bi-arrow-left me-2"></i>Back to Application
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes_urec/footer.php'; ?>
