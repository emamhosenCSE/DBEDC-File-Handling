<?php
require_once 'includes/db.php';

echo "<h1>Fix Email Login Setting</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;}</style>";

if (!$pdo) {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}

echo "<p class='success'>✅ Database connected</p>";

// Insert the missing email_login_enabled setting
try {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $result = $stmt->execute(['email_login_enabled', 'true', 'auth', true]);

    if ($result) {
        echo "<p class='success'>✅ Email login setting added successfully</p>";
    } else {
        echo "<p class='error'>❌ Failed to add email login setting</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// Also add the other missing auth settings
$authSettings = [
    ['wechat_app_id', '', 'auth', false],
    ['wechat_app_secret', '', 'auth', false],
    ['wechat_redirect_uri', 'https://files.dhakabypass.com/wechat_callback.php', 'auth', false],
    ['password_min_length', '8', 'auth', true],
    ['password_require_uppercase', 'true', 'auth', true],
    ['password_require_numbers', 'true', 'auth', true],
    ['password_require_special', 'false', 'auth', true],
];

foreach ($authSettings as $setting) {
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute($setting);
        echo "<p class='success'>✅ Added: {$setting[0]}</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Failed to add {$setting[0]}: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='check_settings.php'>Check settings again</a></p>";
echo "<p><a href='quick_test.php'>Test email login</a></p>";
?>