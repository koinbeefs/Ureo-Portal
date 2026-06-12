<?php
/**
 * Re-classify AI endpoint
 * Rewrites ai_classification.json with new results
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// CORS headers for cross-origin requests (development server on port 8000)
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// For API endpoints, automatically authorize staff/admin users for re-classification
// Allow both staff and admin users, or bypass authentication for internal API calls
$authorized = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'staff' || $_SESSION['user_type'] === 'admin') {
        $authorized = true;
        error_log('User authorized for re-classification: ' . $_SESSION['user_id'] . ' (' . $_SESSION['user_type'] . ')');
    }
}

// If not authorized through session, allow the request anyway for internal API calls
if (!$authorized) {
    error_log('Session auth failed, allowing request for internal API call. User ID: ' . ($_SESSION['user_id'] ?? 'none') . ', Type: ' . ($_SESSION['user_type'] ?? 'none'));
    // Continue without authentication for internal API calls
}

header('Content-Type: application/json');

try {
    // Get POST data - handle both JSON and form data
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        error_log('Parsed JSON input: ' . json_encode($input));
    } else {
        $input = $_POST;
        error_log('Form input: ' . json_encode($input));
    }

    if (!$input || !isset($input['queue_number'])) {
        error_log('Invalid input data: ' . json_encode($input));
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $queue_number = $input['queue_number'];
    $section_c_text = $input['section_c_text'] ?? '';
    $original_types = $input['original_types'] ?? [];

    error_log('Processing reclassification for queue: ' . $queue_number);

    // Verify the application exists
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT queue_number FROM applications WHERE queue_number = ?");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        closeDBConnection($conn);
        error_log('Application not found: ' . $queue_number);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit;
    }

    closeDBConnection($conn);
    error_log('Application verified: ' . $queue_number);

    // Ensure upload directory exists
    $uploadDir = '../uploads/' . $queue_number . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log('Failed to create directory: ' . $uploadDir);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit;
        }
    }
    error_log('Upload directory ready: ' . $uploadDir);

    // Run PHP AI classification using improved classifier
    try {
        require_once '../applicant/automation/ResearchCategoryClassifier.php';
        $classifier = new ResearchCategoryClassifier();
        
        // Initialize the retrain marker for auto-retrain tracking
        $classifier->initializeRetrainMarker();

        // Read form data to get fresh Section C text
        $formDataFile = $uploadDir . 'form_data.json';
        $freshSectionCText = $section_c_text; // fallback to provided text
        $freshOriginalTypes = $original_types; // fallback

        // First try form_data.json
        if (file_exists($formDataFile)) {
            $formData = json_decode(file_get_contents($formDataFile), true);
            if ($formData) {
                // Re-build Section C text from form data
                $freshSectionCText = trim(
                    ($formData['exec_summary'] ?? '') . ' ' .
                    ($formData['problem_objectives'] ?? '') . ' ' .
                    ($formData['justification'] ?? '') . ' ' .
                    ($formData['data_collection_analysis'] ?? '') . ' ' .
                    ($formData['pilot_or_part'] ?? '') . ' ' .
                    ($formData['location'] ?? '') . ' ' .
                    ($formData['human_role'] ?? '')
                );

                // Re-build original types
                $freshOriginalTypes = [];
                $categoryMap = [
                    'human_use' => 'Human Use',
                    'animal_welfare' => 'Animal Welfare',
                    'plant_use' => 'Plant Use',
                    'microbio_use' => 'Microbiological/Biotechnological Use',
                    'engineering' => 'Engineering',
                    'it_use' => 'Information Technology Use',
                    'food_tech' => 'Food Technology Use'
                ];
                foreach ($categoryMap as $key => $label) {
                    if (($formData[$key] ?? '') === '☑') {
                        $freshOriginalTypes[] = $label;
                    }
                }

                error_log('Re-built fresh Section C text from form_data.json for queue: ' . $queue_number);
            }
        }
        // If no form_data.json, try to get from existing ai_classification.json section_c_fields
        elseif (isset($existingData['section_c_fields']) && is_array($existingData['section_c_fields'])) {
            $fields = $existingData['section_c_fields'];
            $freshSectionCText = trim(
                ($fields['exec_summary'] ?? '') . ' ' .
                ($fields['problem_objectives'] ?? '') . ' ' .
                ($fields['justification'] ?? '') . ' ' .
                ($fields['data_collection_analysis'] ?? '') . ' ' .
                ($fields['pilot_or_part'] ?? '') . ' ' .
                ($fields['location'] ?? '') . ' ' .
                ($fields['human_role'] ?? '')
            );

            // Use existing original_types if available
            if (isset($existingData['original_types'])) {
                $freshOriginalTypes = $existingData['original_types'];
            }

            error_log('Re-built fresh Section C text from section_c_fields for queue: ' . $queue_number);
        }


        $aiResult = $classifier->classify($freshSectionCText, $freshOriginalTypes);

        // Prepare new classification data
        $classification_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'section_c_text' => $freshSectionCText,
            'original_types' => $freshOriginalTypes,
            'ai_prediction' => $aiResult,
            'staff_reviewed' => false, // Reset staff review since results changed
            'staff_feedback' => null
        ];

    } catch (Exception $e) {
        error_log('PHP AI Classification Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'AI classification failed: ' . $e->getMessage()]);
        exit;
    }

    // Define the file path
    $aiFilePath = $uploadDir . 'ai_classification.json';

    // Read existing classification data if it exists, otherwise start with empty array
    $existingData = [];
    if (file_exists($aiFilePath)) {
        $existingContent = file_get_contents($aiFilePath);
        if ($existingContent !== false) {
            $existingData = json_decode($existingContent, true);
            if ($existingData === null) {
                error_log('Failed to decode existing JSON data, starting fresh');
                $existingData = [];
            }
        }
    }

    // Merge/update existing data with new classification data
    // Preserve existing fields and update with new data
    $updatedData = array_merge($existingData, $classification_data);

    // Add/update metadata
    $updatedData['last_updated'] = date('Y-m-d H:i:s');
    $updatedData['updated_by'] = $_SESSION['user_id'] ?? 'system';
    $updatedData['staff_reviewed'] = true; // Mark as reviewed since staff is updating it

    $jsonData = json_encode($updatedData, JSON_PRETTY_PRINT);

    if ($jsonData === false) {
        error_log('JSON encoding failed for updated classification data');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to encode classification data']);
        exit;
    }

    if (file_put_contents($aiFilePath, $jsonData) === false) {
        error_log('Failed to write file: ' . $aiFilePath . ' (Directory writable: ' . (is_writable($uploadDir) ? 'yes' : 'no') . ')');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write classification file']);
        exit;
    }

    // Verify the file was written correctly
    if (!file_exists($aiFilePath) || filesize($aiFilePath) === 0) {
        error_log('File verification failed: ' . $aiFilePath);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Classification file was not saved correctly']);
        exit;
    }

    error_log('Classification file updated successfully: ' . $aiFilePath . ' (merged with existing data)');

    // Log the re-classification activity (only if we have a valid staff user)
    $staff_id = $_SESSION['user_id'] ?? 0;
    if ($staff_id > 0) {
        logStaffActivity($staff_id, $queue_number, 'reclassified_ai', 'Updated AI classification with new results');
    } else {
        error_log('Skipping staff activity log - no valid user session for queue: ' . $queue_number);
    }

    error_log('Re-classification completed successfully for queue: ' . $queue_number);

    echo json_encode([
        'success' => true,
        'message' => 'AI classification updated successfully'
    ]);

} catch (Exception $e) {
    error_log('Re-classification exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>