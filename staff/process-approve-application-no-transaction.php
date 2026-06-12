<?php
/**
 * Process Approve Application (No Transaction Version)
 * TAU-UREO Portal
 */

// Suppress warnings for clean JSON output
error_reporting(E_ERROR);
ini_set('display_errors', 0);

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
    // Set JSON headers
    header('Content-Type: application/json');
    
    // Update application status and category (no transaction)
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
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by_type, changed_by, notes) VALUES (?, ?, 'staff', ?, ?)");
    $notes = "Application approved for " . ucfirst($review_type) . " Review";
    $history_stmt->bind_param("ssis", $queue_number, $application['current_status'], $_SESSION['user_id'], $notes);
    $history_stmt->execute();

    // Handle workflows after status update
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
    
    // Provide more specific error messages
    $error_message = $e->getMessage();
    if (strpos($error_message, 'lock wait timeout') !== false) {
        $error_message = "System is busy, please try again. The approval process timed out due to high system load.";
    } elseif (strpos($error_message, 'deadlock') !== false) {
        $error_message = "System conflict detected, please try again. Another process was accessing the same application.";
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing approval: ' . $error_message]);
}

function handleExemptWorkflow($conn, $application, $queue_number) {
    // Create system message for UREC Manager (will be implemented when UREC system is ready)
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'approval', ?, ?)");
    $subject = "Exempt Review Application - " . $queue_number;
    $message_body = "Application " . $queue_number . " has been approved for Exempt Review and is ready for UREC Manager assignment.\n\nApplicant: " . $application['applicant_name'] . "\nResearch Title: " . $application['research_title'];
    $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
    $msg_stmt->execute();

    // Send notification email to applicant
    $template_code = 'EXEMPT_APPROVED';
    $subject = "Application Approved - Exempt Review";
    $body = getEmailTemplate($template_code);

    if ($body) {
        $body = str_replace('{{applicant_name}}', $application['applicant_name'], $body);
        $body = str_replace('{{queue_number}}', $queue_number, $body);
        $body = str_replace('{{review_type}}', 'Exempt', $body);
        sendEmail($application['applicant_email'], $subject, $body, $queue_number, 'exempt_approval');
    }
}

