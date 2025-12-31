-- Added generated_reports table to store historical data
CREATE TABLE IF NOT EXISTS `generated_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `academic_year` VARCHAR(10) NOT NULL,
  `term` VARCHAR(5) NOT NULL,
  `grade` VARCHAR(10) NOT NULL,
  `file_path` VARCHAR(255),
  `report_data` LONGTEXT, -- Stores JSON of students and results for viewing
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fixed teacher code constraint to allow multiple subjects per teacher
-- Removed unique constraint from teacher_code if it exists to allow one teacher multiple rows
-- or we use the logic to check if exists.
ALTER TABLE `timetable_teachers` DROP INDEX IF EXISTS `teacher_code`;
