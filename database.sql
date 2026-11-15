CREATE DATABASE IF NOT EXISTS taskflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE taskflow;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(180) NOT NULL,
    course VARCHAR(120) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    priority ENUM('rendah', 'sedang', 'tinggi') NOT NULL DEFAULT 'sedang',
    due_date DATE DEFAULT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_tasks_completed_due_date (completed, due_date),
    INDEX idx_tasks_user_status_due_date (user_id, completed, due_date),
    CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
