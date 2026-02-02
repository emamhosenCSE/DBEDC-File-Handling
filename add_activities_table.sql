-- Add missing activities table to database
-- This table was dropped by migration_v2.sql but not recreated

CREATE TABLE activities (
    id CHAR(26) PRIMARY KEY,
    user_id CHAR(26) DEFAULT NULL,
    activity_type VARCHAR(50) NOT NULL COMMENT 'letter_created, task_assigned, status_changed, etc',
    entity_type VARCHAR(50) NOT NULL COMMENT 'letter, task, user, department, etc',
    entity_id CHAR(26) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    metadata JSON DEFAULT NULL COMMENT 'Additional context',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (activity_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;