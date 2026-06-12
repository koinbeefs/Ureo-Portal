-- Email Templates Table
-- TAU-UREO Portal
-- Version 1.0

CREATE TABLE IF NOT EXISTS email_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(50) UNIQUE NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    placeholders TEXT COMMENT 'JSON array of available placeholders',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (template_code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default email templates
INSERT INTO email_templates (template_code, template_name, subject, body, description, category, placeholders) VALUES
('REPLY_INTENT', 'Reply to Letter of Intent', 'TAU-REO: Application Requirements', 
'<p>Greetings from TAU-REO!</p>

<p>Please read the general guidelines for research ethics review and evaluation. Then, kindly accomplish the following documents:</p>
<p>
✓&nbsp;&nbsp;&nbsp;Application form TAU-REO-QF-01 <em>(see attached file)</em><br>
✓&nbsp;&nbsp;&nbsp;Research Ethics Review Category Form TAU-REO-QF-02 <em>(see attached file)</em><br>
✓&nbsp;&nbsp;&nbsp;CV of proponents<br>
✓&nbsp;&nbsp;&nbsp;Research proposal/Thesis/Dissertation Outline
</p>

<p>Send the digital copy of the fully accomplished documents through this email. <strong>As you reply to this email with the requirements, please CC your adviser.</strong> Thank you.</p>

<p>Please take note of the following:</p>
<p>
<em>*Do not change the format and font style of the ISO registered forms</em><br>
<em>*Do not make another email thread to send your requirements. Only reply to this email thread for easy tracking and to avoid confusion on our part.</em><br>
<em>*Please be guided with our process cycle time (see the General Guidelines for more info). We process applications during working days only.</em>
</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Initial response to letter of intent submission', 'review_process', '["{{applicant_name}}", "{{queue_number}}"]'),

('ACK_COMPLETE', 'Acknowledgment of Complete Submission', 'TAU-REO: Documents Received', 
'<p>Greetings from TAU-REO!</p>

<p>We acknowledge with appreciation the receipt of your complete documents. Your submission will now undergo evaluation to determine the appropriate Research Ethics Review Category.</p>

<p>Kindly wait for further updates regarding the review process. Should you have any questions, feel free to reach out.</p>

<p>Thank you.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Acknowledgment when all required documents are submitted', 'review_process', '["{{applicant_name}}", "{{queue_number}}"]'),

('INCOMPLETE_DOCS', 'Incomplete Documents Notice', 'TAU-REO: Incomplete Submission', 
'<p>Good day!</p>

<p>We have reviewed your submission for ethics review, and we have identified missing/incomplete documents required for processing your application. Kindly provide the following documents at your earliest convenience:</p>

<p>{{missing_documents}}</p>

<p>Thank you very much.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Notification when documents are missing or incomplete', 'review_process', '["{{applicant_name}}", "{{queue_number}}", "{{missing_documents}}"]'),

('MISSING_SIGNATURES', 'Missing Signatures Notice', 'TAU-REO: Missing Signatures', 
'<p>Good day!</p>

<p>We have reviewed your submission for ethics review and found that some required signatures are missing. To proceed with the review, kindly ensure that the following documents are duly signed:</p>

<p>{{unsigned_documents}}</p>

<p>Thank you very much.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Notification when required signatures are missing', 'review_process', '["{{applicant_name}}", "{{queue_number}}", "{{unsigned_documents}}"]'),

('APPROVED', 'Application Approved', 'TAU-REO: Application Approved', 
'<p>Dear {{applicant_name}},</p>

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
'Approval notification email', 'decision', '["{{applicant_name}}", "{{queue_number}}", "{{category}}", "{{approval_date}}"]'),

('CONDITIONAL_APPROVAL', 'Conditional Approval', 'TAU-REO: Conditional Approval', 
'<p>Dear {{applicant_name}},</p>

<p>Your research ethics application <strong>({{queue_number}})</strong> has been given <strong>CONDITIONAL APPROVAL</strong>.</p>

<p><strong>Category:</strong> {{category}}</p>

<p>The following conditions must be addressed before final approval:</p>

<p>{{conditions}}</p>

<p>Please submit the required revisions at your earliest convenience. Once all conditions are satisfactorily met, final approval will be granted.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Conditional approval with required modifications', 'decision', '["{{applicant_name}}", "{{queue_number}}", "{{category}}", "{{conditions}}"]'),

('REJECTED', 'Application Rejected', 'TAU-REO: Application Not Approved', 
'<p>Dear {{applicant_name}},</p>

<p>We regret to inform you that your research ethics application <strong>({{queue_number}})</strong> has <strong>NOT been approved</strong> at this time.</p>

<p><strong>Reason:</strong></p>
<p>{{rejection_reason}}</p>

<p>You may resubmit your application after addressing the concerns raised. Should you need clarification or guidance, please feel free to contact our office.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Rejection notification email', 'decision', '["{{applicant_name}}", "{{queue_number}}", "{{rejection_reason}}"]'),

('REVISIONS_NEEDED', 'Revisions Required', 'TAU-REO: Revisions Needed', 
'<p>Dear {{applicant_name}},</p>

<p>Thank you for your submission <strong>({{queue_number}})</strong>. After careful review, we require some revisions to your application before we can proceed with the evaluation.</p>

<p><strong>Required revisions:</strong></p>
<p>{{revisions_list}}</p>

<p>Please resubmit your revised documents at your earliest convenience. If you have any questions about the required changes, please do not hesitate to contact us.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Request for revisions or clarifications', 'review_process', '["{{applicant_name}}", "{{queue_number}}", "{{revisions_list}}"]'),

('CERTIFICATE_ISSUED', 'Certificate Issued', 'TAU-REO: Ethics Clearance Certificate', 
'<p>Dear {{applicant_name}},</p>

<p>Your <strong>Ethics Clearance Certificate</strong> has been issued for your approved research project.</p>

<p>
<strong>Certificate Number:</strong> {{certificate_number}}<br>
<strong>Valid Until:</strong> {{valid_until}}<br>
<strong>Queue Number:</strong> {{queue_number}}
</p>

<p>Please find the certificate attached to this email. Keep this certificate for your records and present it when required.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'Certificate issuance notification', 'certificate', '["{{applicant_name}}", "{{queue_number}}", "{{certificate_number}}", "{{valid_until}}"]'),

('GENERAL_UPDATE', 'General Update', 'TAU-REO: Application Update', 
'<p>Dear {{applicant_name}},</p>

<p>We would like to provide you with an update regarding your application <strong>({{queue_number}})</strong>.</p>

<p>{{message_content}}</p>

<p>If you have any questions or concerns, please feel free to reach out to us.</p>

<p>--Best regards,<br>
TAU-REO</p>', 
'General purpose update template', 'general', '["{{applicant_name}}", "{{queue_number}}", "{{message_content}}"]');
