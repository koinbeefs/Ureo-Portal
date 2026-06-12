-- TAU-UREO Portal Database Schema
-- Version 1.0
-- Created: February 2, 2026

CREATE DATABASE IF NOT EXISTS ureo_portal;

USE ureo_portal;

-- Users Table (Staff and Admin)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('staff', 'admin') NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    active_status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- Applications Table
CREATE TABLE applications (
    queue_number VARCHAR(20) PRIMARY KEY,
    applicant_email VARCHAR(100) NOT NULL,
    applicant_name VARCHAR(100),
    applicant_type ENUM(
        'student',
        'researcher',
        'faculty'
    ) NOT NULL,
    research_title VARCHAR(255),
    submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    current_status VARCHAR(50) NOT NULL DEFAULT 'INTENT_RECEIVED',
    category ENUM('exempt', 'expedited', 'full') NULL,
    assigned_staff_id INT NULL,
    has_additional_requirements TINYINT(1) DEFAULT 0,
    completion_attempts INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_staff_id) REFERENCES users (user_id) ON DELETE SET NULL,
    INDEX idx_status (current_status),
    INDEX idx_email (applicant_email),
    INDEX idx_assigned_staff (assigned_staff_id)
);

-- Documents Table
CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validation_status ENUM(
        'pending',
        'validated',
        'rejected'
    ) DEFAULT 'pending',
    validation_notes TEXT,
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE CASCADE,
    INDEX idx_queue (queue_number),
    INDEX idx_type (document_type)
);

-- Required Documents Checklist
CREATE TABLE required_documents (
    requirement_id INT AUTO_INCREMENT PRIMARY KEY,
    document_type VARCHAR(100) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_conditional TINYINT(1) DEFAULT 0,
    conditional_field VARCHAR(100),
    file_formats VARCHAR(100) DEFAULT 'pdf,doc,docx',
    mandatory TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1
);

-- Application Status History
CREATE TABLE status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NULL,
    changed_by_type ENUM('system', 'staff', 'admin') NOT NULL,
    notes TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users (user_id) ON DELETE SET NULL,
    INDEX idx_queue (queue_number)
);

-- Staff Activity Logs
CREATE TABLE staff_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    queue_number VARCHAR(20),
    action_type ENUM(
        'opened_message',
        'sent_reply',
        'approved',
        'rejected',
        'viewed_application',
        'downloaded_document',
        'reclassified_ai',
        'ai_feedback',
        'other'
    ) NOT NULL,
    action_details TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users (user_id) ON DELETE CASCADE,
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE SET NULL,
    INDEX idx_staff (staff_id),
    INDEX idx_queue (queue_number),
    INDEX idx_timestamp (timestamp)
);

-- OTP Sessions Table
CREATE TABLE otp_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    attempts INT DEFAULT 0,
    ip_address VARCHAR(45),
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE CASCADE,
    INDEX idx_queue (queue_number),
    INDEX idx_otp (otp_code)
);

-- Messages Table (Applicant-Staff Communication)
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    sender_type ENUM(
        'applicant',
        'staff',
        'system'
    ) NOT NULL,
    sender_id INT NULL,
    message_content TEXT NOT NULL,
    attachment_path VARCHAR(500),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    read_by INT NULL,
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users (user_id) ON DELETE SET NULL,
    FOREIGN KEY (read_by) REFERENCES users (user_id) ON DELETE SET NULL,
    INDEX idx_queue (queue_number),
    INDEX idx_read (read_status)
);

-- Email Notifications Log
CREATE TABLE email_logs (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20),
    recipient_email VARCHAR(100) NOT NULL,
    email_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE SET NULL,
    INDEX idx_queue (queue_number),
    INDEX idx_status (status)
);

-- System Settings
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default required documents
INSERT INTO
    required_documents (
        document_type,
        display_name,
        description,
        is_conditional,
        mandatory,
        display_order
    )
VALUES (
        'letter_of_intent',
        'Letter of Intent',
        'Official letter expressing intent to conduct research',
        0,
        1,
        1
    ),
    (
        'research_proposal',
        'Research Proposal',
        'Detailed research proposal document',
        0,
        1,
        2
    ),
    (
        'ethics_checklist',
        'Research Ethics Checklist',
        'Completed ethics assessment checklist',
        0,
        1,
        3
    ),
    (
        'informed_consent',
        'Informed Consent Form',
        'Template for participant consent',
        0,
        1,
        4
    ),
    (
        'curriculum_vitae',
        'Curriculum Vitae',
        'CV of principal investigator',
        0,
        1,
        5
    ),
    (
        'data_collection_tools',
        'Data Collection Instruments',
        'Surveys, interview guides, etc.',
        0,
        1,
        6
    );

-- Insert system settings
INSERT INTO
    system_settings (
        setting_key,
        setting_value,
        description
    )
VALUES (
        'queue_counter',
        '0',
        'Current queue number counter'
    ),
    (
        'smtp_host',
        'localhost',
        'SMTP server host'
    ),
    (
        'smtp_port',
        '587',
        'SMTP server port'
    ),
    (
        'system_email',
        'ureo@tau.edu.ph',
        'System sender email address'
    ),
    (
        'otp_expiry_minutes',
        '10',
        'OTP expiration time in minutes'
    ),
    (
        'max_file_size_mb',
        '10',
        'Maximum file upload size in MB'
    ),
    (
        'session_timeout_minutes',
        '30',
        'Session timeout duration'
    );

-- Create default admin account (password: admin123 - CHANGE IN PRODUCTION)
INSERT INTO
    users (
        username,
        password_hash,
        role,
        email,
        full_name
    )
VALUES (
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',
        'admin@tau.edu.ph',
        'System Administrator'
    );

-- Email Templates Table
CREATE TABLE IF NOT EXISTS email_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(50) UNIQUE NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    placeholders TEXT COMMENT 'JSON array of available placeholders',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (template_code),
    INDEX idx_category (category)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;