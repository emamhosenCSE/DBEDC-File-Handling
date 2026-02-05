<?php
/**
 * WeChat OAuth Callback Handler
 * Processes the authorization code and creates/updates user session
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

// Cache Control Headers - Disable all caching for OAuth callback
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Error handling
if (isset($_GET['error'])) {
    die('WeChat OAuth Error: ' . htmlspecialchars($_GET['error']));
}

// Verify state token to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['wechat_oauth_state']) {
    die('Invalid state parameter. Possible CSRF attack.');
}

// Get authorization code
if (!isset($_GET['code'])) {
    die('No authorization code received from WeChat.');
}

$authCode = $_GET['code'];

try {
    // Get WeChat user info
    $userInfo = getWeChatUserInfo($authCode);

    // Create or update user in database
    $userId = getOrCreateUser($userInfo, 'wechat');

    // Get user details for session
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $user['email'] ?: $user['wechat_openid'] . '@wechat.local';
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_avatar'] = $user['avatar_url'];

    // Clean up OAuth state
    unset($_SESSION['wechat_oauth_state']);

    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        die('WeChat OAuth error: ' . $e->getMessage());
    } else {
        die('Authentication failed. Please try again.');
    }
}
?>