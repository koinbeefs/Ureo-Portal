<?php
/**
 * Get Email Details API
 * TAU-UREO Portal - Admin Only
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Set JSON header first
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication for API
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$email_id = (int)($_GET['id'] ?? 0);

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM email_logs WHERE email_id = ?");
$stmt->bind_param("i", $email_id);
$stmt->execute();
$result = $stmt->get_result();

if ($email = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'email' => $email
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Email not found'
    ]);
}

closeDBConnection($conn);
?>
