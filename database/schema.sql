-- ============================================
-- SCHOOL MANAGEMENT SYSTEM DATABASE SCHEMA
-- Complete and Error-Free Version
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================
-- CORE TABLES
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher', 'Social_Affairs_Teacher', 'Finance_Teacher', 'Teacher', 'Student') NOT NULL,
    profile_image VARCHAR(255) DEFAULT 'default-avatar.png',
    created_by INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role status table
CREATE TABLE IF NOT EXISTS role_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role ENUM('Admin', 'HOI', 'DHOI') NOT NULL UNIQUE,
    is_filled BOOLEAN DEFAULT FALSE,
    filled_by INT,
    filled_date TIMESTAMP NULL,
    FOREIGN KEY (filled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    admission_number VARCHAR(10),
    grade VARCHAR(10) NOT NULL,
    stream VARCHAR(50),
    admission_date DATE NOT NULL,
    admission_year INT NOT NULL,
    status ENUM('Active', 'Promoted', 'Graduated') DEFAULT 'Active',
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    parent_email VARCHAR(100),
    address TEXT,
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_grade (grade),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACADEMIC TABLES
-- ============================================

-- Exams table
CREATE TABLE IF NOT EXISTS exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_name VARCHAR(100) NOT NULL,
    exam_type VARCHAR(100) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    total_marks INT NOT NULL DEFAULT 100,
    exam_date DATE NOT NULL,
    is_school_wide BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_grade (grade),
    INDEX idx_exam_date (exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exam results table
CREATE TABLE IF NOT EXISTS exam_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    marks_obtained DECIMAL(5,2) NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_student_subject (exam_id, student_id, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generated Reports table (FIX for reports.php error)
CREATE TABLE IF NOT EXISTS generated_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(10) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    report_data LONGTEXT NOT NULL,
    file_path VARCHAR(255),
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TIMETABLE TABLES (Updated for Multi-Subject)
-- ============================================

-- Lesson hours config
CREATE TABLE IF NOT EXISTS lesson_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(100) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    hours_per_week INT DEFAULT 3,
    UNIQUE KEY unique_subject_grade (subject, grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timetable sessions
CREATE TABLE IF NOT EXISTS timetable_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_break BOOLEAN DEFAULT FALSE,
    break_type ENUM('short', 'lunch', 'games') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timetable teachers mapping
CREATE TABLE IF NOT EXISTS timetable_teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_name VARCHAR(100) NOT NULL,
    teacher_code VARCHAR(20) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    UNIQUE KEY unique_teacher_assignment (teacher_name, grade, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Final generated timetables
CREATE TABLE IF NOT EXISTS timetables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    timetable_name VARCHAR(100) NOT NULL,
    school_type VARCHAR(50),
    grades VARCHAR(255),
    timetable_data LONGTEXT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NEWS AND FEED TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS news_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    media_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (news_id) REFERENCES news_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Default Sessions
INSERT IGNORE INTO timetable_sessions (session_name, start_time, end_time, is_break, break_type) VALUES
('Session 1', '08:20:00', '09:00:00', FALSE, NULL),
('Session 2', '09:00:00', '09:40:00', FALSE, NULL),
('Break 1', '09:40:00', '09:50:00', TRUE, 'short'),
('Session 3', '09:50:00', '10:30:00', FALSE, NULL),
('Session 4', '10:30:00', '11:10:00', FALSE, NULL),
('Break 2', '11:10:00', '11:20:00', TRUE, 'short'),
('Session 5', '11:20:00', '12:00:00', FALSE, NULL),
('Session 6', '12:00:00', '12:50:00', FALSE, NULL),
('Lunch', '12:50:00', '14:00:00', TRUE, 'lunch'),
('Session 7', '14:00:00', '14:40:00', FALSE, NULL),
('Session 8', '14:40:00', '15:20:00', FALSE, NULL),
('Games', '15:20:00', '16:00:00', TRUE, 'games');

-- Default Lesson Quotas
INSERT IGNORE INTO lesson_hours (subject, grade, hours_per_week) VALUES
('Eng', '7', 5), ('Kisw', '7', 5), ('Math', '7', 5), ('Int. Sci', '7', 5), ('CRE', '7', 3), ('SST', '7', 4), ('CAS', '7', 5), ('Pre.tech', '7', 4), ('Agric', '7', 4),
('Eng', '8', 5), ('Kisw', '8', 5), ('Math', '8', 5), ('Int. Sci', '8', 5), ('CRE', '8', 3), ('SST', '8', 4), ('CAS', '8', 5), ('Pre.tech', '8', 4), ('Agric', '8', 4),
('Eng', '9', 5), ('Kisw', '9', 5), ('Math', '9', 5), ('Int. Sci', '9', 5), ('CRE', '9', 3), ('SST', '9', 4), ('CAS', '9', 5), ('Pre.tech', '9', 4), ('Agric', '9', 4);
