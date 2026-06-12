<?php
/**
 * Migrate QF02 remarks from fillable_forms to document_annotations table
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

echo "Starting QF02 remarks migration...\n";

// Create the annotations table first
$tableSql = file_get_contents('create_document_annotations_table.sql');
if ($conn->multi_query($tableSql)) {
    do {
        // consume all results
    } while ($conn->more_results() && $conn->next_result());
    echo "✓ Annotations table created/verified\n";
} else {
    echo "✗ Error creating annotations table: " . $conn->error . "\n";
    exit(1);
}

// Get all QF02 forms that have remarks
$stmt = $conn->prepare("SELECT queue_number, form_data FROM fillable_forms WHERE form_type = 'qf02' AND form_data LIKE '%crit_%_remarks%'");
$stmt->execute();
$result = $stmt->get_result();

$migrated = 0;
$total = 0;

while ($row = $result->fetch_assoc()) {
    $queue_number = $row['queue_number'];
    $form_data = json_decode($row['form_data'], true);
    
    if (!$form_data) {
        continue;
    }
    
    $total++;
    $hasRemarks = false;
    
    // Check for criteria remarks (crit_1_remarks, crit_2_remarks, etc.)
    for ($i = 1; $i <= 9; $i++) {
        $remarkKey = "crit_{$i}_remarks";
        if (isset($form_data[$remarkKey]) && !empty(trim($form_data[$remarkKey]))) {
            $hasRemarks = true;
            
            // Calculate position based on criteria number (using same logic as PDF generation)
            $pageNo = 1;
            $firstRowTop = 32.65;
            $rowHeight = 3.05;
            $colRight = 2.25;
            $colWidth = 12.0;
            
            $x = 100 - ($colRight + $colWidth); // Convert to percentage from right
            $y = $firstRowTop + ($i - 1) * $rowHeight;
            $w = $colWidth;
            $h = $rowHeight;
            
            // Insert annotation
            $insertStmt = $conn->prepare("INSERT INTO document_annotations (queue_number, document_type, annotation_type, page_number, x_position, y_position, width, height, content) VALUES (?, ?, 'remark', ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssiddddds", 
                $queue_number, 
                'qf02', 
                $pageNo, 
                $x, 
                $y, 
                $w, 
                $h, 
                $form_data[$remarkKey]
            );
            
            if ($insertStmt->execute()) {
                echo "✓ Migrated remark for {$queue_number} - Criteria {$i}\n";
            } else {
                echo "✗ Error migrating remark for {$queue_number} - Criteria {$i}: " . $conn->error . "\n";
            }
        }
    }
    
    if ($hasRemarks) {
        $migrated++;
    }
}

echo "\nMigration complete!\n";
echo "Total QF02 forms processed: {$total}\n";
echo "Forms with remarks migrated: {$migrated}\n";

closeDBConnection($conn);
?>
