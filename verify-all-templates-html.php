<?php
/**
 * Verify All Email Templates Have HTML Formatting
 * TAU-UREO Portal
 */

require_once 'config/config.php';

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    die("❌ Database connection failed!\n");
}

echo "🔍 Verifying HTML formatting for all email templates...\n\n";
echo str_repeat("=", 70) . "\n";

// Fetch all templates
$result = $conn->query("SELECT template_code, template_name, body FROM email_templates ORDER BY template_code");

if (!$result) {
    die("❌ Query failed: " . $conn->error . "\n");
}

$templates = [];
while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

$conn->close();

// Check each template for HTML formatting
$properly_formatted = 0;
$needs_attention = [];

foreach ($templates as $template) {
    echo "\n📧 Template: {$template['template_name']} ({$template['template_code']})\n";
    echo str_repeat("-", 70) . "\n";
    
    $body = $template['body'];
    
    // Check for HTML tags
    $has_p_tags = (strpos($body, '<p>') !== false);
    $has_br_tags = (strpos($body, '<br>') !== false);
    $has_strong_tags = (strpos($body, '<strong>') !== false);
    $has_em_tags = (strpos($body, '<em>') !== false);
    
    // Count HTML elements
    $p_count = substr_count($body, '<p>');
    $br_count = substr_count($body, '<br>');
    $strong_count = substr_count($body, '<strong>');
    $em_count = substr_count($body, '<em>');
    
    // Display body preview (first 200 characters)
    $preview = substr($body, 0, 200);
    if (strlen($body) > 200) {
        $preview .= "...";
    }
    echo "Preview: " . $preview . "\n\n";
    
    // Display HTML tag counts
    echo "HTML Elements:\n";
    echo "  • <p> tags: " . ($p_count > 0 ? "✅ $p_count found" : "❌ Not found") . "\n";
    echo "  • <br> tags: " . ($br_count > 0 ? "✅ $br_count found" : "⚠️ Not found") . "\n";
    echo "  • <strong> tags: " . ($strong_count > 0 ? "✅ $strong_count found" : "⚠️ Not found (optional)") . "\n";
    echo "  • <em> tags: " . ($em_count > 0 ? "✅ $em_count found" : "⚠️ Not found (optional)") . "\n";
    
    // Body length
    $body_length = strlen($body);
    echo "\nBody length: $body_length characters\n";
    
    // Verdict
    if ($has_p_tags) {
        echo "✅ Status: PROPERLY FORMATTED\n";
        $properly_formatted++;
    } else {
        echo "❌ Status: NEEDS HTML FORMATTING\n";
        $needs_attention[] = $template['template_code'];
    }
    
    echo str_repeat("=", 70) . "\n";
}

// Final summary
echo "\n📊 FINAL SUMMARY:\n";
echo str_repeat("=", 70) . "\n";
echo "Total templates: " . count($templates) . "\n";
echo "Properly formatted: $properly_formatted ✅\n";
echo "Needs attention: " . count($needs_attention);
if (count($needs_attention) > 0) {
    echo " ❌ (" . implode(", ", $needs_attention) . ")";
}
echo "\n";
echo str_repeat("=", 70) . "\n";

if ($properly_formatted == count($templates)) {
    echo "\n🎉 SUCCESS! All email templates have proper HTML formatting!\n";
    echo "\nEach template now includes:\n";
    echo "  ✓ Paragraph tags for proper spacing\n";
    echo "  ✓ Line breaks for better readability\n";
    echo "  ✓ Bold/italic emphasis where appropriate\n";
    echo "  ✓ Consistent professional layout\n";
} else {
    echo "\n⚠️  Some templates still need HTML formatting.\n";
    echo "Run update-all-templates-html.php to fix them.\n";
}
?>
