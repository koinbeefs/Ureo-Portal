<?php
/**
 * Application History
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireApplicantLogin();
checkSessionTimeout();

// Ensure session has queue_number
if (!isset($_SESSION['queue_number']) || empty($_SESSION['queue_number'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$queue_number = $_SESSION['queue_number'];
$conn = getDBConnection();

// Get application details
$stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    session_destroy();
    closeDBConnection($conn);
    header("Location: login.php?error=application_not_found");
    exit();
}

// Get status history
$history_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp DESC");
$history_stmt->bind_param("s", $queue_number);
$history_stmt->execute();
$status_history = $history_stmt->get_result();

closeDBConnection($conn);

$page_title = 'History';
$base_url = '../';
$active_menu = 'history';
include '../includes/auth_header.php';
?>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-clock-history"></i> Application History
    </h2>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="status-timeline">
                <?php if ($status_history->num_rows > 0): ?>
                    <?php 
                    $count = 0;
                    while ($history = $status_history->fetch_assoc()): 
                        $count++;
                        $is_current = $history['new_status'] === $application['current_status'];
                    ?>
                        <div class="timeline-item position-relative ps-5 pb-4 <?php echo $count === 1 ? '' : 'border-start'; ?>" style="<?php echo $count > 1 ? 'border-color: #006400 !important; border-width: 2px !important;' : ''; ?>">
                            <div class="timeline-marker position-absolute start-0 translate-middle-x rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 40px; height: 40px; background: <?php echo $is_current ? 'linear-gradient(135deg, #006400, #228B22)' : '#F8F8F8'; ?>; border: 3px solid #006400; top: 0;">
                                <i class="bi bi-<?php echo $is_current ? 'arrow-right-circle-fill' : 'check-circle-fill'; ?>" style="color: <?php echo $is_current ? 'white' : '#006400'; ?>; font-size: 1.2rem;"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="mb-0" style="color: #006400; font-weight: 600;">
                                                <?php echo getStatusDisplayName($history['new_status']); ?>
                                                <?php if ($is_current): ?>
                                                    <span class="badge bg-success ms-2">Current Status</span>
                                                <?php endif; ?>
                                            </h5>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i>
                                                <?php echo date('M d, Y', strtotime($history['timestamp'])); ?>
                                                <br>
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('g:i A', strtotime($history['timestamp'])); ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($history['changed_by_type']): ?>
                                            <p class="mb-2 text-muted small">
                                                <i class="bi bi-person"></i>
                                                Updated by: <strong><?php echo ucfirst($history['changed_by_type']); ?></strong>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($history['notes']): ?>
                                            <div class="alert alert-info mb-0 mt-2">
                                                <i class="bi bi-info-circle"></i>
                                                <strong>Notes:</strong>
                                                <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($history['notes'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-clock-history display-4"></i>
                        <p class="mt-3">No history available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/auth_footer.php'; ?>
