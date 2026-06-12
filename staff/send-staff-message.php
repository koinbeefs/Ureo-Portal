<?php
/**
 * Send Staff Message
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';
$message = trim($_POST['message'] ?? '');
$staff_id = $_SESSION['user_id'];

if (empty($queue_number) || empty($message)) {
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=empty_message");
    exit();
}

$conn = getDBConnection();

try {
    // Insert message
    $stmt = $conn->prepare("INSERT INTO messages (queue_number, sender_type, sender_id, message_content, sent_at) VALUES (?, 'staff', ?, ?, NOW())");
    $stmt->bind_param("sis", $queue_number, $staff_id, $message);
    
    if ($stmt->execute()) {
        // Log activity
        logStaffActivity($staff_id, $queue_number, 'sent_reply', 'Sent message to applicant');
        
        header("Location: view-application.php?queue=" . urlencode($queue_number) . "&success=message_sent");
    } else {
        throw new Exception("Failed to send message");
    }
} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=send_failed");
} finally {
    closeDBConnection($conn);
}
exit();
?>
