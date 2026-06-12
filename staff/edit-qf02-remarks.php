<?php
// edit-qf02-remarks.php - Staff interface to add remarks to QF-02 forms

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
use PhpOffice\PhpWord\TemplateProcessor;

requireLogin();

if (!isset($_GET['queue'])) {
    header('Location: dashboard.php');
    exit;
}

$queue_number = $_GET['queue'];
$conn = getDBConnection();

// Handle file download if requested
if (isset($_GET['download'])) {
    $filename = $_GET['download'];
    if (file_exists($filename)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filename));
        readfile($filename);
        unlink($filename);
        exit;
    } else {
        die('File not found');
    }
}

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param('s', $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    header('Location: dashboard.php?error=not_found');
    exit;
}

// Auto-claim unassigned applications
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ? WHERE queue_number = ? AND assigned_staff_id IS NULL");
    $claim_stmt->bind_param("is", $_SESSION['user_id'], $queue_number);
    $claim_stmt->execute();

    if ($claim_stmt->affected_rows > 0) {
        $just_claimed = true;
        $application['assigned_staff_id'] = $_SESSION['user_id']; // Update the local copy

        // Log the auto-claim activity
        logStaffActivity($_SESSION['user_id'], $queue_number, 'other', 'Auto-claimed application for QF-02 remarks editing');
    }
}

// Check if current user can edit this application
$can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);

// Get QF-02 form data if exists
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
$form_stmt->bind_param('s', $queue_number);
$form_stmt->execute();
$form_result = $form_stmt->get_result()->fetch_assoc();

$form_data = [];
if ($form_result) {
    $form_data = json_decode($form_result['form_data'], true) ?? [];
}

