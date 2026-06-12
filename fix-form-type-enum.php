<?php
/**
 * Fix form_type ENUM
 */

require_once 'config/config.php';

$conn = getDBConnection();

echo "<h2>🔧 Fixing form_type ENUM...</h2>";

// Check current ENUM values
$result = $conn->query("SHOW COLUMNS FROM fillable_forms LIKE 'form_type'");
$row = $result->fetch_assoc();

echo "<h3>Current ENUM values:</h3>";
echo "<pre>" . $row['Type'] . "</pre>";

// Add category_form to ENUM
try {
    $conn->query("ALTER TABLE fillable_forms MODIFY COLUMN form_type ENUM('qf01', 'qf02', 'category_form') NOT NULL");
    echo "<h3 style='color: green;'>✅ ENUM updated successfully!</h3>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error updating ENUM: " . $e->getMessage() . "</h3>";
}

// Verify the update
$result = $conn->query("SHOW COLUMNS FROM fillable_forms LIKE 'form_type'");
$row = $result->fetch_assoc();

echo "<h3>Updated ENUM values:</h3>";
echo "<pre>" . $row['Type'] . "</pre>";

closeDBConnection($conn);

echo "<p><a href='approval-debug.html.php'>Test Approval Debug</a></p>";
?>
