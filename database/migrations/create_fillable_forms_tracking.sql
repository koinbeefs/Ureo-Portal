-- Table to track fillable form completion
CREATE TABLE fillable_forms (
    form_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    form_type ENUM('qf01', 'qf02') NOT NULL,
    form_data JSON,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_generated TINYINT(1) DEFAULT 1,
    file_uploaded TINYINT(1) DEFAULT 0,
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    UNIQUE KEY unique_form (queue_number, form_type),
    INDEX idx_queue (queue_number)
);
