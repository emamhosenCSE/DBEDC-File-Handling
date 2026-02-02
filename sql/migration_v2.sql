-- File Tracker Database Migration v2.0
-- Enhanced with: Departments, Stakeholders, Settings, Notifications, Activities, Role-based Access
-- Push Notifications, Email Queue, User Preferences
-- Run this script in your cPanel MySQL database

-- ============================================
-- DROP EXISTING TABLES (for clean reinstall)
-- ============================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS email_queue;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS task_updates;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS letters;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS stakeholders;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

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
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    INDEX idx_manager (manager_id),
    INDEX idx_active (is_active),
    INDEX idx_name (name),
    INDEX idx_order (display_order)
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
-- 3. USER PREFERENCES TABLE
-- ============================================
CREATE TABLE user_preferences (
    id CHAR(26) PRIMARY KEY,
    user_id CHAR(26) NOT NULL UNIQUE,
    quick_actions JSON DEFAULT NULL COMMENT 'Customizable quick action shortcuts',
    dashboard_layout JSON DEFAULT NULL COMMENT 'Dashboard widget preferences',
    theme_preference VARCHAR(20) DEFAULT 'system',
    default_view VARCHAR(50) DEFAULT 'my-tasks',
    items_per_page INT DEFAULT 25,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. STAKEHOLDERS TABLE (Dynamic)
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
-- 5. SETTINGS TABLE (System Configuration)
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
    INDEX idx_group (setting_group),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. LETTERS TABLE (Enhanced)
-- ============================================
CREATE TABLE letters (
    id CHAR(26) PRIMARY KEY,
    reference_no VARCHAR(100) UNIQUE NOT NULL,
    stakeholder_id CHAR(26) NOT NULL COMMENT 'Link to stakeholders table',
    subject TEXT NOT NULL,
    description TEXT COMMENT 'Additional details',
    pdf_filename VARCHAR(255) DEFAULT NULL,
    pdf_original_name VARCHAR(255) DEFAULT NULL,
    pdf_size INT DEFAULT NULL COMMENT 'File size in bytes',
    tencent_doc_url TEXT DEFAULT NULL,
    received_date DATE NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
    status ENUM('ACTIVE', 'ARCHIVED', 'DELETED') DEFAULT 'ACTIVE',
    uploaded_by CHAR(26) DEFAULT NULL,
    department_id CHAR(26) DEFAULT NULL COMMENT 'Primary department',
    tags JSON DEFAULT NULL COMMENT 'Custom tags array',
    metadata JSON DEFAULT NULL COMMENT 'Additional metadata',
    import_batch_id CHAR(26) DEFAULT NULL COMMENT 'For bulk import tracking',
    import_row_number INT DEFAULT NULL,
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
    INDEX idx_import_batch (import_batch_id),
    FULLTEXT idx_search (reference_no, subject, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TASKS TABLE (Enhanced)
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
    INDEX idx_created_at (created_at),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. TASK UPDATES (Activity Log for Tasks)
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
-- 9. ACTIVITIES TABLE (Global Activity Log)
-- ============================================
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

-- ============================================
-- 10. NOTIFICATIONS TABLE (In-App & Push)
-- ============================================
CREATE TABLE notifications (
    id CHAR(26) PRIMARY KEY,
    user_id CHAR(26) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'task_assigned', 'task_updated', 'deadline', 'mention', 'letter_created') DEFAULT 'info',
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'letter, task, etc',
    entity_id CHAR(26) DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_email_sent BOOLEAN DEFAULT FALSE,
    is_push_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    push_sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. EMAIL QUEUE TABLE
-- ============================================
CREATE TABLE email_queue (
    id CHAR(26) PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(500) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    template VARCHAR(50) DEFAULT NULL,
    template_data JSON DEFAULT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. PUSH SUBSCRIPTIONS TABLE
-- ============================================
CREATE TABLE push_subscriptions (
    id CHAR(26) PRIMARY KEY,
    user_id CHAR(26) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh_key TEXT NOT NULL,
    auth_key TEXT NOT NULL,
    user_agent TEXT,
    device_name VARCHAR(100) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA - STAKEHOLDERS
-- ============================================
INSERT INTO stakeholders (id, name, code, color, icon, description, display_order) VALUES
('01STAKEHOLDER000000000IE', 'IE (Implementing Entity)', 'IE', '#3B82F6', '🏢', 'Implementing Entity stakeholder', 1),
('01STAKEHOLDER000000000JV', 'JV (Joint Venture)', 'JV', '#8B5CF6', '🤝', 'Joint Venture partner', 2),
('01STAKEHOLDER0000000RHD', 'RHD (Roads & Highways)', 'RHD', '#10B981', '🛣️', 'Roads and Highways Department', 3),
('01STAKEHOLDER000000000ED', 'ED (Engineering Department)', 'ED', '#F59E0B', '⚙️', 'Engineering Department', 4),
('01STAKEHOLDER000000OTHER', 'Other', 'OTHER', '#6B7280', '📋', 'Other stakeholders', 5);

-- ============================================
-- DEFAULT DATA - SETTINGS (Branding)
-- ============================================
INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description) VALUES
('01SETTING00000COMPANY_NAME', 'company_name', 'DBEDC File Tracker', 'branding', 'string', TRUE, 'Company/Application name'),
('01SETTING00000COMPANY_LOGO', 'company_logo', NULL, 'branding', 'string', TRUE, 'Company logo URL'),
('01SETTING0000PRIMARY_COLOR', 'primary_color', '#667eea', 'branding', 'string', TRUE, 'Primary theme color'),
('01SETTING000SECONDARY_COLOR', 'secondary_color', '#764ba2', 'branding', 'string', TRUE, 'Secondary theme color'),
('01SETTING000000ACCENT_COLOR', 'accent_color', '#10B981', 'branding', 'string', TRUE, 'Accent color for highlights');

-- ============================================
-- DEFAULT DATA - SETTINGS (Email/SMTP)
-- ============================================
INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description) VALUES
('01SETTING00000000SMTP_HOST', 'smtp_host', 'smtp.gmail.com', 'email', 'string', FALSE, 'SMTP server hostname'),
('01SETTING00000000SMTP_PORT', 'smtp_port', '587', 'email', 'integer', FALSE, 'SMTP server port'),
('01SETTING000000SMTP_SECURE', 'smtp_secure', 'tls', 'email', 'string', FALSE, 'SMTP security (tls/ssl)'),
('01SETTING0000SMTP_USERNAME', 'smtp_username', NULL, 'email', 'string', FALSE, 'SMTP username'),
('01SETTING0000SMTP_PASSWORD', 'smtp_password', NULL, 'email', 'string', FALSE, 'SMTP password'),
('01SETTING000SMTP_FROM_EMAIL', 'smtp_from_email', 'noreply@dhakabypass.com', 'email', 'string', FALSE, 'From email address'),
('01SETTING0000SMTP_FROM_NAME', 'smtp_from_name', 'DBEDC File Tracker', 'email', 'string', FALSE, 'From name');

-- ============================================
-- DEFAULT DATA - SETTINGS (Push Notifications)
-- ============================================
INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description) VALUES
('01SETTING000VAPID_PUBLIC_KEY', 'vapid_public_key', NULL, 'push', 'string', TRUE, 'VAPID public key for web push'),
('01SETTING00VAPID_PRIVATE_KEY', 'vapid_private_key', NULL, 'push', 'string', FALSE, 'VAPID private key for web push'),
('01SETTING0000000VAPID_SUBJECT', 'vapid_subject', 'mailto:admin@dhakabypass.com', 'push', 'string', FALSE, 'VAPID subject (email)');

-- ============================================
-- DEFAULT DATA - SETTINGS (System)
-- ============================================
INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description) VALUES
('01SETTING00DEFAULT_PRIORITY', 'default_priority', 'MEDIUM', 'system', 'string', TRUE, 'Default priority for new items'),
('01SETTING000DEFAULT_DUE_DAYS', 'default_due_days', '7', 'system', 'integer', TRUE, 'Default days until due date'),
('01SETTING0000ITEMS_PER_PAGE', 'items_per_page', '25', 'system', 'integer', TRUE, 'Default items per page'),
('01SETTING00ENABLE_EMAIL_NOTIF', 'enable_email_notifications', 'true', 'system', 'boolean', FALSE, 'Enable email notifications'),
('01SETTING00ENABLE_PUSH_NOTIF', 'enable_push_notifications', 'true', 'system', 'boolean', FALSE, 'Enable push notifications');

-- ============================================
-- DEFAULT DATA - ROOT DEPARTMENT
-- ============================================
INSERT INTO departments (id, name, description, display_order) VALUES
('01DEPARTMENT000000000ROOT', 'Organization', 'Root organization department', 0);

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- View: Department Statistics
CREATE OR REPLACE VIEW view_department_stats AS
SELECT 
    d.id,
    d.name,
    d.description,
    d.parent_id,
    m.name as manager_name,
    m.email as manager_email,
    COUNT(DISTINCT u.id) as user_count,
    COUNT(DISTINCT l.id) as letter_count,
    COUNT(DISTINCT t.id) as task_count,
    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_tasks
FROM departments d
LEFT JOIN users m ON d.manager_id = m.id
LEFT JOIN users u ON d.id = u.department_id AND u.is_active = TRUE
LEFT JOIN letters l ON d.id = l.department_id AND l.status = 'ACTIVE'
LEFT JOIN tasks t ON d.id = t.assigned_department
WHERE d.is_active = TRUE
GROUP BY d.id, d.name, d.description, d.parent_id, m.name, m.email;

-- View: User Workload
CREATE OR REPLACE VIEW view_user_workload AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.role,
    u.avatar_url,
    d.id as department_id,
    d.name as department_name,
    COUNT(DISTINCT t.id) as total_tasks,
    SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.due_date < CURDATE() AND t.status NOT IN ('COMPLETED', 'CANCELLED') THEN 1 ELSE 0 END) as overdue_tasks
