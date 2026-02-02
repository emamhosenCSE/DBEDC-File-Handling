-- Advanced Reports Tables Migration
-- Add tables for saved reports and scheduled reports functionality

-- Saved Reports Table
CREATE TABLE IF NOT EXISTS saved_reports (
    id VARCHAR(26) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    config JSON NOT NULL,
    created_by VARCHAR(26) NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_is_public (is_public),
    INDEX idx_created_at (created_at)
);

-- Scheduled Reports Table
CREATE TABLE IF NOT EXISTS scheduled_reports (
    id VARCHAR(26) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    config JSON NOT NULL,
    schedule_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    recipients JSON NOT NULL, -- Array of email addresses
    created_by VARCHAR(26) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_is_active (is_active),
    INDEX idx_next_run (next_run),
    INDEX idx_schedule_type (schedule_type)
);

-- Report Executions Log Table
CREATE TABLE IF NOT EXISTS report_executions (
    id VARCHAR(26) PRIMARY KEY,
    report_id VARCHAR(26) NULL, -- NULL for ad-hoc reports
    report_type ENUM('saved', 'scheduled', 'ad_hoc') NOT NULL,
    parameters JSON NULL,
    execution_time DECIMAL(5,2) NULL, -- in seconds
    record_count INT DEFAULT 0,
    status ENUM('success', 'error', 'timeout') DEFAULT 'success',
    error_message TEXT NULL,
    executed_by VARCHAR(26) NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES saved_reports(id) ON DELETE SET NULL,
    FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_report_id (report_id),
    INDEX idx_executed_by (executed_by),
    INDEX idx_executed_at (executed_at),
    INDEX idx_status (status)
);

-- Add some sample saved reports for demonstration
INSERT IGNORE INTO saved_reports (id, name, config, created_by, is_public) VALUES
('01HQ0000000000000000000001', 'Monthly Department Performance', '{
    "entity": "letters",
    "filters": {"date_range": "this_month"},
    "group_by": "department",
    "metrics": ["count", "completion_rate", "avg_completion_days"]
}', (SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1), TRUE),

('01HQ0000000000000000000002', 'Overdue Tasks by Priority', '{
    "entity": "tasks",
    "filters": {"status": "PENDING"},
    "group_by": "priority",
    "metrics": ["count", "overdue_count"]
}', (SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1), TRUE),

('01HQ0000000000000000000003', 'Stakeholder Analysis', '{
    "entity": "letters",
    "filters": {"date_range": "this_quarter"},
    "group_by": "stakeholder",
    "metrics": ["count", "completion_rate"]
}', (SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1), TRUE);