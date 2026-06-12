<?php
declare(strict_types=1);
/**
 * UREC Chairperson Assignment Management
 * TAU-UREO Portal
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

if (!$is_chairperson) {
    header("Location: ../dashboard.php");
    exit();
}

$conn = getDBConnection();

// Fetch applications needing assignment in this committee
$query = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count
    FROM applications a
    WHERE a.urec_committee_id = ? 
    AND (a.current_status = ? OR a.current_status = ?)
    AND a.urec_reviewed_by IS NULL
    ORDER BY a.submission_timestamp ASC
";
$stmt = $conn->prepare($query);
$f_status = STATUS_FORWARDED_TO_UREC;
$a_status = STATUS_ASSIGNING_UREC_EVALUATOR;
$stmt->bind_param("iss", $committee_id, $f_status, $a_status);
$stmt->execute();
$pending_applications = $stmt->get_result();

// Fetch committee members for the dropdown
$mem_stmt = $conn->prepare("SELECT user_id, full_name, committee_designation FROM users WHERE committee_id = ? AND active_status = 1");
$mem_stmt->bind_param("i", $committee_id);
$mem_stmt->execute();
$members_list = $mem_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);

$page_title = 'Assign Applications';
$active_menu = 'assignments';

// Adjusting includes path since we are in a subfolder
require_once __DIR__ . '/../../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-person-plus-fill me-2 text-primary"></i> Chairperson Assignment</h2>
            <p class="text-muted small mb-0">Manage and assign applications to members of your ethical committee.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $pending_applications->num_rows; ?> Pending Assignments</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 20px;">
        <div class="card-body p-0">
            <?php if ($pending_applications->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">Ref. No.</th>
                                <th>Research Title</th>
                                <th>Applicant</th>
                                <th>Submitted</th>
                                <th>Assignment</th>
                                <th class="pe-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $pending_applications->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($app['queue_number']); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                            <?php echo htmlspecialchars($app['research_title']); ?>
                                        </div>
                                    </td>
                                    <td><div class="small"><?php echo htmlspecialchars($app['applicant_name']); ?></div></td>
                                    <td><small class="text-muted"><?php echo formatDate($app['submission_timestamp']); ?></small></td>
                                    <td>
                                        <button class="btn btn-sm btn-blue" data-bs-toggle="modal" data-bs-target="#assignModal" 
                                                data-queue="<?php echo htmlspecialchars($app['queue_number']); ?>"
                                                data-title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                            <i class="bi bi-people-fill me-1"></i>Assign Evaluators
                                        </button>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <a href="../view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="btn btn-sm btn-light border me-1">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($app['urec_reviewed_by']): ?>
                                        <a href="reset-evaluator-assignment.php?queue=<?php echo urlencode($app['queue_number']); ?>" 
                                           class="btn btn-sm btn-warning border" 
                                           title="Reset evaluator assignment">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-all text-success display-1 mb-4"></i>
                    <h4 class="fw-bold">All caught up!</h4>
                    <p class="text-muted">There are no applications waiting for assignment in your committee.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Multiple Evaluator Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-people-fill me-2 text-primary"></i>
                    <span id="modalTitle">Assign Evaluators</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="closeModal()"></button>
            </div>
            <form action="../process-action.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="queue_number" id="modalQueueNumber">
                    <input type="hidden" name="action" value="assign_evaluators">
                    
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-3">Application Details</h6>
                        <div class="bg-light rounded p-3">
                            <p class="mb-1"><strong>Queue Number:</strong> <span id="modalQueue"></span></p>
                            <p class="mb-0"><strong>Research Title:</strong> <span id="modalResearchTitle"></span></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-3">Select Evaluators <span class="text-danger">*</span></h6>
                        <div class="row" id="evaluatorCheckboxes">
                            <?php foreach ($members_list as $member): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="evaluator_ids[]" 
                                               value="<?php echo $member['user_id']; ?>" id="eval_<?php echo $member['user_id']; ?>">
                                        <label class="form-check-label" for="eval_<?php echo $member['user_id']; ?>">
                                            <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                            <small class="text-muted d-block">(<?php echo htmlspecialchars($member['committee_designation']); ?>)</small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Select one or more evaluators for this application. The first selected evaluator will be marked as primary.</small>
                    </div>
                </div>
                
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>Assign Evaluators
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Close modal function
function closeModal() {
    const modal = document.getElementById('assignModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.remove();
    }
}

// Initialize Bootstrap modal
document.addEventListener('DOMContentLoaded', function() {
    // Check if Bootstrap modal is available
    if (typeof bootstrap !== 'undefined') {
        const assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
        
        document.getElementById('assignModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const queueNumber = button.getAttribute('data-queue');
            const researchTitle = button.getAttribute('data-title');
            
            document.getElementById('modalQueueNumber').value = queueNumber;
            document.getElementById('modalQueue').textContent = queueNumber;
            document.getElementById('modalResearchTitle').textContent = researchTitle;
            
            // Clear all checkboxes when modal opens
            document.querySelectorAll('input[name="evaluator_ids[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    } else {
        // Fallback for manual modal handling
        console.log('Bootstrap not loaded, using fallback modal handling');
        document.addEventListener('click', function(e) {
            if (e.target.matches('[data-bs-toggle="modal"]')) {
                e.preventDefault();
                const button = e.target;
                const queueNumber = button.getAttribute('data-queue');
                const researchTitle = button.getAttribute('data-title');
                
                document.getElementById('modalQueueNumber').value = queueNumber;
                document.getElementById('modalQueue').textContent = queueNumber;
                document.getElementById('modalResearchTitle').textContent = researchTitle;
                
                // Clear all checkboxes
                document.querySelectorAll('input[name="evaluator_ids[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Show modal manually
                document.getElementById('assignModal').style.display = 'block';
                document.getElementById('assignModal').classList.add('show');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes_urec/footer.php'; ?>
