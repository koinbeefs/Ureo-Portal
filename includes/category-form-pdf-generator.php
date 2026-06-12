<?php
/**
 * PDF Generator for Category Forms
 * TAU-UREO Portal
 * Generates PDFs with floating remarks for category-specific forms
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

class CategoryFormPDFGenerator {
    private $queue_number;
    private $category;
    private $form_data;
    private $conn;
    
    public function __construct($queue_number, $category, $form_data) {
        $this->queue_number = $queue_number;
        $this->category = $category;
        $this->form_data = $form_data;
        $this->conn = getDBConnection();
    }
    
    /**
     * Generate category form PDF with floating remarks
     */
    public function generatePDF() {
        require_once '../vendor/autoload.php';
        
        // Get the annotated QF02 PDF path
        $annotated_qf02_path = $this->form_data['annotated_qf02_path'] ?? '';
        
        if (empty($annotated_qf02_path)) {
            throw new Exception("Annotated QF02 PDF path not found");
        }
        
        $full_qf02_path = '../' . $annotated_qf02_path;
        
        if (!file_exists($full_qf02_path)) {
            throw new Exception("Annotated QF02 PDF file not found: " . $full_qf02_path);
        }
        
        // Load the category form template
        $template_path = $this->getCategoryTemplatePath();
        
        if (!file_exists($template_path)) {
            throw new Exception("Category form template not found: " . $template_path);
        }
        
        // Generate the combined PDF
        return $this->createCombinedPDF($full_qf02_path, $template_path);
    }
    
    /**
     * Get the category-specific template path
     */
    private function getCategoryTemplatePath() {
        $template_file = "TAU-REO-" . strtoupper($this->category) . "-checklist.php";
        $template_path = "../assets/to_send/for_reply_to_categories/for_reply_to_" . $this->category . "/" . $template_file;
        
        return $template_path;
    }
    
    /**
     * Create combined PDF with QF02 and category form
     */
    private function createCombinedPDF($qf02_path, $template_path) {
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
        
        // Create new PDF
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        
        // Add QF02 PDF (first page with remarks)
        $qf02_pageCount = $pdf->setSourceFile($qf02_path);
        
        for ($pageNo = 1; $pageNo <= $qf02_pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            
            $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
            $pdf->useTemplate($templateId);
        }
        
        // Add category form pages
        $this->addCategoryFormPages($pdf, $template_path);
        
        // Save the combined PDF
        $outputFilename = 'TAU-REO-' . strtoupper($this->category) . '-Complete_' . $this->queue_number . '_' . date('His') . '.pdf';
        $outputPath = '../uploads/' . $this->queue_number . '/' . $outputFilename;
        
        // Ensure directory exists
        if (!is_dir('../uploads/' . $this->queue_number)) {
            mkdir('../uploads/' . $this->queue_number, 0777, true);
        }
        
        $pdf->Output(__DIR__ . '/' . $outputPath, 'F');
        
        // Return relative path for storage
        return 'uploads/' . $this->queue_number . '/' . $outputFilename;
    }
    
    /**
     * Add category form pages to PDF
     */
    private function addCategoryFormPages($pdf, $template_path) {
        // Capture the category form HTML
        ob_start();
        include $template_path;
        $form_html = ob_get_clean();
        
        // Create a temporary HTML to PDF conversion
        $this->convertHTMLToPDF($pdf, $form_html);
    }
    
    /**
     * Convert HTML to PDF pages
     */
    private function convertHTMLToPDF($pdf, $html) {
        // For now, we'll create a simple page with category information
        // In a full implementation, you might use a library like DomPDF for HTML to PDF conversion
        
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, ucfirst($this->category) . ' Category Checklist', 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, 'Queue Number: ' . $this->queue_number, 0, 1);
        $pdf->Cell(0, 8, 'Category: ' . ucfirst($this->category), 0, 1);
        $pdf->Cell(0, 8, 'Review Type: ' . ucfirst($this->form_data['review_type'] ?? 'expedited'), 0, 1);
        $pdf->Ln(10);
        
        // Add form data if available
        if (isset($this->form_data['submitted_data'])) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Submitted Data:', 0, 1);
            $pdf->SetFont('helvetica', '', 11);
            
            foreach ($this->form_data['submitted_data'] as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $pdf->Cell(0, 6, ucfirst(str_replace('_', ' ', $key)) . ': ' . $value, 0, 1);
            }
        }
        
        // Add notes section
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Notes:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 5, 'This form has been completed as part of the ' . ucfirst($this->category) . ' category review process. All required fields have been filled out by the applicant.');
    }
    
    /**
     * Generate category form PDF only (without QF02)
     */
    public function generateCategoryFormOnly() {
        require_once '../vendor/autoload.php';
        
        // Get the category form template
        $template_path = $this->getCategoryTemplatePath();
        
        if (!file_exists($template_path)) {
            throw new Exception("Category form template not found: " . $template_path);
        }
        
        // Create new PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 10);
        
        // Add category form pages
        $this->addCategoryFormPages($pdf, $template_path);
        
        // Save the category form PDF
        $outputFilename = 'TAU-REO-' . strtoupper($this->category) . '-Form_' . $this->queue_number . '_' . date('His') . '.pdf';
        $outputPath = '../uploads/' . $this->queue_number . '/' . $outputFilename;
        
        // Ensure directory exists
        if (!is_dir('../uploads/' . $this->queue_number)) {
            mkdir('../uploads/' . $this->queue_number, 0777, true);
        }
        
        $pdf->Output(__DIR__ . '/' . $outputPath, 'F');
        
        // Return relative path for storage
        return 'uploads/' . $this->queue_number . '/' . $outputFilename;
    }
    
    public function __destruct() {
        if ($this->conn) {
            closeDBConnection($this->conn);
        }
    }
}

/**
 * Utility function to generate category form PDF
 */
function generateCategoryFormPDF($queue_number, $category, $form_data) {
    try {
        $generator = new CategoryFormPDFGenerator($queue_number, $category, $form_data);
        return $generator->generateCategoryFormOnly();
    } catch (Exception $e) {
        error_log("Error generating category form PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Utility function to generate combined PDF (QF02 + Category Form)
 */
function generateCombinedReviewPDF($queue_number, $category, $form_data) {
    try {
        $generator = new CategoryFormPDFGenerator($queue_number, $category, $form_data);
        return $generator->generatePDF();
    } catch (Exception $e) {
        error_log("Error generating combined review PDF: " . $e->getMessage());
        return false;
    }
}
?>
