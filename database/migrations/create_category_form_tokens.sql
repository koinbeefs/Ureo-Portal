-- Category Form Tokens Table
-- Stores access tokens for category forms sent to applicants

CREATE TABLE IF NOT EXISTS category_form_tokens (
    token_id INT PRIMARY KEY AUTO_INCREMENT,
    queue_number VARCHAR(20) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    accessed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
    INDEX idx_queue_number (queue_number),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
