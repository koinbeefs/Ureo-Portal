<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$queue_number = $_POST['queue_number'] ?? null;
$action = $_POST['action'] ?? null; // 'accept' or 'correct'
$corrected_category = $_POST['corrected_category'] ?? null;
$staff_note = $_POST['staff_note'] ?? '';

if (!$queue_number || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $conn = getDBConnection();

    // Get application details and check permissions
    $app_stmt = $conn->prepare("SELECT assigned_staff_id, current_status FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();

    if (!$application) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit;
    }

    // Check if current user can edit this application
    $can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);
    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this application']);
        exit;
    }

    // Load AI classification result
    $aiFile = '../uploads/' . $queue_number . '/ai_classification.json';
    
    if (!file_exists($aiFile)) {
        echo json_encode(['success' => false, 'message' => 'AI classification file not found']);
        exit;
    }
    
    $aiData = json_decode(file_get_contents($aiFile), true);
    
    if (!$aiData) {
        echo json_encode(['success' => false, 'message' => 'Invalid AI classification data']);
        exit;
    }
    
    // Determine human label and agreement
    $systemPredicted = $aiData['ai_prediction']['predicted'] ?? 'Unknown';
    
    // Handle multiple categories (comma-separated from multi-select checkboxes)
    if ($action === 'correct') {
        // Parse comma-separated categories into an array
        $correctedCategories = array_filter(array_map('trim', explode(',', $corrected_category)));
        if (empty($correctedCategories)) {
            echo json_encode(['success' => false, 'message' => 'At least one corrected category is required']);
            exit;
        }
        $humanLabel = implode(', ', $correctedCategories);
    } else {
        $humanLabel = $systemPredicted;
    }
    $agreed = ($action === 'accept');
    
    if ($action === 'correct' && empty($corrected_category)) {
        echo json_encode(['success' => false, 'message' => 'Corrected category is required']);
        exit;
    }
    
    // Prepare history entry
    $historyEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'filename' => 'QF01_' . $queue_number,
        'section_c_text' => $aiData['section_c_text'] ?? '',
        'original_types' => $aiData['original_types'] ?? [],
        'system_predicted' => $systemPredicted,
        'system_score' => $aiData['ai_prediction']['max_score'] ?? 0,
        'system_reason' => $aiData['ai_prediction']['reason'] ?? '',
        'human_label' => $humanLabel,
        'human_note' => $staff_note,
        'agreed' => $agreed,
        'staff_id' => $_SESSION['user_id'] ?? null,
        'staff_name' => $_SESSION['full_name'] ?? 'Unknown'
    ];
    
    // Append to history.jsonl
    $historyFile = '../applicant/automation/history.jsonl';
    $historyLine = json_encode($historyEntry) . "\n";
    
    if (file_put_contents($historyFile, $historyLine, FILE_APPEND | LOCK_EX) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to write to history file']);
        exit;
    }
    
    // Update AI classification file with staff feedback
    $aiData['staff_reviewed'] = true;
    $aiData['staff_feedback'] = [
        'action' => $action,
        'final_category' => $humanLabel,
        'staff_note' => $staff_note,
        'reviewed_by' => $_SESSION['full_name'] ?? 'Unknown',
        'reviewed_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($aiFile, json_encode($aiData, JSON_PRETTY_PRINT));

    // Learn from the correction using improved PHP-ML classifier
    try {
        require_once '../applicant/automation/ResearchCategoryClassifier.php';
        $classifier = ResearchCategoryClassifier::getInstance();
        
        // Initialize the retrain marker on first use
        $classifier->initializeRetrainMarker();

        $systemPredicted = $aiData['ai_prediction']['predicted'] ?? 'Unknown';
        $sectionCText = $aiData['section_c_text'] ?? '';

        // Teach the classifier about this correction
        // This will auto-retrain if enough new corrections have been collected
        $classifier->learnFromCorrection($sectionCText, $systemPredicted, $humanLabel, $staff_note);

    } catch (Throwable $e) {
        // Log the error but don't fail the feedback submission
        error_log('PHP AI Learning Error: ' . $e->getMessage());
    }
    
    // Log staff activity
    logStaffActivity(
        $_SESSION['user_id'],
        $queue_number,
        'ai_feedback',
        "Reviewed AI classification: " . ($agreed ? 'Accepted' : 'Corrected to ' . $humanLabel)
    );
    
    echo json_encode([
        'success' => true,
        'message' => $agreed ? 'AI prediction accepted' : 'Correction recorded',
        'action' => $action,
        'final_category' => $humanLabel
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
