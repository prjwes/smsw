-- Add new tables and columns for all new features

-- Add personal_files table for personal documents/notes
CREATE TABLE IF NOT EXISTS personal_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255),
    file_type ENUM('document', 'note', 'file') DEFAULT 'file',
    file_size INT,
    content LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at)
);

-- Add comment_media table for media attachments in comments
CREATE TABLE IF NOT EXISTS comment_media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT NOT NULL,
    media_url VARCHAR(255) NOT NULL,
    media_type ENUM('image', 'video', 'document') DEFAULT 'image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES post_comments(id) ON DELETE CASCADE
);

-- Add lesson_hours table to manage lessons per week for each subject
CREATE TABLE IF NOT EXISTS lesson_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(100) NOT NULL,
    grade ENUM('7', '8', '9') NOT NULL,
    hours_per_week INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subject_grade (subject, grade)
);

-- Insert default lesson hours
INSERT IGNORE INTO lesson_hours (subject, grade, hours_per_week) VALUES
('English', '7', 5), ('English', '8', 5), ('English', '9', 5),
('Kiswahili', '7', 5), ('Kiswahili', '8', 5), ('Kiswahili', '9', 5),
('Mathematics', '7', 5), ('Mathematics', '8', 5), ('Mathematics', '9', 5),
('Integrated Science', '7', 5), ('Integrated Science', '8', 5), ('Integrated Science', '9', 5),
('CRE', '7', 3), ('CRE', '8', 3), ('CRE', '9', 3),
('CA&S', '7', 3), ('CA&S', '8', 3), ('CA&S', '9', 3),
('Pre-technical Studies', '7', 3), ('Pre-technical Studies', '8', 3), ('Pre-technical Studies', '9', 3),
('Social Studies', '7', 3), ('Social Studies', '8', 3), ('Social Studies', '9', 3),
('Agriculture', '7', 2), ('Agriculture', '8', 2), ('Agriculture', '9', 2);

-- Modify timetable_teachers to include teacher_code
ALTER TABLE timetable_teachers ADD COLUMN IF NOT EXISTS teacher_code VARCHAR(10) UNIQUE;

-- Update timetables table to support multi-class and filtering
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS classes_included VARCHAR(255);
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS timetable_name VARCHAR(255);

-- Add teacher_timetable table for personal teacher schedules
CREATE TABLE IF NOT EXISTS teacher_timetables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    timetable_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES timetable_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_timetable (teacher_id, timetable_id)
);

-- Add table for grade-specific timetable filters
CREATE TABLE IF NOT EXISTS timetable_grade_filters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    timetable_id INT NOT NULL,
    grade ENUM('7', '8', '9') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE,
    UNIQUE KEY unique_timetable_grade (timetable_id, grade)
);
