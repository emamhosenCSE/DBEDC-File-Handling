<?php
/**
 * System Configuration
 * Centralized configuration management for dynamic content
 */

// Only require db.php if database config exists
$dbAvailable = false;
if (file_exists(__DIR__ . '/db_config.php')) {
    try {
        require_once __DIR__ . '/db.php';
        $dbAvailable = true;
    } catch (Exception $e) {
        $dbAvailable = false;
    }
}

/**
 * Get system configuration with fallbacks
 */
function getSystemConfig($key = null, $default = null) {
    static $config = null;

    if ($config === null) {
        $config = [
            // Company/Branding
            'company_name' => 'DBEDC File Tracker',
            'company_logo' => null,
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',

            // Email Configuration
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
            'smtp_from_email' => 'noreply@dhakabypass.com',
            'smtp_from_name' => 'DBEDC File Tracker',

            // Workflow Automation
            'workflow_escalation_days' => 3,
            'workflow_reminder_days' => 2,
            'workflow_review_months' => 6,

            // System Limits
            'max_upload_size' => 10 * 1024 * 1024, // 10MB
            'max_file_uploads' => 5,
            'session_timeout' => 3600, // 1 hour
            'cron_execution_timeout' => 300, // 5 minutes

            // UI/UX
            'items_per_page' => 25,
            'search_debounce_ms' => 300,
            'toast_duration_ms' => 5000,

            // Notifications
            'push_notification_title' => 'DBEDC File Tracker',
            'email_signature' => 'DBEDC File Tracking System',

            // Security
            'password_min_length' => 8,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 15,

            // Performance
            'cache_ttl_seconds' => 3600, // 1 hour
            'db_query_timeout' => 30, // seconds
        ];

        // Load from database settings
        global $dbAvailable;
        if ($dbAvailable) {
            try {
                global $pdo;
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group IN ('branding', 'email', 'workflow', 'system')");
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                // Override defaults with database values
                foreach ($settings as $settingKey => $settingValue) {
                    $configKey = str_replace(['branding_', 'email_', 'workflow_', 'system_'], '', $settingKey);
                    if (array_key_exists($configKey, $config)) {
                        // Convert string values to appropriate types
                        if (is_numeric($settingValue)) {
                            $config[$configKey] = strpos($settingValue, '.') !== false ? (float)$settingValue : (int)$settingValue;
                        } elseif (in_array(strtolower($settingValue), ['true', 'false'])) {
                            $config[$configKey] = strtolower($settingValue) === 'true';
                        } else {
                            $config[$configKey] = $settingValue;
                        }
                    }
                }
            } catch (Exception $e) {
                // Use defaults if database is not available
                error_log("Failed to load system config from database: " . $e->getMessage());
            }
        }
    }

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

/**
 * Get workflow configuration
 */
function getWorkflowConfig() {
    return [
        'escalation_days' => getSystemConfig('workflow_escalation_days', 3),
        'reminder_days' => getSystemConfig('workflow_reminder_days', 2),
        'review_months' => getSystemConfig('workflow_review_months', 6),
    ];
}

/**
 * Get email configuration
 */
function getEmailDefaults() {
    return [
        'host' => getSystemConfig('smtp_host', 'smtp.gmail.com'),
        'port' => getSystemConfig('smtp_port', 587),
        'secure' => getSystemConfig('smtp_secure', 'tls'),
        'from_email' => getSystemConfig('smtp_from_email', 'noreply@dhakabypass.com'),
        'from_name' => getSystemConfig('smtp_from_name', 'DBEDC File Tracker'),
    ];
}

/**
 * Get branding configuration
 */
function getBrandingConfig() {
    return [
        'company_name' => getSystemConfig('company_name', 'DBEDC File Tracker'),
        'company_logo' => getSystemConfig('company_logo'),
        'primary_color' => getSystemConfig('primary_color', '#667eea'),
        'secondary_color' => getSystemConfig('secondary_color', '#764ba2'),
    ];
}

/**
 * Get system limits
 */
function getSystemLimits() {
    return [
        'max_upload_size' => getSystemConfig('max_upload_size', 10 * 1024 * 1024),
        'max_file_uploads' => getSystemConfig('max_file_uploads', 5),
        'session_timeout' => getSystemConfig('session_timeout', 3600),
        'cron_execution_timeout' => getSystemConfig('cron_execution_timeout', 300),
        'items_per_page' => getSystemConfig('items_per_page', 25),
    ];
}

/**
 * Update system configuration
 */
function updateSystemConfig($key, $value, $group = 'system') {
    global $dbAvailable;
    if (!$dbAvailable) {
        return false; // Cannot update if database not available
    }

    try {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
        ");

        $dataType = is_int($value) ? 'integer' : (is_float($value) ? 'float' : (is_bool($value) ? 'boolean' : 'string'));
        $isPublic = in_array($group, ['branding']) && in_array($key, ['company_name', 'company_logo', 'primary_color', 'secondary_color']);

        $stmt->execute([
            generateULID(),
            $group . '_' . $key,
            (string)$value,
            $group,
            $dataType,
            $isPublic
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Failed to update system config: " . $e->getMessage());
        return false;
    }
}
?>