FROM users u
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN tasks t ON u.id = t.assigned_to
WHERE u.is_active = TRUE
GROUP BY u.id, u.name, u.email, u.role, u.avatar_url, d.id, d.name;

-- View: Letter Summary
CREATE OR REPLACE VIEW view_letter_summary AS
SELECT 
    l.id,
    l.reference_no,
    l.subject,
    l.received_date,
    l.priority,
    l.status,
    l.created_at,
    s.name as stakeholder_name,
    s.code as stakeholder_code,
    s.color as stakeholder_color,
    d.name as department_name,
    u.name as uploaded_by_name,
    COUNT(DISTINCT t.id) as task_count,
    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks
FROM letters l
LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
LEFT JOIN departments d ON l.department_id = d.id
LEFT JOIN users u ON l.uploaded_by = u.id
LEFT JOIN tasks t ON l.id = t.letter_id
WHERE l.status != 'DELETED'
GROUP BY l.id, l.reference_no, l.subject, l.received_date, l.priority, l.status, l.created_at,
         s.name, s.code, s.color, d.name, u.name;

-- ============================================
-- STORED PROCEDURES
-- ============================================

-- Procedure: Get unread notification count
DELIMITER //
DROP PROCEDURE IF EXISTS sp_get_unread_count;
CREATE PROCEDURE sp_get_unread_count(IN p_user_id CHAR(26))
BEGIN
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = p_user_id AND is_read = FALSE;
END //
DELIMITER ;

