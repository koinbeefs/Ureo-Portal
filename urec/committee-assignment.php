<?php
/**
 * Committee Assignment Module for Classification Chairperson
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
$app_sql = "SELECT a.*, ai.predicted_primary, ai.confidence_level, ai.staff_verified, ai.staff_feedback 
           FROM applications a 
           LEFT JOIN (
               SELECT queue_number, 
                      JSON_UNQUOTE(JSON_EXTRACT(ai_prediction, '$.predicted')) as predicted_primary,
                      JSON_UNQUOTE(JSON_EXTRACT(ai_prediction, '$.confidence')) as confidence_level,
                      staff_verified, staff_feedback
               FROM ai_classifications
           ) ai ON a.queue_number = ai.queue_number
           WHERE a.queue_number = ?";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    die("Application not found");
}

// Check if application is ready for committee assignment
$valid_statuses = [STATUS_UREC_REVIEW_REQUIRED];
if (!in_array($application['current_status'], $valid_statuses)) {
    die("Application is not ready for committee assignment");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $committee_id = $_POST['committee_id'] ?? '';
    $evaluator_ids = $_POST['evaluator_ids'] ?? [];
    $notes = $_POST['notes'] ?? '';
    
    if (empty($committee_id)) {
        $error = "Please select a committee";
    } elseif (empty($evaluator_ids)) {
        $error = "Please select at least one evaluator";
    } else {
        try {
            $conn->begin_transaction();
            
            // Update application with committee assignment
            $update_sql = "UPDATE applications SET urec_committee_id = ?, current_status = ?, last_updated = NOW() WHERE queue_number = ?";
            $stmt = $conn->prepare($update_sql);
            $new_status = STATUS_FORWARDED_TO_UREC;
            $stmt->bind_param("iss", $committee_id, $new_status, $queue_number);
            $stmt->execute();
            
            // Assign evaluators (use the first selected evaluator as primary for now, but store all assignments)
            $primary_evaluator = $evaluator_ids[0];
            $evaluator_update_sql = "UPDATE applications SET urec_reviewed_by = ? WHERE queue_number = ?";
            $evaluator_stmt = $conn->prepare($evaluator_update_sql);
            $evaluator_stmt->bind_param("is", $primary_evaluator, $queue_number);
            $evaluator_stmt->execute();
            
            // Log evaluator assignments
            foreach ($evaluator_ids as $evaluator_id) {
                $eval_log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'evaluator_assignment', ?)";
                $eval_log_stmt = $conn->prepare($eval_log_sql);
                $eval_details = "Assigned as evaluator (Primary: " . ($evaluator_id == $primary_evaluator ? 'Yes' : 'No') . ")";
                $eval_log_stmt->bind_param("iss", $evaluator_id, $queue_number, $eval_details);
                $eval_log_stmt->execute();
            }
            
            // Add to status history
            $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'staff', ?)";
            $history_stmt = $conn->prepare($history_sql);
            $evaluator_list = implode(', ', $evaluator_ids);
            $history_notes = "Assigned to Committee ID $committee_id with evaluators: $evaluator_list. " . $notes;
            $history_stmt->bind_param("sssis", $queue_number, $application['current_status'], $new_status, $staff_id, $history_notes);
            $history_stmt->execute();
            
            // Log activity
            logStaffActivity($staff_id, $queue_number, 'committee_assignment', "Assigned to Committee ID $committee_id with " . count($evaluator_ids) . " evaluators");
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Application successfully assigned to committee with " . count($evaluator_ids) . " evaluator(s) and forwarded.";
            header("Location: dashboard.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Committee Assignment';
require_once '../includes_urec/header.php';
?>

<div class="main-container">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Committee Assignment</h4>
                <p class="text-muted mb-0">Assign application to appropriate UREC committee</p>
            </div>
            <div>
                <span class="badge bg-primary"><?php echo htmlspecialchars($queue_number); ?></span>
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
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($application['category'] ?? 'Not categorized'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Current Status:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars($application['current_status']); ?></span></p>
                        <p><strong>Submitted:</strong> <?php echo formatDate($application['submission_timestamp']); ?></p>
                        <p><strong>AI Classification:</strong> <?php echo htmlspecialchars($application['predicted_primary'] ?? 'Not classified'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Classification Results -->
        <?php if ($application['predicted_primary']): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">AI Classification Results</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Predicted Category:</strong> <?php echo htmlspecialchars($application['predicted_primary']); ?></p>
                        <p><strong>Confidence Level:</strong> <span class="badge bg-<?php echo $application['confidence_level'] === 'high' ? 'success' : ($application['confidence_level'] === 'moderate' ? 'info' : 'warning'); ?>"><?php echo htmlspecialchars($application['confidence_level']); ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Staff Verified:</strong> <?php echo $application['staff_verified'] ? '✅ Yes' : '❌ No'; ?></p>
                        <?php if ($application['staff_feedback']): ?>
                            <p><strong>Final Category:</strong> <?php echo htmlspecialchars(json_decode($application['staff_feedback'], true)['final_category'] ?? 'N/A'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Committee Assignment Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-4">Assign Committee</h6>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="committee_id" class="form-label">Select UREC Committee <span class="text-danger">*</span></label>
                                <select class="form-select" id="committee_id" name="committee_id" required onchange="loadEvaluators(this.value)">
                                    <option value="">Choose a committee...</option>
                                    <option value="1">Committee 1 - Human Use</option>
                                    <option value="2">Committee 2 - Animal Welfare</option>
                                    <option value="3">Committee 3 - Plant Use</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="evaluator_ids" class="form-label">Select Evaluators <span class="text-danger">*</span></label>
                                <div id="evaluatorSelection">
                                    <p class="text-muted">Please select a committee first to load available evaluators.</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Assignment Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add notes about this committee assignment..."></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title small">Committee Guidelines</h6>
                                    <ul class="small mb-0">
                                        <li><strong>Human Use:</strong> Research involving human participants</li>
                                        <li><strong>Animal Welfare:</strong> Research involving animals</li>
                                        <li><strong>Plant Use:</strong> Research involving plants, crops, agriculture</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Assign & Forward to Committee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadEvaluators(committeeId) {
    const evaluatorDiv = document.getElementById('evaluatorSelection');
    
    if (!committeeId) {
        evaluatorDiv.innerHTML = '<p class="text-muted">Please select a committee first to load available evaluators.</p>';
        return;
    }
    
    // Show loading state
    evaluatorDiv.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm me-2"></div>Loading evaluators...</div>';
    
    // Fetch evaluators for the selected committee
    fetch(`get-evaluators.php?committee_id=${committeeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.evaluators.length > 0) {
                let html = '<div class="form-check-group">';
                data.evaluators.forEach(evaluator => {
                    html += `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="evaluator_ids[]" value="${evaluator.user_id}" id="eval_${evaluator.user_id}">
                            <label class="form-check-label" for="eval_${evaluator.user_id}">
                                <strong>${evaluator.full_name}</strong>
                                <span class="text-muted">(${evaluator.committee_designation})</span>
                            </label>
                        </div>
                    `;
                });
                html += '</div>';
                html += '<small class="text-muted">Select one or more evaluators for this application.</small>';
                evaluatorDiv.innerHTML = html;
            } else {
                evaluatorDiv.innerHTML = '<p class="text-warning">No evaluators available for this committee.</p>';
            }
        })
        .catch(error => {
            evaluatorDiv.innerHTML = '<p class="text-danger">Error loading evaluators. Please try again.</p>';
            console.error('Error:', error);
        });
}

// Pre-load evaluators if committee is already selected
document.addEventListener('DOMContentLoaded', function() {
    const committeeSelect = document.getElementById('committee_id');
    if (committeeSelect.value) {
        loadEvaluators(committeeSelect.value);
    }
});
</script>

<?php require_once '../includes_urec/footer.php'; ?>
