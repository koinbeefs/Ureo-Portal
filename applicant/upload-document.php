<?php
/**
 * Document Upload Handler
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireApplicantLogin();

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queue_number = $_SESSION['queue_number'];
    $document_type = sanitizeInput($_POST['document_type']);
    $is_revision = isset($_POST['is_revision']) && $_POST['is_revision'] === '1';
    
    // Check if document type is already uploaded (only prevent if not a revision)
    if (!$is_revision) {
        $conn = getDBConnection();
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM documents WHERE queue_number = ? AND document_type = ?");
        $check_stmt->bind_param("ss", $queue_number, $document_type);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if ($check_result['count'] > 0) {
            closeDBConnection($conn);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Document type already uploaded. You cannot upload multiple files for the same document type.']);
                exit();
            } else {
                header("Location: documents.php?error=already_uploaded");
                exit();
            }
        }
        closeDBConnection($conn);
    }
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document_file'];
        
        // Validate file
        $errors = validateFileUpload($file);
        
        if (empty($errors)) {
            // Create upload directory if not exists
            $upload_path = UPLOAD_DIR . $queue_number . '/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $document_type . '_' . time() . '.' . $extension;
            $file_path = $upload_path . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Save to database
                $conn = getDBConnection();
                $relative_path = 'uploads/' . $queue_number . '/' . $filename;
                
                if ($is_revision) {
                    // For revisions, update existing record or insert new one
                    // First check if document exists
                    $check_stmt = $conn->prepare("SELECT document_id FROM documents WHERE queue_number = ? AND document_type = ? ORDER BY upload_timestamp DESC LIMIT 1");
                    $check_stmt->bind_param("ss", $queue_number, $document_type);
                    $check_stmt->execute();
                    $existing_doc = $check_stmt->get_result()->fetch_assoc();
                    
                    if ($existing_doc) {
                        // Update existing document
                        $stmt = $conn->prepare("UPDATE documents SET document_name = ?, file_path = ?, file_size = ?, upload_timestamp = NOW(), validation_status = 'pending' WHERE document_id = ?");
                        $stmt->bind_param("ssii", $file['name'], $relative_path, $file['size'], $existing_doc['document_id']);
                    } else {
                        // Insert new document (shouldn't happen for revisions, but just in case)
                        $stmt = $conn->prepare("INSERT INTO documents (queue_number, document_type, document_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssi", $queue_number, $document_type, $file['name'], $relative_path, $file['size']);
                    }
                } else {
                    // Normal upload
                    $stmt = $conn->prepare("INSERT INTO documents (queue_number, document_type, document_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $queue_number, $document_type, $file['name'], $relative_path, $file['size']);
                }
                
                if ($stmt->execute()) {
                    $document_id = $stmt->insert_id ?: $existing_doc['document_id'];
                    
                    // If this is a revision upload and application is in revision status, 
                    // update status back to under staff review
                    if ($is_revision) {
                        $status_stmt = $conn->prepare("UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = ? AND current_status = ?");
                        $status_stmt->bind_param("sss", STATUS_UNDER_STAFF_REVIEW, $queue_number, STATUS_REVISIONS_REQUIRED);
                        $status_stmt->execute();
                        
                        // Log status change
                        $log_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, 'applicant', 'applicant', 'Revision submitted')");
                        $log_stmt->bind_param("sss", $queue_number, STATUS_REVISIONS_REQUIRED, STATUS_UNDER_STAFF_REVIEW);
                        $log_stmt->execute();
                    }
                    
                    closeDBConnection($conn);
                    
                    // Trigger automation check
                    require_once 'automation/validate-documents.php';
                    validateDocuments($queue_number);
                    
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Document uploaded successfully',
                            'document_id' => $document_id,
                            'document_type' => $document_type
                        ]);
                        exit();
                    } else {
                        header("Location: documents.php?upload=success");
                        exit();
                    }
                } else {
                    unlink($file_path);
                    closeDBConnection($conn);
                    
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
                        exit();
                    } else {
                        header("Location: documents.php?error=database");
                        exit();
                    }
                }
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
                    exit();
                } else {
                    header("Location: documents.php?error=upload");
                    exit();
                }
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                exit();
            } else {
                header("Location: documents.php?error=" . urlencode(implode(', ', $errors)));
                exit();
            }
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit();
        } else {
            header("Location: documents.php?error=nofile");
            exit();
        }
    }
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    } else {
        header("Location: documents.php");
        exit();
    }
}
?>
