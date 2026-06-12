<?php
/**
 * Check Application Status
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';

echo "<h2>Application Status Check for: $queue_number</h2>";

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

echo "<h3>Current Application Status</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>Queue Number</td><td>" . $application['queue_number'] . "</td></tr>";
echo "<tr><td>Current Status</td><td><strong style='color: blue;'>" . $application['current_status'] . "</strong></td></tr>";
echo "<tr><td>Applicant Name</td><td>" . $application['applicant_name'] . "</td></tr>";
echo "<tr><td>Research Title</td><td>" . $application['research_title'] . "</td></tr>";
echo "<tr><td>Category</td><td>" . ($application['category'] ?? 'Not set') . "</td></tr>";
echo "<tr><td>Assigned Staff ID</td><td>" . ($application['assigned_staff_id'] ?? 'Not assigned') . "</td></tr>";
echo "<tr><td>Last Updated</td><td>" . $application['last_updated'] . "</td></tr>";
echo "</table>";

echo "<h3>Valid Statuses for Approval</h3>";
$valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
echo "<ul>";
foreach ($valid_statuses as $status) {
    $color = ($status === $application['current_status']) ? 'green' : 'black';
    echo "<li style='color: $color; font-weight: " . (($status === $application['current_status']) ? 'bold' : 'normal') . ";'>$status</li>";
}
echo "</ul>";

if (in_array($application['current_status'], $valid_statuses)) {
    echo "<p style='color: green; font-weight: bold;'>✅ Application IS in a valid state for approval</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Application is NOT in a valid state for approval</p>";
    echo "<p><strong>Current status '" . $application['current_status'] . "' is not in the allowed list.</strong></p>";
}

echo "<h3>Status History</h3>";
$history_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp DESC LIMIT 10");
$history_stmt->bind_param("s", $queue_number);
$history_stmt->execute();
$history = $history_stmt->get_result();

if ($history->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
    echo "<tr><th>Timestamp</th><th>Previous Status</th><th>New Status</th><th>Changed By</th><th>Notes</th></tr>";
    while ($row = $history->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['timestamp'] . "</td>";
        echo "<td>" . ($row['previous_status'] ?? 'N/A') . "</td>";
        echo "<td><strong>" . $row['new_status'] . "</strong></td>";
        echo "<td>" . $row['changed_by_type'] . " " . $row['changed_by'] . "</td>";
        echo "<td>" . $row['notes'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No status history found</p>";
}

closeDBConnection($conn);

?>
