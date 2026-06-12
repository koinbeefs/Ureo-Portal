<?php
declare(strict_types=1);
/**
 * Debug UREC Assignment
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

echo "<h2>UREC Assignment Debug</h2>";

// 1. Check recent application status
echo "<h3>1. Recent Application Status</h3>";
$app_query = "SELECT queue_number, applicant_name, current_status, urec_committee_id, urec_reviewed_by, forwarded_to_urec_at FROM applications WHERE current_status IN ('FORWARDED_TO_UREC', 'ASSIGNING_UREC_EVALUATOR', 'UNDER_ETHICAL_REVIEW') ORDER BY forwarded_to_urec_at DESC LIMIT 5";
$app_result = $conn->query($app_query);

if ($app_result && $app_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Queue Number</th><th>Applicant</th><th>Status</th><th>UREC Committee ID</th><th>Reviewed By</th><th>Forwarded At</th></tr>";
    while ($row = $app_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['queue_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['applicant_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['current_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['urec_committee_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['urec_reviewed_by'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['forwarded_to_urec_at'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No applications found with UREC statuses</p>";
}

// 2. Check UREC users and their committee assignments
echo "<h3>2. UREC Users and Committee Assignments</h3>";
$users_query = "SELECT user_id, username, full_name, user_role, committee_designation, committee_id, active_status FROM users WHERE user_role = 'urec' ORDER BY committee_id, user_id";
$users_result = $conn->query($users_query);

if ($users_result && $users_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Designation</th><th>Committee ID</th><th>Active</th></tr>";
    while ($row = $users_result->fetch_assoc()) {
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
    echo "<p>No UREC users found</p>";
}

// 3. Check committees
echo "<h3>3. UREC Committees</h3>";
$committees_query = "SELECT committee_id, committee_name, committee_code, is_active FROM urec_committees ORDER BY committee_id";
$committees_result = $conn->query($committees_query);

if ($committees_result && $committees_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Committee ID</th><th>Name</th><th>Code</th><th>Active</th></tr>";
    while ($row = $committees_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['committee_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['committee_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['committee_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['is_active']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No committees found</p>";
}

// 4. Check what the chairperson should see
echo "<h3>4. What Chairperson (ID 5) Should See</h3>";
$chair_id = 5;
$chair_query = "SELECT committee_id FROM users WHERE user_id = ?";
$chair_stmt = $conn->prepare($chair_query);
$chair_stmt->bind_param("i", $chair_id);
$chair_stmt->execute();
$chair_result = $chair_stmt->get_result();
$chair_data = $chair_result->fetch_assoc();

if ($chair_data) {
    echo "<p>Chairperson (ID 5) committee_id: " . htmlspecialchars($chair_data['committee_id']) . "</p>";
    
    $apps_query = "SELECT queue_number, applicant_name, current_status, urec_committee_id FROM applications WHERE urec_committee_id = ? AND (current_status = 'FORWARDED_TO_UREC' OR current_status = 'ASSIGNING_UREC_EVALUATOR') AND urec_reviewed_by IS NULL";
    $apps_stmt = $conn->prepare($apps_query);
    $apps_stmt->bind_param("i", $chair_data['committee_id']);
    $apps_stmt->execute();
    $apps_result = $apps_stmt->get_result();
    
    echo "<p>Applications chairperson should see: " . $apps_result->num_rows . "</p>";
    
    if ($apps_result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Queue Number</th><th>Applicant</th><th>Status</th><th>UREC Committee ID</th></tr>";
        while ($row = $apps_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['queue_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['applicant_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['current_status']) . "</td>";
            echo "<td>" . htmlspecialchars($row['urec_committee_id']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>Chairperson (ID 5) not found or has no committee_id</p>";
}

closeDBConnection($conn);
?>
