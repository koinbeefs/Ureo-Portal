<?php
/**
 * Check Committee Assignment for Applications
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Current Applications Status ===\n";

// Get all applications with their committee assignments
$sql = "SELECT queue_number, current_status, urec_committee_id, urec_reviewed_by, category, research_title FROM applications ORDER BY queue_number";
$result = $conn->query($sql);

while ($app = $result->fetch_assoc()) {
    echo "Queue: {$app['queue_number']}\n";
    echo "  Status: {$app['current_status']}\n";
    echo "  UREC Committee ID: {$app['urec_committee_id']}\n";
    echo "  Reviewed By: {$app['urec_reviewed_by']}\n";
    echo "  Category: {$app['category']}\n";
    echo "  Title: " . substr($app['research_title'], 0, 50) . "...\n";
    echo "\n";
}

echo "\n=== UREC Committee Members ===\n";

// Get UREC users
$users_sql = "SELECT user_id, username, committee_designation, committee_id, full_name FROM users WHERE user_role = 'urec' ORDER BY committee_id, user_id";
$users_result = $conn->query($users_sql);

while ($user = $users_result->fetch_assoc()) {
    echo "User ID: {$user['user_id']}\n";
    echo "  Username: {$user['username']}\n";
    echo "  Committee ID: {$user['committee_id']}\n";
    echo "  Designation: {$user['committee_designation']}\n";
    echo "  Name: {$user['full_name']}\n";
    echo "\n";
}

echo "\n=== Status Constants ===\n";
require_once 'config/config.php';
echo "STATUS_FORWARDED_TO_UREC: " . STATUS_FORWARDED_TO_UREC . "\n";
echo "STATUS_UREC_REVIEW_REQUIRED: " . STATUS_UREC_REVIEW_REQUIRED . "\n";
echo "STATUS_UNDER_ETHICAL_REVIEW: " . STATUS_UNDER_ETHICAL_REVIEW . "\n";

closeDBConnection($conn);
?>
