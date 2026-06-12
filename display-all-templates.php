<?php
/**
 * Display All Email Templates with HTML Rendering
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/email-template-functions.php';

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    die("❌ Database connection failed!\n");
}

echo "📧 EMAIL TEMPLATES - HTML FORMATTED SAMPLES\n";
echo str_repeat("=", 80) . "\n\n";

// Sample data for placeholders
$sample_placeholders = [
    '{{applicant_name}}' => 'Dr. Juan Dela Cruz',
    '{{queue_number}}' => 'TAU-REO-2026-001',
    '{{category}}' => 'Full Review',
    '{{approval_date}}' => 'February 3, 2026',
    '{{missing_documents}}' => '• CV of all proponents<br>• Research proposal outline<br>• Consent forms',
    '{{unsigned_documents}}' => '• Application form TAU-REO-QF-01<br>• Category form TAU-REO-QF-02',
    '{{conditions}}' => '• Update the informed consent form to include data privacy statement<br>• Revise methodology section to clarify participant recruitment<br>• Add risk mitigation strategies',
    '{{rejection_reason}}' => 'The research methodology does not adequately address ethical considerations for vulnerable populations. Major revisions are required before resubmission.',
    '{{revisions_list}}' => '• Clarify the informed consent process<br>• Add more details on data protection measures<br>• Update the timeline to be more realistic',
    '{{certificate_number}}' => 'CERT-2026-001',
    '{{valid_until}}' => 'February 3, 2027',
    '{{message_content}}' => 'Your application is currently under review by our ethics committee. We will notify you of the decision within 5-7 working days.'
];

// Fetch all templates
$result = $conn->query("SELECT template_code, template_name, subject, body, category FROM email_templates ORDER BY category, template_code");

if (!$result) {
    die("❌ Query failed: " . $conn->error . "\n");
}

$current_category = '';
$count = 0;

while ($row = $result->fetch_assoc()) {
    $count++;
    
    // Display category header if changed
    if ($row['category'] !== $current_category) {
        $current_category = $row['category'];
        echo "\n" . str_repeat("█", 80) . "\n";
        echo "📁 CATEGORY: " . strtoupper($current_category) . "\n";
        echo str_repeat("█", 80) . "\n";
    }
    
    echo "\n┌" . str_repeat("─", 78) . "┐\n";
    echo "│ $count. {$row['template_name']} ({$row['template_code']})" . str_repeat(" ", 78 - strlen("$count. {$row['template_name']} ({$row['template_code']})") - 2) . "│\n";
    echo "└" . str_repeat("─", 78) . "┘\n\n";
    
    echo "📨 Subject: {$row['subject']}\n\n";
    
    // Process template with sample data
    $processed_body = $row['body'];
    foreach ($sample_placeholders as $placeholder => $value) {
        $processed_body = str_replace($placeholder, $value, $processed_body);
    }
    
    // Display body with HTML indicators
    echo "📝 Email Body (HTML):\n";
    echo str_repeat("─", 80) . "\n";
    
    // Show both raw HTML and a text representation
    $lines = explode("\n", $processed_body);
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            echo $line . "\n";
        }
    }
    
    echo str_repeat("─", 80) . "\n";
    
    // Statistics
    $p_count = substr_count($processed_body, '<p>');
    $br_count = substr_count($processed_body, '<br>');
    $strong_count = substr_count($processed_body, '<strong>');
    $em_count = substr_count($processed_body, '<em>');
    
    echo "\n📊 HTML Elements: ";
    echo "<p>: $p_count | ";
    echo "<br>: $br_count | ";
    echo "<strong>: $strong_count | ";
    echo "<em>: $em_count";
    echo "\n";
    echo "📏 Length: " . strlen($processed_body) . " characters\n";
    
    echo "\n" . str_repeat("=", 80) . "\n";
}

$conn->close();

echo "\n✅ All $count templates displayed successfully!\n";
echo "\n💡 Key HTML Formatting Features:\n";
echo "   • <p> tags - Create proper paragraph spacing\n";
echo "   • <br> tags - Add line breaks within paragraphs\n";
echo "   • <strong> tags - Bold emphasis for important information\n";
echo "   • <em> tags - Italic text for notes and special instructions\n";
echo "   • &nbsp; - Non-breaking spaces for proper alignment\n";
echo "\n🎯 All emails will render beautifully in recipients' inboxes!\n";
?>
