<?php
/**
 * Temporary script to create config.php file
 * Run this once and then delete it
 */

// Create config.php with the content
$configContent = "<?php
/**
 * Configuration File
 * Contains sensitive configuration like OAuth credentials
 * DO NOT commit this file to version control
 */

// Load database settings if available
\$oauthSettings = [];
\$pdo = null; // Initialize
try {
    if (file_exists(__DIR__ . '/db_config.php')) {
        require_once __DIR__ . '/db_config.php';
        require_once __DIR__ . '/db.php';
        
        if (\$pdo !== null) {
            \$stmt = \$pdo->query(\"SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'\");
            \$oauthSettings = \$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
} catch (Exception \$e) {
    // Database not ready yet
}

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', \$oauthSettings['google_client_id'] ?? getenv('GOOGLE_CLIENT_ID') ?: 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', \$oauthSettings['google_client_secret'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: 'your_google_client_secret');
define('GOOGLE_REDIRECT_URI', \$oauthSettings['google_redirect_uri'] ?? getenv('GOOGLE_REDIRECT_URI') ?: 'https://yourdomain.com/callback.php');

// Database Configuration (if needed)
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'file_tracker');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

// Other settings
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') ?: true);
?>";

file_put_contents(__DIR__ . '/includes/config.php', $configContent);
echo "config.php created successfully!";
?>