-- Table for Multiple Evaluator Assignments
CREATE TABLE evaluator_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    evaluator_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'completed', 'withdrawn') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluator_per_app (queue_number, evaluator_id),
    INDEX idx_queue (queue_number),
    INDEX idx_evaluator (evaluator_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
