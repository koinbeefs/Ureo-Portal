<?php
/**
 * Run Email Templates Migration
 * TAU-UREO Portal
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

echo "====================================\n";
echo "TAU-REO Email Templates Migration\n";
echo "====================================\n\n";

$conn = getDBConnection();

// Read SQL file
$sql_file = __DIR__ . '/database/migrations/create_email_templates.sql';
if (!file_exists($sql_file)) {
    die("❌ Migration file not found: $sql_file\n");
}

$sql = file_get_contents($sql_file);

// Split into individual queries
$queries = array_filter(array_map('trim', explode(';', $sql)));

$success_count = 0;
$error_count = 0;

foreach ($queries as $query) {
    if (empty($query)) continue;
    
    try {
        if ($conn->query($query)) {
            $success_count++;
            if (stripos($query, 'CREATE TABLE') !== false) {
                echo "✓ Table created successfully\n";
            } elseif (stripos($query, 'INSERT INTO') !== false) {
                echo "✓ Template inserted\n";
            }
        } else {
            $error_count++;
            echo "⚠ Query warning: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n====================================\n";
echo "Migration Summary:\n";
echo "====================================\n";
echo "✓ Successful queries: $success_count\n";
if ($error_count > 0) {
    echo "⚠ Queries with errors: $error_count\n";
}

// Verify templates
$result = $conn->query("SELECT COUNT(*) as count FROM email_templates");
if ($result) {
    $row = $result->fetch_assoc();
    echo "\n📧 Total email templates: " . $row['count'] . "\n";
}

closeDBConnection($conn);

echo "\n✅ Migration completed!\n";
