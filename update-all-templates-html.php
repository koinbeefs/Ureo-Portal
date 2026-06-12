<?php
/**
 * Update All Email Templates with HTML Formatting
 * TAU-UREO Portal
 */

require_once 'config/config.php';

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    die("❌ Database connection failed!\n");
}

echo "🔄 Updating all email templates with proper HTML formatting...\n\n";

// Array of templates to update with their HTML-formatted bodies
$templates = [
    'ACK_COMPLETE' => '<p>Greetings from TAU-REO!</p>

<p>We acknowledge with appreciation the receipt of your complete documents. Your submission will now undergo evaluation to determine the appropriate Research Ethics Review Category.</p>

<p>Kindly wait for further updates regarding the review process. Should you have any questions, feel free to reach out.</p>

<p>Thank you.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'INCOMPLETE_DOCS' => '<p>Good day!</p>

<p>We have reviewed your submission for ethics review, and we have identified missing/incomplete documents required for processing your application. Kindly provide the following documents at your earliest convenience:</p>

<p>{{missing_documents}}</p>

<p>Thank you very much.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'MISSING_SIGNATURES' => '<p>Good day!</p>

<p>We have reviewed your submission for ethics review and found that some required signatures are missing. To proceed with the review, kindly ensure that the following documents are duly signed:</p>

<p>{{unsigned_documents}}</p>

<p>Thank you very much.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'APPROVED' => '<p>Dear {{applicant_name}},</p>

<p>We are pleased to inform you that your research ethics application <strong>({{queue_number}})</strong> has been <strong>APPROVED</strong>.</p>

<p>
<strong>Category:</strong> {{category}}<br>
<strong>Approval Date:</strong> {{approval_date}}
</p>

<p>You may now proceed with your research as outlined in your approved proposal. Please ensure that you adhere to all ethical guidelines and protocols throughout your study.</p>

<p>Should you need any certificates or have any questions, please feel free to contact us.</p>

<p><strong>Congratulations!</strong></p>

<p>--Best regards,<br>
TAU-REO</p>',

    'CONDITIONAL_APPROVAL' => '<p>Dear {{applicant_name}},</p>

<p>Your research ethics application <strong>({{queue_number}})</strong> has been given <strong>CONDITIONAL APPROVAL</strong>.</p>

<p><strong>Category:</strong> {{category}}</p>

<p>The following conditions must be addressed before final approval:</p>

<p>{{conditions}}</p>

<p>Please submit the required revisions at your earliest convenience. Once all conditions are satisfactorily met, final approval will be granted.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'REJECTED' => '<p>Dear {{applicant_name}},</p>

<p>We regret to inform you that your research ethics application <strong>({{queue_number}})</strong> has <strong>NOT been approved</strong> at this time.</p>

<p><strong>Reason:</strong></p>
<p>{{rejection_reason}}</p>

<p>You may resubmit your application after addressing the concerns raised. Should you need clarification or guidance, please feel free to contact our office.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'REVISIONS_NEEDED' => '<p>Dear {{applicant_name}},</p>

<p>Thank you for your submission <strong>({{queue_number}})</strong>. After careful review, we require some revisions to your application before we can proceed with the evaluation.</p>

<p><strong>Required revisions:</strong></p>
<p>{{revisions_list}}</p>

<p>Please resubmit your revised documents at your earliest convenience. If you have any questions about the required changes, please do not hesitate to contact us.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'CERTIFICATE_ISSUED' => '<p>Dear {{applicant_name}},</p>

<p>Your <strong>Ethics Clearance Certificate</strong> has been issued for your approved research project.</p>

<p>
<strong>Certificate Number:</strong> {{certificate_number}}<br>
<strong>Valid Until:</strong> {{valid_until}}<br>
<strong>Queue Number:</strong> {{queue_number}}
</p>

<p>Please find the certificate attached to this email. Keep this certificate for your records and present it when required.</p>

<p>--Best regards,<br>
TAU-REO</p>',

    'GENERAL_UPDATE' => '<p>Dear {{applicant_name}},</p>

<p>We would like to provide you with an update regarding your application <strong>({{queue_number}})</strong>.</p>

<p>{{message_content}}</p>

<p>If you have any questions or concerns, please feel free to reach out to us.</p>

<p>--Best regards,<br>
TAU-REO</p>'
];

// Update each template
$updated_count = 0;
$failed = [];

foreach ($templates as $template_code => $html_body) {
    $stmt = $conn->prepare("UPDATE email_templates SET body = ? WHERE template_code = ?");
    $stmt->bind_param("ss", $html_body, $template_code);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "✅ Updated: $template_code\n";
            $updated_count++;
        } else {
            echo "⚠️  No changes for: $template_code (already up to date or not found)\n";
        }
    } else {
        echo "❌ Failed to update: $template_code - " . $stmt->error . "\n";
        $failed[] = $template_code;
    }
    
    $stmt->close();
}

$conn->close();

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 Update Summary:\n";
echo "   Templates updated: $updated_count\n";
if (count($failed) > 0) {
    echo "   Failed updates: " . count($failed) . " (" . implode(", ", $failed) . ")\n";
}
echo str_repeat("=", 60) . "\n";

if ($updated_count > 0) {
    echo "\n✅ All email templates now have proper HTML formatting!\n";
    echo "   Each template includes:\n";
    echo "   • <p> tags for paragraphs\n";
    echo "   • <strong> tags for bold emphasis\n";
    echo "   • <br> tags for line breaks\n";
    echo "   • Consistent greeting and signature format\n";
} else {
    echo "\n⚠️  No templates were updated. They may already have HTML formatting.\n";
}
?>