if (empty($form_data)) {
    header('Location: view-application.php?queue=' . urlencode($queue_number) . '&error=qf02_not_completed');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check permissions
    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    require_once '../vendor/autoload.php';

    // Update remarks in form data
    $criteria = range(1, 20);
    foreach ($criteria as $num) {
        if (isset($_POST["crit_{$num}_remarks"])) {
            $form_data["crit_{$num}_remarks"] = trim($_POST["crit_{$num}_remarks"]);
        }
    }

    // Save updated form data
    $updated_json = json_encode($form_data);
    $update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'qf02'");
    $update_stmt->bind_param('ss', $updated_json, $queue_number);

    if ($update_stmt->execute()) {
        // Generate updated PDF file with remarks baked in
        try {
            // Get original PDF path from documents table
            $doc_stmt = $conn->prepare("SELECT file_path FROM documents WHERE queue_number = ? AND (document_type LIKE '%QF02%' OR document_name LIKE '%qf02%') ORDER BY upload_timestamp DESC LIMIT 1");
            $doc_stmt->bind_param('s', $queue_number);
            $doc_stmt->execute();
            $doc_res = $doc_stmt->get_result()->fetch_assoc();

            $originalPdf = $doc_res['file_path'] ?? '';
            $fullOriginalPath = '../' . $originalPdf;

            if (empty($originalPdf) || !file_exists($fullOriginalPath)) {
                throw new Exception("Original QF-02 PDF not found at: " . $fullOriginalPath);
            }

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

            $outputFilename = 'TAU-REO-QF-02_Annotated_' . $queue_number . '_' . date('His') . '.pdf';
            $outputPath = '../uploads/' . $queue_number . '/' . $outputFilename;

            // Ensure directory exists
            if (!is_dir('../uploads/' . $queue_number)) {
                mkdir('../uploads/' . $queue_number, 0777, true);
            }

            $pdf->Output(__DIR__ . '/' . $outputPath, 'F');

            // Log activity
            logStaffActivity($_SESSION['user_id'], $queue_number, 'other', 'Generated annotated QF-02 PDF');

            echo json_encode([
                'success' => true,
                'message' => 'QF-02 remarks saved and PDF annotated successfully!',
                'download_url' => $outputPath
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error generating PDF: ' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving remarks.']);
        exit;
    }
}

// Connection will be closed at the end of the file

$page_title = 'Edit QF-02 Remarks';
$base_url = '../';
$active_menu = 'dashboard';
$is_modal = isset($_GET['modal']) && $_GET['modal'] === '1';

if (!$is_modal) {
    include '../includes/auth_header.php';
}
?>

<?php if ($is_modal): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?> - TAU-UREO Portal</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <style>
        .pdf-viewer-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 220px);
            background: #525659;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.5);
        }

        #pdf-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        #floating-remarks-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }

        #remarks-svg-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .float-remark-box {
            position: absolute;
            background: #1a1a1a;
            color: #fff;
            border-radius: 8px;
            padding: 0;
            width: min(260px, 40vw);
            max-width: 90vw;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
            overflow: visible;
            transition: top 0.3s ease, left 0.3s ease, opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            pointer-events: auto;
            opacity: 1;
            /* Always visible if text exists */
        }

        .float-remark-box.hidden {
            display: none;
        }

        /* The yellow dot that anchors to the PDF row */
        .float-remark-anchor-dot {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #ffcc00;
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(255, 204, 0, 0.9);
            border: 1.5px solid #000;
            z-index: 20;
            pointer-events: none;
        }

        .float-remark-header {
            background: #ffcc00;
            color: #000;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 800;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
        }

        .float-remark-badge {
            background: #000;
            color: #ffcc00;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .float-remark-content {
            padding: 10px 15px;
            font-size: 12px;
            line-height: 1.5;
            max-height: 120px;
            overflow-y: auto;
        }

        .float-remark-box.active {
            border-color: #ffcc00;
            box-shadow: 0 0 20px rgba(255, 204, 0, 0.6);
            opacity: 1;
            z-index: 100;
        }

        .editor-layout {
            display: flex;
            gap: 20px;
            height: calc(100vh - 180px);
        }

        .input-panel {
            flex: 0 0 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .viewer-panel {
            flex: 1;
            position: relative;
        }

        /* Custom Scrollbar for input panel */
        .input-panel::-webkit-scrollbar {
            width: 6px;
        }

        .input-panel::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .input-panel::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        .input-panel::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .queue-badge {
                font-size: 1rem;
                padding: 0.5rem 1rem;
            }

            .criteria-card .row>div {
                margin-bottom: 0.5rem;
            }

            /* Responsive floating remark bubbles */
            .float-remark-box {
                width: 180px !important;
                font-size: 0.8rem !important;
                min-width: 120px !important;
            }

            .float-remark-header {
                padding: 3px 6px !important;
                font-size: 9px !important;
            }

            .float-remark-content {
                padding: 4px 6px !important;
                font-size: 0.75rem !important;
                line-height: 1.2 !important;
            }
        }

        @media (max-width: 576px) {
            .float-remark-box {
                width: 140px !important;
                font-size: 0.7rem !important;
                min-width: 100px !important;
            }

            .float-remark-header {
                padding: 2px 4px !important;
                font-size: 8px !important;
                gap: 2px !important;
            }

            .float-remark-content {
                padding: 3px 4px !important;
                font-size: 0.65rem !important;
                line-height: 1.1 !important;
            }

            .float-remark-badge {
                width: 14px !important;
                height: 14px !important;
                font-size: 7px !important;
            }
        }

        @media (max-width: 480px) {
            .float-remark-box {
                width: 120px !important;
                font-size: 0.65rem !important;
                min-width: 80px !important;
                max-width: 120px !important;
            }

            .float-remark-header {
                padding: 1px 3px !important;
                font-size: 7px !important;
                gap: 2px !important;
            }

            .float-remark-content {
                padding: 2px 3px !important;
                font-size: 0.6rem !important;
                line-height: 1.0 !important;
            }

            .float-remark-badge {
                width: 12px !important;
                height: 12px !important;
                font-size: 6px !important;
            }

            /* Responsive layout adjustments */
            .editor-layout {
                flex-direction: column;
                height: auto;
                gap: 15px;
            }

            .input-panel {
                flex: 1;
                max-height: 40vh;
                padding-right: 0;
            }

            .viewer-panel {
                min-height: 50vh;
            }

            .pdf-viewer-container {
                height: 45vh;
            }

            .page-header {
                padding: 1rem;
            }

            .progress-section {
                margin-bottom: 1rem;
            }
        }
    </style>

    <div class="main-container">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="queue-badge mb-2">
                            <i class="bi bi-ticket-detailed me-1"></i><?php echo htmlspecialchars($queue_number); ?>
                        </div>
                        <h4 class="mb-1 text-dark fw-bold">
                            <i class="bi bi-pencil-square text-primary me-2"></i>QF-02 Remarks Editor
                        </h4>
                        <p class="text-muted mb-0 small">Add staff remarks to QF-02 form criteria for clarification or
                            revision requests</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex flex-column align-items-end">
                            <h6 class="text-primary mb-1 fw-bold">
                                <?php echo htmlspecialchars($application['applicant_name']); ?>
                            </h6>
                            <small
                                class="text-muted"><?php echo htmlspecialchars($form_data['title'] ?? 'Research Title Not Available'); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-modern">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($just_claimed): ?>
                <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert">
                    <i class="bi bi-hand-index-thumb me-2"></i>Application has been automatically assigned to you for QF-02
                    remarks editing.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$can_edit): ?>
                <div class="alert alert-warning alert-modern">
                    <i class="bi bi-lock me-2"></i>This application is assigned to another staff member and cannot be
                    edited.
                </div>
            <?php endif; ?>

            <!-- Progress Section -->
            <div class="progress-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-2 fw-bold text-dark">
                            <i class="bi bi-bar-chart-line me-2"></i>QF-02 Review Progress
                        </h6>
                        <p class="text-muted mb-0 small">Track your progress through the 20 criteria for comprehensive
                            review</p>
                    </div>
                    <div class="col-md-4">
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar progress-bar-custom" role="progressbar" style="width: 0%"
                                aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">0 of 20 criteria reviewed</small>
                    </div>
                </div>
            </div>

            <div class="editor-layout">
                <!-- Input Panel -->
                <div class="input-panel">
                    <div class="card mb-3">
                        <div class="card-header bg-white border-bottom-0">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-primary me-2"></i>Review
                                Criteria</h6>
                        </div>
                        <div class="card-body p-2">
                            <form id="remarksForm" <?php echo !$can_edit ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                                <?php
                                $criteriaList = [
                                    1 => "The research involves interaction with human participants, such as surveys, interviews, or clinical tests.",
                                    2 => "The research involves the collection of identifiable or sensitive data (e.g. health data, biometric information)",
                                    3 => "The research involves a vulnerable population (e.g. minors, pregnant women, elderly, persons with disabilities)",
                                    4 => "The research involves physical, psychological, social, legal, or economic risk.",
                                    5 => "The study requires informed consent from the participants.",
                                    6 => "The research involves live animals for experimentation.",
                                    7 => "The research involves procedures that could cause pain, distress, or discomfort to the animal.",
                                    8 => "The research involves working with endangered, protected, or non-domestic species",
                                    9 => "The research protocol is aligned with Bureau of Animal Industry (BAI) requirements for animal care and use.",
                                    10 => "The research involves genetically modified organisms (GMOs) or new varieties.",
                                    11 => "The research involves field trials, environmental release, or agricultural practices that may affect biodiversity.",
                                    12 => "The research involves the importation, exportation, or propagation of plant materials.",
                                    13 => "The research involves handling of pathogenic microorganisms or bio-hazardous materials.",
                                    14 => "The research involves the use of microorganisms that have potential health, safety, or environmental risks.",
                                    15 => "The research involves the collection of personal data (e.g. data from social media, health data, or private information).",
                                    16 => "The research involves software development, algorithms, or IT or computer systems to be tested with human participants.",
                                    17 => "The research involves cyber security, privacy concerns, or data protection issues.",
                                    18 => "The research involves the development or testing of machinery, equipment, or prototypes that could have risks to users.",
                                    19 => "The research have a negative impact to the environment (e.g. waste management, emissions, or energy consumption).",
                                    20 => "The research involves potentially hazardous food production techniques (e.g. chemical additive, genetic modification)."
                                ];
                                foreach ($criteriaList as $num => $text):
                                    $yes_checked = (isset($form_data["crit_{$num}_yes"]) && $form_data["crit_{$num}_yes"] === 'on');
                                    $no_checked = (isset($form_data["crit_{$num}_no"]) && $form_data["crit_{$num}_no"] === 'on');
                                    $current_remark = $form_data["crit_{$num}_remarks"] ?? '';
                                    ?>
                                    <div class="card mb-2 border-0 bg-white" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-primary rounded-circle"
                                                    style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center;"><?php echo $num; ?></span>
                                                <?php if ($yes_checked): ?>
                                                    <span class="badge bg-success small"><i
                                                            class="bi bi-check-circle me-1"></i>Yes</span>
                                                <?php elseif ($no_checked): ?>
                                                    <span class="badge bg-secondary small"><i
                                                            class="bi bi-x-circle me-1"></i>No</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="small mb-2 text-dark" style="font-size: 0.8rem; line-height: 1.3;">
                                                <?php echo htmlspecialchars($text); ?>
                                            </p>
                                            <input type="text" class="form-control form-control-sm remark-input"
                                                name="crit_<?php echo $num; ?>_remarks" data-index="<?php echo $num; ?>"
                                                value="<?php echo htmlspecialchars($current_remark); ?>"
                                                placeholder="Type remark..." <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card border-primary shadow-sm" style="position: sticky; bottom: 0; z-index: 100;">
                        <div class="card-body p-3 text-center">
                            <button type="button" id="saveRemarksBtn" class="btn w-100 btn-modern" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <i class="bi bi-save me-2"></i>Save & Download PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Viewer Panel -->
                <div class="viewer-panel">
                    <div class="pdf-viewer-container">
                        <?php
                        // Get the PDF path from documents table
                        $doc_stmt = $conn->prepare("SELECT file_path FROM documents WHERE queue_number = ? AND (document_type LIKE '%QF02%' OR document_name LIKE '%qf02%') ORDER BY upload_timestamp DESC LIMIT 1");
                        $doc_stmt->bind_param('s', $queue_number);
                        $doc_stmt->execute();
                        $doc_res = $doc_stmt->get_result()->fetch_assoc();
                        $pdf_path = $doc_res['file_path'] ?? '';
                        ?>
                        <iframe id="pdf-iframe"
                            src="view-document.php?path=<?php echo urlencode($pdf_path); ?>#toolbar=0&navpanes=0"></iframe>
                        <svg id="remarks-svg-layer"></svg>
                        <div id="floating-remarks-layer">
                            <!-- Floating boxes will be injected here by JS -->
                        </div>
                    </div>
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Remarks are aligned with the PDF
                            cells.</small>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary"
                                onclick="document.getElementById('pdf-iframe').contentWindow.location.reload()"><i
                                    class="bi bi-arrow-clockwise"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const layer = document.getElementById('floating-remarks-layer');
            const inputs = document.querySelectorAll('.remark-input');
            const remarksForm = document.getElementById('remarksForm');

            // Tuning Percentages (MATCH PHP SERVER-SIDE)
            const tuning = {
                firstRowTop: 33.05,
                rowHeight: 3,
                colRight: 9.15,
                colWidth: 13.5
            };

            const svgLayer = document.getElementById('remarks-svg-layer');

            function updateFloatingBox(index, text, isFocused = false) {
                let box = layer.querySelector(`.float-box-${index}`);
                let dot = layer.querySelector(`.float-dot-${index}`);

                if (!text || text.trim() === '') {
                    if (box) box.remove();
                    if (dot) dot.remove();
                    const line = svgLayer.querySelector(`.float-line-${index}`);
                    if (line) line.remove();
                    return;
                }

                if (!box) {
                    box = document.createElement('div');
                    box.className = `float-remark-box float-box-${index}`;
                    box.innerHTML = `
                <div class="float-remark-header">
                    <div class="float-remark-badge">${index}</div>
                    <span>Criteria Point ${index}</span>
                </div>
                <div class="float-remark-content"></div>
            `;
                    layer.appendChild(box);

                    dot = document.createElement('div');
                    dot.className = `float-remark-anchor-dot float-dot-${index}`;
                    layer.appendChild(dot);

                    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                    line.setAttribute("class", `float-line-${index}`);
                    line.setAttribute("stroke", "#ffcc00");
                    line.setAttribute("stroke-width", "1.5");
                    line.setAttribute("stroke-dasharray", "4,2");
                    line.setAttribute("opacity", "0.6");
                    svgLayer.appendChild(line);
                }

                box.querySelector('.float-remark-content').textContent = text;

                if (isFocused) {
                    box.classList.add('active');
                    box.style.zIndex = "1000";
                } else {
                    box.classList.remove('active');
                    box.style.zIndex = "10";
                }

                syncOverlay();
            }

            // Anchoring Logic: Absolute mapping from PDF canvas
            const iframe = document.getElementById('pdf-iframe');

            function syncOverlay() {
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    const canvas = iframeDoc.querySelector('.pdf-canvas');
                    if (canvas) {
                        const rect = canvas.getBoundingClientRect();
                        const boxes = Array.from(layer.querySelectorAll('.float-remark-box'));

                        boxes.sort((a, b) => {
                            const idxA = parseInt(a.className.match(/float-box-(\d+)/)[1]);
                            const idxB = parseInt(b.className.match(/float-box-(\d+)/)[1]);
                            return idxA - idxB;
                        });

                        let lastBottom = rect.top; // Start stacking from canvas top
                        const margin = 12;
                        
                        // Responsive positioning adjustments
                        const viewportWidth = window.innerWidth;
                        let horizontalOffset = 50; // Default offset
                        let dotSize = 10; // Default dot size
                        let maxBoxWidth = 260; // Default max width
                        
                        if (viewportWidth <= 768) {
                            horizontalOffset = 25;
                            dotSize = 8;
                            maxBoxWidth = 180;
                        } else if (viewportWidth <= 576) {
                            horizontalOffset = 15;
                            dotSize = 6;
                            maxBoxWidth = 140;
                        } else if (viewportWidth <= 480) {
                            horizontalOffset = 10;
                            dotSize = 5;
                            maxBoxWidth = 120;
                        } else if (viewportWidth <= 360) {
                            horizontalOffset = 5;
                            dotSize = 4;
                            maxBoxWidth = 100;
                        }

                        boxes.forEach(box => {
                            const index = parseInt(box.className.match(/float-box-(\d+)/)[1]);
                            const dot = layer.querySelector(`.float-dot-${index}`);
                            const line = svgLayer.querySelector(`.float-line-${index}`);

                            const topPercent = tuning.firstRowTop + (index - 1) * tuning.rowHeight;
                            const rowMidPx = (topPercent + (tuning.rowHeight / 2)) / 100 * rect.height;

                            // Dot positioning (Center of column)
                            const columnX = rect.left + (1 - (tuning.colRight + (tuning.colWidth / 2)) / 100) * rect.width;
                            const dotY = rect.top + rowMidPx;

                            if (dot) {
                                dot.style.left = (columnX - (dotSize / 2)) + 'px';
                                dot.style.top = (dotY - (dotSize / 2)) + 'px';
                                dot.style.width = dotSize + 'px';
                                dot.style.height = dotSize + 'px';
                            }

                            // Card positioning with stacking - adjust for screen size
                            const idealTop = dotY - (box.offsetHeight / 2);
                            let finalTop = Math.max(idealTop, lastBottom + margin);
                            
                            // Ensure box doesn't go off screen on small devices
                            const maxLeft = window.innerWidth - box.offsetWidth - 20;
                            const boxLeft = Math.min(rect.left + rect.width + horizontalOffset, maxLeft);
                            
                            box.style.top = finalTop + 'px';
                            box.style.left = boxLeft + 'px';

                            // Update SVG line with responsive positioning
                            if (line) {
                                const lineEndX = Math.min(rect.left + rect.width + horizontalOffset, maxLeft);
                                line.setAttribute("x1", columnX);
                                line.setAttribute("y1", dotY);
                                line.setAttribute("x2", lineEndX);
                                line.setAttribute("y2", finalTop + (box.offsetHeight / 2));
                            }

                            lastBottom = finalTop + box.offsetHeight;
                        });
                    }
                } catch (e) { }
                requestAnimationFrame(syncOverlay);
            }

            iframe.onload = function () {
                requestAnimationFrame(syncOverlay);
            };

            inputs.forEach(input => {
                // Initial state: hide boxes
                updateFloatingBox(input.dataset.index, input.value, false);

                input.addEventListener('input', function () {
                    updateFloatingBox(this.dataset.index, this.value, true);
                });
                input.addEventListener('focus', function () {
                    updateFloatingBox(this.dataset.index, this.value, true);
                });
                input.addEventListener('blur', function () {
                    updateFloatingBox(this.dataset.index, this.value, false);
                });
            });

            // Handle window resize for responsive bubbles
            window.addEventListener('resize', function() {
                requestAnimationFrame(syncOverlay);
            });

            // Handle form submission
            document.getElementById('saveRemarksBtn').addEventListener('click', function () {
                const btn = this;
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
                const formData = new FormData(remarksForm);
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Success: Download and notify
                            const downloadLink = document.createElement('a');
                            downloadLink.href = data.download_url;
                            downloadLink.download = '';
                            document.body.appendChild(downloadLink);
                            downloadLink.click();
                            document.body.removeChild(downloadLink);
                            if (window.parent) {
                                window.parent.postMessage({
                                    type: 'qf02RemarksCompleted',
                                    success: true,
                                    message: data.message
                                }, '*');
                            }
                        } else {
                            alert('Error: ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = originalHtml;
                        }
                    })
                    .catch(error => {
                        alert('Request failed: ' + error.message);
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
            });
        });
    </script>

    <?php if (!$is_modal): ?>
        <?php include '../includes/auth_footer.php'; ?>
    <?php else: ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>

    </html>
<?php endif; ?>

<?php closeDBConnection($conn); ?>