-- Procedure: Mark all notifications as read
DELIMITER //
DROP PROCEDURE IF EXISTS sp_mark_all_read;
CREATE PROCEDURE sp_mark_all_read(IN p_user_id CHAR(26))
BEGIN
    UPDATE notifications 
    SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
    WHERE user_id = p_user_id AND is_read = FALSE;
END //
DELIMITER ;

-- Procedure: Process email queue
DELIMITER //
DROP PROCEDURE IF EXISTS sp_get_pending_emails;
CREATE PROCEDURE sp_get_pending_emails(IN p_limit INT)
BEGIN
    SELECT * FROM email_queue 
    WHERE status = 'pending' 
    AND scheduled_at <= CURRENT_TIMESTAMP
    AND attempts < max_attempts
    ORDER BY scheduled_at ASC
    LIMIT p_limit;
END //
DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger: Auto-create user preferences on user insert
DELIMITER //
DROP TRIGGER IF EXISTS tr_user_after_insert;
CREATE TRIGGER tr_user_after_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO user_preferences (id, user_id, quick_actions, dashboard_layout)
    VALUES (
        CONCAT('01PREF', SUBSTRING(NEW.id, 6)),
        NEW.id,
        '["add-letter", "my-tasks", "notifications", "calendar"]',
        '{"showCalendar": true, "showActivity": true, "showStats": true}'
    );
END //
DELIMITER ;

-- Trigger: Log activity on letter creation
DELIMITER //
DROP TRIGGER IF EXISTS tr_letter_after_insert;
CREATE TRIGGER tr_letter_after_insert
AFTER INSERT ON letters
FOR EACH ROW
BEGIN
    INSERT INTO activities (id, user_id, activity_type, entity_type, entity_id, title, description, created_at)
    VALUES (
        CONCAT('01ACT', LPAD(FLOOR(RAND() * 999999999999999999999), 21, '0')),
        NEW.uploaded_by,
        'letter_created',
        'letter',
        NEW.id,
        'Letter Created',
        CONCAT('New letter "', NEW.reference_no, '" was created'),
        CURRENT_TIMESTAMP
    );
END //
DELIMITER ;

-- Trigger: Log activity on task status change
DELIMITER //
DROP TRIGGER IF EXISTS tr_task_after_update;
CREATE TRIGGER tr_task_after_update
AFTER UPDATE ON tasks
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activities (id, user_id, activity_type, entity_type, entity_id, title, description, metadata, created_at)
        VALUES (
            CONCAT('01ACT', LPAD(FLOOR(RAND() * 999999999999999999999), 21, '0')),
            NEW.assigned_to,
            'task_status_changed',
            'task',
            NEW.id,
            'Task Status Updated',
            CONCAT('Task status changed from ', OLD.status, ' to ', NEW.status),
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status),
            CURRENT_TIMESTAMP
        );
    END IF;
END //
DELIMITER ;

DELIMITER ;

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
    'departments', 'users', 'user_preferences', 'stakeholders', 'settings', 
    'letters', 'tasks', 'task_updates', 'activities', 'notifications',
    'email_queue', 'push_subscriptions'
);

-- Show table counts
SELECT 'Stakeholders' as entity, COUNT(*) as count FROM stakeholders
UNION ALL
SELECT 'Settings' as entity, COUNT(*) as count FROM settings
UNION ALL
SELECT 'Departments' as entity, COUNT(*) as count FROM departments;
