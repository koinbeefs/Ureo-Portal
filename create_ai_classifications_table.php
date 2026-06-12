<?php
/**
 * Create AI Classifications Table if Missing
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Creating AI Classifications Table ===\n";

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'ai_classifications'");

if ($table_check->num_rows == 0) {
    echo "Creating ai_classifications table...\n";
    
    $create_sql = "
    CREATE TABLE ai_classifications (
        classification_id int AUTO_INCREMENT PRIMARY KEY,
        queue_number varchar(20) NOT NULL,
        predicted_categories json NOT NULL,
        predicted_primary varchar(100) DEFAULT NULL,
        confidence_level enum('high','moderate','low') DEFAULT 'moderate',
        max_score decimal(5,4) DEFAULT NULL,
        all_scores json DEFAULT NULL,
        reasoning text,
        similar_past_cases json DEFAULT NULL,
        learning_stats json DEFAULT NULL,
        staff_verified tinyint(1) DEFAULT 0,
        staff_verified_by int DEFAULT NULL,
        staff_corrected_categories json DEFAULT NULL,
        staff_feedback text,
        verified_at datetime DEFAULT NULL,
        section_c_text text,
        processed_at datetime DEFAULT CURRENT_TIMESTAMP,
        KEY idx_queue (queue_number),
        KEY idx_verified (staff_verified),
        CONSTRAINT ai_classifications_ibfk_1 FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE CASCADE,
        CONSTRAINT ai_classifications_ibfk_2 FOREIGN KEY (staff_verified_by) REFERENCES users (user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
    
    $conn->query($create_sql);
    echo "✅ Table created successfully\n";
} else {
    echo "ℹ️ Table already exists\n";
}

echo "\n=== Migrating JSON Data to Database ===\n";

// Get applications that have AI classification JSON files but no database records
$app_sql = "SELECT queue_number FROM applications WHERE current_status IN ('UREC_REVIEW_REQUIRED', 'UNDER_AUTO_REVIEW')";
$app_result = $conn->query($app_sql);

$migrated = 0;

while ($app = $app_result->fetch_assoc()) {
    $queue_number = $app['queue_number'];
    $json_file = __DIR__ . "/uploads/{$queue_number}/ai_classification.json";
    
    if (file_exists($json_file)) {
        $json_content = file_get_contents($json_file);
        $classification = json_decode($json_content, true);
        
        if ($classification) {
            // Check if already migrated
            $check_sql = "SELECT classification_id FROM ai_classifications WHERE queue_number = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $queue_number);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                // Insert into database
                $insert_sql = "
                INSERT INTO ai_classifications (
                    queue_number, predicted_categories, predicted_primary, confidence_level, 
                    max_score, all_scores, reasoning, staff_verified, staff_feedback,
                    section_c_text, processed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = $conn->prepare($insert_sql);
                
                $predicted_categories = json_encode($classification['original_types'] ?? []);
                $predicted_primary = $classification['ai_prediction']['predicted'] ?? null;
                $confidence_level = $classification['ai_prediction']['confidence'] ?? 'moderate';
                $max_score = $classification['ai_prediction']['max_score'] ?? null;
                $all_scores = json_encode($classification['ai_prediction']['scores'] ?? []);
                $reasoning = $classification['ai_prediction']['reason'] ?? '';
                $staff_verified = isset($classification['staff_reviewed']) && $classification['staff_reviewed'] ? 1 : 0;
                $staff_feedback = json_encode($classification['staff_feedback'] ?? '');
                $section_c_text = $classification['section_c_text'] ?? '';
                $processed_at = $classification['timestamp'] ?? date('Y-m-d H:i:s');
                
                $stmt->bind_param("ssssdssssss", 
                    $queue_number, $predicted_categories, $predicted_primary, $confidence_level,
                    $max_score, $all_scores, $reasoning, $staff_verified, $staff_feedback,
                    $section_c_text, $processed_at
                );
                
                if ($stmt->execute()) {
                    echo "✅ Migrated: $queue_number\n";
                    $migrated++;
                } else {
                    echo "❌ Failed to migrate: $queue_number - " . $conn->error . "\n";
                }
            }
        }
    }
}

echo "\nMigration complete. Processed $migrated applications.\n";

closeDBConnection($conn);
?>
