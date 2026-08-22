-- ============================================================
-- To Do List App - Database Schema + Seed Data
-- Import this file into MySQL before using the application.
-- ============================================================

-- CREATE DATABASE IF NOT EXISTS todo_app
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;

-- USE todo_app;

-- ------------------------------------------------------------
-- Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: todos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS todos (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT NOT NULL,
  text       VARCHAR(500) NOT NULL,
  completed  TINYINT(1) NOT NULL DEFAULT 0,
  `order`    INT NOT NULL DEFAULT 0,
  is_fixed   TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_todos_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_todos_user_order ON todos (user_id, `order`);

-- ------------------------------------------------------------
-- Seed data
-- Demo login: demo@example.com / password123
-- ------------------------------------------------------------
INSERT INTO users (name, email, password) VALUES
('Demo User', 'demo@example.com', '$2y$12$lawDl5dPjD4j95ABn7PODeERk2//w//1BunzHLCz6Mk6hACiEU/3K');

INSERT INTO todos (user_id, text, completed, `order`, is_fixed) VALUES
(1, 'Review quarterly ERP module requirements', 0, 1, 1),
(1, 'Prepare onboarding checklist for new hires', 0, 2, 1),
(1, 'Submit expense report for last month', 1, 3, 1);
