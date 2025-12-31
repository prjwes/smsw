-- Create club messages table for group chat
CREATE TABLE IF NOT EXISTS club_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT,
    message_type ENUM('text', 'image', 'file') DEFAULT 'text',
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_club_created (club_id, created_at)
);
