<?php
/**
 * Debug Workflow Progression Issue
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== UREO-0003 Detailed Analysis ===\n";

// Get complete application details
$app_sql = "SELECT * FROM applications WHERE queue_number = 'UREO-0003'";
$app_result = $conn->query($app_sql);
$app = $app_result->fetch_assoc();

if ($app) {
    echo "Queue Number: {$app['queue_number']}\n";
    echo "Current Status: {$app['current_status']}\n";
    echo "Applicant Email: {$app['applicant_email']}\n";
    echo "Applicant Name: {$app['applicant_name']}\n";
    echo "Category: {$app['category']}\n";
    echo "Assigned Staff ID: {$app['assigned_staff_id']}\n";
    echo "UREC Committee ID: {$app['urec_committee_id']}\n";
    echo "UREC Reviewed By: {$app['urec_reviewed_by']}\n";
    echo "Submission Date: {$app['submission_timestamp']}\n";
    echo "Last Updated: {$app['last_updated']}\n";
    echo "Has Additional Requirements: " . ($app['has_additional_requirements'] ? 'Yes' : 'No') . "\n";
    echo "Completion Attempts: {$app['completion_attempts']}\n";
} else {
    echo "Application UREO-0003 not found.\n";
    exit();
}

echo "\n=== Documents Submitted ===\n";
$docs_sql = "SELECT document_type, document_name, validation_status, upload_timestamp FROM documents WHERE queue_number = 'UREO-0003'";
$docs_result = $conn->query($docs_sql);

if ($docs_result->num_rows > 0) {
    while ($doc = $docs_result->fetch_assoc()) {
        echo "Type: {$doc['document_type']}, Name: {$doc['document_name']}, Status: {$doc['validation_status']}, Uploaded: {$doc['upload_timestamp']}\n";
    }
} else {
    echo "No documents found.\n";
}

echo "\n=== Status History ===\n";
$history_sql = "SELECT previous_status, new_status, notes, timestamp, changed_by_type FROM status_history WHERE queue_number = 'UREO-0003' ORDER BY timestamp";
$history_result = $conn->query($history_sql);

if ($history_result->num_rows > 0) {
    while ($hist = $history_result->fetch_assoc()) {
        echo "From: {$hist['previous_status']} → To: {$hist['new_status']} ({$hist['timestamp']}) by {$hist['changed_by_type']}\n";
        if ($hist['notes']) {
            echo "  Notes: {$hist['notes']}\n";
        }
    }
} else {
    echo "No status history found.\n";
}

echo "\n=== Required Documents Checklist ===\n";
$req_docs_sql = "SELECT document_type, display_name, mandatory, is_conditional FROM required_documents WHERE active = 1 ORDER BY display_order";
$req_docs_result = $conn->query($req_docs_sql);

$required_docs = [];
while ($req_doc = $req_docs_result->fetch_assoc()) {
    $required_docs[] = $req_doc;
    echo "Required: {$req_doc['document_type']} - {$req_doc['display_name']} (Mandatory: " . ($req_doc['mandatory'] ? 'Yes' : 'No') . ")\n";
}

echo "\n=== Missing Documents Analysis ===\n";
$submitted_types = [];
if ($docs_result->num_rows > 0) {
    $docs_result->data_seek(0); // Reset pointer
    while ($doc = $docs_result->fetch_assoc()) {
        $submitted_types[] = $doc['document_type'];
    }
}

$missing = [];
foreach ($required_docs as $req_doc) {
    if (!in_array($req_doc['document_type'], $submitted_types) && $req_doc['mandatory']) {
        $missing[] = $req_doc['document_type'];
        echo "Missing: {$req_doc['document_type']} - {$req_doc['display_name']}\n";
    }
}

if (empty($missing)) {
    echo "All mandatory documents have been submitted.\n";
}

echo "\n=== Workflow Progression Requirements ===\n";
require_once 'config/config.php';

echo "To move from INTENT_RECEIVED, the system typically checks:\n";
echo "1. All required documents submitted\n";
echo "2. Documents validated\n";
echo "3. Application categorized\n";
echo "4. Staff assigned for initial review\n\n";

echo "Current Assessment:\n";
echo "✓ Documents submitted: " . (count($submitted_types) >= count($required_docs) ? 'YES' : 'NO') . "\n";
echo "✓ Documents validated: " . ($app['current_status'] !== 'INTENT_RECEIVED' ? 'YES' : 'NO - Still in INTENT_RECEIVED') . "\n";
echo "✓ Application categorized: " . ($app['category'] ? 'YES (' . $app['category'] . ')' : 'NO') . "\n";
echo "✓ Staff assigned: " . ($app['assigned_staff_id'] ? 'YES' : 'NO') . "\n";

closeDBConnection($conn);
?>
