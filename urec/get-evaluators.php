<?php
/**
 * Get Evaluators for a Committee
 * Returns JSON response for AJAX requests
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$committee_id = $_GET['committee_id'] ?? '';

if (empty($committee_id) || !is_numeric($committee_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid committee ID']);
    exit();
}

$conn = getDBConnection();

try {
    // Get UREC evaluators for the specified committee (excluding chairpersons)
    $evaluator_sql = "
        SELECT user_id, username, full_name, committee_designation, committee_id, email
        FROM users 
        WHERE user_role = 'urec' 
        AND committee_id = ?
        AND (committee_designation NOT LIKE '%Chair%' AND committee_designation NOT LIKE '%Head%')
        AND active_status = 1
        ORDER BY full_name
    ";
    
    $stmt = $conn->prepare($evaluator_sql);
    $stmt->bind_param("i", $committee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $evaluators = [];
    while ($row = $result->fetch_assoc()) {
        $evaluators[] = [
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'committee_designation' => $row['committee_designation'],
            'committee_id' => (int)$row['committee_id'],
            'email' => $row['email']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'evaluators' => $evaluators,
        'count' => count($evaluators)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

closeDBConnection($conn);
?>
