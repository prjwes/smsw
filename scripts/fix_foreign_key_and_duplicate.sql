-- Fix foreign key constraint in post_comments
-- The error was: Constraint fails on news_posts but schema says post_comments_ibfk_1 references club_posts
ALTER TABLE post_comments DROP FOREIGN KEY IF EXISTS post_comments_ibfk_1;
ALTER TABLE post_comments ADD CONSTRAINT post_comments_ibfk_1 FOREIGN KEY (post_id) REFERENCES news_posts(id) ON DELETE CASCADE;

-- Ensure timetable_teachers has the right unique constraint to avoid duplicate entry errors
-- The error was: Duplicate entry 'T6921' for key 'teacher_code'
ALTER TABLE timetable_teachers DROP INDEX IF EXISTS teacher_code;
ALTER TABLE timetable_teachers ADD INDEX idx_teacher_code (teacher_code);

-- Also ensure news_comments is correctly referenced if dashboard uses that
-- The dashboard.php uses news_comments table
CREATE TABLE IF NOT EXISTS news_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (news_id) REFERENCES news_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If comment_media references post_comments, ensure post_comments exists and is correct
CREATE TABLE IF NOT EXISTS post_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES news_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
