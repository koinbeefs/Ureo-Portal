<?php
/**
 * @file        add_urec_user.php
 * @description Add UREC user with Animal Use Chairperson designation
 * @author      System
 * @created     2025-05-19
 */

require_once 'config/database.php';

// Create database connection
$conn = getDBConnection();

try {
    // Check current users
    echo "=== Current Users ===\n";
    $check_sql = "SELECT user_id, username, user_role, committee_designation, committee_id, full_name, email FROM users ORDER BY user_id";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: {$row['user_id']}, Username: {$row['username']}, Role: {$row['user_role']}, Committee: {$row['committee_designation']}, Committee ID: {$row['committee_id']}, Name: {$row['full_name']}, Email: {$row['email']}\n";
        }
    } else {
        echo "No users found.\n";
    }
    
    echo "\n=== Adding UREC User ===\n";
    
    // Check if user already exists
    $check_user_sql = "SELECT user_id FROM users WHERE username = 'plant_use_chair'";
    $check_result = $conn->query($check_user_sql);
    
    if ($check_result->num_rows > 0) {
        echo "User 'plant_use_chair' already exists. Updating existing record...\n";
        
        // Update existing user
        $update_sql = "UPDATE users SET 
            user_role = 'urec',
            committee_designation = 'Plant Use Chairperson',
            committee_id = 3,
            full_name = 'Plant Use Committee Chairperson',
            email = 'plantuse.chair@tau.edu.ph'
            WHERE username = 'plant_use_chair'";
            
        if ($conn->query($update_sql)) {
            echo "Existing user updated successfully!\n";
        } else {
            echo "Error updating user: " . $conn->error . "\n";
        }
    } else {
        echo "Creating new UREC user...\n";
        
        // Generate password hash (default password: urec123)
        $password_hash = password_hash('urec123', PASSWORD_DEFAULT);
        
        // Insert new UREC user
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
            'plant_use_chair',
            '$password_hash',
            'urec',
            'Plant Use Chairperson',
            3,
            'staff',
            'plantuse.chair@tau.edu.ph',
            'Plant Use Committee Chairperson',
            1,
            1
        )";
        
        if ($conn->query($insert_sql)) {
            echo "New UREC user created successfully!\n";
            echo "Username: plant_use_chair\n";
            echo "Password: urec123\n";
            echo "Email: plantuse.chair@tau.edu.ph\n";
            echo "Role: UREC\n";
            echo "Committee Designation: Plant Use Chairperson\n";
            echo "Committee ID: 3\n";
        } else {
            echo "Error creating user: " . $conn->error . "\n";
        }
    }
    
    echo "\n=== Updated Users List ===\n";
    $final_check = $conn->query($check_sql);
    if ($final_check->num_rows > 0) {
        while ($row = $final_check->fetch_assoc()) {
            echo "ID: {$row['user_id']}, Username: {$row['username']}, Role: {$row['user_role']}, Committee: {$row['committee_designation']}, Committee ID: {$row['committee_id']}, Name: {$row['full_name']}, Email: {$row['email']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    closeDBConnection($conn);
}
?>
