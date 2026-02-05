<?php
/**
 * Email Login Verification Script
 * Run this on your live server to verify email login functionality
 */

echo "🔍 Email Login Verification for files.dhakabypass.com\n";
echo "====================================================\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check required files
$requiredFiles = [
    'includes/db.php',
    'includes/auth.php',
    'includes/config.php',
    'api/auth.php',
    'login.php'
];

echo "\n📁 File Check:\n";
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file (MISSING)\n";
    }
}

// Check database connection
echo "\n🗄️ Database Check:\n";
try {
    require_once 'includes/db.php';
    if ($pdo) {
        echo "✅ Database connection successful\n";

        // Check users table
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Users table exists\n";

            // Count users
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            echo "📊 Total users: " . $result['count'] . "\n";

            // Count users with passwords
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE password_hash IS NOT NULL");
            $result = $stmt->fetch();
            echo "🔑 Users with passwords: " . $result['count'] . "\n";

            // Show recent users
            $stmt = $pdo->query("SELECT email, provider, created_at FROM users ORDER BY created_at DESC LIMIT 3");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "\n👥 Recent Users:\n";
            foreach ($users as $user) {
                echo "  • {$user['email']} (via {$user['provider']}) - {$user['created_at']}\n";
            }

        } else {
            echo "❌ Users table does not exist\n";
        }

    } else {
        echo "❌ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Check configuration
echo "\n⚙️ Configuration Check:\n";
if (defined('EMAIL_LOGIN_ENABLED')) {
    echo "Email Login: " . (EMAIL_LOGIN_ENABLED ? "✅ ENABLED" : "❌ DISABLED") . "\n";
} else {
    echo "❌ EMAIL_LOGIN_ENABLED not defined\n";
}

if (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID)) {
    echo "Google OAuth: ✅ Configured\n";
} else {
    echo "Google OAuth: ❌ Not configured\n";
}

// Test authentication function
echo "\n🧪 Authentication Test:\n";

function testAuthFunction($email, $password) {
    global $pdo;

    if (!$pdo) return "❌ No database connection";

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password_hash IS NOT NULL AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return "❌ User not found or no password set";
        }

        if (!password_verify($password, $user['password_hash'])) {
            return "❌ Invalid password";
        }

        return "✅ Login successful for {$user['name']} ({$user['provider']} user)";
    } catch (Exception $e) {
        return "❌ Error: " . $e->getMessage();
    }
}

// Test with dummy data first
echo "Test 1 - Non-existent user: " . testAuthFunction('nonexistent@example.com', 'password') . "\n";

// Test with actual users if they exist
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT email, provider FROM users WHERE password_hash IS NOT NULL LIMIT 1");
        $user = $stmt->fetch();

        if ($user) {
            echo "Test 2 - Real user with wrong password: " . testAuthFunction($user['email'], 'wrongpassword') . "\n";
            echo "Test 3 - Real user with correct password: " . testAuthFunction($user['email'], 'password123') . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Could not test with real users: " . $e->getMessage() . "\n";
}

echo "\n🌐 Web Interface URLs:\n";
echo "Login Page: https://files.dhakabypass.com/login.php\n";
echo "Quick Test: https://files.dhakabypass.com/quick_test.php\n";
echo "Live Test: https://files.dhakabypass.com/live_server_test.php\n";

echo "\n📋 Next Steps:\n";
echo "1. Visit the URLs above to test the web interface\n";
echo "2. If no users have passwords, set one using the quick_test.php interface\n";
echo "3. Test email login through the login page\n";
echo "4. Verify that Google OAuth users can login with email\n";

echo "\n✅ Verification complete!\n";
?>