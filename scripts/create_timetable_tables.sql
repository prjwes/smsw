-- Create lesson hours table
CREATE TABLE IF NOT EXISTS lesson_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(100) NOT NULL,
    grade ENUM('7', '8', '9') NOT NULL,
    hours_per_week INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subject_grade (subject, grade)
);

-- Create timetable teachers table
CREATE TABLE IF NOT EXISTS timetable_teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_name VARCHAR(100) NOT NULL,
    teacher_code VARCHAR(10) NOT NULL,
    grade ENUM('7', '8', '9') NOT NULL,
    subject VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_subject_grade (teacher_code, grade, subject)
);

-- Create timetables table
CREATE TABLE IF NOT EXISTS timetables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    timetable_name VARCHAR(255) NOT NULL,
    school_type VARCHAR(50) NOT NULL,
    grades VARCHAR(50) NOT NULL,
    timetable_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
