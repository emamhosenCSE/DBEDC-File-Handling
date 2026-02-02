-- File Tracker Database Migration v2.0
-- Enhanced with: Departments, Stakeholders, Settings, Notifications, Activities, Role-based Access
-- Run this script in your cPanel MySQL database

-- Drop tables if they exist (for clean reinstall)
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS task_updates;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS letters;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS stakeholders;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;

-- ============================================
-- 1. DEPARTMENTS TABLE (Hierarchical)
-- ============================================
CREATE TABLE departments (
    id CHAR(26) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id CHAR(26) DEFAULT NULL COMMENT 'For hierarchical structure',
    manager_id CHAR(26) DEFAULT NULL COMMENT 'Department manager/head',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_manager (manager_id),
    INDEX idx_active (is_active),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. USERS TABLE (Enhanced with Roles)
-- ============================================
CREATE TABLE users (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    google_id VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    department_id CHAR(26) DEFAULT NULL,
    role ENUM('ADMIN', 'MANAGER', 'MEMBER', 'VIEWER') DEFAULT 'MEMBER',
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    push_subscription JSON DEFAULT NULL COMMENT 'Web Push API subscription',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_google_id (google_id),
    INDEX idx_department (department_id),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key to departments after users table exists
ALTER TABLE departments 
ADD CONSTRAINT fk_dept_manager 
FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- ============================================
-- 3. STAKEHOLDERS TABLE (Dynamic)
-- ============================================
CREATE TABLE stakeholders (
    id CHAR(26) PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Short code (e.g., IE, JV, RHD)',
    color VARCHAR(7) DEFAULT '#6B7280' COMMENT 'Hex color for badges',
    icon VARCHAR(50) DEFAULT NULL COMMENT 'Icon or emoji',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. SETTINGS TABLE (System Configuration)
-- ============================================
CREATE TABLE settings (
    id CHAR(26) PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general' COMMENT 'branding, email, system, etc',
    data_type ENUM('string', 'json', 'boolean', 'integer') DEFAULT 'string',
    is_public BOOLEAN DEFAULT FALSE COMMENT 'Can be accessed by frontend',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. LETTERS TABLE (Enhanced)
-- ============================================
CREATE TABLE letters (
    id CHAR(26) PRIMARY KEY,
    reference_no VARCHAR(100) UNIQUE NOT NULL,
    stakeholder_id CHAR(26) NOT NULL COMMENT 'Link to stakeholders table',
    subject TEXT NOT NULL,
    description TEXT COMMENT 'Additional details',
    pdf_filename VARCHAR(255) DEFAULT NULL,
    pdf_thumbnail VARCHAR(255) DEFAULT NULL COMMENT 'Thumbnail path',
    tencent_doc_url TEXT DEFAULT NULL,
    received_date DATE NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
    status ENUM('ACTIVE', 'ARCHIVED', 'DELETED') DEFAULT 'ACTIVE',
    uploaded_by CHAR(26) DEFAULT NULL,
    department_id CHAR(26) DEFAULT NULL COMMENT 'Primary department',
    tags JSON DEFAULT NULL COMMENT 'Custom tags array',
    metadata JSON DEFAULT NULL COMMENT 'Additional metadata',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (stakeholder_id) REFERENCES stakeholders(id) ON DELETE RESTRICT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_reference (reference_no),
    INDEX idx_stakeholder (stakeholder_id),
    INDEX idx_priority (priority),
    INDEX idx_status (status),
    INDEX idx_received_date (received_date),
    INDEX idx_department (department_id),
    INDEX idx_created_at (created_at),
    FULLTEXT idx_search (reference_no, subject, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. TASKS TABLE (Enhanced)
-- ============================================
CREATE TABLE tasks (
    id CHAR(26) PRIMARY KEY,
    letter_id CHAR(26) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    assigned_to CHAR(26) DEFAULT NULL,
    assigned_department CHAR(26) DEFAULT NULL,
    status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'PENDING',
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
    due_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by CHAR(26) DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    completed_by CHAR(26) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (letter_id) REFERENCES letters(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_department) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_assigned_dept (assigned_department),
    INDEX idx_letter (letter_id),
    INDEX idx_due_date (due_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TASK UPDATES (Activity Log for Tasks)
-- ============================================
CREATE TABLE task_updates (
    id CHAR(26) PRIMARY KEY,
    task_id CHAR(26) NOT NULL,
    user_id CHAR(26) DEFAULT NULL,
    old_status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT NULL,
    new_status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_task (task_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. ACTIVITIES TABLE (Global Activity Log)
-- ============================================
CREATE TABLE activities (
    id CHAR(26) PRIMARY KEY,
    user_id CHAR(26) DEFAULT NULL,
    activity_type VARCHAR(50) NOT NULL COMMENT 'letter_created, task_assigned, status_changed, etc',
    entity_type VARCHAR(50) NOT NULL COMMENT 'letter, task, user, department, etc',
    entity_id CHAR(26) NOT NULL,
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

-- ============================================
-- 9. NOTIFICATIONS TABLE (In-App & Push)
-- ============================================
CREATE TABLE notifications (
    id CHAR(26) PRIMARY KEY,
    user_id CHAR(26) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'task_assigned', 'deadline', 'mention') DEFAULT 'info',
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'letter, task, etc',
    entity_id CHAR(26) DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT FALSE COMMENT 'For push notifications',
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default Stakeholders
INSERT INTO stakeholders (id, name, code, color, icon, display_order) VALUES
('01STAKEHOLDER000000000IE', 'IE (Implementing Entity)', 'IE', '#3B82F6', '🏢', 1),
('01STAKEHOLDER000000000JV', 'JV (Joint Venture)', 'JV', '#8B5CF6', '🤝', 2),
('01STAKEHOLDER0000000RHD', 'RHD (Roads & Highways)', 'RHD', '#10B981', '🛣️', 3),
('01STAKEHOLDER000000000ED', 'ED (Engineering Department)', 'ED', '#F59E0B', '⚙️', 4),
('01STAKEHOLDER000000OTHER', 'Other', 'OTHER', '#6B7280', '📋', 5);

-- Default Settings (Branding)
INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public) VALUES
('01SETTING00000COMPANY_NAME', 'company_name', 'DBEDC File Tracker', 'branding', 'string', TRUE),
('01SETTING00000COMPANY_LOGO', 'company_logo', NULL, 'branding', 'string', TRUE),
('01SETTING0000PRIMARY_COLOR', 'primary_color', '#667eea', 'branding', 'string', TRUE),
('01SETTING000SECONDARY_COLOR', 'secondary_color', '#764ba2', 'branding', 'string', TRUE),
('01SETTING00000000000SMTP_HOST', 'smtp_host', 'smtp.gmail.com', 'email', 'string', FALSE),
('01SETTING00000000000SMTP_PORT', 'smtp_port', '587', 'email', 'string', FALSE),
('01SETTING0000000SMTP_USERNAME', 'smtp_username', NULL, 'email', 'string', FALSE),
('01SETTING0000000SMTP_PASSWORD', 'smtp_password', NULL, 'email', 'string', FALSE),
('01SETTING00000SMTP_FROM_EMAIL', 'smtp_from_email', 'noreply@dhakabypass.com', 'email', 'string', FALSE),
('01SETTING00000SMTP_FROM_NAME', 'smtp_from_name', 'DBEDC File Tracker', 'email', 'string', FALSE);

-- Sample Department (Optional - can be removed)
INSERT INTO departments (id, name, description) VALUES
('01DEPARTMENT000000000ROOT', 'Root Department', 'Main organization department');

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- View: Department Statistics
CREATE OR REPLACE VIEW view_department_stats AS
SELECT 
    d.id,
    d.name,
    COUNT(DISTINCT u.id) as user_count,
    COUNT(DISTINCT l.id) as letter_count,
    COUNT(DISTINCT t.id) as task_count,
    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending_tasks
FROM departments d
LEFT JOIN users u ON d.id = u.department_id AND u.is_active = TRUE
LEFT JOIN letters l ON d.id = l.department_id
LEFT JOIN tasks t ON d.id = t.assigned_department
WHERE d.is_active = TRUE
GROUP BY d.id, d.name;

-- View: User Workload
CREATE OR REPLACE VIEW view_user_workload AS
SELECT 
    u.id,
    u.name,
    u.email,
    d.name as department_name,
    COUNT(DISTINCT t.id) as total_tasks,
    SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.due_date < CURDATE() AND t.status != 'COMPLETED' THEN 1 ELSE 0 END) as overdue_tasks
FROM users u
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN tasks t ON u.id = t.assigned_to
WHERE u.is_active = TRUE
GROUP BY u.id, u.name, u.email, d.name;

-- ============================================
-- MIGRATION COMPLETE
-- ============================================

-- Run this query to verify installation
SELECT 
    'Tables Created' as status,
    COUNT(*) as count 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN (
    'departments', 'users', 'stakeholders', 'settings', 
    'letters', 'tasks', 'task_updates', 'activities', 'notifications'
);
