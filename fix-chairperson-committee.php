<?php
declare(strict_types=1);
/**
 * Fix Chairperson Committee Assignment
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

echo "<h2>Fix Chairperson Committee Assignment</h2>";

// Check current chairperson settings
$chair_query = "SELECT user_id, username, full_name, user_role, committee_designation, committee_id, active_status FROM users WHERE user_id = 5 OR username LIKE '%chair%' OR committee_designation LIKE '%Chair%'";
$chair_result = $conn->query($chair_query);

echo "<h3>Current Chairperson Settings</h3>";
if ($chair_result && $chair_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Designation</th><th>Committee ID</th><th>Active</th></tr>";
    while ($row = $chair_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['committee_designation'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['committee_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['active_status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No chairperson found</p>";
}

// Fix the chairperson assignment
echo "<h3>Fixing Chairperson Assignment</h3>";

// Update user_id 5 to be properly assigned to HUMAN_USE committee (committee_id = 1)
$update_query = "UPDATE users SET committee_id = 1, committee_designation = 'Human Use Chairperson', active_status = 1 WHERE user_id = 5";
$update_result = $conn->query($update_query);

if ($update_result) {
    echo "<p>Successfully updated user_id 5 to Human Use Chairperson with committee_id = 1</p>";
} else {
    echo "<p>Error updating user_id 5: " . htmlspecialchars($conn->error) . "</p>";
}

// Verify the update
$verify_query = "SELECT user_id, username, full_name, user_role, committee_designation, committee_id, active_status FROM users WHERE user_id = 5";
$verify_result = $conn->query($verify_query);

echo "<h3>Updated Chairperson Settings</h3>";
if ($verify_result && $verify_result->num_rows > 0) {
    $row = $verify_result->fetch_assoc();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Designation</th><th>Committee ID</th><th>Active</th></tr>";
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['user_role']) . "</td>";
    echo "<td>" . htmlspecialchars($row['committee_designation']) . "</td>";
    echo "<td>" . htmlspecialchars($row['committee_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['active_status']) . "</td>";
    echo "</tr>";
    echo "</table>";
    
    // Check if this user should now see the application
    echo "<h3>Applications Chairperson Should Now See</h3>";
    $apps_query = "SELECT queue_number, applicant_name, current_status, urec_committee_id FROM applications WHERE urec_committee_id = ? AND (current_status = 'FORWARDED_TO_UREC' OR current_status = 'ASSIGNING_UREC_EVALUATOR') AND urec_reviewed_by IS NULL";
    $apps_stmt = $conn->prepare($apps_query);
    $apps_stmt->bind_param("i", $row['committee_id']);
    $apps_stmt->execute();
    $apps_result = $apps_stmt->get_result();
    
    echo "<p>Applications chairperson should see: " . $apps_result->num_rows . "</p>";
    
    if ($apps_result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Queue Number</th><th>Applicant</th><th>Status</th><th>UREC Committee ID</th></tr>";
        while ($app_row = $apps_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($app_row['queue_number']) . "</td>";
            echo "<td>" . htmlspecialchars($app_row['applicant_name']) . "</td>";
            echo "<td>" . htmlspecialchars($app_row['current_status']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$app_row['urec_committee_id']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

closeDBConnection($conn);

echo "<p><a href='debug-urec-assignment.php'>Back to Debug Script</a></p>";
echo "<p><a href='../urec/urec_admin/assign-application.php'>Go to Chairperson Assignment Page</a></p>";
?>
