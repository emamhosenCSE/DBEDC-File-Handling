<?php
/**
 * WeChat OAuth Configuration Helper
 * Helps set up WeChat OAuth credentials in the database
 */

require_once __DIR__ . '/includes/auth.php';
ensureSystemInstalled();

// Check if we have database access
try {
    require_once __DIR__ . '/includes/db.php';
    $dbAvailable = true;
} catch (Exception $e) {
    $dbAvailable = false;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = trim($_POST['wechat_app_id'] ?? '');
    $appSecret = trim($_POST['wechat_app_secret'] ?? '');
    $redirectUri = trim($_POST['wechat_redirect_uri'] ?? '');

    if (empty($appId) || empty($appSecret)) {
        $error = 'App ID and App Secret are required';
    } else {
        try {
            if ($dbAvailable && isset($pdo)) {
                // Save to database
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value, setting_group, is_public)
                    VALUES (?, ?, 'auth', FALSE),
                           (?, ?, 'auth', FALSE),
                           (?, ?, 'auth', FALSE)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([
                    'wechat_app_id', $appId,
                    'wechat_app_secret', $appSecret,
                    'wechat_redirect_uri', $redirectUri ?: 'https://' . $_SERVER['HTTP_HOST'] . '/wechat_callback.php'
                ]);
                $message = 'WeChat OAuth credentials saved successfully!';
            } else {
                // Show configuration code
                $configCode = "<?php\n";
                $configCode .= "// Add to includes/config.php or set as environment variables:\n";
                $configCode .= "define('WECHAT_APP_ID', '" . addslashes($appId) . "');\n";
                $configCode .= "define('WECHAT_APP_SECRET', '" . addslashes($appSecret) . "');\n";
                $configCode .= "define('WECHAT_REDIRECT_URI', '" . addslashes($redirectUri ?: 'https://' . $_SERVER['HTTP_HOST'] . '/wechat_callback.php') . "');\n";
                $message = '<pre>' . htmlspecialchars($configCode) . '</pre>';
            }
        } catch (Exception $e) {
            $error = 'Failed to save configuration: ' . $e->getMessage();
        }
    }
}

// Get current values
$currentAppId = '';
$currentAppSecret = '';
$currentRedirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/wechat_callback.php';

if ($dbAvailable && isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth' AND setting_key LIKE 'wechat_%'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $currentAppId = $settings['wechat_app_id'] ?? '';
        $currentAppSecret = $settings['wechat_app_secret'] ?? '';
        $currentRedirectUri = $settings['wechat_redirect_uri'] ?? $currentRedirectUri;
    } catch (Exception $e) {
        // Settings table might not exist yet
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeChat OAuth Setup - File Tracker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover {
            background: #0056b3;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            margin-bottom: 20px;
        }
        .setup-steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .setup-steps ol {
            margin: 0;
            padding-left: 20px;
        }
        .setup-steps li {
            margin-bottom: 10px;
        }
        code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WeChat OAuth Setup</h1>

        <div class="info">
            <strong>📋 Quick Setup Steps:</strong>
            <div class="setup-steps">
                <ol>
                    <li>Go to <a href="https://open.weixin.qq.com/" target="_blank">WeChat Open Platform</a></li>
                    <li>Create a developer account and app</li>
                    <li>Get your AppID and AppSecret from the app settings</li>
                    <li>Fill in the form below with your credentials</li>
                    <li>Test the WeChat login on your login page</li>
                </ol>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="wechat_app_id">WeChat App ID:</label>
                <input type="text" id="wechat_app_id" name="wechat_app_id"
                       value="<?php echo htmlspecialchars($currentAppId); ?>"
                       placeholder="wx1234567890abcdef" required>
                <small>Get this from your WeChat app's "Basic Configuration" page</small>
            </div>

            <div class="form-group">
                <label for="wechat_app_secret">WeChat App Secret:</label>
                <input type="password" id="wechat_app_secret" name="wechat_app_secret"
                       value="<?php echo htmlspecialchars($currentAppSecret); ?>"
                       placeholder="your_app_secret_here" required>
                <small>This is sensitive - never share it publicly</small>
            </div>

            <div class="form-group">
                <label for="wechat_redirect_uri">Redirect URI:</label>
                <input type="text" id="wechat_redirect_uri" name="wechat_redirect_uri"
                       value="<?php echo htmlspecialchars($currentRedirectUri); ?>"
                       placeholder="https://yourdomain.com/wechat_callback.php">
                <small>Make sure this matches your WeChat app configuration</small>
            </div>

            <button type="submit" class="btn">
                <?php echo $dbAvailable ? 'Save WeChat Configuration' : 'Generate Configuration Code'; ?>
            </button>
        </form>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>🔧 Manual Configuration</h3>
            <p>If you prefer to configure manually, add these lines to your <code>includes/config.php</code> file:</p>
            <pre><code>define('WECHAT_APP_ID', 'your_wechat_app_id_here');
define('WECHAT_APP_SECRET', 'your_wechat_app_secret_here');
define('WECHAT_REDIRECT_URI', 'https://yourdomain.com/wechat_callback.php');</code></pre>

            <p>Or set as environment variables:</p>
            <pre><code>WECHAT_APP_ID=your_wechat_app_id_here
WECHAT_APP_SECRET=your_wechat_app_secret_here
WECHAT_REDIRECT_URI=https://yourdomain.com/wechat_callback.php</code></pre>
        </div>

        <div style="margin-top: 20px; text-align: center; color: #666;">
            <p>Need help? Check the <a href="WECHAT_SETUP_GUIDE.md" target="_blank">complete setup guide</a></p>
        </div>
    </div>
</body>
</html>