-- ============================================
-- Authentication Enhancement Migration
-- Adds WeChat OAuth and Email Login support
-- ============================================

-- Add new columns to users table
ALTER TABLE users
ADD COLUMN provider ENUM('google', 'wechat', 'email') DEFAULT 'google' AFTER google_id,
ADD COLUMN wechat_id VARCHAR(100) UNIQUE DEFAULT NULL AFTER provider,
ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER wechat_id,
ADD COLUMN wechat_unionid VARCHAR(100) DEFAULT NULL AFTER wechat_id,
ADD COLUMN wechat_openid VARCHAR(100) DEFAULT NULL AFTER wechat_unionid;

-- Update existing users to have provider = 'google'
UPDATE users SET provider = 'google' WHERE google_id IS NOT NULL;

-- Make google_id nullable since not all users will use Google
ALTER TABLE users MODIFY COLUMN google_id VARCHAR(100) DEFAULT NULL;

-- Add indexes for new fields
ALTER TABLE users ADD INDEX idx_provider (provider);
ALTER TABLE users ADD INDEX idx_wechat_id (wechat_id);
ALTER TABLE users ADD INDEX idx_wechat_unionid (wechat_unionid);

-- ============================================
-- Settings for OAuth providers
-- ============================================

-- Insert WeChat OAuth settings (to be configured by admin)
INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES
('wechat_app_id', '', 'auth', FALSE),
('wechat_app_secret', '', 'auth', FALSE),
('wechat_redirect_uri', 'https://yourdomain.com/wechat_callback.php', 'auth', FALSE)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Insert email login settings
INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES
('email_login_enabled', 'true', 'auth', TRUE),
('password_min_length', '8', 'auth', TRUE),
('password_require_uppercase', 'true', 'auth', TRUE),
('password_require_numbers', 'true', 'auth', TRUE),
('password_require_special', 'false', 'auth', TRUE)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);