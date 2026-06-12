<?php
/**
 * Send Message Handler
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireApplicantLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $queue_number = $_SESSION['queue_number'];
    $message = sanitizeInput($_POST['message']);
    
    if (!empty($message)) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("INSERT INTO messages (queue_number, sender_type, message_content) VALUES (?, 'applicant', ?)");
        $stmt->bind_param("ss", $queue_number, $message);
        
        if ($stmt->execute()) {
            // Notify assigned staff
            $app_stmt = $conn->prepare("SELECT assigned_staff_id FROM applications WHERE queue_number = ?");
            $app_stmt->bind_param("s", $queue_number);
            $app_stmt->execute();
            $app_result = $app_stmt->get_result();
            
            if ($app_result->num_rows > 0) {
                $app = $app_result->fetch_assoc();
                if ($app['assigned_staff_id']) {
                    $staff_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
                    $staff_stmt->bind_param("i", $app['assigned_staff_id']);
                    $staff_stmt->execute();
                    $staff_result = $staff_stmt->get_result();
                    
                    if ($staff_result->num_rows > 0) {
                        $staff = $staff_result->fetch_assoc();
                        
                        $email_subject = "TAU-UREO - New Message from $queue_number";
                        $email_body = "
                        <html>
                        <body style='font-family: Arial, sans-serif;'>
                            <h2>New Message Received</h2>
                            <p>Dear " . htmlspecialchars($staff['full_name']) . ",</p>
                            <p>You have received a new message from application <strong>$queue_number</strong>:</p>
                            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #0d6efd; margin: 20px 0;'>
                                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                            </div>
                            <p><a href='" . BASE_URL . "staff/view-application.php?queue=$queue_number'>View in Staff Portal</a></p>
                        </body>
                        </html>
                        ";
                        sendEmail($staff['email'], $email_subject, $email_body, $queue_number);
                    }
                }
            }
        }
        
        closeDBConnection($conn);
        header("Location: dashboard.php?msg=sent#messages");
    } else {
        header("Location: dashboard.php?error=empty#messages");
    }
} else {
    header("Location: dashboard.php");
}
exit();
?>
