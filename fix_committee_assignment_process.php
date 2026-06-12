<?php
/**
 * Fix Committee Assignment Process in Workflow
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Fixing Committee Assignment Process ===\n";

// Find the workflow files that handle application progression
$workflow_files = [
    'staff/process-application.php',
    'staff/ai-classification.php', 
    'includes/functions.php'
];

echo "Checking workflow files...\n";

foreach ($workflow_files as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        echo "✅ Found: $file\n";
        
        // Check if the file handles committee assignment
        $content = file_get_contents($filepath);
        
        if (strpos($content, 'ai_classification.json') !== false) {
            echo "  📋 Contains AI classification handling\n";
        }
        
        if (strpos($content, 'urec_committee_id') !== false) {
            echo "  📋 Contains committee assignment\n";
        }
        
        if (strpos($content, 'FORWARDED_TO_UREC') !== false) {
            echo "  📋 Contains UREC forwarding\n";
        }
    } else {
        echo "❌ Missing: $file\n";
    }
}

echo "\n=== Creating Committee Assignment Function ===\n";

// Create a function to handle committee assignment based on AI classification
$function_code = '
/**
 * Assign committee based on AI classification JSON
 * @param string $queue_number Application queue number
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function assignCommitteeFromAI($queue_number, $conn) {
    $json_file = __DIR__ . "/../uploads/{$queue_number}/ai_classification.json";
    
    if (!file_exists($json_file)) {
        return false;
    }
    
    $json_content = file_get_contents($json_file);
    $classification = json_decode($json_content, true);
    
    if (!$classification || !isset($classification[\'staff_feedback\'][\'final_category\'])) {
        return false;
    }
    
    $final_category = $classification[\'staff_feedback\'][\'final_category\'];
    
    // Map categories to committee IDs
    $committee_mapping = [
        \'Human Use\' => 1,
        \'Animal Welfare\' => 2,
        \'Plant Use\' => 3,
        \'Microbiological/Biotechnological Use\' => 1,
        \'Engineering\' => 1,
        \'Information Technology Use\' => 1,
        \'Food Technology Use\' => 3
    ];
    
    $committee_id = $committee_mapping[$final_category] ?? 1;
    
    // Update application
    require_once __DIR__ . \'/../config/config.php\';
    $new_status = STATUS_FORWARDED_TO_UREC;
    
    $update_sql = "UPDATE applications SET urec_committee_id = ?, current_status = ?, last_updated = NOW() WHERE queue_number = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iss", $committee_id, $new_status, $queue_number);
    
    return $stmt->execute();
}
';

echo "Adding committee assignment function to functions.php...\n";

$functions_file = __DIR__ . '/includes/functions.php';
if (file_exists($functions_file)) {
    $current_content = file_get_contents($functions_file);
    
    // Check if function already exists
    if (strpos($current_content, 'assignCommitteeFromAI') === false) {
        // Add the function to the end of the file
        file_put_contents($functions_file, $current_content . $function_code);
        echo "✅ Function added to functions.php\n";
    } else {
        echo "ℹ️ Function already exists in functions.php\n";
    }
}

echo "\n=== Updating AI Classification Process ===\n";

// Check ai-classification.php and add committee assignment
$ai_class_file = __DIR__ . '/staff/ai-classification.php';
if (file_exists($ai_class_file)) {
    $ai_content = file_get_contents($ai_class_file);
    
    // Look for where staff confirms the classification
    if (strpos($ai_content, 'staff_verified') !== false) {
        echo "Found staff verification section in ai-classification.php\n";
        
        // Add committee assignment after staff verification
        $assignment_code = '
                // Assign committee based on final classification
                if (assignCommitteeFromAI($queue_number, $conn)) {
                    echo "<div class=\'alert alert-success alert-modern\'>Application assigned to appropriate committee.</div>";
                }
        ';
        
        // This would need to be manually added to the appropriate location
        echo "📝 Manual addition needed: Add committee assignment call after staff verification\n";
        echo "   Add this code after staff verification: assignCommitteeFromAI($queue_number, $conn);\n";
    }
}

echo "\n=== Creating Automated Workflow Fix ===\n";

// Create a script to be run after AI classification is complete
$workflow_script = '<?php
/**
 * Automated Committee Assignment
 * Run this after AI classification is complete
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/functions.php";

$conn = getDBConnection();

// Get applications that have AI classification but no committee assignment
$sql = "SELECT a.queue_number FROM applications a 
        LEFT JOIN ai_classifications ai ON a.queue_number = ai.queue_number
        WHERE ai.staff_verified = 1 
        AND (a.urec_committee_id IS NULL OR a.urec_committee_id = 0)
        AND a.current_status = \'UREC_REVIEW_REQUIRED\'";

$result = $conn->query($sql);
$processed = 0;

while ($row = $result->fetch_assoc()) {
    $queue_number = $row["queue_number"];
    
    if (assignCommitteeFromAI($queue_number, $conn)) {
        echo "✅ Assigned committee for $queue_number\n";
        $processed++;
    } else {
        echo "❌ Failed to assign committee for $queue_number\n";
    }
}

echo "\nProcessed $processed applications\n";
?>';

file_put_contents(__DIR__ . '/auto_assign_committees.php', $workflow_script);
echo "✅ Created auto_assign_committees.php script\n";

echo "\n=== Summary ===\n";
echo "1. ✅ Added assignCommitteeFromAI() function to functions.php\n";
echo "2. ✅ Created auto_assign_committees.php for bulk processing\n";
echo "3. 📝 Manual update needed: Add function call to ai-classification.php\n";
echo "\nTo complete the fix:\n";
echo "- Add assignCommitteeFromAI(\$queue_number, \$conn); after staff verification in ai-classification.php\n";
echo "- Run php auto_assign_committees.php to process existing applications\n";

closeDBConnection($conn);
?>
