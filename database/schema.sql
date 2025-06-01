-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255),
    is_premium BOOLEAN DEFAULT FALSE,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create friendships table
CREATE TABLE friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (user1_id, user2_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create messages table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    text TEXT,
    image VARCHAR(255),
    type ENUM('text', 'image') NOT NULL DEFAULT 'text',
    is_read BOOLEAN DEFAULT FALSE,
    edited_at DATETIME,
    deleted_at DATETIME,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create calls table
CREATE TABLE calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caller_id INT NOT NULL,
    recipient_id INT NOT NULL,
    status ENUM('initiated', 'answered', 'missed', 'ended') NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    has_video BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_sessions table for managing active sessions
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_token (session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better query performance
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
CREATE INDEX idx_messages_created_at ON messages(created_at);
CREATE INDEX idx_friendships_users ON friendships(user1_id, user2_id);
CREATE INDEX idx_friendships_status ON friendships(status);
CREATE INDEX idx_calls_users ON calls(caller_id, recipient_id);
CREATE INDEX idx_calls_start_time ON calls(start_time);

-- Create trigger to update user's last_seen timestamp
DELIMITER //
CREATE TRIGGER update_user_last_seen
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.is_online = 0 AND OLD.is_online = 1 THEN
        SET NEW.last_seen = NOW();
    END IF;
END//
DELIMITER ;

-- Create trigger to set message creation timestamp
DELIMITER //
CREATE TRIGGER set_message_timestamp
BEFORE INSERT ON messages
FOR EACH ROW
BEGIN
    SET NEW.created_at = NOW();
END//
DELIMITER ;
