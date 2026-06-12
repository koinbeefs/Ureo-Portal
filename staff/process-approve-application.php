<?php
/**
 * Process Approve Application
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';
$review_type = $_POST['review_type'] ?? '';

if (empty($queue_number) || empty($review_type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit();
}

// Validate review type
$valid_review_types = ['exempt', 'expedited', 'full'];
if (!in_array($review_type, $valid_review_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid review type.']);
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

// Check if user can edit this application
$can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);
if (!$can_edit) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to approve this application.']);
    exit();
}

// Check if application is in a valid state for approval
$valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
if (!in_array($application['current_status'], $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Application is not in a valid state for approval.']);
    exit();
}

try {
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Update application status and category first (no transaction - like edit-qf02-remarks.php)
    if ($review_type === 'exempt') {
        // Exempt goes directly to UREC Manager
        $new_status = 'UREC_REVIEW_REQUIRED';
    } else {
        // Expedited/Full goes to category forms
        $new_status = 'CATEGORY_FORMS_REQUIRED';
    }
    
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
    $update_stmt->bind_param("sss", $new_status, $review_type, $queue_number);
    $update_stmt->execute();

    // Log approval activity
    logStaffActivity($_SESSION['user_id'], $queue_number, 'approved', "Application approved with $review_type review");

    // Add status history entry
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $notes = "Application approved for " . ucfirst($review_type) . " Review";
    $changed_by_type = 'staff';
    $history_stmt->bind_param("ssssss", $queue_number, $application['current_status'], $new_status, $_SESSION['user_id'], $changed_by_type, $notes);
    $history_stmt->execute();

    // Handle workflows after status update (no transaction lock)
    if ($review_type === 'exempt') {
        // Handle exempt workflow - send to UREC Manager
        handleExemptWorkflow($conn, $application, $queue_number);
    } else {
        // Handle expedited/full workflow - send category forms
        handleCategoryFormsWorkflow($conn, $application, $queue_number, $review_type);
    }

    closeDBConnection($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Application approved successfully.',
        'review_type' => $review_type,
        'next_status' => $new_status
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        closeDBConnection($conn);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing approval: ' . $e->getMessage()]);
}

function handleExemptWorkflow($conn, $application, $queue_number) {
    // Create system message for UREC Manager (Head)
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'approval', ?, ?)");
    $subject = "Exempt Review Application - " . $queue_number;
    $message_body = "Application " . $queue_number . " has been approved for Exempt Review and is ready for UREC Manager (Head) assignment.\n\n" .
                    "Applicant: " . $application['applicant_name'] . "\n" .
                    "Research Title: " . $application['research_title'] . "\n\n" .
                    "Note: This application does not require a research checklist and should be assigned directly to a UREC member or handled by the Manager.";
    $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
    $msg_stmt->execute();

    // Send notification email to applicant
    $template_code = 'EXEMPT_APPROVED';
    $placeholders = getApplicationPlaceholders($queue_number);
    $placeholders['review_type'] = 'Exempt';
    
    sendTemplatedEmail($application['applicant_email'], $template_code, $placeholders, $queue_number);
}

function handleCategoryFormsWorkflow($conn, $application, $queue_number, $review_type) {
    // Determine category based on AI/Staff classification
    $category = determineCategory($queue_number);
    
    // Create category form record in category_forms (for metadata)
    $category_form_data = [
        'category' => $category,
        'review_type' => $review_type,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $form_data_json = json_encode($category_form_data);
    $insert_stmt = $conn->prepare("INSERT INTO category_forms (queue_number, category, review_type, form_data) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE category = ?, review_type = ?, form_data = ?");
    $insert_stmt->bind_param("sssssss", $queue_number, $category, $review_type, $form_data_json, $category, $review_type, $form_data_json);
    $insert_stmt->execute();

    // 0. Generate Category Token for secure access
    $token = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    $token_data = [
        'token' => $token,
        'expires_at' => $expires_at,
        'category' => $category,
        'review_type' => $review_type
    ];
    
    $token_json = json_encode($token_data);
    $token_stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data) VALUES (?, 'category_token', ?) ON DUPLICATE KEY UPDATE form_data = ?");
    $token_stmt->bind_param("sss", $queue_number, $token_json, $token_json);
    $token_stmt->execute();

    // 1. Generate Annotated PDF with Floating Remarks
    $qf02_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
    $qf02_stmt->bind_param("s", $queue_number);
    $qf02_stmt->execute();
    $qf02_res = $qf02_stmt->get_result()->fetch_assoc();
    $qf02_data = json_decode($qf02_res['form_data'] ?? '{}', true);
    
    $annotated_pdf_rel = generateAnnotatedPDF($queue_number, $qf02_data);
    $annotated_pdf_full = realpath(__DIR__ . '/../' . $annotated_pdf_rel);

    // 2. Collect folder assets
    $attachments = [];
    if ($annotated_pdf_full && file_exists($annotated_pdf_full)) {
        $attachments[] = ['path' => $annotated_pdf_full, 'name' => basename($annotated_pdf_full)];
    }

    $category_assets_dir = realpath(__DIR__ . "/../assets/to_send/for_reply_to_categories/for_reply_to_$category/");
    if ($category_assets_dir && is_dir($category_assets_dir)) {
        $files = scandir($category_assets_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $file_path = $category_assets_dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($file_path)) {
                $attachments[] = ['path' => $file_path, 'name' => $file];
            }
        }
    }

    // 3. Send Templated Email
    $template_code = 'CATEGORY_FORMS_REQUIRED';
    $placeholders = getApplicationPlaceholders($queue_number);
    $placeholders['review_type'] = ucfirst($review_type);
    $placeholders['category'] = ucfirst($category);
    
    sendTemplatedEmail($application['applicant_email'], $template_code, $placeholders, $queue_number, $attachments);

    // 4. Create System Message
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'requirement', ?, ?)");
    $subject = "Category Forms Required - " . $queue_number;
    $message_body = "Application " . $queue_number . " requires " . ucfirst($category) . " category forms for " . ucfirst($review_type) . " review.\n\n" .
                    "Attached to your email is the annotated QF-02 with remarks and the guidelines for your classification.\n" .
                    "Category: " . ucfirst($category) . "\nReview Type: " . ucfirst($review_type);
    $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
    $msg_stmt->execute();
}

function determineCategory($queue_number) {
    // Read category from AI classification file
    $ai_classification_path = '../uploads/' . $queue_number . '/ai_classification.json';
    
    if (!file_exists($ai_classification_path)) {
        // Fallback to human if AI classification file not found
        return 'human';
    }
    
    $ai_data = json_decode(file_get_contents($ai_classification_path), true);
    
    // Check if staff has reviewed and provided final category
    if (isset($ai_data['staff_feedback']['final_category'])) {
        $final_category = $ai_data['staff_feedback']['final_category'];
        
        // Map category names to database values
        $category_mapping = [
            'Human Use' => 'human',
            'Animal Welfare' => 'animal', 
            'Plant Use' => 'plant',
            'Microbiological/Biotechnological Use' => 'microbiological',
            'Engineering' => 'engineering',
            'Information Technology Use' => 'it',
            'Food Technology Use' => 'food'
        ];
        
        return $category_mapping[$final_category] ?? 'human';
    }
    
    // Fallback to AI prediction if no staff review
    if (isset($ai_data['ai_prediction']['predicted'])) {
        $ai_prediction = $ai_data['ai_prediction']['predicted'];
        
        $category_mapping = [
            'Human Use' => 'human',
            'Animal Welfare' => 'animal',
            'Plant Use' => 'plant', 
            'Microbiological/Biotechnological Use' => 'microbiological',
            'Engineering' => 'engineering',
            'Information Technology Use' => 'it',
            'Food Technology Use' => 'food'
        ];
        
        return $category_mapping[$ai_prediction] ?? 'human';
    }
    
    // Default fallback
    return 'human';
}

function generateAnnotatedPDF($queue_number, $form_data) {
    // Get original QF02 PDF
    $conn = getDBConnection();
    $doc_stmt = $conn->prepare("SELECT file_path FROM documents WHERE queue_number = ? AND (document_type = 'qf02' OR document_name LIKE '%qf02%') ORDER BY upload_timestamp DESC LIMIT 1");
    $doc_stmt->bind_param('s', $queue_number);
    $doc_stmt->execute();
    $doc_res = $doc_stmt->get_result()->fetch_assoc();
    
    $originalPdf = $doc_res['file_path'] ?? '';
    // Ensure we have a clean path
    $fullOriginalPath = realpath(__DIR__ . '/../' . ltrim($originalPdf, '/'));
    
    if (empty($originalPdf) || !file_exists($fullOriginalPath)) {
        // Log error but don't stop the whole process
        error_log("Original QF-02 PDF not found for $queue_number at: $fullOriginalPath");
        return null;
    }
    
    // Use the existing PDF generation logic
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Manual fallback for FPDI
    if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
        $fpdiAutoload = __DIR__ . '/../vendor/setasign/fpdi/src/autoload.php';
        if (file_exists($fpdiAutoload)) {
            require_once $fpdiAutoload;
        }
    }
    
    // TCPDF fallback
    if (!class_exists('TCPDF')) {
        $tcpdfMain = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdfMain)) {
            require_once $tcpdfMain;
        }
    }
    
    // Use FPDI + TCPDF
    try {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        
        $pageCount = $pdf->setSourceFile($fullOriginalPath);
        
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            
            $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
            $pdf->useTemplate($templateId);
            
            if ($pageNo === 1) {
                // PREMIUM REMARK BAKE (Matches Floating Remark Calibration)
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(180, 0, 0); // Slightly reddish for visibility
                
                $pdfWidth = $size['width'];
                $pdfHeight = $size['height'];
                
                // Calibration constants from test-qf02-floating-tuning.php
                $firstRowTop = 32.65;
                $rowHeight = 3.05;
                $colRight = 2.25;
                $colWidth = 20.15; 
                
                for ($num = 1; $num <= 20; $num++) {
                    $remark = $form_data["crit_{$num}_remarks"] ?? '';
                    if (!empty($remark)) {
                        $x = $pdfWidth * (1 - ($colRight + $colWidth) / 100);
                        $y = $pdfHeight * ($firstRowTop + ($num - 1) * $rowHeight) / 100;
                        $w = $pdfWidth * ($colWidth / 100);
                        $h = $pdfHeight * ($rowHeight / 100);
                        
                        $pdf->SetXY($x, $y);
                        // Vertical center within the row
                        $pdf->MultiCell($w, $h, $remark, 0, 'L', false, 1, $x, $y + 0.5, true, 0, false, true, $h, 'M', true);
                    }
                }
                
                // Handle breaking line remarks if any
                for ($num = 1; $num <= 5; $num++) {
                    $remark = $form_data["brk_{$num}_remarks"] ?? '';
                    if (!empty($remark)) {
                        // Position for breaking lines (below the main table)
                        $y = $pdfHeight * (94.5 + ($num - 1) * 1.5) / 100;
                        $x = $pdfWidth * 0.1;
                        $w = $pdfWidth * 0.8;
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($w, 4, $remark, 0, 0, 'L');
                    }
                }
            }
        }
        
        $outputFilename = 'QF-02_Remarks_' . $queue_number . '_' . date('Ymd_His') . '.pdf';
        $uploadDir = __DIR__ . '/../uploads/' . $queue_number;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $outputPath = $uploadDir . '/' . $outputFilename;
        $pdf->Output($outputPath, 'F');
        
        return 'uploads/' . $queue_number . '/' . $outputFilename;
    } catch (Exception $e) {
        error_log("PDF Generation failed for $queue_number: " . $e->getMessage());
        return null;
    }
}

exit();
?>