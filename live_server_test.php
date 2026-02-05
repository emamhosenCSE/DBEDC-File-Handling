<?php
/**
 * Live Server Email Login Test
 * Run this on your live server to test email login functionality
 */

echo "🔍 Live Server Email Login Test\n";
echo "================================\n\n";

require_once __DIR__ . '/includes/db.php';

if (!$pdo) {
    echo "❌ Database connection failed. Check your database configuration.\n";
    exit(1);
}

echo "✅ Database connection successful\n\n";

// Test function (same as the one we modified)
function testAuthenticateWithEmail($email, $password) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password_hash IS NOT NULL AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    return $user;
}

// Check current users
echo "📊 Current Users in Database:\n";
echo "-----------------------------\n";

try {
    $stmt = $pdo->query('SELECT id, email, provider, created_at FROM users ORDER BY created_at DESC LIMIT 5');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo "⚠️  No users found in database. You need to create some test users first.\n\n";
        echo "To create a test user who 'registered with Google' but can login with email:\n";
        echo "1. Register/login with Google OAuth first\n";
        echo "2. Then manually set a password for that user in the database\n\n";
    } else {
        foreach ($users as $user) {
            echo "• {$user['email']} (Provider: {$user['provider']})\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking users: " . $e->getMessage() . "\n\n";
}

// Test configurations
echo "🔧 Testing Configurations:\n";
echo "-------------------------\n";

// Check if email login is enabled
if (defined('EMAIL_LOGIN_ENABLED') && EMAIL_LOGIN_ENABLED) {
    echo "✅ Email login is enabled\n";
} else {
    echo "❌ Email login is disabled\n";
}

// Check OAuth configurations
$oauthStatus = [];
$oauthStatus[] = defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID) ? "✅ Google OAuth configured" : "❌ Google OAuth not configured";
$oauthStatus[] = defined('WECHAT_APP_ID') && !empty(WECHAT_APP_ID) ? "✅ WeChat OAuth configured" : "❌ WeChat OAuth not configured";

foreach ($oauthStatus as $status) {
    echo "$status\n";
}

echo "\n🧪 Running Authentication Tests:\n";
echo "--------------------------------\n";

// If no users exist, create a test scenario
if (empty($users)) {
    echo "⚠️  No users to test. Here's how to test manually:\n\n";
    echo "1. Visit: https://files.dhakabypass.com/login.php\n";
    echo "2. Click on 'Email Login' tab\n";
    echo "3. Try logging in with any email/password\n";
    echo "4. Should see 'Invalid email or password' message\n\n";
    echo "5. To test with a real user:\n";
    echo "   - First register/login with Google OAuth\n";
    echo "   - Then set a password for that user in the database\n";
    echo "   - Try email login with that user's email\n\n";
} else {
    // Test with existing users who have passwords
    $usersWithPasswords = array_filter($users, function($user) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = ?");
        $stmt->execute([$user['email']]);
        $result = $stmt->fetch();
        return !empty($result['password_hash']);
    });

    if (empty($usersWithPasswords)) {
        echo "⚠️  No users have passwords set. Users need passwords to use email login.\n\n";
        echo "To add a password to an existing user:\n";
        echo "UPDATE users SET password_hash = '" . password_hash('testpassword123', PASSWORD_DEFAULT) . "' WHERE email = 'user@example.com';\n\n";
    } else {
        echo "Found " . count($usersWithPasswords) . " user(s) with passwords set.\n\n";

        // Test authentication for each user with password
        foreach ($usersWithPasswords as $user) {
            echo "Testing user: {$user['email']} (Provider: {$user['provider']})\n";

            // Test correct password
            $result = testAuthenticateWithEmail($user['email'], 'password123'); // Using our test password
            if ($result) {
                echo "✅ Correct password accepted\n";
            } else {
                echo "❌ Correct password rejected (user might have different password)\n";
            }

            // Test wrong password
            $result = testAuthenticateWithEmail($user['email'], 'wrongpassword');
            if (!$result) {
                echo "✅ Wrong password correctly rejected\n";
            } else {
                echo "❌ Wrong password was accepted (security issue!)\n";
            }

            echo "\n";
        }
    }
}

echo "🌐 Web Interface Test:\n";
echo "---------------------\n";
echo "Visit these URLs to test manually:\n";
echo "• Login Page: https://files.dhakabypass.com/login.php\n";
echo "• Email Login Tab: Click the 'Email Login' tab on the login page\n";
echo "• API Test: https://files.dhakabypass.com/api/auth.php (POST with email/password)\n\n";

echo "📋 Manual Testing Steps:\n";
echo "-----------------------\n";
echo "1. Open https://files.dhakabypass.com/login.php\n";
echo "2. Click 'Email Login' tab\n";
echo "3. Enter email and password of a user who has a password set\n";
echo "4. Click 'Sign In'\n";
echo "5. Should redirect to dashboard if successful\n";
echo "6. Should show error if credentials are wrong\n\n";

echo "🎯 Expected Results:\n";
echo "------------------\n";
echo "• Users who registered with Google OAuth can login with email if they have a password\n";
echo "• Users who registered with email can login normally\n";
echo "• Invalid credentials are rejected\n";
echo "• Login redirects to dashboard on success\n\n";

echo "✅ Live server test completed!\n";
?>