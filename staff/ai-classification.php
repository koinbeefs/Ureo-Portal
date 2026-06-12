<?php
/**
 * AI Classification Review Page (Staff)
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Text formatting helper function
function formatTextContent($text) {
    if (preg_match('/^\d+\.\s/', $text) || preg_match('/\d+\.\s.*\n/', $text)) {
        $items = preg_split('/(?=\d+\.\s)/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $output = '<ul class="list-unstyled" style="line-height: 1.8;">';
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            $lines = explode("\n", $item);
            $mainItem = array_shift($lines);
            $output .= '<li class="mb-3"><strong>' . htmlspecialchars($mainItem) . '</strong>';
            if (!empty($lines)) {
                $output .= '<ul class="list-unstyled ms-4 mt-1">';
                foreach ($lines as $subLine) {
                    $subLine = trim($subLine);
                    if (!empty($subLine)) {
                        $output .= '<li class="mb-1">' . htmlspecialchars($subLine) . '</li>';
                    }
                }
                $output .= '</ul>';
            }
            $output .= '</li>';
        }
        $output .= '</ul>';
    } else {
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $text);
        $currentParagraph = '';
        $sentenceCount = 0;
        $output = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;
            $currentParagraph .= $sentence . ' ';
            $sentenceCount++;
            if ($sentenceCount >= 3 || str_word_count($currentParagraph) > 100) {
                $output .= '<p class="mb-3" style="line-height: 1.7; text-align: justify;">' . htmlspecialchars(trim($currentParagraph)) . '</p>';
                $currentParagraph = '';
                $sentenceCount = 0;
            }
        }
        if (!empty(trim($currentParagraph))) {
            $output .= '<p class="mb-0" style="line-height: 1.7; text-align: justify;">' . htmlspecialchars(trim($currentParagraph)) . '</p>';
        }
    }
    return $output;
}

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';
$staff_id = $_SESSION['user_id'];

if (empty($queue_number)) {
    die('Error: No queue number provided');
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    die('Error: Application not found');
}

// Auto-claim unassigned applications
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ? WHERE queue_number = ? AND assigned_staff_id IS NULL");
    $claim_stmt->bind_param("is", $staff_id, $queue_number);
    $claim_stmt->execute();

    if ($claim_stmt->affected_rows > 0) {
        $just_claimed = true;
        $application['assigned_staff_id'] = $staff_id; // Update the local copy

        // Log the auto-claim activity
        logStaffActivity($staff_id, $queue_number, 'other', 'Auto-claimed application for AI classification review');
    }
}

// Check if current user can edit this application
$can_edit = ($application['assigned_staff_id'] == $staff_id || !$application['assigned_staff_id']);

// Check for AI classification
$ai_classification = null;
$ai_file_path = '../uploads/' . $queue_number . '/ai_classification.json';
if (!file_exists($ai_file_path)) {
    die('Error: AI classification not found');
}

$ai_classification = json_decode(file_get_contents($ai_file_path), true);
if (!$ai_classification) {
    die('Error: Unable to load AI classification data');
}

// Check for form data to show individual fields
$form_data = null;
$form_data_path = '../uploads/' . $queue_number . '/form_data.json';
if (file_exists($form_data_path)) {
    $form_data = json_decode(file_get_contents($form_data_path), true);
}

// Also check for section_c_fields in ai_classification
$section_c_fields = $ai_classification['section_c_fields'] ?? null;

// Log activity
logStaffActivity($staff_id, $queue_number, 'viewed_application', 'Reviewed AI classification');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Classification Review - TAU UREO Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root {
            --tau-green-dark: #006400;
            --tau-green-primary: #228B22;
            --tau-green-light: #e8f5e9;
            --tau-accent: #ffd700;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .main-container {
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .left-panel {
            background-color: white;
            border-right: 1px solid #dee2e6;
            box-shadow: inset -1px 0 0 rgba(0,0,0,0.1);
        }
        .right-panel {
            background-color: #f8f9fa;
            overflow-y: auto;
            max-height: 100vh;
        }
        .document-header {
            background: linear-gradient(135deg, #006400, #228B22);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .ai-results-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .section-card {
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .section-card .card-header {
            background: white;
            border-bottom: 2px solid #f8f9fa;
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--tau-green-dark);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            line-height: 1.4;
        }

        .stats-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        .progress {
            border-radius: 8px;
            height: 24px;
            background-color: #e9ecef;
        }
        .badge-modern {
            border-radius: 20px;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
        }
        .btn-modern {
            border-radius: 25px;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            transition: all 0.3s ease;
        }
        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #dee2e6, transparent);
            margin: 2rem 0;
        }
        .stats-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        .ai-reasoning {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-left: 4px solid #007bff;
            border-radius: 8px;
            padding: 1.5rem;
        }
        .form-check-input:checked {
            background-color: #006400;
            border-color: #006400;
        }
        .offcanvas {
            border-radius: 12px 0 0 12px;
            box-shadow: -4px 0 12px rgba(0,0,0,0.15);
        }
        .timeline-marker {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #dee2e6;
        }
        .alert-modern {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<?php if ($just_claimed): ?>
<div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
    <i class="bi bi-hand-index-thumb"></i> Application has been automatically assigned to you for AI classification review.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$can_edit): ?>
<div class="alert alert-warning alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
    <i class="bi bi-lock"></i> This application is assigned to another staff member and cannot be edited.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="main-container">
    <div class="row g-0 h-100">
        <!-- Left Panel: Document Viewer -->
        <div class="col-md-6 left-panel">
            <div class="document-header">
                <h6 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>QF-01 Document Viewer</h6>
            </div>
            <div class="p-3">
                <?php
                // Find the QF-01 document
                $qf01_doc = null;
                $docs_for_ai = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? AND document_type = 'qf01' ORDER BY upload_timestamp DESC LIMIT 1");
                $docs_for_ai->bind_param("s", $queue_number);
                $docs_for_ai->execute();
                $qf01_result = $docs_for_ai->get_result();
                if ($qf01_result->num_rows > 0) {
                    $qf01_doc = $qf01_result->fetch_assoc();
                    $file_path = '../' . $qf01_doc['file_path'];
                    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

                    if ($file_ext === 'pdf') {
                        echo '<iframe src="view-document.php?path=' . urlencode($qf01_doc['file_path']) . '" style="width: 100%; height: calc(100vh - 140px); border: 1px solid #dee2e6; border-radius: 8px;"></iframe>';
                    } elseif ($file_ext === 'docx') {
                        echo '<div id="docxViewer" style="padding: 20px; background: white; border: 1px solid #dee2e6; border-radius: 8px; min-height: calc(100vh - 140px);"></div>';
                        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>';
                        echo '<script>
                            fetch("view-document.php?path=' . urlencode($qf01_doc['file_path']) . '")
                                .then(response => response.arrayBuffer())
                                .then(arrayBuffer => mammoth.convertToHtml({arrayBuffer: arrayBuffer}))
                                .then(result => {
                                    document.getElementById("docxViewer").innerHTML = result.value;
                                })
                                .catch(err => {
                                    document.getElementById("docxViewer").innerHTML = "<div class=\'alert alert-danger alert-modern\'>Error loading document: " + err.message + "</div>";
                                });
                        </script>';
                    }
                } else {
                    echo '<div class="alert alert-warning alert-modern text-center py-5">
                            <i class="bi bi-file-earmark-x display-4 text-warning mb-3"></i>
                            <h6>QF-01 Document Not Found</h6>
                            <p class="text-muted mb-0">The QF-01 document could not be located for this application.</p>
                          </div>';
                }
                ?>
            </div>
        </div>

        <!-- Right Panel: AI Results -->
        <div class="col-md-6 right-panel">
            <div class="ai-results-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-cpu-fill me-2"></i>AI Classification Results</h6>
                <button type="button" class="btn btn-light btn-sm btn-modern" id="reclassifyBtn" title="Re-run AI classification" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                    <i class="bi bi-arrow-clockwise me-1"></i>Re-classify
                </button>
            </div>
            <div class="p-4">
                <!-- AI Prediction -->
                <div class="section-card mb-4">
                    <div class="card-header">
                        <i class="bi bi-robot"></i> AI Prediction
                        <small class="text-muted ms-auto">System-generated classification result</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h3 class="text-primary mb-3 fw-bold">
                                <i class="bi bi-tag-fill me-2"></i>
                                <?php echo htmlspecialchars($ai_classification['ai_prediction']['predicted'] ?? 'Unknown'); ?>
                            </h3>
                            <div class="d-flex justify-content-center gap-3 mb-3">
                                <?php
                                $score = ($ai_classification['ai_prediction']['max_score'] ?? 0) * 100;
                                $confidence = $ai_classification['ai_prediction']['learning_stats']['confidence'] ?? 'low';
                                $badge_color = $confidence === 'high' ? 'success' : ($confidence === 'moderate' ? 'warning' : 'danger');
                                ?>
                                <div class="text-center">
                                    <div class="badge bg-<?php echo $badge_color; ?> badge-modern fs-6 mb-1">
                                        <?php echo number_format($score, 1); ?>% Confidence
                                    </div>
                                    <small class="text-muted d-block"><?php echo ucfirst($confidence); ?> confidence</small>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($ai_classification['ai_prediction']['similar_past_cases'])): ?>
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-bar-chart-line me-1"></i>Similarity Analysis</h6>
                            <?php
                            $totalSimilarity = 0;
                            foreach ($ai_classification['ai_prediction']['similar_past_cases'] as $case) {
                                $totalSimilarity += ($case['score'] ?? 0);
                            }
                            $avgSimilarity = $totalSimilarity / count($ai_classification['ai_prediction']['similar_past_cases']);
                            $avgPercent = round($avgSimilarity * 100);

                            // Determine badge color based on average similarity
                            if ($avgPercent >= 70) {
                                $simBadgeColor = 'success';
                            } elseif ($avgPercent >= 50) {
                                $simBadgeColor = 'warning';
                            } else {
                                $simBadgeColor = 'danger';
                            }
                            ?>
                            <div class="text-center mb-3">
                                <span class="badge bg-<?php echo $simBadgeColor; ?> badge-modern fs-6">
                                    <?php echo $avgPercent; ?>% Average Similarity
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div>
                            <h6 class="text-muted mb-3"><i class="bi bi-graph-up me-1"></i>All Category Scores</h6>
                            <div class="mb-3">
                                <?php
                                // Calculate average past case scores by category
                                $pastCaseScores = [];
                                if (!empty($ai_classification['ai_prediction']['similar_past_cases'])) {
                                    foreach ($ai_classification['ai_prediction']['similar_past_cases'] as $case) {
                                        $category = $case['label'] ?? 'Unknown';
                                        if (!isset($pastCaseScores[$category])) {
                                            $pastCaseScores[$category] = ['total' => 0, 'count' => 0];
                                        }
                                        $pastCaseScores[$category]['total'] += ($case['score'] ?? 0);
                                        $pastCaseScores[$category]['count']++;
                                    }
                                    // Calculate averages
                                    foreach ($pastCaseScores as $cat => $data) {
                                        $pastCaseScores[$cat] = $data['total'] / $data['count'];
                                    }
                                }

                                foreach (($ai_classification['ai_prediction']['scores'] ?? []) as $cat => $cat_score):
                                    $hasPastData = isset($pastCaseScores[$cat]);
                                    $pastScore = $hasPastData ? $pastCaseScores[$cat] : 0;
                                ?>
                                    <div class="mb-3 p-3 bg-light rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-semibold text-truncate" style="max-width: 40%; font-size: 0.9rem;"><?php echo htmlspecialchars($cat); ?></span>
                                            <div class="d-flex gap-2" style="width: 55%;">
                                                <!-- Current AI Score -->
                                                <div class="progress flex-fill" style="height: 20px;" title="Current AI: <?php echo number_format($cat_score * 100, 0); ?>%">
                                                    <div class="progress-bar bg-primary" role="progressbar"
                                                         style="width: <?php echo ($cat_score * 100); ?>%"
                                                         aria-valuenow="<?php echo ($cat_score * 100); ?>"
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <small class="text-white fw-bold"><?php echo number_format($cat_score * 100, 0); ?>%</small>
                                                    </div>
                                                </div>
                                                <!-- Past Cases Average Score -->
                                                <?php if ($hasPastData): ?>
                                                    <div class="progress flex-fill" style="height: 20px;" title="Past Cases Avg: <?php echo number_format($pastScore * 100, 0); ?>%">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                             style="width: <?php echo ($pastScore * 100); ?>%"
                                                             aria-valuenow="<?php echo ($pastScore * 100); ?>"
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <small class="text-white fw-bold"><?php echo number_format($pastScore * 100, 0); ?>%</small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="progress flex-fill" style="height: 20px;">
                                                        <div class="progress-bar bg-secondary" role="progressbar" style="width: 0%">
                                                            <small class="text-white">N/A</small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <!-- Legend -->
                                <div class="d-flex gap-3 justify-content-center small text-muted">
                                    <span><span class="badge bg-primary">&nbsp;&nbsp;</span> Current AI</span>
                                    <span><span class="badge bg-success">&nbsp;&nbsp;</span> Past Cases Avg</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Reasoning -->
                <div class="section-card mb-4">
                    <div class="card-header">
                        <i class="bi bi-brain"></i> AI Analysis & Reasoning
                        <small class="text-muted ms-auto">System's decision-making process</small>
                    </div>
                    <div class="card-body p-4">
                        <!-- Section C Text Analyzed -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2"><i class="bi bi-file-text"></i> Section C Text Analyzed:</h6>
                            <div class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto; font-size: 0.9em; border-left: 4px solid #6c757d;">
                                <?php
                                $sectionCText = $ai_classification['section_c_text'] ?? 'No text available';

                                // Split by tab characters (field separators)
                                $parts = preg_split('/\t+/', $sectionCText);

                                foreach ($parts as $index => $part) {
                                    $part = trim($part);
                                    if (empty($part)) continue;

                                    // Check if this part contains numbered lists (1. 2. 3. etc.)
                                    if (preg_match('/^\d+\.\s/', $part) || preg_match('/\d+\.\s.*\n/', $part)) {
                                        // Split into individual numbered items
                                        $items = preg_split('/(?=\d+\.\s)/', $part, -1, PREG_SPLIT_NO_EMPTY);

                                        echo '<div class="mb-3">';
                                        echo '<ul class="list-unstyled" style="line-height: 1.8;">';
                                        foreach ($items as $item) {
                                            $item = trim($item);
                                            if (empty($item)) continue;

                                            // Split item into lines to handle sub-bullets
                                            $lines = explode("\n", $item);
                                            $mainItem = array_shift($lines); // First line is the main numbered item

                                            echo '<li class="mb-3"><strong>' . htmlspecialchars($mainItem) . '</strong>';

                                            // Handle sub-bullets
                                            if (!empty($lines)) {
                                                echo '<ul class="list-unstyled ms-4 mt-1">';
                                                foreach ($lines as $subLine) {
                                                    $subLine = trim($subLine);
                                                    if (!empty($subLine)) {
                                                        echo '<li class="mb-1">' . htmlspecialchars($subLine) . '</li>';
                                                    }
                                                }
                                                echo '</ul>';
                                            }
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                        echo '</div>';
                                    } else {
                                        // Regular paragraph - split by sentence boundaries for better readability
                                        $text = $part;

                                        // First, try to split by obvious sentence endings followed by capital letters
                                        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $text);

                                        echo '<div class="mb-3">';

                                        $currentParagraph = '';
                                        $sentenceCount = 0;

                                        foreach ($sentences as $sentence) {
                                            $sentence = trim($sentence);
                                            if (empty($sentence)) continue;

                                            $currentParagraph .= $sentence . ' ';
                                            $sentenceCount++;

                                            // Create new paragraph every 3-4 sentences or when word count exceeds 120
                                            if ($sentenceCount >= 3 || str_word_count($currentParagraph) > 120) {
                                                echo '<p class="mb-3" style="line-height: 1.7; text-align: justify;">' . htmlspecialchars(trim($currentParagraph)) . '</p>';
                                                $currentParagraph = '';
                                                $sentenceCount = 0;
                                            }
                                        }

                                        // Output remaining paragraph
                                        if (!empty(trim($currentParagraph))) {
                                            echo '<p class="mb-3" style="line-height: 1.7; text-align: justify;">' . htmlspecialchars(trim($currentParagraph)) . '</p>';
                                        }

                                        echo '</div>';
                                    }

                                    // Add visual separator between major sections (not after last one)
                                    if ($index < count($parts) - 1) {
                                        echo '<hr class="my-3" style="border-top: 2px solid rgba(0,0,0,0.1);">';
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Individual Section C Fields (if form data or section_c_fields available) -->
                        <?php if ($form_data || $section_c_fields): ?>
                        <div class="mb-4">
                            <button class="btn btn-outline-primary btn-modern" type="button" data-bs-toggle="offcanvas" data-bs-target="#sectionCFieldsDrawer" aria-controls="sectionCFieldsDrawer">
                                <i class="bi bi-list-check me-1"></i>View Detailed Section C Fields
                            </button>
                        </div>
                        <?php endif; ?>

                        <div class="section-divider"></div>

                        <!-- AI Analysis -->
                        <div>
                            <h6 class="text-muted mb-2"><i class="bi bi-robot"></i> AI Analysis & Reasoning:</h6>
                            <div class="bg-light p-3 rounded" style="border-left: 4px solid #007bff;">
                                <?php
                                $reasoning = $ai_classification['ai_prediction']['reason'] ?? 'No reasoning provided';

                                // Escape HTML first for security
                                $reasoning = htmlspecialchars($reasoning);

                                // Convert headers (### Header)
                                $reasoning = preg_replace('/### (.*?)(\n|$)/', '<h6 class="mb-3 mt-2 text-primary fw-bold">$1</h6>', $reasoning);

                                // Convert bold text (**text**)
                                $reasoning = preg_replace('/\*\*(.*?)\*\*/', '<strong class="text-dark">$1</strong>', $reasoning);

                                // Convert double line breaks to paragraph breaks
                                $reasoning = preg_replace('/\n\n/', '</p><p class="mb-2">', $reasoning);

                                // Convert single line breaks to <br>
                                $reasoning = preg_replace('/\n/', '<br>', $reasoning);

                                // Wrap in paragraph
                                $reasoning = '<div style="line-height: 1.8;">' . $reasoning . '</div>';

                                echo $reasoning;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Original Types Selected by Applicant -->
                <?php if (!empty($ai_classification['original_types'])): ?>
                <div class="section-card mb-4">
                    <div class="card-header">
                        <i class="bi bi-person-circle"></i> Applicant's Selection
                        <small class="text-muted ms-auto">Categories chosen by the applicant</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($ai_classification['original_types'] as $type): ?>
                                <span class="badge bg-secondary badge-modern fs-6">
                                    <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($type); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                These are the research categories selected by the applicant during the initial application process.
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Similar Past Cases -->
                <?php if (!empty($ai_classification['ai_prediction']['similar_past_cases'])): ?>
                <div class="section-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-bar-chart-line-fill"></i>
                            <span>Learning Data Analysis</span>
                            <small class="text-muted ms-2">Similar past cases used for training</small>
                        </div>
                        <button class="btn btn-outline-dark btn-sm btn-modern" type="button" data-bs-toggle="collapse" data-bs-target="#pastCasesDetails" aria-expanded="false" aria-controls="pastCasesDetails">
                            <i class="bi bi-chevron-down me-1"></i>Details
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <?php
                        // Calculate aggregated statistics
                        $totalCases = count($ai_classification['ai_prediction']['similar_past_cases']);
                        $totalSimilarity = 0;
                        $categoryCount = [];
                        
                        foreach ($ai_classification['ai_prediction']['similar_past_cases'] as $case) {
                            $totalSimilarity += ($case['score'] ?? 0);
                            $label = $case['label'] ?? 'Unknown';
                            if (!isset($categoryCount[$label])) {
                                $categoryCount[$label] = 0;
                            }
                            $categoryCount[$label]++;
                        }
                        
                        $avgSimilarity = $totalSimilarity / $totalCases;
                        $avgPercent = round($avgSimilarity * 100);
                        arsort($categoryCount);
                        $mostCommon = array_key_first($categoryCount);
                        $mostCommonCount = $categoryCount[$mostCommon];
                        ?>
                        
                        <!-- Aggregated Summary -->
                        <div class="mb-4">
                            <div class="row text-center g-3">
                                <div class="col-md-4">
                                    <div class="stats-card">
                                        <div class="stats-number text-primary"><?php echo $totalCases; ?></div>
                                        <div class="stats-label">Similar Cases Found</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stats-card">
                                        <div class="stats-number text-<?php echo $avgPercent >= 70 ? 'success' : ($avgPercent >= 50 ? 'warning' : 'danger'); ?>"><?php echo $avgPercent; ?>%</div>
                                        <div class="stats-label">Average Similarity</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stats-card">
                                        <div class="stats-label mb-1">Most Common</div>
                                        <div class="stats-number text-info" style="font-size: 1.1rem !important;"><?php echo htmlspecialchars($mostCommon); ?></div>
                                        <div class="stats-label"><?php echo $mostCommonCount; ?> cases</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Collapsible Details -->
                        <div class="collapse" id="pastCasesDetails">
                            <div class="section-divider"></div>
                            <div class="mb-3">
                                <h6 class="text-muted"><i class="bi bi-info-circle me-1"></i>Learning Insights</h6>
                                <p class="text-muted small mb-3">The AI learned from these similar cases to improve its prediction accuracy and confidence scoring.</p>
                            </div>
                            <?php foreach ($ai_classification['ai_prediction']['similar_past_cases'] as $idx => $case): ?>
                                <div class="mb-3 p-3 bg-light rounded border-start border-primary border-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="timeline-marker me-3"></div>
                                            <strong class="text-primary">Case <?php echo ($idx + 1); ?>:</strong>
                                        </div>
                                        <span class="badge bg-<?php echo strpos($case['status'], 'Agreed') !== false ? 'success' : 'danger'; ?> badge-modern">
                                            <?php echo htmlspecialchars($case['status']); ?>
                                        </span>
                                    </div>
                                    <div class="ms-4">
                                        <small class="text-muted d-block mb-1">
                                            <strong>AI Prediction:</strong> <span class="text-primary"><?php echo htmlspecialchars($case['system_predicted'] ?? 'Unknown'); ?></span> →
                                            <strong>Staff Decision:</strong> <span class="text-success"><?php echo htmlspecialchars($case['label'] ?? 'Unknown'); ?></span>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-percent me-1"></i>Similarity Score: <strong><?php echo number_format(($case['score'] ?? 0) * 100, 0); ?>%</strong>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Staff Feedback Section -->
                <?php if ($ai_classification['staff_reviewed'] && !empty($ai_classification['staff_feedback'])): ?>
                    <div class="alert alert-success alert-modern">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-success fw-bold">Review Completed</h6>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <strong class="text-dark">Action:</strong>
                                        <span class="badge bg-primary badge-modern ms-1"><?php echo ucfirst($ai_classification['staff_feedback']['action'] ?? 'Unknown'); ?></span>
                                    </div>
                                    <div class="col-sm-6">
                                        <strong class="text-dark">Final Category:</strong>
                                        <span class="badge bg-info badge-modern ms-1"><?php echo htmlspecialchars($ai_classification['staff_feedback']['final_category'] ?? 'Not specified'); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($ai_classification['staff_feedback']['staff_note'])): ?>
                                    <div class="mt-2">
                                        <strong class="text-dark">Notes:</strong>
                                        <div class="mt-1 p-2 bg-light rounded small"><?php echo htmlspecialchars($ai_classification['staff_feedback']['staff_note']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted">
                                        <i class="bi bi-person me-1"></i>Reviewed by <?php echo htmlspecialchars($ai_classification['staff_feedback']['reviewed_by'] ?? 'Unknown'); ?>
                                        on <?php echo formatDate($ai_classification['staff_feedback']['reviewed_at'] ?? null); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="section-card">
                        <div class="card-header">
                            <i class="bi bi-person-check"></i> Staff Review Required
                            <small class="text-muted ms-auto">Accept AI prediction or provide correction</small>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-info alert-modern mb-4">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Review Process:</strong> Evaluate the AI classification results above and either accept the prediction or provide a corrected category with notes.
                            </div>
                            
                            <div id="aiFeedbackForm">
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark mb-3">Your Decision:</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="aiAction" id="aiAccept" value="accept" autocomplete="off" checked>
                                        <label class="btn btn-outline-success btn-modern flex-fill" for="aiAccept">
                                            <i class="bi bi-check-circle me-2"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">Accept AI Prediction</div>
                                                <small>Use the suggested category</small>
                                            </div>
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="aiAction" id="aiCorrect" value="correct" autocomplete="off">
                                        <label class="btn btn-outline-warning btn-modern flex-fill" for="aiCorrect">
                                            <i class="bi bi-pencil-square me-2"></i>
                                            <div class="text-start">
                                                <div class="fw-bold">Correct Prediction</div>
                                                <small>Choose different category</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div id="correctionSection" style="display: none;">
                                    <div class="card bg-light border-warning mb-3">
                                        <div class="card-body">
                                            <h6 class="text-warning mb-3"><i class="bi bi-exclamation-triangle me-1"></i>Correction Required</h6>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold mb-3">Correct Category/Categories:</label>
                                                <small class="text-muted d-block mb-2">Select one or more categories that apply:</small>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Human Use" id="catHumanUse">
                                                            <label class="form-check-label" for="catHumanUse">Human Use</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Animal Welfare" id="catAnimalWelfare">
                                                            <label class="form-check-label" for="catAnimalWelfare">Animal Welfare</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Plant Use" id="catPlantUse">
                                                            <label class="form-check-label" for="catPlantUse">Plant Use</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Microbiological/Biotechnological Use" id="catMicrobio">
                                                            <label class="form-check-label" for="catMicrobio">Microbiological/Biotechnological Use</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Engineering" id="catEngineering">
                                                            <label class="form-check-label" for="catEngineering">Engineering</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Information Technology Use" id="catITUse">
                                                            <label class="form-check-label" for="catITUse">Information Technology Use</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input category-checkbox" type="checkbox" value="Food Technology Use" id="catFoodTech">
                                                            <label class="form-check-label" for="catFoodTech">Food Technology Use</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="categoryRequiredAlert" class="alert alert-warning mt-2 mb-0 py-2 d-none">
                                                    <small><i class="bi bi-exclamation-circle me-1"></i>Please select at least one category.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark">Additional Notes <small class="text-muted">(Optional)</small>:</label>
                                    <textarea class="form-control" id="staffNote" rows="4" placeholder="Add any notes about your decision or reasoning..."></textarea>
                                    <div class="form-text">These notes will help improve future AI classifications.</div>
                                </div>

                                <button type="button" class="btn btn-primary btn-modern w-100 py-3" id="submitAiFeedback" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                    <i class="bi bi-send-fill me-2"></i>Submit Review
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($form_data || $section_c_fields): ?>
<!-- Section C Fields Offcanvas (placed at body level for proper overlay) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="sectionCFieldsDrawer" aria-labelledby="sectionCFieldsDrawerLabel" style="width: 600px;">
    <div class="offcanvas-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
        <h5 class="offcanvas-title" id="sectionCFieldsDrawerLabel">
            <i class="bi bi-list-check me-2"></i>Detailed Section C Fields
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Executive Summary -->
        <?php 
        $execSummary = ($form_data['exec_summary'] ?? $section_c_fields['exec_summary'] ?? '');
        if (!empty($execSummary)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-file-text text-primary me-1"></i> Executive Summary</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($execSummary); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Problem Statement & Objectives -->
        <?php 
        $problemObjectives = ($form_data['problem_objectives'] ?? $section_c_fields['problem_objectives'] ?? '');
        if (!empty($problemObjectives)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-bullseye text-primary me-1"></i> Problem Statement & Objectives</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($problemObjectives); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Justification -->
        <?php 
        $justification = ($form_data['justification'] ?? $section_c_fields['justification'] ?? '');
        if (!empty($justification)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-check-circle text-primary me-1"></i> Justification</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($justification); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Collection & Analysis -->
        <?php 
        $dataCollection = ($form_data['data_collection_analysis'] ?? $section_c_fields['data_collection_analysis'] ?? '');
        if (!empty($dataCollection)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-graph-up text-primary me-1"></i> Data Collection & Analysis</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($dataCollection); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pilot Study or Partnership -->
        <?php 
        $pilotOrPart = ($form_data['pilot_or_part'] ?? $section_c_fields['pilot_or_part'] ?? '');
        if (!empty($pilotOrPart)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-people text-primary me-1"></i> Pilot Study or Partnership</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($pilotOrPart); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Location -->
        <?php 
        $location = ($form_data['location'] ?? $section_c_fields['location'] ?? '');
        if (!empty($location)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-geo-alt text-primary me-1"></i> Location</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($location); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Human Role -->
        <?php 
        $humanRole = ($form_data['human_role'] ?? $section_c_fields['human_role'] ?? '');
        if (!empty($humanRole)): 
        ?>
        <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-person text-primary me-1"></i> Human Role</h6>
            <div class="bg-light p-3 rounded" style="border-left: 3px solid #007bff;">
                <?php echo formatTextContent($humanRole); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
<?php if (!($ai_classification['staff_reviewed'] && !empty($ai_classification['staff_feedback']))): ?>
document.addEventListener('DOMContentLoaded', function() {
    const acceptRadio = document.getElementById('aiAccept');
    const correctRadio = document.getElementById('aiCorrect');
    const correctionSection = document.getElementById('correctionSection');
    const submitBtn = document.getElementById('submitAiFeedback');
    
    // Ensure correction section is hidden initially
    if (correctionSection) {
        correctionSection.style.display = 'none';
    }
    
    // Toggle correction section
    if (acceptRadio && correctRadio) {
        acceptRadio.addEventListener('change', function() {
            if (this.checked) {
                correctionSection.style.display = 'none';
            }
        });
        
        correctRadio.addEventListener('change', function() {
            if (this.checked) {
                correctionSection.style.display = 'block';
            }
        });
        
        // Also add click handlers to labels as fallback
        const acceptLabel = document.querySelector('label[for="aiAccept"]');
        const correctLabel = document.querySelector('label[for="aiCorrect"]');
        
        if (acceptLabel) {
            acceptLabel.addEventListener('click', function() {
                setTimeout(() => {
                    if (acceptRadio.checked) {
                        correctionSection.style.display = 'none';
                    }
                }, 10);
            });
        }
        
        if (correctLabel) {
            correctLabel.addEventListener('click', function() {
                setTimeout(() => {
                    if (correctRadio.checked) {
                        correctionSection.style.display = 'block';
                    }
                }, 10);
            });
        }
    } else {
    }
    
    // Handle form submission
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            const action = document.querySelector('input[name="aiAction"]:checked')?.value;
            const staffNote = document.getElementById('staffNote')?.value;
            
            // Get selected categories (allows multi-select)
            const selectedCategories = [];
            const checkboxes = document.querySelectorAll('.category-checkbox:checked');
            checkboxes.forEach(cb => selectedCategories.push(cb.value));
            
            // Validation
            if (action === 'correct' && selectedCategories.length === 0) {
                document.getElementById('categoryRequiredAlert').classList.remove('d-none');
                return;
            } else {
                document.getElementById('categoryRequiredAlert').classList.add('d-none');
            }
            
            const correctedCategory = action === 'correct' ? selectedCategories.join(', ') : '';
            
            // Confirm submission
            const confirmMsg = action === 'accept' 
                ? 'Are you sure you want to accept the AI prediction?' 
                : 'Are you sure you want to submit this correction?';
                
            if (!confirm(confirmMsg)) {
                return;
            }
            
            // Disable button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('queue_number', '<?php echo $queue_number; ?>');
            formData.append('action', action);
            if (action === 'correct') {
                formData.append('corrected_category', correctedCategory);
                // Also send individual categories for granular logging
                selectedCategories.forEach((cat, index) => {
                    formData.append('corrected_categories[' + index + ']', cat);
                });
            }
            formData.append('staff_note', staffNote);
            
            // Submit via AJAX
            fetch('handle-ai-feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Notify parent window and close
                    if (window.parent) {
                        window.parent.postMessage({
                            type: 'aiReviewCompleted',
                            success: true,
                            message: data.message
                        }, '*');
                    }
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Submit Review';
                }
            })
            .catch(error => {
                alert('Error submitting feedback: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Submit Review';
            });
        });
    }
});
<?php endif; ?>

// Re-classification functionality
document.getElementById('reclassifyBtn').addEventListener('click', function() {
    if (!confirm('Are you sure you want to re-run the AI classification? This will replace the current results.')) {
        return;
    }

    const btn = this;
    const originalHtml = btn.innerHTML;

    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Re-classifying...';

    // Get current AI data
    const currentData = <?php echo json_encode($ai_classification); ?>;

    // Call PHP classifier via AJAX
    fetch('reclassify-ai.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            queue_number: '<?php echo $queue_number; ?>',
            section_c_text: currentData.section_c_text,
            original_types: currentData.original_types
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('AI classification failed: ' + response.status + ' ' + response.statusText);
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            alert('AI classification completed successfully! The page will now refresh to show the new results.');
            location.reload();
        } else {
            throw new Error(result.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        alert('Error during re-classification: ' + error.message);
        // Reset button
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
});

</script>

</body>
</html>