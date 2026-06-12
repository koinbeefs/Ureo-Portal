<?php
/**
 * Debug Approval Process
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/email-template-functions.php';

// Test queue number
$queue_number = 'UREO-0001';
$review_type = 'expedited';

echo "<h2>Testing Approval Process for: $queue_number</h2>";
echo "<h3>Review Type: $review_type</h3>";

// Test step by step
try {
    echo "<h4>Step 1: Database Connection</h4>";
    $conn = getDBConnection();
    echo "<p>✅ Database connected successfully</p>";
    
    echo "<h4>Step 2: Get Application Details</h4>";
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        echo "<p style='color: red;'>❌ Application not found</p>";
        exit();
    }
    echo "<p>✅ Application found: " . $application['applicant_name'] . "</p>";
    
    echo "<h4>Step 3: Category Detection</h4>";
    $category = determineCategory($queue_number);
    echo "<p>✅ Category detected: $category</p>";
    
    echo "<h4>Step 4: Check QF02 Form Data</h4>";
    $form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
    $form_stmt->bind_param("s", $queue_number);
    $form_stmt->execute();
    $qf02_data = $form_stmt->get_result()->fetch_assoc();
    
    if (!$qf02_data) {
        echo "<p style='color: red;'>❌ QF02 form data not found</p>";
        exit();
    }
    echo "<p>✅ QF02 form data found</p>";
    
    echo "<h4>Step 5: Test PDF Generation (this might be slow)</h4>";
    $start_time = microtime(true);
    
    try {
        // Test PDF generation with timeout protection
        set_time_limit(30); // 30 second timeout
        
        $annotated_pdf_path = generateAnnotatedPDF($queue_number, $qf02_data['form_data']);
        
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        
        echo "<p>✅ PDF generated successfully in " . number_format($duration, 2) . " seconds</p>";
        echo "<p>PDF Path: $annotated_pdf_path</p>";
        
        if (file_exists($annotated_pdf_path)) {
            echo "<p>✅ PDF file exists and is accessible</p>";
        } else {
            echo "<p style='color: red;'>❌ PDF file not found at expected location: $annotated_pdf_path</p>";
        }
        
    } catch (Exception $e) {
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        
        echo "<p style='color: red;'>❌ PDF generation failed after " . number_format($duration, 2) . " seconds</p>";
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h4>Step 6: Test Database Transaction</h4>";
    
    // Test just the transaction part without PDF
    $conn->begin_transaction();
    $conn->query("SET SESSION innodb_lock_wait_timeout = 5");
    
    $new_status = 'CATEGORY_FORMS_REQUIRED';
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
    $update_stmt->bind_param("sss", $new_status, $review_type, $queue_number);
    $update_stmt->execute();
    
    $conn->commit();
    echo "<p>✅ Database transaction completed successfully</p>";
    
    closeDBConnection($conn);
    
    echo "<h3>✅ All tests completed successfully!</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during testing</h3>";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack Trace:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

// Include the functions we're testing
function determineCategory($queue_number) {
    $ai_classification_path = 'uploads/' . $queue_number . '/ai_classification.json';
    
    if (!file_exists($ai_classification_path)) {
        return 'human';
    }
    
    $ai_data = json_decode(file_get_contents($ai_classification_path), true);
    
    if (isset($ai_data['staff_feedback']['final_category'])) {
        $final_category = $ai_data['staff_feedback']['final_category'];
        
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
    
    return 'human';
}

function generateAnnotatedPDF($queue_number, $form_data) {
    require_once 'vendor/autoload.php';
    
    $conn = getDBConnection();
    $doc_stmt = $conn->prepare("SELECT file_path FROM documents WHERE queue_number = ? AND (document_type LIKE '%QF02%' OR document_name LIKE '%qf02%') ORDER BY upload_timestamp DESC LIMIT 1");
    $doc_stmt->bind_param('s', $queue_number);
    $doc_stmt->execute();
    $doc_res = $doc_stmt->get_result()->fetch_assoc();
    $conn->close();
    
    $originalPdf = $doc_res['file_path'] ?? '';
    $fullOriginalPath = realpath($originalPdf);
    
    if (empty($originalPdf) || !file_exists($fullOriginalPath)) {
        throw new Exception("Original QF-02 PDF not found at: $fullOriginalPath");
    }
    
    // Use the existing PDF generation logic from edit-qf02-remarks.php
    require_once 'vendor/autoload.php';
    
    // Manual fallback for FPDI if Composer autoloader is broken
    if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
        $fpdiAutoload = 'vendor/setasign/fpdi/src/autoload.php';
        if (file_exists($fpdiAutoload)) {
            require_once $fpdiAutoload;
        }
    }
    
    // TCPDF often needs a manual help if not in classmap
    if (!class_exists('TCPDF')) {
        $tcpdfMain = 'vendor/tecnickcom/tcpdf/tcpdf.php';
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
    $outputDir = realpath('uploads/' . $queue_number);
    
    // Ensure directory exists
    if (!is_dir($outputDir)) {
        mkdir('uploads/' . $queue_number, 0777, true);
        $outputDir = realpath('uploads/' . $queue_number);
    }
    
    $fullOutputPath = $outputDir . '/' . $outputFilename;
    
    // Use absolute path for TCPDF output
    $pdf->Output($fullOutputPath, 'F');
    
    // Return relative path for storage
    return 'uploads/' . $queue_number . '/' . $outputFilename;
}

?>
