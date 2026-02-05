<?php
/**
 * Configuration File
 * Contains sensitive configuration like OAuth credentials
 * DO NOT commit this file to version control
 */

// Load database settings if available
$oauthSettings = [];
try {
    if (file_exists(__DIR__ . '/db_config.php')) {
        require_once __DIR__ . '/db_config.php';
        require_once __DIR__ . '/db.php';

        if ($pdo !== null) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
            $oauthSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
} catch (Exception $e) {
    // Database not ready yet
    error_log("Config loading error: " . $e->getMessage());
}

// Google OAuth Configuration
$clientId = $oauthSettings['google_client_id'] ?? getenv('GOOGLE_CLIENT_ID') ?: 'your_google_client_id';
$clientSecret = $oauthSettings['google_client_secret'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: 'your_google_client_secret';
$redirectUri = $oauthSettings['google_redirect_uri'] ?? getenv('GOOGLE_REDIRECT_URI') ?: 'https://yourdomain.com/callback.php';

define('GOOGLE_CLIENT_ID', $clientId);
define('GOOGLE_CLIENT_SECRET', $clientSecret);
define('GOOGLE_REDIRECT_URI', $redirectUri);

// WeChat OAuth Configuration
$wechatAppId = $oauthSettings['wechat_app_id'] ?? getenv('WECHAT_APP_ID') ?: 'your_wechat_app_id';
$wechatAppSecret = $oauthSettings['wechat_app_secret'] ?? getenv('WECHAT_APP_SECRET') ?: 'your_wechat_app_secret';
$wechatRedirectUri = $oauthSettings['wechat_redirect_uri'] ?? getenv('WECHAT_REDIRECT_URI') ?: 'https://yourdomain.com/wechat_callback.php';

define('WECHAT_APP_ID', $wechatAppId);
define('WECHAT_APP_SECRET', $wechatAppSecret);
define('WECHAT_REDIRECT_URI', $wechatRedirectUri);

// Email Login Configuration
$emailLoginEnabled = $oauthSettings['email_login_enabled'] ?? getenv('EMAIL_LOGIN_ENABLED') ?: 'true';
$passwordMinLength = $oauthSettings['password_min_length'] ?? getenv('PASSWORD_MIN_LENGTH') ?: '8';
$passwordRequireUppercase = $oauthSettings['password_require_uppercase'] ?? getenv('PASSWORD_REQUIRE_UPPERCASE') ?: 'true';
$passwordRequireNumbers = $oauthSettings['password_require_numbers'] ?? getenv('PASSWORD_REQUIRE_NUMBERS') ?: 'true';
$passwordRequireSpecial = $oauthSettings['password_require_special'] ?? getenv('PASSWORD_REQUIRE_SPECIAL') ?: 'false';

define('EMAIL_LOGIN_ENABLED', $emailLoginEnabled === 'true');
define('PASSWORD_MIN_LENGTH', (int)$passwordMinLength);
define('PASSWORD_REQUIRE_UPPERCASE', $passwordRequireUppercase === 'true');
define('PASSWORD_REQUIRE_NUMBERS', $passwordRequireNumbers === 'true');
define('PASSWORD_REQUIRE_SPECIAL', $passwordRequireSpecial === 'true');

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
?>