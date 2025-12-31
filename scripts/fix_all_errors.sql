-- ============================================
-- FIX ALL DATABASE ERRORS SCRIPT
-- Run this if you're having table issues
-- ============================================

-- Use your database
USE school_management;

-- Drop and recreate news_posts table if it has issues
DROP TABLE IF EXISTS news_comments;
DROP TABLE IF EXISTS news_posts;

CREATE TABLE news_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200),
    content TEXT,
    media_type ENUM('text', 'image', 'video', 'link') DEFAULT 'text',
    media_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (news_id) REFERENCES news_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_news (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure student_media table exists
CREATE TABLE IF NOT EXISTS student_media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    media_type ENUM('image', 'document', 'video', 'other') DEFAULT 'document',
    file_path VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure club_messages table exists
CREATE TABLE IF NOT EXISTS club_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'file', 'image') DEFAULT 'text',
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_club_date (club_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure KNEC tables exist
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knec_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    website_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES knec_websites(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure personal_files has updated_at column
ALTER TABLE personal_files 
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Initialize KNEC websites if not exists
INSERT IGNORE INTO knec_websites (id, website_name, website_url) VALUES
(1, 'CBA Portal', 'https://cba.knec.ac.ke/Main'),
(2, 'TPAD', 'https://tpad2.tsc.go.ke/'),
(3, 'T-PAY', 'https://tpay.tsc.go.ke/fa392bf9a4692aa0cc/zln'),
(4, 'TSC PORTAL', 'https://www.tsc.go.ke/index.php');

-- Verify all tables exist
SELECT 'Checking tables...' as status;
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = 'school_management'
ORDER BY 
    TABLE_NAME;

SELECT 'All tables fixed successfully!' as status;
