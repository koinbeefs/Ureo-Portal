-- Create table for document annotations/remarks
CREATE TABLE IF NOT EXISTS document_annotations (
    annotation_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    annotation_type ENUM('remark', 'comment', 'highlight') DEFAULT 'remark',
    page_number INT DEFAULT 1,
    x_position DECIMAL(8, 3), -- X position as percentage (0-100)
    y_position DECIMAL(8, 3), -- Y position as percentage (0-100)
    width DECIMAL(8, 3),      -- Width as percentage (0-100)
    height DECIMAL(8, 3),     -- Height as percentage (0-100)
    content TEXT NOT NULL,    -- The remark/comment content
    created_by INT,
    created_by_type ENUM('staff', 'admin') DEFAULT 'staff',
    committee_id INT DEFAULT NULL, -- Tracking which committee made the pin
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (committee_id) REFERENCES urec_committees(committee_id) ON DELETE SET NULL,
    INDEX idx_queue_document (queue_number, document_type),
    INDEX idx_page (page_number),
    INDEX idx_committee (committee_id)
);
