<?php
/**
 * Add UREC Evaluator under Plant Use Committee
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Adding Plant Use Committee Evaluator ===\n";

// Check current users
echo "Current Plant Use Committee members:\n";
$check_sql = "SELECT user_id, username, user_role, committee_designation, committee_id, full_name, email FROM users WHERE committee_id = 3 ORDER BY user_id";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['user_id']}, Username: {$row['username']}, Role: {$row['user_role']}, Committee: {$row['committee_designation']}, Name: {$row['full_name']}, Email: {$row['email']}\n";
    }
} else {
    echo "No Plant Use Committee members found.\n";
}

echo "\n=== Adding Plant Use Evaluator ===\n";

// Check if user already exists
$check_user_sql = "SELECT user_id FROM users WHERE username = 'plant_evaluator01'";
$check_result = $conn->query($check_user_sql);

if ($check_result->num_rows > 0) {
    echo "User 'plant_evaluator01' already exists. Updating existing record...\n";
    
    // Update existing user
    $update_sql = "UPDATE users SET 
        user_role = 'urec',
        committee_designation = 'Plant Use Evaluator',
        committee_id = 3,
        full_name = 'Plant Use Committee Evaluator',
        email = 'plant.evaluator01@tau.edu.ph'
        WHERE username = 'plant_evaluator01'";
        
    if ($conn->query($update_sql)) {
        echo "Existing user updated successfully!\n";
    } else {
        echo "Error updating user: " . $conn->error . "\n";
    }
} else {
    echo "Creating new Plant Use Evaluator...\n";
    
    // Generate password hash (default password: evaluator123)
    $password_hash = password_hash('evaluator123', PASSWORD_DEFAULT);
    
    // Insert new UREC evaluator
    $insert_sql = "INSERT INTO users (
        username, 
        password_hash, 
        user_role, 
        committee_designation, 
        committee_id, 
        role, 
        email, 
        full_name, 
        active_status,
        is_active
    ) VALUES (
        'plant_evaluator01',
        '$password_hash',
        'urec',
        'Plant Use Evaluator',
        3,
        'staff',
        'plant.evaluator01@tau.edu.ph',
        'Plant Use Committee Evaluator',
        1,
        1
    )";
    
    if ($conn->query($insert_sql)) {
        echo "New Plant Use Evaluator created successfully!\n";
        echo "Username: plant_evaluator01\n";
        echo "Password: evaluator123\n";
        echo "Email: plant.evaluator01@tau.edu.ph\n";
        echo "Role: UREC\n";
        echo "Committee Designation: Plant Use Evaluator\n";
        echo "Committee ID: 3\n";
    } else {
        echo "Error creating user: " . $conn->error . "\n";
    }
}

echo "\n=== Updated Plant Use Committee Members ===\n";
$final_check = $conn->query($check_sql);
if ($final_check->num_rows > 0) {
    while ($row = $final_check->fetch_assoc()) {
        echo "ID: {$row['user_id']}, Username: {$row['username']}, Role: {$row['user_role']}, Committee: {$row['committee_designation']}, Committee ID: {$row['committee_id']}, Name: {$row['full_name']}, Email: {$row['email']}\n";
    }
}

closeDBConnection($conn);
?>