function handleCategoryFormsWorkflow($conn, $application, $queue_number, $review_type) {
    // Get QF02 form data with remarks
    $form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
    $form_stmt->bind_param("s", $queue_number);
    $form_stmt->execute();
    $qf02_data = $form_stmt->get_result()->fetch_assoc();
    
    if (!$qf02_data) {
        throw new Exception("QF02 form data not found");
    }

    // Determine category based on AI classification file
    $category = determineCategory($queue_number);
    
    // Create category form record first (quick operation)
    $category_form_data = [
        'category' => $category,
        'review_type' => $review_type,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $insert_stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data) VALUES (?, 'category_form', ?)");
    $insert_stmt->bind_param("ss", $queue_number, json_encode($category_form_data));
    $insert_stmt->execute();

    // Send notification to applicant about category forms
    $template_code = 'CATEGORY_FORMS_REQUIRED';
    $subject = "Category Forms Required - " . ucfirst($review_type) . " Review";
    $body = getEmailTemplate($template_code);

    if ($body) {
        $body = str_replace('{{applicant_name}}', $application['applicant_name'], $body);
        $body = str_replace('{{queue_number}}', $queue_number, $body);
        $body = str_replace('{{review_type}}', ucfirst($review_type), $body);
        $body = str_replace('{{category}}', ucfirst($category), $body);
        sendEmail($application['applicant_email'], $subject, $body, $queue_number, 'category_forms');
    }

    // Create system message
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'requirement', ?, ?)");
    $subject = "Category Forms Required - " . $queue_number;
    $message_body = "Application " . $queue_number . " requires " . ucfirst($category) . " category forms for " . ucfirst($review_type) . " review.\n\nApplicant: " . $application['applicant_name'] . "\nCategory: " . ucfirst($category) . "\nReview Type: " . ucfirst($review_type);
    $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
    $msg_stmt->execute();
    
    // Generate PDF with floating remarks (expensive operation)
    $annotated_pdf_path = generateAnnotatedPDF($queue_number, $qf02_data['form_data']);
    
    // Update category form with PDF path
    $updated_form_data = array_merge($category_form_data, [
        'annotated_qf02_path' => $annotated_pdf_path
    ]);
    
    // Update the category form record with PDF path
    $final_update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'category_form'");
    $final_update_stmt->bind_param("ss", json_encode($updated_form_data), $queue_number);
    $final_update_stmt->execute();
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
    require_once '../vendor/autoload.php';
    
    // Get original QF02 PDF
    $conn = getDBConnection();
    $doc_stmt = $conn->prepare("SELECT file_path FROM documents WHERE queue_number = ? AND (document_type LIKE '%QF02%' OR document_name LIKE '%qf02%') ORDER BY upload_timestamp DESC LIMIT 1");
    $doc_stmt->bind_param('s', $queue_number);
    $doc_stmt->execute();
    $doc_res = $doc_stmt->get_result()->fetch_assoc();
    $conn->close();
    
    $originalPdf = $doc_res['file_path'] ?? '';
    $fullOriginalPath = realpath('../' . $originalPdf);
    
    if (empty($originalPdf) || !file_exists($fullOriginalPath)) {
        throw new Exception("Original QF-02 PDF not found at: $fullOriginalPath");
    }
    
    // Use the existing PDF generation logic from edit-qf02-remarks.php
    require_once '../vendor/autoload.php';
    
    // Manual fallback for FPDI if Composer autoloader is broken
    if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
        $fpdiAutoload = '../vendor/setasign/fpdi/src/autoload.php';
        if (file_exists($fpdiAutoload)) {
            require_once $fpdiAutoload;
        }
    }
    
    // TCPDF often needs a manual help if not in classmap
    if (!class_exists('TCPDF')) {
        $tcpdfMain = '../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdfMain)) {
            require_once $tcpdfMain;
        }
    }
    
    // Use FPDI + TCPDF
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
        
        // Only bake remarks on page 1 (where the criteria table is)
        if ($pageNo === 1) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            
            // Tuning constants (MUST MATCH tuning tool)
            $pdfWidth = $size['width'];
            $pdfHeight = $size['height'];
            $firstRowTop = 32.65;
            $rowHeight = 3.05;
            $colRight = 2.25;
            $colWidth = 20.15; // %
            
            foreach (range(1, 20) as $num) {
                $remark = $form_data["crit_{$num}_remarks"] ?? '';
                if (!empty($remark)) {
                    $x = $pdfWidth * (1 - ($colRight + $colWidth) / 100);
                    $y = $pdfHeight * ($firstRowTop + ($num - 1) * $rowHeight) / 100;
                    $w = $pdfWidth * ($colWidth / 100);
                    $h = $pdfHeight * ($rowHeight / 100);
                    
                    $pdf->SetXY($x, $y);
                    $pdf->MultiCell($w, $h, $remark, 0, 'L', false, 1, $x, $y, true, 0, false, true, $h, 'M', true);
                }
            }
        }
    }
    
    // Save the annotated PDF with absolute path
    $outputFilename = 'TAU-REO-QF-02_Annotated_' . $queue_number . '_' . date('His') . '.pdf';
    $outputDir = realpath('../uploads/' . $queue_number);
    
    // Ensure directory exists
    if (!is_dir($outputDir)) {
        mkdir('../uploads/' . $queue_number, 0777, true);
        $outputDir = realpath('../uploads/' . $queue_number);
    }
    
    $fullOutputPath = $outputDir . '/' . $outputFilename;
    
    // Use absolute path for TCPDF output
    $pdf->Output($fullOutputPath, 'F');
    
    // Return relative path for storage
    return 'uploads/' . $queue_number . '/' . $outputFilename;
}

exit();
?>
