<?php
// Configure session for testing
if (session_status() === PHP_SESSION_NONE) {
    $domain = $_SERVER['HTTP_HOST'] ?? 'files.dhakabypass.com';
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Set test session data
$_SESSION['user_id'] = '01TESTUSER123456789012345';
$_SESSION['user_email'] = 'test@example.com';
$_SESSION['user_name'] = 'Test User';
$_SESSION['user_avatar'] = null;

echo "Test session set. User ID: " . $_SESSION['user_id'];
echo "<br><a href='session_debug.php'>Check session debug</a>";
echo "<br><a href='dashboard.php'>Go to dashboard</a>";
?>