-- Migration script for existing installations
-- Add KNEC portal features and fix personal files

-- Add updated_at column to personal_files if not exists
ALTER TABLE personal_files 
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Create KNEC websites table
CREATE TABLE IF NOT EXISTS knec_websites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    website_name VARCHAR(100) NOT NULL,
    website_url VARCHAR(500) NOT NULL,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_content_hash VARCHAR(64),
    has_updates BOOLEAN DEFAULT FALSE,
    last_update_detected TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create KNEC notifications table
CREATE TABLE IF NOT EXISTS knec_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    website_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES knec_websites(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
);

-- Insert default KNEC websites
INSERT IGNORE INTO knec_websites (website_name, website_url) VALUES
('CBA Portal', 'https://cba.knec.ac.ke/Main'),
('TPAD', 'https://tpad2.tsc.go.ke/'),
('T-PAY', 'https://tpay.tsc.go.ke/fa392bf9a4692aa0cc/zln'),
('TSC PORTAL', 'https://www.tsc.go.ke/index.php');

-- Update lesson hours to match requirements (5 sessions for major subjects)
UPDATE lesson_hours SET hours_per_week = 5 WHERE subject IN ('English', 'Kiswahili', 'Mathematics', 'Integrated Science', 'CA&S');
UPDATE lesson_hours SET hours_per_week = 4 WHERE subject IN ('Social Studies', 'Pre-technical Studies', 'Agriculture');
UPDATE lesson_hours SET hours_per_week = 3 WHERE subject = 'CRE';

-- Add any missing lesson hours entries
INSERT IGNORE INTO lesson_hours (subject, grade, hours_per_week) VALUES
('English', '7', 5),
('English', '8', 5),
('English', '9', 5),
('Kiswahili', '7', 5),
('Kiswahili', '8', 5),
('Kiswahili', '9', 5),
('Mathematics', '7', 5),
('Mathematics', '8', 5),
('Mathematics', '9', 5),
('Integrated Science', '7', 5),
('Integrated Science', '8', 5),
('Integrated Science', '9', 5),
('CRE', '7', 3),
('CRE', '8', 3),
('CRE', '9', 3),
('CA&S', '7', 5),
('CA&S', '8', 5),
('CA&S', '9', 5),
('Pre-technical Studies', '7', 4),
('Pre-technical Studies', '8', 4),
('Pre-technical Studies', '9', 4),
('Social Studies', '7', 4),
('Social Studies', '8', 4),
('Social Studies', '9', 4),
('Agriculture', '7', 4),
('Agriculture', '8', 4),
('Agriculture', '9', 4);
