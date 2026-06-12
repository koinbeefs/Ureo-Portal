<?php
/**
 * Migration Script: Add body_html column to email_logs
 */

require_once 'config/config.php';

$conn = getDBConnection();

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM email_logs LIKE 'body_html'");

if ($result->num_rows == 0) {
    echo "Adding body_html column to email_logs table...\n";
    
    $sql = "ALTER TABLE email_logs ADD COLUMN body_html TEXT AFTER subject";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully added body_html column!\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
} else {
    echo "✓ body_html column already exists.\n";
}

// Check current structure
echo "\nCurrent email_logs table structure:\n";
$result = $conn->query("DESCRIBE email_logs");
while ($row = $result->fetch_assoc()) {
    echo "  - {$row['Field']} ({$row['Type']})\n";
}

closeDBConnection($conn);
?>
