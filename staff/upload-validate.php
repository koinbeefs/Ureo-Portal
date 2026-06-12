<?php
// upload-validate.php
// ────────────────────────────────────────────────────────────────
// Improved version – more robust section C extraction + fallback
// ────────────────────────────────────────────────────────────────

require_once 'vendor/autoload.php';
use PhpOffice\PhpWord\IOFactory;

$uploadMessage = '';
$sectionC = '';
$fullTextForDebug = '';     // for fallback & debug
$detectedTypes = [];
$mlResult = [];
$humanLabel = '';
$humanNote = '';
$saveSuccess = false;
$usedFallback = false;
$systemClassifications = [];

// Load system classifications
if (file_exists('system-classifications.json')) {
    $systemClassifications = json_decode(file_get_contents('system-classifications.json'), true) ?? [];
}

// ────────────────────────────────────────────────────────────────
// Helper: Extract text from section C.3 (Detailed Description of the Methodology)
// ────────────────────────────────────────────────────────────────
function extractSectionC($phpWord) {
    // First, extract ALL text from the document
    $fullText = '';
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            $fullText .= extractTextFromElement($element) . " ";
        }
    }
    $fullText = trim($fullText);

    // Focus on C.3 triggers - try multiple patterns
    $startTriggers = [
        'C.3 Detailed Description of the Methodology', 
        'C.3 Detailed Description',
        'C.3', 'C3', 'c.3', 'c3',
        'Detailed Description of the Methodology', 'detailed description', 'methodology',
        'Description of the Methodology', 'description of methodology',
        'location/specific site of the study', 'location/site of the study', 
        'specific site of the study', 'location of the study',
        'If humans will be the subject', 'role of human', 'human subjects',
        'subjects are animals, plants, microorganisms'
    ];

    $stopTriggers = [
        'Informed Consent Form attached', 'consent attached', 'consent form',
        'informed consent', 'consent',
        'Declaration', 'Adviser\'s Approval', 'Adviser Approval',
        'adviser approval', 'adviser\'s approval',
        'I certify that', 'ethical principles', 'final report',
        'TAU-Research Ethics Review Committee', 'ethics clearance certificate',
        'D. Declaration', 'd. declaration'
    ];

    $lowerFull = strtolower($fullText);
    $startPos = -1;
    $stopPos = strlen($fullText);

    // Find earliest start position for C.3
    foreach ($startTriggers as $trigger) {
        $pos = stripos($lowerFull, strtolower($trigger));
        if ($pos !== false && ($startPos === -1 || $pos < $startPos)) {
            $startPos = $pos;
        }
    }

    // If no C.3 found, try to find any section C content
    if ($startPos === -1) {
        $fallbackTriggers = [
            'C. Project/Study Description', 'c. project', 'section c',
            'C.1', 'c.1', 'Executive Summary', 'executive summary',
            'C.2', 'c.2', 'Statement of the problem', 'objectives'
        ];
        foreach ($fallbackTriggers as $trigger) {
            $pos = stripos($lowerFull, strtolower($trigger));
            if ($pos !== false && ($startPos === -1 || $pos < $startPos)) {
                $startPos = $pos;
            }
        }
    }

    // Find earliest stop position after start
    if ($startPos !== -1) {
        foreach ($stopTriggers as $trigger) {
            $pos = stripos($lowerFull, strtolower($trigger), $startPos);
            if ($pos !== false && $pos > $startPos && $pos < $stopPos) {
                $stopPos = $pos;
            }
        }
    }

    if ($startPos === -1) {
        // Couldn't find any section markers, return full text
        return $fullText;
    }

    $sectionText = substr($fullText, $startPos, $stopPos - $startPos);
    $sectionText = trim($sectionText);

    // If extracted text is too short, return full text
    if (strlen($sectionText) < 30) {
        return $fullText;
    }

    return $sectionText;
}

// Helper to extract text from any element recursively
function extractTextFromElement($element) {
    $text = '';
    
    // Handle tables specially
    if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        foreach ($element->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $cellElement) {
                    $text .= extractTextFromElement($cellElement) . " ";
                }
                $text .= "\n";
            }
        }
        return $text;
    }
    
    // Handle text runs
    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        foreach ($element->getElements() as $subElement) {
            if (method_exists($subElement, 'getText')) {
                $text .= $subElement->getText();
            }
        }
        return $text;
    }
    
    // Try getText method
    if (method_exists($element, 'getText')) {
        $text = $element->getText() ?? '';
    }
    
    // Recursively get text from sub-elements
    if (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $subElement) {
            $text .= extractTextFromElement($subElement);
        }
    }
    
    return $text;
}

