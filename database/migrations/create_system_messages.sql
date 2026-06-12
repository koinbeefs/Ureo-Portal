-- System Messages Table
-- Replaces email functionality with in-system messaging
CREATE TABLE IF NOT EXISTS system_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    queue_number VARCHAR(50) NOT NULL,
    message_type ENUM('acknowledgment', 'requirement', 'update', 'approval', 'rejection', 'certificate') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message_body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    INDEX idx_queue_number (queue_number),
    INDEX idx_message_type (message_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Documents Table
-- Stores references to template documents provided to applicants
CREATE TABLE IF NOT EXISTS system_documents (
    system_doc_id INT PRIMARY KEY AUTO_INCREMENT,
    queue_number VARCHAR(50) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    document_type ENUM('template', 'guideline', 'reference') NOT NULL,
    provided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    INDEX idx_queue_number (queue_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
