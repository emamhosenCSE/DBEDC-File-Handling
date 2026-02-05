<?php
/**
 * WeChat OAuth Test Script
 * Tests the WeChat OAuth configuration and flow
 */

require_once __DIR__ . '/includes/auth.php';
ensureSystemInstalled();

$message = '';
$errors = [];

try {
    // Check if WeChat credentials are configured
    $appId = getWeChatAppId();
    $appSecret = getWeChatAppSecret();
    $redirectUri = getWeChatRedirectUri();

    if (empty($appId)) {
        $errors[] = 'WeChat App ID is not configured';
    }
    if (empty($appSecret)) {
        $errors[] = 'WeChat App Secret is not configured';
    }
    if (empty($redirectUri)) {
        $errors[] = 'WeChat Redirect URI is not configured';
    }

    // Test OAuth URL generation
    if (!empty($appId) && !empty($redirectUri)) {
        $authUrl = getWeChatAuthUrl();
        if (strpos($authUrl, 'appid=' . $appId) === false) {
            $errors[] = 'OAuth URL does not contain correct App ID';
        }
        if (strpos($authUrl, 'redirect_uri=' . urlencode($redirectUri)) === false) {
            $errors[] = 'OAuth URL does not contain correct redirect URI';
        }
    }

    // Check if callback file exists
    if (!file_exists(__DIR__ . '/wechat_callback.php')) {
        $errors[] = 'WeChat callback file (wechat_callback.php) is missing';
    }

    // Check database connection
    require_once __DIR__ . '/includes/db.php';
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE auth_provider = 'wechat'");
    $wechatUsers = $stmt->fetchColumn();

    if (count($errors) === 0) {
        $message = '✅ WeChat OAuth configuration looks good!';
    }

} catch (Exception $e) {
    $errors[] = 'Configuration error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeChat OAuth Test - File Tracker</title>
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
        .status {
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
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .test-section h3 {
            margin-top: 0;
            color: #495057;
        }
        .config-item {
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .config-label {
            font-weight: bold;
            color: #495057;
        }
        .config-value {
            font-family: monospace;
            word-break: break-all;
        }
        .masked {
            color: #6c757d;
            font-style: italic;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .error-list {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error-list ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WeChat OAuth Test</h1>

        <?php if (count($errors) > 0): ?>
            <div class="error-list">
                <strong>❌ Issues Found:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="status success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="test-section">
            <h3>🔧 Configuration Check</h3>
            <div class="config-item">
                <span class="config-label">App ID:</span>
                <span class="config-value">
                    <?php echo !empty($appId) ? htmlspecialchars(substr($appId, 0, 10) . '...') : '<span class="masked">Not configured</span>'; ?>
                </span>
            </div>
            <div class="config-item">
                <span class="config-label">App Secret:</span>
                <span class="config-value">
                    <?php echo !empty($appSecret) ? '<span class="masked">Configured (hidden for security)</span>' : '<span class="masked">Not configured</span>'; ?>
                </span>
            </div>
            <div class="config-item">
                <span class="config-label">Redirect URI:</span>
                <span class="config-value">
                    <?php echo htmlspecialchars($redirectUri ?? 'Not configured'); ?>
                </span>
            </div>
        </div>

        <?php if (!empty($appId) && !empty($redirectUri)): ?>
        <div class="test-section">
            <h3>🔗 OAuth URL Test</h3>
            <p>Generated OAuth URL:</p>
            <div class="config-item">
                <span class="config-value" style="word-break: break-all;">
                    <?php echo htmlspecialchars($authUrl ?? ''); ?>
                </span>
            </div>
            <p>
                <a href="<?php echo htmlspecialchars($authUrl ?? ''); ?>" class="btn" target="_blank">
                    🧪 Test WeChat Login
                </a>
                <small style="display: block; margin-top: 10px; color: #6c757d;">
                    This will redirect you to WeChat for authentication (opens in new tab)
                </small>
            </p>
        </div>
        <?php endif; ?>

        <div class="test-section">
            <h3>📊 Database Status</h3>
            <div class="config-item">
                <span class="config-label">WeChat Users:</span>
                <span class="config-value"><?php echo $wechatUsers ?? 0; ?> registered users</span>
            </div>
        </div>

        <div class="test-section">
            <h3>🛠️ Quick Actions</h3>
            <a href="setup_wechat.php" class="btn">Configure WeChat OAuth</a>
            <a href="login.php" class="btn btn-secondary">Test Login Page</a>
            <a href="WECHAT_SETUP_GUIDE.md" class="btn btn-secondary" target="_blank">View Setup Guide</a>
        </div>

        <div style="margin-top: 30px; text-align: center; color: #666;">
            <p>Having issues? Check the <a href="WECHAT_SETUP_GUIDE.md" target="_blank">complete setup guide</a> or contact support.</p>
        </div>
    </div>
</body>
</html>