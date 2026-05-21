-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User Settings table (holds API keys, secrets, defaults)
CREATE TABLE IF NOT EXISTS `settings` (
  `user_id` INT PRIMARY KEY,
  `youtube_client_id` VARCHAR(255) DEFAULT NULL,
  `youtube_client_secret` VARCHAR(255) DEFAULT NULL,
  `youtube_access_token` TEXT DEFAULT NULL,
  `youtube_refresh_token` TEXT DEFAULT NULL,
  `youtube_token_expiry` DATETIME DEFAULT NULL,
  `gemini_api_key` VARCHAR(255) DEFAULT NULL,
  `default_privacy` ENUM('public', 'private', 'unlisted') DEFAULT 'private',
  `default_category` VARCHAR(10) DEFAULT '22', -- 22 is People & Blogs
  `default_tags` TEXT DEFAULT NULL,
  `default_description` TEXT DEFAULT NULL,
  `timezone` VARCHAR(100) DEFAULT 'UTC',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Videos and Upload Queue table
CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_size` BIGINT NOT NULL,
  `title` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `tags` TEXT DEFAULT NULL,
  `privacy_status` ENUM('public', 'private', 'unlisted') DEFAULT 'private',
  `category_id` VARCHAR(10) DEFAULT '22',
  `thumbnail_path` VARCHAR(255) DEFAULT NULL,
  `playlist_id` VARCHAR(255) DEFAULT NULL,
  `is_short` TINYINT(1) DEFAULT 0,
  `status` ENUM('draft', 'queued', 'uploading', 'completed', 'failed') DEFAULT 'draft',
  `scheduled_time` DATETIME DEFAULT NULL,
  `youtube_video_id` VARCHAR(50) DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `retry_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status_time` (`status`, `scheduled_time`)
) ENGINE=InnoDB;

-- System and Upload Activity Logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `log_type` ENUM('auth', 'upload', 'scheduler', 'cron', 'error') NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
