<?php
/**
 * Validate Document Action (Staff)
 * TAU-UREO Portal
 */

header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$document_id = $_POST['document_id'] ?? '';
$action = $_POST['action'] ?? '';
$notes = $_POST['notes'] ?? '';
$staff_id = $_SESSION['user_id'];

if (empty($document_id) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

if (!in_array($action, ['validate', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get document details
    $doc_stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ?");
    $doc_stmt->bind_param("i", $document_id);
    $doc_stmt->execute();
    $document = $doc_stmt->get_result()->fetch_assoc();
    
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }
    
    // Update document status
    $new_status = $action === 'validate' ? 'validated' : 'rejected';
    $update_stmt = $conn->prepare("UPDATE documents SET validation_status = ?, validation_notes = ? WHERE document_id = ?");
    $update_stmt->bind_param("ssi", $new_status, $notes, $document_id);
    $update_stmt->execute();
    
    if ($update_stmt->affected_rows > 0) {
        // Log activity
        $action_detail = $action === 'validate' ? 'Validated document: ' : 'Rejected document: ';
        $action_detail .= $document['document_name'];
        if (!empty($notes)) {
            $action_detail .= ' (Note: ' . $notes . ')';
        }
        
        logStaffActivity($staff_id, $document['queue_number'], 'other', $action_detail);
        
        $message = $action === 'validate' ? 'Document validated successfully' : 'Document rejected successfully';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update document status']);
    }
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    error_log("Document validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the document']);
}
?>