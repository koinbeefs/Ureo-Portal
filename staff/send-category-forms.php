<?php
/**
 * Send Category Forms to Applicant
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit();
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit();
}

// Get category form data
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_form'");
$form_stmt->bind_param("s", $queue_number);
$form_stmt->execute();
$category_form_result = $form_stmt->get_result()->fetch_assoc();

if (!$category_form_result) {
    echo json_encode(['success' => false, 'message' => 'Category form data not found.']);
    exit();
}

$category_form_data = json_decode($category_form_result['form_data'], true);
$category = $category_form_data['category'] ?? 'human';
$review_type = $category_form_data['review_type'] ?? 'expedited';

try {
    // Generate unique token for category form access
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Store token in fillable_forms table as category_token type
    $token_data = [
        'token' => $token,
        'expires_at' => $expires_at,
        'category' => $category,
        'review_type' => $review_type
    ];
    
    $token_stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data, completed_at) VALUES (?, 'category_token', ?, NULL)");
    $token_stmt->bind_param("ss", $queue_number, json_encode($token_data));
    $token_stmt->execute();

    // Generate category form URL
    $form_url = $base_url . 'applicant/category-form.php?queue=' . urlencode($queue_number) . '&token=' . urlencode($token);
    
    // Send email to applicant with category form link
    $template_code = 'CATEGORY_FORMS_LINK';
    $subject = "Complete Your " . ucfirst($category) . " Category Forms - " . $queue_number;
    $body = getEmailTemplate($template_code);

    if ($body) {
        $body = str_replace('{{applicant_name}}', $application['applicant_name'], $body);
        $body = str_replace('{{queue_number}}', $queue_number);
        $body = str_replace('{{category}}', ucfirst($category), $body);
        $body = str_replace('{{review_type}}', ucfirst($review_type), $body);
        $body = str_replace('{{form_url}}', $form_url, $body);
        $body = str_replace('{{expires_at}}', date('F j, Y, g:i A', strtotime($expires_at)));
        
        $email_sent = sendEmail($application['applicant_email'], $subject, $body, $queue_number, 'category_forms_link');
        
        if ($email_sent) {
            // Log email sent
            logStaffActivity($_SESSION['user_id'], $queue_number, 'sent_category_forms', "Category forms link sent to applicant for " . ucfirst($category));
            
            // Update category form data with token info
            $updated_form_data = array_merge($category_form_data, [
                'token_sent' => true,
                'token_sent_at' => date('Y-m-d H:i:s'),
                'form_url' => $form_url,
                'expires_at' => $expires_at
            ]);
            
            $update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'category_form'");
            $update_stmt->bind_param("ss", json_encode($updated_form_data), $queue_number);
            $update_stmt->execute();
            
            closeDBConnection($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category forms link sent to applicant successfully.',
                'form_url' => $form_url,
                'expires_at' => $expires_at
            ]);
        } else {
            throw new Exception("Failed to send email");
        }
    } else {
        throw new Exception("Email template not found");
    }

} catch (Exception $e) {
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'message' => 'Error sending category forms: ' . $e->getMessage()]);
}

exit();
?>
