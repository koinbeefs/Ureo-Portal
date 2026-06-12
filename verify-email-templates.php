<?php
/**
 * Verify Email Template System
 * TAU-UREO Portal
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email-template-functions.php';

echo "====================================\n";
echo "Email Template System Verification\n";
echo "====================================\n\n";

$conn = getDBConnection();

// Check if table exists
echo "1. Checking email_templates table...\n";
$result = $conn->query("SHOW TABLES LIKE 'email_templates'");
if ($result->num_rows > 0) {
    echo "   ✓ Table exists\n\n";
} else {
    echo "   ❌ Table not found! Run migration first.\n\n";
    exit();
}

// Count templates
echo "2. Counting templates...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM email_templates");
$row = $result->fetch_assoc();
echo "   ✓ Total templates: " . $row['count'] . "\n\n";

// List all templates
echo "3. Available templates:\n";
$result = $conn->query("SELECT template_code, template_name, category, is_active FROM email_templates ORDER BY category, template_name");
while ($template = $result->fetch_assoc()) {
    $status = $template['is_active'] ? '✓' : '✗';
    echo "   $status [{$template['category']}] {$template['template_name']} ({$template['template_code']})\n";
}
echo "\n";

// Test template retrieval
echo "4. Testing template retrieval...\n";
$template = getEmailTemplate('REPLY_INTENT');
if ($template) {
    echo "   ✓ Successfully retrieved template: " . $template['template_name'] . "\n";
    echo "   ✓ Subject: " . substr($template['subject'], 0, 50) . "...\n";
} else {
    echo "   ❌ Failed to retrieve template\n";
}
echo "\n";

// Test placeholder replacement
echo "5. Testing placeholder replacement...\n";
$test_template = "Dear {{applicant_name}}, your queue number is {{queue_number}}.";
$test_placeholders = [
    'applicant_name' => 'Juan Dela Cruz',
    'queue_number' => 'UREO-2026-0001'
];
$processed = processEmailTemplate($test_template, $test_placeholders);
echo "   Original: $test_template\n";
echo "   Processed: $processed\n";
if (strpos($processed, 'Juan Dela Cruz') !== false && strpos($processed, 'UREO-2026-0001') !== false) {
    echo "   ✓ Placeholder replacement working\n";
} else {
    echo "   ❌ Placeholder replacement failed\n";
}
echo "\n";

// Test category filtering
echo "6. Testing category filtering...\n";
$review_templates = getEmailTemplates('review_process');
echo "   ✓ Review Process templates: " . count($review_templates) . "\n";
$decision_templates = getEmailTemplates('decision');
echo "   ✓ Decision templates: " . count($decision_templates) . "\n";
echo "\n";

// Test with actual application (if exists)
echo "7. Testing with application data...\n";
$result = $conn->query("SELECT queue_number FROM applications LIMIT 1");
if ($result && $result->num_rows > 0) {
    $app = $result->fetch_assoc();
    $placeholders = getApplicationPlaceholders($app['queue_number']);
    echo "   ✓ Retrieved placeholders for: " . $app['queue_number'] . "\n";
    echo "   ✓ Available placeholders: " . count($placeholders) . "\n";
    echo "   - Applicant: " . ($placeholders['applicant_name'] ?? 'N/A') . "\n";
    echo "   - Email: " . ($placeholders['applicant_email'] ?? 'N/A') . "\n";
} else {
    echo "   ⚠ No applications found in database (this is OK for new installations)\n";
}
echo "\n";

closeDBConnection($conn);

echo "====================================\n";
echo "✅ Verification Complete!\n";
echo "====================================\n\n";

echo "Next Steps:\n";
echo "1. Login to staff portal\n";
echo "2. Open any application\n";
echo "3. Click 'Send Template Email' button\n";
echo "4. Select a template and send test email\n\n";
