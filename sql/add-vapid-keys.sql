-- VAPID Keys for Web Push Notifications
-- Generated on 2024-02-02

-- Insert VAPID keys into settings table
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('vapid_public_key', 'U7IWDu2IO7ptDCAJ-qGAMdJUk2RI8Pgc4jxoLvcDQig', 'push'),
('vapid_private_key', '2oKHil7_AEbajSBpqoCgdi9LjCDHFixUbywmxk83-8pTshYO7Yg7um0MIAn6oYAx0lSTZEjw-BziPGgu9wNCKA', 'push');

-- Verify insertion
SELECT * FROM settings WHERE setting_group = 'push';
