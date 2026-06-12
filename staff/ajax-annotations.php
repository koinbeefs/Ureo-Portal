<?php
declare(strict_types=1);

/**
 * Ajax Annotations Handler
 * Saves and loads coordinate-based pins for document review.
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Review check (Staff, Admin, or UREC)
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

if (!$user_id || !in_array($user_role, ['staff', 'admin', 'urec'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $queue_number = $_POST['queue_number'] ?? '';
        $doc_type = $_POST['document_type'] ?? '';
        $page = (int)($_POST['page_number'] ?? 1);
        $x = (float)($_POST['x_position'] ?? 0);
        $y = (float)($_POST['y_position'] ?? 0);
        $content = $_POST['content'] ?? '';
        $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : null;

        if (empty($queue_number) || empty($doc_type) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO document_annotations 
            (queue_number, document_type, page_number, x_position, y_position, content, created_by, created_by_type, committee_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $role_for_db = ($user_role === 'admin') ? 'admin' : 'staff'; // Treat urec as staff creator type
        
        $stmt->bind_param("ssiddsisi", $queue_number, $doc_type, $page, $x, $y, $content, $user_id, $role_for_db, $committee_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'annotation_id' => $stmt->insert_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save annotation']);
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['annotation_id'] ?? 0);
        
        // Only allow deleting own annotations unless admin
        if ($user_role === 'admin') {
            $stmt = $conn->prepare("DELETE FROM document_annotations WHERE annotation_id = ?");
            $stmt->bind_param("i", $id);
        } else {
            $stmt = $conn->prepare("DELETE FROM document_annotations WHERE annotation_id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found or permission denied']);
        }
    }
} else {
    // GET: Load annotations
    if ($action === 'load') {
        $queue_number = $_GET['queue_number'] ?? '';
        $doc_type = $_GET['document_type'] ?? '';

        if (empty($queue_number) || empty($doc_type)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT a.*, u.full_name as author_name 
            FROM document_annotations a
            LEFT JOIN users u ON a.created_by = u.user_id
            WHERE a.queue_number = ? AND a.document_type = ?
            ORDER BY a.page_number ASC, a.created_at ASC
        ");
        $stmt->bind_param("ss", $queue_number, $doc_type);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $annotations = [];
        while ($row = $res->fetch_assoc()) {
            $annotations[] = $row;
        }
        
        echo json_encode(['success' => true, 'annotations' => $annotations]);
    }
}