// ────────────────────────────────────────────────────────────────
// Helper: Detect checked review types
// ────────────────────────────────────────────────────────────────
function detectReviewTypes($fullText) {
    $types = [];
    $map = [
        'Human Use'                              => 'Human Use',
        'Animal Welfare'                         => 'Animal Welfare',
        'Plant Use'                              => 'Plant Use',
        'Microbiological/Biotechnological Use'   => 'Microbiological/Biotechnological Use',
        'Microbiological/Biotechnical Use'       => 'Microbiological/Biotechnological Use', // typo variation
        'Engineering'                            => 'Engineering',
        'Information Technology Use'             => 'Information Technology Use',
        'Food Technology Use'                    => 'Food Technology Use',
    ];

    $lower = strtolower($fullText);

    // First, try to find the review type section
    $reviewSection = '';
    if (preg_match('/(review applied for|review type|type of review|applied for)[^.!?]*[.!?]/i', $lower, $matches)) {
        $reviewSection = $matches[0];
    }

    // If we found a review section, search within it first
    $searchText = $reviewSection ?: $lower;

    foreach ($map as $search => $label) {
        // Multiple detection strategies
        $found = false;

        // Strategy 1: Look for explicit checkbox symbols
        $checkboxPatterns = [
            // Unicode checkbox symbols
            '/☑\s*' . preg_quote(strtolower($search), '/') . '/i',
            '/☑\s*' . preg_quote(strtolower($label), '/') . '/i',
            '/\[x\]\s*' . preg_quote(strtolower($search), '/') . '/i',
            '/\[x\]\s*' . preg_quote(strtolower($label), '/') . '/i',
            '/\[X\]\s*' . preg_quote(strtolower($search), '/') . '/i',
            '/\[X\]\s*' . preg_quote(strtolower($label), '/') . '/i',
            '/✓\s*' . preg_quote(strtolower($search), '/') . '/i',
            '/✓\s*' . preg_quote(strtolower($label), '/') . '/i',
            '/√\s*' . preg_quote(strtolower($search), '/') . '/i',
            '/√\s*' . preg_quote(strtolower($label), '/') . '/i',
            '/yes\s*' . preg_quote(strtolower($search), '/') . '/i',
            '/yes\s*' . preg_quote(strtolower($label), '/') . '/i',
            '/x\s+' . preg_quote(strtolower($search), '/') . '/i',
            '/x\s+' . preg_quote(strtolower($label), '/') . '/i',
            '/X\s+' . preg_quote(strtolower($search), '/') . '/i',
            '/X\s+' . preg_quote(strtolower($label), '/') . '/i',
            // Reverse order
            '/' . preg_quote(strtolower($search), '/') . '\s*☑/i',
            '/' . preg_quote(strtolower($label), '/') . '\s*☑/i',
            '/' . preg_quote(strtolower($search), '/') . '\s*\[x\]/i',
            '/' . preg_quote(strtolower($label), '/') . '\s*\[x\]/i',
            '/' . preg_quote(strtolower($search), '/') . '\s*✓/i',
            '/' . preg_quote(strtolower($label), '/') . '\s*✓/i',
        ];

        foreach ($checkboxPatterns as $pattern) {
            if (preg_match($pattern, $searchText)) {
                $types[] = $label;
                $found = true;
                break;
            }
        }

        // Strategy 2: If no explicit checkboxes, look for the label in review context
        if (!$found && $reviewSection) {
            if (preg_match('/\b' . preg_quote(strtolower($search), '/') . '\b/i', $reviewSection)) {
                $types[] = $label;
                $found = true;
            }
        }
    }

    // Strategy 3: Fallback - if still no types found, check the entire document
    if (empty($types)) {
        foreach ($map as $search => $label) {
            // Look for the exact label text in various contexts
            if (preg_match('/review.{0,300}' . preg_quote(strtolower($search), '/') . '/i', $lower) ||
                preg_match('/applied.{0,300}' . preg_quote(strtolower($search), '/') . '/i', $lower) ||
                preg_match('/type.{0,300}' . preg_quote(strtolower($search), '/') . '/i', $lower) ||
                preg_match('/\b' . preg_quote(strtolower($search), '/') . '\b.{0,50}(checked|selected|yes|true)/i', $lower)) {
                $types[] = $label;
            }
        }
    }

    return array_unique($types);
}
// ────────────────────────────────────────────────────────────────
// Process file upload
// ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['docx_file']) && $_FILES['docx_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['docx_file']['tmp_name'];
    $fileName = $_FILES['docx_file']['name'];

    if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'docx') {
        $uploadMessage = "Please upload a .docx file only.";
    } else {
        try {
            $phpWord = IOFactory::load($fileTmp);

            // ── Full text for type detection & fallback ──
            $fullText = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $el) {
                    $fullText .= extractTextFromElement($el) . "\n";
                }
            }
            $fullTextForDebug = trim($fullText);

            $detectedTypes = detectReviewTypes($fullTextForDebug);
            
            // Debug: show what we're looking for
            if (empty($detectedTypes)) {
                error_log("DEBUG: No review types detected. Full text length: " . strlen($fullTextForDebug));
                error_log("DEBUG: Contains 'human use': " . (stripos($fullTextForDebug, 'human use') !== false ? 'YES' : 'NO'));
                error_log("DEBUG: Text sample: " . substr($fullTextForDebug, 0, 500));
            }

            // Try to extract section C
            $sectionC = extractSectionC($phpWord);

            // Check if we got full document (fallback) or extracted section
            $isFullDocument = (strlen($sectionC) > 1000); // Rough heuristic - full docs are much longer

            if ($isFullDocument) {
                $usedFallback = true;
                $uploadMessage = "Document processed successfully.<br>Note: Using full document text (section isolation not possible).";
            } else {
                $uploadMessage = "Document processed successfully.<br>Extracted relevant section content.";
            }

            // ── Send to Flask ──
            $payload = json_encode([
                'section_c' => $sectionC,
                'original_types' => $detectedTypes,
                'used_fallback'  => $usedFallback,
                'focus_section' => 'C.3 Methodology'  // Now focusing on C.3 Detailed Description of the Methodology
            ]);

            $ch = curl_init('http://127.0.0.1:5001/classify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $mlResult = json_decode($response, true) ?? [];
            } else {
                $uploadMessage .= "<br>ML service error (HTTP $httpCode): " . ($curlErr ?: 'No response');
            }
        } catch (Exception $e) {
            $uploadMessage = "Error loading document: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ────────────────────────────────────────────────────────────────
// Save staff review
// ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_review') {
    $record = [
        'timestamp'       => date('c'),
        'filename'        => $_POST['filename'] ?? 'manual.docx',
        'section_c_text'  => substr($_POST['section_c'] ?? '', 0, 3000), // truncate if huge
        'original_types'  => json_decode($_POST['original_types'] ?? '[]', true),
        'system_predicted'=> $_POST['system_predicted'] ?? '',
        'system_score'    => floatval($_POST['system_score'] ?? 0),
        'system_reason'   => $_POST['system_reason'] ?? '',
        'human_label'     => $_POST['human_label'] ?? '',
        'human_note'      => $_POST['human_note'] ?? '',
        'agreed'          => ($_POST['human_label'] ?? '') === ($_POST['system_predicted'] ?? '')
    ];

    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents('history.jsonl', $line, FILE_APPEND | LOCK_EX);

    $saveSuccess = true;
    $uploadMessage = "Review saved to history.jsonl";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload & Validate Ethics Form</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1100px; margin: 30px auto; line-height: 1.6; }
        .container { padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .message { padding: 12px; border-radius: 6px; margin: 15px 0; }
        .success { background: #e8f5e9; color: #2e7d32; }
        .warning { background: #fff3cd; color: #856404; }
        .error   { background: #ffebee; color: #c62828; }
        textarea { width: 100%; height: 160px; font-family: Consolas, monospace; padding: 10px; }
        label { display: block; margin: 12px 0 6px; font-weight: bold; }
        button { padding: 10px 28px; background: #1976d2; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #1565c0; }
        .review-box { margin-top: 30px; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 8px; }
        .debug-box { background: #f0f0f0; padding: 12px; border-left: 4px solid #757575; margin: 15px 0; font-size: 0.95rem; }
    </style>
</head>
<body>

<h1>Upload & Validate TAU Ethics Application</h1>

<?php if ($uploadMessage): ?>
    <div class="message <?= $saveSuccess ? 'success' : (strpos($uploadMessage, 'Warning') !== false ? 'warning' : 'error') ?>">
        <?= nl2br(htmlspecialchars($uploadMessage)) ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <label>Upload filled .docx file:</label>
    <input type="file" name="docx_file" accept=".docx" required>
    <br><br>
    <button type="submit">Upload & Analyze</button>
</form>

<?php if (!empty($sectionC) || $usedFallback): ?>
    <div class="review-box">
        <h2>Analysis Result</h2>

        <?php if ($usedFallback): ?>
            <p class="warning"><strong>Fallback mode active:</strong> Section C could not be isolated → full document text was sent to the classifier.</p>
        <?php endif; ?>

        <h3>Detected review type(s) in document:</h3>
        <p><strong><?= $detectedTypes ? implode(', ', $detectedTypes) : 'None / Not detected' ?></strong></p>

        <h3>System draft classification:</h3>
        <?php if (!empty($mlResult)): ?>
            <?php
                // Check if there's a high-confidence past case (>30% similarity)
                $usePastCase = false;
                $pastCasePrimary = null;
                $pastCaseScore = 0;
                $pastCaseReason = '';

                if (!empty($mlResult['similar_past_cases'])) {
                    foreach ($mlResult['similar_past_cases'] as $past) {
                        if (($past['score'] ?? 0) > 0.30) {
                            $usePastCase = true;
                            $pastCasePrimary = $past['label'] ?? null;
                            $pastCaseScore = round(($past['score'] ?? 0) * 100, 1);
                            $pastCaseReason = "Based on similar past case with {$pastCaseScore}% similarity";
                            break; // Use the first (highest scoring) past case
                        }
                    }
                }

                // Determine the primary prediction
                $primaryPrediction = $usePastCase ? $pastCasePrimary : ($mlResult['predicted'] ?? '—');
                $primaryScore = $usePastCase ? $pastCaseScore : (isset($mlResult['max_score']) ? round($mlResult['max_score'] * 100, 1) : 0);
                $primaryReason = $usePastCase ? $pastCaseReason : ($mlResult['reason'] ?? 'No reason provided');
            ?>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">
                <p><strong>Primary prediction:</strong> <?= htmlspecialchars($primaryPrediction) ?>
                    <?php if ($usePastCase): ?>
                        <span style="background: #4caf50; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.85em; margin-left: 8px;">From Past Cases</span>
                    <?php endif; ?>
                </p>
                <p><strong>Confidence score:</strong> <?= $primaryScore ?>%</p>
                <p><strong>Reason:</strong> <?= htmlspecialchars($primaryReason) ?></p>

                <?php
                // Generate dynamic classification options
                $dynamicOptions = [];
                
                // Add the primary prediction
                if (!empty($primaryPrediction) && $primaryPrediction !== '—') {
                    $dynamicOptions[] = [
                        'category' => $primaryPrediction,
                        'confidence' => $primaryScore,
                        'type' => $usePastCase ? 'past_case' : 'primary',
                        'reason' => $usePastCase ? 'Based on similar past case' : 'Best semantic match for section C content'
                    ];
                }

                // If using past case, still show the original ML prediction as an alternative
                if ($usePastCase && !empty($mlResult['predicted']) && $mlResult['predicted'] !== $primaryPrediction) {
                    $dynamicOptions[] = [
                        'category' => $mlResult['predicted'],
                        'confidence' => round(($mlResult['max_score'] ?? 0) * 100, 1),
                        'type' => 'ml_alternative',
                        'reason' => 'System semantic analysis suggestion'
                    ];
                }

                // Add other classification options
                if (!empty($mlResult['predicted']) && !empty($systemClassifications['classifications'][$mlResult['predicted']])) {
                    $mlPredicted = $mlResult['predicted'];
                    $config = $systemClassifications['classifications'][$mlPredicted];

                    // Add alternatives if confidence is below threshold or if configured to include them
                    $maxAlternatives = $systemClassifications['fallback_rules']['max_alternatives'] ?? 2;
                    if (count($config['alternatives'] ?? []) > 0 &&
                        ($mlResult['max_score'] < ($config['confidence_threshold'] ?? 0.85) ||
                         ($systemClassifications['fallback_rules']['include_alternatives'] ?? true))) {

                        $altCount = 0;
                        foreach ($config['alternatives'] as $alt) {
                            if ($altCount >= $maxAlternatives) break;
                            
                            // Skip if already added
                            $alreadyAdded = false;
                            foreach ($dynamicOptions as $opt) {
                                if ($opt['category'] === $alt) {
                                    $alreadyAdded = true;
                                    break;
                                }
                            }
                            if ($alreadyAdded) continue;

                            // Check if this alternative has a reasonable score
                            $altScore = $mlResult['scores'][$alt] ?? 0;
                            if ($altScore > ($systemClassifications['fallback_rules']['low_confidence_threshold'] ?? 0.60)) {
                                $dynamicOptions[] = [
                                    'category' => $alt,
                                    'confidence' => round($altScore * 100, 1),
                                    'type' => 'alternative',
                                    'reason' => 'Alternative classification option'
                                ];
                                $altCount++;
                            }
                        }
                    }
                }

                // Add other high-scoring categories as additional options
                if (!empty($mlResult['scores'])) {
                    arsort($mlResult['scores']); // Sort by score descending
                    $addedCategories = array_column($dynamicOptions, 'category');
                    $additionalCount = 0;
                    $maxAdditional = 2;

                    foreach ($mlResult['scores'] as $cat => $score) {
                        if ($additionalCount >= $maxAdditional) break;
                        if (in_array($cat, $addedCategories)) continue;
                        if ($score < ($systemClassifications['fallback_rules']['low_confidence_threshold'] ?? 0.60)) continue;

                        $dynamicOptions[] = [
                            'category' => $cat,
                            'confidence' => round($score * 100, 1),
                            'type' => 'additional',
                            'reason' => 'High-scoring alternative from semantic analysis'
                        ];
                        $additionalCount++;
                    }
                }
                ?>

                <?php if (!empty($dynamicOptions)): ?>
                    <h4>Classification Options:</h4>
                    <div style="display: grid; gap: 8px;">
                        <?php foreach ($dynamicOptions as $option): ?>
                            <?php 
                                $bgColor = '#fff';
                                $textColor = '#666';
                                $badge = '';
                                
                                if ($option['type'] === 'past_case') {
                                    $bgColor = '#e8f5e9';
                                    $textColor = '#2e7d32';
                                    $badge = '★ Past Case';
                                } elseif ($option['type'] === 'primary') {
                                    $bgColor = '#e3f2fd';
                                    $textColor = '#1565c0';
                                    $badge = '◆ ML Prediction';
                                } elseif ($option['type'] === 'ml_alternative') {
                                    $bgColor = '#fff3e0';
                                    $textColor = '#e65100';
                                    $badge = '◇ ML Suggestion';
                                } elseif ($option['type'] === 'alternative') {
                                    $bgColor = '#fce4ec';
                                    $textColor = '#c2185b';
                                    $badge = '○ Alternative';
                                } else {
                                    $badge = '• Additional';
                                }
                            ?>
                            <div style="padding: 10px; border: 2px solid <?= $option['type'] === 'past_case' ? '#4caf50' : '#ddd' ?>; border-radius: 3px; background: <?= $bgColor ?>">
                                <strong style="color: <?= $textColor ?>; font-size: 1.05em;">
                                    <?= htmlspecialchars($option['category']) ?>
                                </strong>
                                <span style="float: right; font-weight: bold; color: <?= $textColor ?>;"><?= $option['confidence'] ?>%</span>
                                <br>
                                <span style="background: <?= $textColor ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-top: 4px; display: inline-block;">
                                    <?= $badge ?>
                                </span>
                                <br><small style="color: #555; margin-top: 4px; display: block;"><?= htmlspecialchars($option['reason']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($mlResult['scores'])): ?>
                <details style="margin: 10px 0;">
                    <summary>All category scores</summary>
                    <ul style="margin: 8px 0 0 20px;">
                        <?php foreach ($mlResult['scores'] as $cat => $score): ?>
                            <li><?= htmlspecialchars($cat) ?>: <?= round($score * 100, 1) ?>%</li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <?php if (!empty($mlResult['similar_past_cases'])): ?>
                <h4>Similar past cases (learning from history, 30% threshold):</h4>
                <div style="background: #f0f7ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <?php foreach ($mlResult['similar_past_cases'] as $past): ?>
                        <?php 
                            $pastScore = round(($past['score'] ?? 0) * 100, 1);
                            $isHighConfidence = ($past['score'] ?? 0) > 0.30;
                        ?>
                        <div style="padding: 8px; border-bottom: 1px solid #ddd; background: <?= $isHighConfidence ? '#e8f5e9' : 'transparent' ?>;">
                            <strong style="color: <?= $isHighConfidence ? '#2e7d32' : '#1976d2' ?>;">
                                <?= $pastScore ?>% similar → labeled <strong><?= htmlspecialchars($past['label']) ?></strong>
                            </strong>
                            <?php if ($isHighConfidence): ?>
                                <span style="background: #4caf50; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.8em; margin-left: 8px;">
                                    ✓ Used as Primary
                                </span>
                            <?php endif; ?>
                            <br><small style="color: #555;"><?= htmlspecialchars($past['snippet']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($mlResult['learning_stats'])): ?>
                    <p style="font-size: 0.9em; color: #666;">
                        <strong>Learning Status:</strong>
                        <?= $mlResult['learning_stats']['total_history_count'] ?> total cases in history,
                        <?= $mlResult['learning_stats']['similar_cases_found'] ?> similar cases found (≥30% match),
                        Confidence: <?= htmlspecialchars($mlResult['learning_stats']['confidence_level']) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size: 0.9em; color: #999;">No similar past cases found in learning history.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">No classification result (ML service may be down).</p>
        <?php endif; ?>

        <hr style="margin: 25px 0;">

        <h3>Staff / UREO Review</h3>
        <form method="post">
            <input type="hidden" name="action" value="save_review">
            <input type="hidden" name="section_c" value="<?= htmlspecialchars($sectionC) ?>">
            <input type="hidden" name="original_types" value="<?= htmlspecialchars(json_encode($detectedTypes)) ?>">
            <input type="hidden" name="system_predicted" value="<?= htmlspecialchars($primaryPrediction ?? ($mlResult['predicted'] ?? '')) ?>">
            <input type="hidden" name="system_score" value="<?= $primaryScore ?? ($mlResult['max_score'] ?? 0) ?>">
            <input type="hidden" name="system_reason" value="<?= htmlspecialchars($primaryReason ?? ($mlResult['reason'] ?? '')) ?>">
            <input type="hidden" name="filename" value="<?= htmlspecialchars($fileName ?? 'manual_upload.docx') ?>">

            <label>Correct / Final classification:</label>
            <select name="human_label" required style="padding: 8px; width: 100%; max-width: 400px;">
                <option value="">— Please select —</option>
                <option value="Human Use" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Human Use' ? 'selected' : '' ?>>Human Use</option>
                <option value="Animal Welfare" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Animal Welfare' ? 'selected' : '' ?>>Animal Welfare</option>
                <option value="Plant Use" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Plant Use' ? 'selected' : '' ?>>Plant Use</option>
                <option value="Microbiological/Biotechnological Use" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Microbiological/Biotechnological Use' ? 'selected' : '' ?>>Microbiological/Biotechnological Use</option>
                <option value="Engineering" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
                <option value="Information Technology Use" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Information Technology Use' ? 'selected' : '' ?>>Information Technology Use</option>
                <option value="Food Technology Use" <?= ($primaryPrediction ?? ($mlResult['predicted'] ?? '')) === 'Food Technology Use' ? 'selected' : '' ?>>Food Technology Use</option>
            </select>

            <label>Notes / Explanation for change (optional):</label>
            <textarea name="human_note" placeholder="e.g. Applicant selected Human Use, but content is clearly about plant field trials with no human involvement..."></textarea>

            <br><br>
            <button type="submit">Save Review → Add to Learning History</button>
        </form>

        <?php if (strlen($sectionC) < 500): ?>
            <div class="debug-box">
                <strong>Debug – extracted content (<?= $isFullDocument ? 'full document' : 'section C.3' ?>):</strong><br>
                <pre style="white-space: pre-wrap;"><?= htmlspecialchars(substr($sectionC, 0, 800)) ?></pre>
                <?php if (strlen($sectionC) > 800): ?>
                    <em>... (truncated, total length: <?= strlen($sectionC) ?> characters)</em>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>