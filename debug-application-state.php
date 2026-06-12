<?php
/**
 * Debug Application State
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';

echo "<h2>Debug Application State: $queue_number</h2>";

$conn = getDBConnection();

// Get current application status
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo "<p style='color: red;'>❌ Application not found</p>";
    exit();
}

echo "<h3>Current Application State</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>Queue Number</td><td>" . $application['queue_number'] . "</td></tr>";
echo "<tr><td>Current Status</td><td><strong style='color: red;'>" . $application['current_status'] . "</strong></td></tr>";
echo "<tr><td>Category</td><td>" . ($application['category'] ?? 'Not set') . "</td></tr>";
echo "<tr><td>Last Updated</td><td>" . $application['last_updated'] . "</td></tr>";
echo "</table>";

echo "<h3>Check for Category Form Records</h3>";
$form_stmt = $conn->prepare("SELECT * FROM fillable_forms WHERE queue_number = ? AND form_type IN ('category_form', 'category_token')");
$form_stmt->bind_param("s", $queue_number);
$form_stmt->execute();
$forms = $form_stmt->get_result();

if ($forms->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Found " . $forms->num_rows . " category form records:</p>";
    echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
    echo "<tr><th>Form Type</th><th>Created At</th><th>Completed At</th><th>Data</th></tr>";
    while ($form = $forms->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $form['form_type'] . "</td>";
        echo "<td>" . $form['created_at'] . "</td>";
        echo "<td>" . ($form['completed_at'] ?? 'Not completed') . "</td>";
        echo "<td>" . substr($form['form_data'], 0, 100) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✅ No category form records found</p>";
}

echo "<h3>Valid Statuses for Approval</h3>";
$valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
echo "<ul>";
foreach ($valid_statuses as $status) {
    $color = ($status === $application['current_status']) ? 'red' : 'green';
    echo "<li style='color: $color; font-weight: " . (($status === $application['current_status']) ? 'bold' : 'normal') . ";'>$status</li>";
}
echo "</ul>";

if (in_array($application['current_status'], $valid_statuses)) {
    echo "<p style='color: green; font-weight: bold;'>✅ Application IS in a valid state for approval</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Application is NOT in a valid state for approval</p>";
    echo "<p><strong>Current status '" . $application['current_status'] . "' is not in the allowed list.</strong></p>";
    echo "<p>This application has already been processed!</p>";
}

closeDBConnection($conn);

echo "<h3>Solution</h3>";
echo "<p>The application needs to be reset to a valid status before approval can be tested again.</p>";
echo "<p>Run: <a href='reset-application-status.php'>reset-application-status.php</a></p>";

?>
