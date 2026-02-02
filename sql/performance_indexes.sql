-- Database Performance Optimization
-- Add missing indexes for better query performance
-- Run this after the main migration_v2.sql

-- ============================================
-- PERFORMANCE INDEXES
-- ============================================

-- Tasks table indexes
CREATE INDEX idx_tasks_status_priority ON tasks(status, priority);
CREATE INDEX idx_tasks_assigned_due ON tasks(assigned_to, due_date);
CREATE INDEX idx_tasks_letter_status ON tasks(letter_id, status);
CREATE INDEX idx_tasks_created_assigned ON tasks(created_at, assigned_to);

-- Letters table indexes
CREATE INDEX idx_letters_stakeholder_date ON letters(stakeholder_id, received_date);
CREATE INDEX idx_letters_priority_status ON letters(priority, status);
CREATE INDEX idx_letters_department_date ON letters(department_id, received_date);
CREATE INDEX idx_letters_uploaded_date ON letters(uploaded_by, created_at);

-- Users table indexes
CREATE INDEX idx_users_department_role ON users(department_id, role);
CREATE INDEX idx_users_email_active ON users(email, is_active);
CREATE INDEX idx_users_last_login ON users(last_login);

-- Notifications table indexes
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_type_created ON notifications(type, created_at);
CREATE INDEX idx_notifications_entity ON notifications(entity_type, entity_id);

-- Activities table indexes (partitioning candidate)
CREATE INDEX idx_activities_user_date ON activities(user_id, created_at);
CREATE INDEX idx_activities_entity_date ON activities(entity_type, entity_id, created_at);
CREATE INDEX idx_activities_type_date ON activities(activity_type, created_at);

-- Email queue indexes
CREATE INDEX idx_email_queue_status_scheduled ON email_queue(status, scheduled_at);
CREATE INDEX idx_email_queue_recipient_status ON email_queue(recipient_email, status);

-- Settings table indexes
CREATE INDEX idx_settings_group_key ON settings(setting_group, setting_key);

-- Departments table indexes
CREATE INDEX idx_departments_parent_active ON departments(parent_id, is_active);

-- Stakeholders table indexes
CREATE INDEX idx_stakeholders_active_order ON stakeholders(is_active, display_order);

-- Push subscriptions indexes
CREATE INDEX idx_push_user_active ON push_subscriptions(user_id, is_active);

-- Task updates indexes
CREATE INDEX idx_task_updates_task_date ON task_updates(task_id, created_at);

-- User preferences indexes
CREATE INDEX idx_user_preferences_user ON user_preferences(user_id);

-- ============================================
-- FULL-TEXT SEARCH INDEXES
-- ============================================

-- Full-text search on letters
ALTER TABLE letters ADD FULLTEXT idx_letters_search (reference_no, subject, description);

-- Full-text search on tasks
ALTER TABLE tasks ADD FULLTEXT idx_tasks_search (title, description);

-- ============================================
-- OPTIMIZATION COMPLETE
-- ============================================