<?php
/**
 * Certificate Generation System
 * TAU-UREO Portal
 * Generates ethical clearance certificates for approved applications
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Generate Certificate of Ethical Clearance
 */
function generateCertificate($queue_number) {
    $conn = getDBConnection();
    
    // Get application details
    $stmt = $conn->prepare("
        SELECT a.*, 
               u.full_name as assigned_staff_name
        FROM applications a
        LEFT JOIN users u ON a.assigned_staff_id = u.user_id
        WHERE a.queue_number = ? AND a.current_status = 'APPROVED'
    ");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        closeDBConnection($conn);
        return ['success' => false, 'message' => 'Application not found or not approved'];
    }
    
    // Check if certificate already exists
    $check_stmt = $conn->prepare("SELECT certificate_number FROM certificates WHERE queue_number = ?");
    $check_stmt->bind_param("s", $queue_number);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        closeDBConnection($conn);
        return [
            'success' => true,
            'certificate_number' => $existing['certificate_number'],
            'message' => 'Certificate already exists'
        ];
    }
    
    // Generate certificate number
    $year = date('Y');
    $count_stmt = $conn->query("SELECT COUNT(*) as count FROM certificates WHERE YEAR(issue_date) = $year");
    $count = $count_stmt->fetch_assoc()['count'] + 1;
    $certificate_number = sprintf("TAU-UREO-%s-%04d", $year, $count);
    
    // Calculate validity period (typically 1 year from approval)
    $approval_date = $application['last_updated'];
    $valid_from = date('Y-m-d', strtotime($approval_date));
    $valid_until = date('Y-m-d', strtotime($approval_date . ' +1 year'));
    
    // Insert certificate record
    $insert_stmt = $conn->prepare("
        INSERT INTO certificates (
            certificate_number, queue_number, issue_date, 
            valid_from, valid_until, issued_by, status
        ) VALUES (?, ?, NOW(), ?, ?, ?, 'active')
    ");
    $issued_by = $application['assigned_staff_id'] ?? null;
    $insert_stmt->bind_param("ssssi", 
        $certificate_number, 
        $queue_number, 
        $valid_from, 
        $valid_until,
        $issued_by
    );
    
    if ($insert_stmt->execute()) {
        // Update application status
        $update_stmt = $conn->prepare("
            UPDATE applications 
            SET current_status = 'CERTIFICATE_ISSUED', last_updated = NOW()
            WHERE queue_number = ?
        ");
        $update_stmt->bind_param("s", $queue_number);
        $update_stmt->execute();
        
        // Add to status history
        $history_stmt = $conn->prepare("
            INSERT INTO status_history (queue_number, status, notes, timestamp)
            VALUES (?, 'CERTIFICATE_ISSUED', ?, NOW())
        ");
        $notes = "Certificate of Ethical Clearance issued: " . $certificate_number;
        $history_stmt->bind_param("ss", $queue_number, $notes);
        $history_stmt->execute();
        
        // Generate PDF certificate
        $pdf_result = generateCertificatePDF($application, $certificate_number, $valid_from, $valid_until);
        
        // Send email to applicant
        sendCertificateEmail($application, $certificate_number, $pdf_result['file_path']);
        
        // Log activity
        logStaffActivity($issued_by, $queue_number, 'other', 
            "Certificate issued: " . $certificate_number);
        
        closeDBConnection($conn);
        
        return [
            'success' => true,
            'certificate_number' => $certificate_number,
            'pdf_path' => $pdf_result['file_path'],
            'message' => 'Certificate generated successfully'
        ];
    }
    
    closeDBConnection($conn);
    return ['success' => false, 'message' => 'Failed to generate certificate'];
}

/**
 * Generate PDF Certificate Document
 */
function generateCertificatePDF($application, $certificate_number, $valid_from, $valid_until) {
    // Certificate directory
    $cert_dir = __DIR__ . '/../uploads/certificates/';
    if (!file_exists($cert_dir)) {
        mkdir($cert_dir, 0755, true);
    }
    
    $filename = $certificate_number . '.pdf';
    $file_path = $cert_dir . $filename;
    
    // Generate HTML certificate
    $html = generateCertificateHTML($application, $certificate_number, $valid_from, $valid_until);
    
    // For now, save as HTML (can be upgraded to PDF using libraries like TCPDF or mPDF)
    file_put_contents($file_path . '.html', $html);
    
    return [
        'success' => true,
        'file_path' => $file_path . '.html',
        'filename' => $filename . '.html'
    ];
}

/**
 * Generate Certificate HTML
 */
function generateCertificateHTML($application, $certificate_number, $valid_from, $valid_until) {
    $issue_date = date('F d, Y');
    $valid_from_formatted = date('F d, Y', strtotime($valid_from));
    $valid_until_formatted = date('F d, Y', strtotime($valid_until));
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate of Ethical Clearance - {$certificate_number}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 40px;
            background: #ffffff;
        }
        .certificate {
            border: 15px solid #006400;
            padding: 40px;
            background: linear-gradient(to bottom, #ffffff 0%, #f8f8f8 100%);
            min-height: 90vh;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #228B22;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 48px;
            color: #006400;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .title {
            font-size: 32px;
            color: #006400;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 18px;
            color: #333333;
            margin-bottom: 10px;
        }
        .content {
            line-height: 1.8;
            font-size: 14px;
            color: #333333;
            margin: 30px 0;
        }
        .info-box {
            background: #f0f8f0;
            border-left: 4px solid #006400;
            padding: 15px;
            margin: 20px 0;
        }
        .info-label {
            font-weight: bold;
            color: #006400;
            display: inline-block;
            width: 200px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }
        .signature-block {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-top: 2px solid #333333;
            margin-top: 60px;
            padding-top: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #228B22;
            font-size: 12px;
            color: #666666;
        }
        .cert-number {
            font-weight: bold;
            color: #006400;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="logo">TAU</div>
            <div class="subtitle">TARLAC AGRICULTURAL UNIVERSITY</div>
            <div class="subtitle">University Research Ethics Office (UREO)</div>
            <div class="title">Certificate of Ethical Clearance</div>
        </div>
        
        <div class="content">
            <p style="text-align: center; margin-bottom: 30px;">
                <span class="cert-number">Certificate No.: {$certificate_number}</span>
            </p>
            
            <p>This is to certify that the research project titled:</p>
            
            <div class="info-box">
                <p style="font-size: 16px; font-weight: bold; text-align: center; margin: 10px 0;">
                    "{$application['project_title']}"
                </p>
            </div>
            
            <p>Submitted by <strong>{$application['applicant_name']}</strong>, {$application['position']} 
            of {$application['department']}, {$application['college']}, has been reviewed and approved by the 
            Tarlac Agricultural University Research Ethics Committee.</p>
            
            <div class="info-box">
                <div><span class="info-label">Application Type:</span> {$application['application_type']}</div>
                <div><span class="info-label">Category:</span> {$application['category']}</div>
                <div><span class="info-label">Queue Number:</span> {$application['queue_number']}</div>
                <div><span class="info-label">Date of Approval:</span> {$issue_date}</div>
                <div><span class="info-label">Valid From:</span> {$valid_from_formatted}</div>
                <div><span class="info-label">Valid Until:</span> {$valid_until_formatted}</div>
            </div>
            
            <p>This certificate confirms that the research proposal has met the ethical standards required for 
            conducting research involving human participants and/or sensitive data. The principal investigator 
            is expected to conduct the research in accordance with the approved protocol and to report any 
            significant changes or adverse events to the Ethics Committee.</p>
            
            <p><strong>CONDITIONS:</strong></p>
            <ul>
                <li>This clearance is valid for one (1) year from the date of issuance.</li>
                <li>Any modifications to the research protocol must be submitted for review and approval.</li>
                <li>Adverse events or unanticipated problems must be reported immediately.</li>
                <li>A continuing review or final report must be submitted before expiration.</li>
                <li>This certificate may be revoked if violations of ethical standards are discovered.</li>
            </ul>
        </div>
        
        <div class="signatures">
            <div class="signature-block">
                <div class="signature-line">
                    <strong>Chairperson</strong><br>
                    TAU Research Ethics Committee
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <strong>Director</strong><br>
                    University Research Ethics Office
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Tarlac Agricultural University</strong></p>
            <p>Romulo Boulevard, Tarlac City, Philippines 2300</p>
            <p>University Research Ethics Office | ureo@tau.edu.ph</p>
            <p style="margin-top: 20px; font-style: italic;">
                This is a system-generated certificate. Verify authenticity at 
                https://ureo.tau.edu.ph/verify?cert={$certificate_number}
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    
    return $html;
}

/**
 * Send certificate email to applicant
 */
function sendCertificateEmail($application, $certificate_number, $pdf_path) {
    $subject = "Certificate of Ethical Clearance Issued - " . $certificate_number;
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='background: linear-gradient(135deg, #006400, #228B22); color: white; padding: 20px; text-align: center;'>
            <h2>🎉 Certificate Issued!</h2>
        </div>
        <div style='padding: 20px;'>
            <p>Dear {$application['applicant_name']},</p>
            
            <p>Congratulations! Your research ethics application has been <strong>approved</strong> and 
            your Certificate of Ethical Clearance has been issued.</p>
            
            <div style='background: #f0f8f0; border-left: 4px solid #006400; padding: 15px; margin: 20px 0;'>
                <p><strong>Certificate Number:</strong> {$certificate_number}</p>
                <p><strong>Queue Number:</strong> {$application['queue_number']}</p>
                <p><strong>Project Title:</strong> {$application['project_title']}</p>
            </div>
            
            <p>Your certificate is attached to this email. You can also download it from your applicant portal.</p>
            
            <p><strong>Important Reminders:</strong></p>
            <ul>
                <li>This certificate is valid for one year from the date of issuance</li>
                <li>Report any protocol modifications for review and approval</li>
                <li>Submit continuing review or final report before expiration</li>
                <li>Report any adverse events immediately</li>
            </ul>
            
            <p>Thank you for your compliance with ethical research standards.</p>
            
            <p>Best regards,<br>
            <strong>TAU University Research Ethics Office</strong></p>
        </div>
    </body>
    </html>
    ";
    
    // Note: Actual email sending would use PHPMailer with attachment
    // For now, just log
    return sendEmail($application['applicant_email'], $subject, $message, $application['queue_number']);
}

// Command line interface
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php generate-certificate.php <queue_number>\n";
        exit(1);
    }
    
    $queue_number = $argv[1];
    $result = generateCertificate($queue_number);
    
    if ($result['success']) {
        echo "✓ Certificate generated successfully\n";
        echo "Certificate Number: " . $result['certificate_number'] . "\n";
        echo "PDF Path: " . $result['pdf_path'] . "\n";
    } else {
        echo "✗ Error: " . $result['message'] . "\n";
    }
}
?>
