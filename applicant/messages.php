<?php
/**
 * Messages
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

// Get messages
$msg_stmt = $conn->prepare("SELECT m.*, u.full_name as staff_name FROM messages m LEFT JOIN users u ON m.sender_id = u.user_id WHERE m.queue_number = ? ORDER BY sent_at DESC");
$msg_stmt->bind_param("s", $queue_number);
$msg_stmt->execute();
$messages = $msg_stmt->get_result();

closeDBConnection($conn);

$page_title = 'Messages';
$base_url = '../';
$active_menu = 'messages';
include '../includes/auth_header.php';
?>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-chat-dots"></i> Messages
    </h2>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="messages-container mb-4" style="max-height: 600px; overflow-y: auto;">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-item mb-3 p-3 border-start border-4 <?php echo $msg['sender_type'] === 'staff' ? 'border-primary bg-light' : 'border-success'; ?>" style="border-radius: 0.5rem;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong style="color: <?php echo $msg['sender_type'] === 'staff' ? '#006400' : '#228B22'; ?>;">
                                        <i class="bi bi-<?php echo $msg['sender_type'] === 'staff' ? 'person-badge' : 'person'; ?>"></i>
                                        <?php echo $msg['sender_type'] === 'staff' ? htmlspecialchars($msg['staff_name']) : 'You'; ?>
                                    </strong>
                                    <small class="text-muted ms-2">
                                        <i class="bi bi-clock"></i> 
                                        <?php echo date('M d, Y g:i A', strtotime($msg['sent_at'])); ?>
                                    </small>
                                </div>
                                <?php if ($msg['read_status'] == 0 && $msg['sender_type'] === 'staff'): ?>
                                    <span class="badge bg-danger">New</span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-0" style="color: #333333;">
                                <?php echo nl2br(htmlspecialchars($msg['message_content'] ?? '')); ?>
                            </p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-dots display-4"></i>
                        <p class="mt-3">No messages yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Send Message Form -->
            <div class="border-top pt-4">
                <h5 style="color: #006400;">Send a Message</h5>
                <form method="POST" action="send-message.php">
                    <div class="mb-3">
                        <label for="message" class="form-label">Your Message</label>
                        <textarea class="form-control" name="message" id="message" rows="4" placeholder="Type your message here..." required></textarea>
                    </div>
                    <button class="btn text-white" type="submit" style="background: linear-gradient(135deg, #006400, #228B22); border: none; font-weight: 600;">
                        <i class="bi bi-send"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/auth_footer.php'; ?>
