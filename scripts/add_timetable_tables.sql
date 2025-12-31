-- Add timetable tables
ALTER TABLE timetables MODIFY school_type VARCHAR(50);

-- Timetable Teachers table
CREATE TABLE IF NOT EXISTS `timetable_teachers` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `teacher_name` varchar(100) NOT NULL,
  `grade` varchar(10) NOT NULL,
  `subject` varchar(50) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `grade` (`grade`),
  KEY `subject` (`subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timetables table
CREATE TABLE IF NOT EXISTS `timetables` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `school_type` varchar(50) NOT NULL,
  `grades` varchar(50) NOT NULL,
  `timetable_data` longtext NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `school_grades` (`school_type`, `grades`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add student media galleries table
CREATE TABLE IF NOT EXISTS `student_media` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `student_id` int NOT NULL,
  `media_type` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  KEY `student_id` (`student_id`),
  KEY `media_type` (`media_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
