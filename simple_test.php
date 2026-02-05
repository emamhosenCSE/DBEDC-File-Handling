<?php
// Simple email login test without session issues
require_once 'includes/db.php';

echo "Testing Email Authentication\n";
echo "===========================\n\n";

function testAuthenticateWithEmail($email, $password) {
    global $pdo;

    // Check if user exists with a password set (allows login for OAuth users who have set passwords)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password_hash IS NOT NULL AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    return $user;
}

// Test the Google OAuth user with email login
echo "Test 1: Google OAuth user with email login\n";
$user = testAuthenticateWithEmail('googleuser@example.com', 'password123');
if ($user) {
    echo "✅ SUCCESS: Login worked for Google OAuth user\n";
    echo "   User: {$user['name']} ({$user['email']})\n";
    echo "   Provider: {$user['provider']}\n";
} else {
    echo "❌ FAILED: Login failed for Google OAuth user\n";
}

echo "\n";

// Test the email provider user
echo "Test 2: Email provider user\n";
$user = testAuthenticateWithEmail('test@example.com', 'password123');
if ($user) {
    echo "✅ SUCCESS: Login worked for email provider user\n";
    echo "   User: {$user['name']} ({$user['email']})\n";
    echo "   Provider: {$user['provider']}\n";
} else {
    echo "❌ FAILED: Login failed for email provider user\n";
}

echo "\n";

// Test invalid credentials
echo "Test 3: Invalid password\n";
$user = testAuthenticateWithEmail('googleuser@example.com', 'wrongpassword');
if (!$user) {
    echo "✅ SUCCESS: Invalid password correctly rejected\n";
} else {
    echo "❌ FAILED: Invalid password was accepted\n";
}

echo "\n";

// Test non-existent user
echo "Test 4: Non-existent user\n";
$user = testAuthenticateWithEmail('nonexistent@example.com', 'password123');
if (!$user) {
    echo "✅ SUCCESS: Non-existent user correctly rejected\n";
} else {
    echo "❌ FAILED: Non-existent user was accepted\n";
}

echo "\n🎉 Email login testing completed!\n";
?>