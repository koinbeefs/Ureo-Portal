<?php
/**
 * Debug Approval Issue
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Applications Table Structure ===\n";
$structure_sql = "DESCRIBE applications";
$result = $conn->query($structure_sql);

while ($row = $result->fetch_assoc()) {
    echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}\n";
}

echo "\n=== Current Applications ===\n";
$apps_sql = "SELECT queue_number, current_status, urec_reviewed_by, urec_decision, urec_decision_date FROM applications ORDER BY queue_number";
$result = $conn->query($apps_sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Queue: {$row['queue_number']}, Status: {$row['current_status']}, UREC Reviewed By: {$row['urec_reviewed_by']}, Decision: {$row['urec_decision']}, Decision Date: {$row['urec_decision_date']}\n";
    }
} else {
    echo "No applications found.\n";
}

echo "\n=== Status Constants ===\n";
require_once 'config/config.php';
echo "STATUS_APPROVED: " . (defined('STATUS_APPROVED') ? STATUS_APPROVED : 'NOT_DEFINED') . "\n";
echo "STATUS_REJECTED: " . (defined('STATUS_REJECTED') ? STATUS_REJECTED : 'NOT_DEFINED') . "\n";
echo "STATUS_REVISIONS_REQUIRED: " . (defined('STATUS_REVISIONS_REQUIRED') ? STATUS_REVISIONS_REQUIRED : 'NOT_DEFINED') . "\n";
echo "STATUS_UREC_REVIEW_REQUIRED: " . (defined('STATUS_UREC_REVIEW_REQUIRED') ? STATUS_UREC_REVIEW_REQUIRED : 'NOT_DEFINED') . "\n";
echo "STATUS_UNDER_ETHICAL_REVIEW: " . (defined('STATUS_UNDER_ETHICAL_REVIEW') ? STATUS_UNDER_ETHICAL_REVIEW : 'NOT_DEFINED') . "\n";
echo "STATUS_FORWARDED_TO_UREC: " . (defined('STATUS_FORWARDED_TO_UREC') ? STATUS_FORWARDED_TO_UREC : 'NOT_DEFINED') . "\n";

closeDBConnection($conn);
?>
