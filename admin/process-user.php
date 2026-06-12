<?php
/**
 * User CRUD Operations Handler
 * TAU-UREO Portal - Admin Only
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
checkSessionTimeout();

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? '';
$conn = getDBConnection();

try {
    switch ($action) {
        case 'add':
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            $active_status = isset($_POST['active_status']) ? 1 : 0;

            // Validate inputs
            if (empty($username) || empty($full_name) || empty($email) || empty($password)) {
                throw new Exception('All fields are required');
            }

            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            // Check if username exists
            $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Username already exists');
            }

            // Check if email exists
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Email already exists');
            }

            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users (username, password_hash, full_name, email, role, active_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssssi", $username, $password_hash, $full_name, $email, $role, $active_status);

            if ($stmt->execute()) {
                // Log activity
                logStaffActivity($_SESSION['user_id'], null, 'other', "Created user: $username ($role)");

                echo json_encode(['success' => true, 'message' => 'User created successfully']);
            }
            else {
                throw new Exception('Failed to create user');
            }
            break;

        case 'edit':
            $user_id = (int)$_POST['user_id'];
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            $active_status = isset($_POST['active_status']) ? 1 : 0;

            // Validate inputs
            if (empty($username) || empty($full_name) || empty($email)) {
                throw new Exception('Required fields cannot be empty');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            // Check if username exists (excluding current user)
            $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $check->bind_param("si", $username, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Username already exists');
            }

            // Check if email exists (excluding current user)
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Email already exists');
            }

            // Update user
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception('Password must be at least 6 characters');
                }
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username = ?, password_hash = ?, full_name = ?, email = ?, role = ?, active_status = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("sssssii", $username, $password_hash, $full_name, $email, $role, $active_status, $user_id);
            }
            else {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, email = ?, role = ?, active_status = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("ssssii", $username, $full_name, $email, $role, $active_status, $user_id);
            }

            if ($stmt->execute()) {
                // Log activity
                logStaffActivity($_SESSION['user_id'], null, 'other', "Updated user: $username");

                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            }
            else {
                throw new Exception('Failed to update user');
            }
            break;

        case 'delete':
            $user_id = (int)$_POST['user_id'];

            // Prevent deleting self
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception('Cannot delete your own account');
            }

            // Get username for logging
            $user = $conn->query("SELECT username FROM users WHERE user_id = $user_id")->fetch_assoc();

            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                // Log activity
                logStaffActivity($_SESSION['user_id'], null, 'other', "Deleted user: " . $user['username']);

                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            }
            else {
                throw new Exception('Failed to delete user');
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

closeDBConnection($conn);
?>
