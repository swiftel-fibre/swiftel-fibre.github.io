-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS swiftel_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the newly created database
USE swiftel_db;

-- Table: user
-- Stores user accounts with roles for authorization
CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) UNIQUE NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL, -- Increased length for PHP password_hash
    role VARCHAR(20) NOT NULL DEFAULT 'employee', -- 'employee', 'employer', 'admin'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: request
-- Stores resource requests submitted by users
CREATE TABLE IF NOT EXISTS request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_type VARCHAR(50) NOT NULL, -- e.g., 'Resource Request' (from frontend)
    description TEXT,
    amount DECIMAL(10, 2) NOT NULL, -- Cost of the request
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'completed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: setting
-- Stores application-wide settings like budget and limits
CREATE TABLE IF NOT EXISTS setting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL, -- Store value as text, can be parsed as JSON if complex
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Data for settings (if tables are new)
-- The PHP backend's db.php script will attempt to insert these if they don't exist,
-- so you don't strictly need to run these inserts manually if your PHP script runs.
-- However, running them manually ensures the database is pre-populated.

-- Initial Budget: 100,000.00
INSERT IGNORE INTO setting (setting_key, setting_value) VALUES ('current_budget', '100000.00');

-- Max active requests per employee
INSERT IGNORE INTO setting (setting_key, setting_value) VALUES ('max_active_requests', '5');

-- Maintenance window start time (HH:MM format)
INSERT IGNORE INTO setting (setting_key, setting_value) VALUES ('maintenance_window_start', '02:00');

-- Maintenance window end time (HH:MM format)
INSERT IGNORE INTO setting (setting_key, setting_value) VALUES ('maintenance_window_end', '04:00');
