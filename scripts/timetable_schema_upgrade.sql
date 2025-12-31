-- Complete timetable schema upgrade with all required tables

-- Drop existing tables if they exist (be careful with this in production!)
DROP TABLE IF EXISTS timetable_sessions;
DROP TABLE IF EXISTS timetable_settings;
DROP TABLE IF EXISTS generated_timetables;
DROP TABLE IF EXISTS lesson_hours;
DROP TABLE IF EXISTS timetable_teachers;
DROP TABLE IF EXISTS timetables;

-- Table for timetable settings (timing, breaks, etc.)
CREATE TABLE IF NOT EXISTS timetable_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO timetable_settings (setting_key, setting_value, description) VALUES
('first_session_start', '08:20', 'Time when first session starts'),
('lesson_duration', '40', 'Duration of each lesson in minutes'),
('short_break_duration', '10', 'Duration of short breaks in minutes'),
('lunch_duration', '70', 'Duration of lunch break in minutes (1hr 10min)'),
('games_duration', '40', 'Duration of games time in minutes'),
('last_session_end', '15:20', 'Time when last session ends'),
('working_days', 'Monday,Tuesday,Wednesday,Thursday,Friday', 'Working days of the week'),
('default_grades', '7,8,9', 'Default grades in the school');

-- Table for session time slots
CREATE TABLE IF NOT EXISTS timetable_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_number INT NOT NULL,
    session_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_break BOOLEAN DEFAULT 0,
    break_type ENUM('short', 'lunch', 'games', 'none') DEFAULT 'none',
    session_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default sessions based on requirements
INSERT INTO timetable_sessions (session_number, session_name, start_time, end_time, is_break, break_type, session_order) VALUES
(1, 'Session 1', '08:20', '09:00', 0, 'none', 1),
(2, 'Session 2', '09:00', '09:40', 0, 'none', 2),
(0, 'Break 1', '09:40', '09:50', 1, 'short', 3),
(3, 'Session 3', '09:50', '10:30', 0, 'none', 4),
(4, 'Session 4', '10:30', '11:10', 0, 'none', 5),
(0, 'Break 2', '11:10', '11:20', 1, 'short', 6),
(5, 'Session 5', '11:20', '12:00', 0, 'none', 7),
(6, 'Session 6', '12:10', '12:50', 0, 'none', 8),
(0, 'Lunch Break', '12:50', '14:00', 1, 'lunch', 9),
(7, 'Session 7', '14:00', '14:40', 0, 'none', 10),
(8, 'Session 8', '14:40', '15:20', 0, 'none', 11),
(0, 'Games', '15:20', '16:00', 1, 'games', 12);

-- Table for lesson hours per week (session quotas per subject per grade)
CREATE TABLE IF NOT EXISTS lesson_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(100) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    hours_per_week INT NOT NULL DEFAULT 3,
    can_have_double_session BOOLEAN DEFAULT 0,
    priority_before_break BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subject_grade (subject, grade)
);

-- Insert default subject session counts for all grades
INSERT INTO lesson_hours (subject, grade, hours_per_week, priority_before_break) VALUES
-- Grade 7
('Eng', '7', 5, 0),
('Kisw', '7', 5, 0),
('Math', '7', 5, 0),
('Int. Sci', '7', 5, 0),
('CRE', '7', 3, 0),
('SST', '7', 4, 0),
('CAS', '7', 5, 1),
('Pre.tech', '7', 4, 0),
('Agric', '7', 4, 0),
-- Grade 8
('Eng', '8', 5, 0),
('Kisw', '8', 5, 0),
('Math', '8', 5, 0),
('Int. Sci', '8', 5, 0),
('CRE', '8', 3, 0),
('SST', '8', 4, 0),
('CAS', '8', 5, 1),
('Pre.tech', '8', 4, 0),
('Agric', '8', 4, 0),
-- Grade 9
('Eng', '9', 5, 0),
('Kisw', '9', 5, 0),
('Math', '9', 5, 0),
('Int. Sci', '9', 5, 0),
('CRE', '9', 3, 0),
('SST', '9', 4, 0),
('CAS', '9', 5, 1),
('Pre.tech', '9', 4, 0),
('Agric', '9', 4, 0);

-- Table for teacher assignments
CREATE TABLE IF NOT EXISTS timetable_teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_name VARCHAR(255) NOT NULL,
    teacher_code VARCHAR(20) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_grade_subject (grade, subject)
);

-- Insert sample teachers
INSERT INTO timetable_teachers (teacher_name, teacher_code, grade, subject) VALUES
-- Grade 7 Teachers
('Mr. John Kamau', '01', '7', 'Eng'),
('Mrs. Mary Wanjiru', '02', '7', 'Kisw'),
('Mr. Peter Ochieng', '03', '7', 'Math'),
('Ms. Grace Achieng', '04', '7', 'Int. Sci'),
('Mr. David Mwangi', '05', '7', 'CRE'),
('Mrs. Jane Njeri', '06', '7', 'SST'),
('Mr. Samuel Kiprono', '07', '7', 'CAS'),
('Mr. James Otieno', '08', '7', 'Pre.tech'),
('Mrs. Sarah Wangari', '09', '7', 'Agric'),
-- Grade 8 Teachers
('Mr. John Kamau', '01', '8', 'Eng'),
('Mrs. Mary Wanjiru', '02', '8', 'Kisw'),
('Mr. Peter Ochieng', '03', '8', 'Math'),
('Ms. Grace Achieng', '04', '8', 'Int. Sci'),
('Mr. Paul Kipchoge', '10', '8', 'CRE'),
('Mrs. Jane Njeri', '06', '8', 'SST'),
('Mr. Samuel Kiprono', '07', '8', 'CAS'),
('Mr. James Otieno', '08', '8', 'Pre.tech'),
('Mrs. Sarah Wangari', '09', '8', 'Agric'),
-- Grade 9 Teachers
('Mr. John Kamau', '01', '9', 'Eng'),
('Mrs. Mary Wanjiru', '02', '9', 'Kisw'),
('Mr. Robert Mburu', '11', '9', 'Math'),
('Ms. Grace Achieng', '04', '9', 'Int. Sci'),
('Mr. Paul Kipchoge', '10', '9', 'CRE'),
('Mrs. Jane Njeri', '06', '9', 'SST'),
('Mr. Samuel Kiprono', '07', '9', 'CAS'),
('Mr. James Otieno', '08', '9', 'Pre.tech'),
('Mrs. Sarah Wangari', '09', '9', 'Agric');

-- Table for generated timetables
CREATE TABLE IF NOT EXISTS timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_name VARCHAR(255) NOT NULL,
    school_type VARCHAR(50) DEFAULT 'Secondary',
    grades VARCHAR(100) NOT NULL,
    timetable_data LONGTEXT NOT NULL,
    generation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    INDEX idx_grades (grades),
    INDEX idx_created (created_at)
);

-- Success message
SELECT 'Timetable schema upgraded successfully!' AS message;
