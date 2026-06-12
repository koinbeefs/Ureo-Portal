-- Update urec_assignments table to support multiple evaluators per application
ALTER TABLE urec_assignments 
ADD COLUMN queue_number VARCHAR(20) NOT NULL AFTER assignment_id,
ADD COLUMN is_primary TINYINT(1) DEFAULT 0 AFTER user_id,
ADD COLUMN status ENUM('active', 'completed', 'withdrawn') DEFAULT 'active' AFTER is_active,
ADD COLUMN notes TEXT AFTER status,
ADD COLUMN application_id INT DEFAULT NULL AFTER committee_id;

-- Update existing records to populate queue_number from applications table
UPDATE urec_assignments ua
INNER JOIN applications a ON ua.user_id = a.urec_reviewed_by
SET ua.queue_number = a.queue_number,
    ua.application_id = (
        SELECT CAST(SUBSTRING(a.queue_number, 6) AS UNSIGNED) 
        FROM applications WHERE a.queue_number = ua.queue_number
    )
WHERE ua.queue_number IS NULL;

-- Add foreign key for applications
ALTER TABLE urec_assignments 
ADD CONSTRAINT fk_urec_assignments_applications 
FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE;

-- Add unique constraint for primary evaluator per application
ALTER TABLE urec_assignments 
ADD CONSTRAINT uc_primary_evaluator_per_app 
UNIQUE (queue_number, is_primary, status);

-- Add index for better performance
CREATE INDEX idx_urec_assignments_queue ON urec_assignments(queue_number);
CREATE INDEX idx_urec_assignments_status ON urec_assignments(status);
