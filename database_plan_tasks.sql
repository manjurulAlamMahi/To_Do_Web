-- ============================================================
-- My Plan / Task Module - Database Schema + Seed Data
-- Import this file separately from database.sql (e.g. drag-and-
-- drop into phpMyAdmin). It only creates a new `plan_tasks`
-- table and references the existing `users` table by foreign
-- key — it never modifies `users` or `todos`.
-- ============================================================

-- ------------------------------------------------------------
-- Table: plan_tasks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_tasks (
  id                 INT PRIMARY KEY AUTO_INCREMENT,
  user_id            INT NOT NULL,
  project_name       VARCHAR(500) NOT NULL,
  responsible_person VARCHAR(200) NULL,
  comment            VARCHAR(1000) NULL,
  completed          TINYINT(1) NOT NULL DEFAULT 0,
  `order`            INT NOT NULL DEFAULT 0,
  is_fixed           TINYINT(1) NOT NULL DEFAULT 0,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_tasks_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_plan_tasks_user_order ON plan_tasks (user_id, `order`);

-- ------------------------------------------------------------
-- Seed data
-- Assumes the demo user (id 1) from database.sql already exists.
-- Safe to delete these lines if you don't want demo rows.
-- ------------------------------------------------------------
INSERT INTO plan_tasks (user_id, project_name, responsible_person, comment, completed, `order`, is_fixed) VALUES
(1, 'ERP Rollout - Phase 1', 'Alice Rahman', 'Kickoff meeting scheduled', 0, 1, 1),
(1, 'Vendor Contract Review', 'Md. Karim', 'Waiting on legal sign-off', 0, 2, 1),
(1, 'Office Relocation Plan', 'Sara Islam', 'Budget approved', 1, 3, 1);
