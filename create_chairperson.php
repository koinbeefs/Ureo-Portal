<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

$username = 'urec_human_chair';
$password = 'UREC_chair123!';
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$user_role = 'urec';
$role = 'staff'; // Fallback for legacy columns if needed
$full_name = 'Human Use Chairperson';
$email = 'human.chair@tau.edu.ph';
$committee_id = 1;
$designation = 'Chairperson';

// Check if user already exists
$check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    die("Error: User '$username' already exists.\n");
}

$stmt = $conn->prepare("
    INSERT INTO users (
        username, 
        password_hash, 
        user_role, 
        role,
        full_name, 
        email, 
        committee_id, 
        committee_designation,
        active_status,
        is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
");

$stmt->bind_param(
    "ssssssis", 
    $username, 
    $password_hash, 
    $user_role, 
    $role, 
    $full_name, 
    $email, 
    $committee_id, 
    $designation
);

if ($stmt->execute()) {
    echo "Success: Account created for $username\n";
    echo "ID: " . $conn->insert_id . "\n";
} else {
    echo "Error creating account: " . $conn->error . "\n";
}

closeDBConnection($conn);
