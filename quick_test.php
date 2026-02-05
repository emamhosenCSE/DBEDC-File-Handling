<?php
/**
 * Quick Email Login Test for Live Server
 * Upload this to your server and run it to verify email login works
 */

require_once 'includes/db.php';
require_once 'includes/config.php';

echo "<h1>🚀 Email Login Test Results</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Check database connection
if (!$pdo) {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}

echo "<p class='success'>✅ Database connected successfully</p>";

// Check if email login is enabled
if (!defined('EMAIL_LOGIN_ENABLED') || !EMAIL_LOGIN_ENABLED) {
    echo "<p class='error'>❌ Email login is disabled in configuration</p>";
} else {
    echo "<p class='success'>✅ Email login is enabled</p>";
}

// Check for users with passwords
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE password_hash IS NOT NULL AND is_active = TRUE");
$result = $stmt->fetch();
$userCount = $result['count'];

echo "<p><strong>Users with passwords:</strong> $userCount</p>";

if ($userCount == 0) {
    echo "<p class='warning'>⚠️ No users have passwords set. Email login won't work for any users.</p>";
    echo "<p><strong>To fix this:</strong></p>";
    echo "<ol>";
    echo "<li>Have a user register/login with Google OAuth first</li>";
    echo "<li>Set a password for that user in the database:</li>";
    echo "</ol>";
    echo "<pre>UPDATE users SET password_hash = '" . password_hash('yourpassword', PASSWORD_DEFAULT) . "' WHERE email = 'user@example.com';</pre>";
} else {
    echo "<p class='success'>✅ Found users with passwords - email login should work</p>";

    // Show a few example users
    $stmt = $pdo->query("SELECT email, provider FROM users WHERE password_hash IS NOT NULL AND is_active = TRUE LIMIT 3");
    $users = $stmt->fetchAll();

    echo "<p><strong>Example users you can test:</strong></p>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li><code>{$user['email']}</code> (registered via: {$user['provider']})</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<h2>🧪 Test Email Login</h2>";
echo "<form method='POST' style='border:1px solid #ccc;padding:20px;border-radius:5px;'>";
echo "<p><label>Email: <input type='email' name='test_email' required></label></p>";
echo "<p><label>Password: <input type='password' name='test_password' required></label></p>";
echo "<button type='submit' style='background:#007bff;color:white;border:none;padding:10px 20px;border-radius:5px;'>Test Login</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['test_email'] ?? '';
    $password = $_POST['test_password'] ?? '';

    echo "<hr><h3>Test Result:</h3>";

    if (empty($email) || empty($password)) {
        echo "<p class='error'>❌ Email and password are required</p>";
    } else {
        // First, check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo "<p class='error'>❌ User not found in database</p>";
        } elseif (empty($user['password_hash'])) {
            // User exists but has no password - set one
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$passwordHash, $user['id']]);

            echo "<p class='success'>✅ Password set successfully for user: {$user['name']} ({$user['email']})</p>";
            echo "<p><strong>Provider:</strong> {$user['provider']}</p>";
            echo "<p>You can now test email login with this user.</p>";
        } else {
            // User has password - test login
            if (password_verify($password, $user['password_hash'])) {
                echo "<p class='success'>✅ Login successful!</p>";
                echo "<p><strong>User:</strong> {$user['name']} ({$user['email']})</p>";
                echo "<p><strong>Provider:</strong> {$user['provider']}</p>";
            } else {
                echo "<p class='error'>❌ Invalid password</p>";
            }
        }
    }
}

echo "<hr>";
echo "<h2>🌐 Manual Testing</h2>";
echo "<p>Test the login page: <a href='../login.php' target='_blank'>https://files.dhakabypass.com/login.php</a></p>";
echo "<ol>";
echo "<li>Click 'Email Login' tab</li>";
echo "<li>Enter email and password from above</li>";
echo "<li>Click 'Sign In'</li>";
echo "<li>Should redirect to dashboard if successful</li>";
echo "</ol>";
?>