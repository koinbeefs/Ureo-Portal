-- Create separate table for category forms
CREATE TABLE IF NOT EXISTS category_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    category VARCHAR(50) NOT NULL,
    review_type ENUM('exempt', 'expedited', 'full') NOT NULL,
    form_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    INDEX idx_queue (queue_number)
);
