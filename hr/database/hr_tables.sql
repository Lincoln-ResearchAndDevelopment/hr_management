-- HR System Database Tables
-- Create hr_sessions table for HR authentication

CREATE TABLE IF NOT EXISTS hr_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hr_id (hr_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
);

-- Add log table for HR activities
CREATE TABLE IF NOT EXISTS hr_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    description LONGTEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hr_id (hr_id),
    INDEX idx_timestamp (timestamp)
);
