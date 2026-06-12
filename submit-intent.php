<?php
/**
 * Letter of Intent Submission
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/email-template-functions.php';

$success_message = '';
$error_message = '';
$queue_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicant_name = sanitizeInput($_POST['applicant_name']);
    $applicant_email = sanitizeInput($_POST['applicant_email']);
    $applicant_type = sanitizeInput($_POST['applicant_type']);
    $research_title = sanitizeInput($_POST['research_title']);
    
    if (!filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address.";
    } else {
        $conn = getDBConnection();
        
        // Generate queue number
        $queue_number = generateQueueNumber($conn);
        
        // Insert application
        $stmt = $conn->prepare("INSERT INTO applications (queue_number, applicant_name, applicant_email, applicant_type, research_title, current_status) VALUES (?, ?, ?, ?, ?, ?)");
        $status = STATUS_INTENT_RECEIVED;
        $stmt->bind_param("ssssss", $queue_number, $applicant_name, $applicant_email, $applicant_type, $research_title, $status);
        
        if ($stmt->execute()) {
            // Update status to requirements sent
            updateApplicationStatus($queue_number, STATUS_REQUIREMENTS_SENT, null, 'system', 'Automated requirements list sent');
            
            // Prepare placeholders for email template
            $placeholders = [
                'applicant_name' => $applicant_name,
                'queue_number' => $queue_number,
                'applicant_email' => $applicant_email,
                'research_title' => $research_title,
                'current_status' => STATUS_REQUIREMENTS_SENT,
                'submission_date' => date('F d, Y'),
                'category' => 'Pending',
                'assigned_staff' => 'Not assigned',
                'approval_date' => date('F d, Y'),
                'current_date' => date('F d, Y')
            ];
            
            // Get template and process it
            $template = getEmailTemplate('REPLY_INTENT');
            if ($template) {
                $email_subject = processEmailTemplate($template['subject'], $placeholders);
                $email_body = processEmailTemplate($template['body'], $placeholders);
            } else {
                // Fallback if template not found
                $email_subject = "TAU-REO: Application Requirements - Queue Number: $queue_number";
                $email_body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <p>Greetings from TAU-REO!</p>
                    <p>Dear $applicant_name,</p>
                    <p>Your queue number is: <strong>$queue_number</strong></p>
                    <p>Please check your Document Management page for the required documents.</p>
                    <p>--Best regards,<br>TAU-REO</p>
                </body>
                </html>
                ";
            }
            
            // Save message to system instead of sending email
            $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'acknowledgment', ?, ?)");
            $msg_stmt->bind_param("sss", $queue_number, $email_subject, $email_body);
            $msg_stmt->execute();
            
            // Store system documents (the 3 required files)
            $system_docs = [
                ['name' => 'General Guidelines', 'path' => 'assets/to_send/for_reply_to_letter_of_intent/General Guidelines.pdf', 'type' => 'guideline'],
                ['name' => 'TAU-REO-QF-01 Application Form', 'path' => 'assets/to_send/for_reply_to_letter_of_intent/TAU-REO-QF-01 Application for Research Ethics Review Form Rev01.docx', 'type' => 'template'],
                ['name' => 'TAU-REO-QF-02 Review Category', 'path' => 'assets/to_send/for_reply_to_letter_of_intent/TAU-REO-QF-02 Research Ethics Review Category.docx', 'type' => 'template']
            ];
            
            $doc_stmt = $conn->prepare("INSERT INTO system_documents (queue_number, document_name, document_path, document_type) VALUES (?, ?, ?, ?)");
            foreach ($system_docs as $doc) {
                $doc_stmt->bind_param("ssss", $queue_number, $doc['name'], $doc['path'], $doc['type']);
                $doc_stmt->execute();
            }
            
            // Update status to requirements pending
            updateApplicationStatus($queue_number, STATUS_REQUIREMENTS_PENDING);
            
            $success_message = "Your application has been submitted successfully!";
        } else {
            $error_message = "Error submitting application. Please try again.";
        }
        
        closeDBConnection($conn);
    }
}
?>
<?php
$page_title = 'Submit Letter of Intent';
$active_page = 'submit';
$base_url = './';
include 'includes/header.php';
?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($success_message): ?>
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h2 class="text-success mb-3"><?php echo $success_message; ?></h2>
                            <div class="alert alert-info">
                                <h4>Your Queue Number: <strong><?php echo $queue_number; ?></strong></h4>
                                <p class="mb-2">Your required documents and instructions are now available in your Document Management page.</p>
                                <p class="mb-0"><small><i class="bi bi-info-circle"></i> Login with your queue number to access the fillable forms and upload your documents.</small></p>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                <a href="applicant/login.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Login Now
                                </a>
                                <a href="track-application.php" class="btn btn-success btn-lg">
                                    <i class="bi bi-search"></i> Track Application
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-house"></i> Return Home
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h2 class="card-title mb-4">
                                <i class="bi bi-file-earmark-text"></i> Submit Letter of Intent
                            </h2>
                            
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-circle"></i> <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <p class="text-muted mb-4">
                                Please fill out the form below to start your research ethics application. 
                                You will receive a queue number via email upon submission.
                            </p>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="applicant_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="applicant_name" name="applicant_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="applicant_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="applicant_email" name="applicant_email" required>
                                    <div class="form-text">Your queue number and updates will be sent to this email.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="applicant_type" class="form-label">Applicant Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="applicant_type" name="applicant_type" required>
                                        <option value="">Select...</option>
                                        <option value="student">Student</option>
                                        <option value="faculty">Faculty</option>
                                        <option value="researcher">Researcher</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="research_title" class="form-label">Research Title <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="research_title" name="research_title" rows="3" required></textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> What happens next?</h6>
                                    <ul class="mb-0">
                                        <li>You'll receive a <strong>Queue Number</strong> via email</li>
                                        <li>A list of <strong>required documents</strong> will be provided</li>
                                        <li>Login using your queue number to upload documents</li>
                                        <li>Your application will be automatically reviewed</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-secondary btn-lg">
                                        <i class="bi bi-send"></i> Submit Letter of Intent
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
