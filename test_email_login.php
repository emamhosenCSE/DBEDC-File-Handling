<?php
/**
 * Test Email Login Functionality
 * Tests email login for users who registered via different providers
 */

require_once 'includes/auth.php';
ensureSystemInstalled();

echo "Starting email login tests...\n\n";

try {
    require_once 'includes/db.php';
    echo "Database connection: OK\n\n";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test the Google OAuth user with email login
echo "Testing Google OAuth user with email login:\n";
$user = authenticateWithEmail('googleuser@example.com', 'password123');
if ($user) {
    echo "✅ SUCCESS: Login worked for Google OAuth user\n";
    echo "   User: {$user['name']} ({$user['email']})\n";
    echo "   Provider: {$user['provider']}\n";
} else {
    echo "❌ FAILED: Login failed for Google OAuth user\n";
}

echo "\n";

// Test the email provider user
echo "Testing email provider user:\n";
$user = authenticateWithEmail('test@example.com', 'password123');
if ($user) {
    echo "✅ SUCCESS: Login worked for email provider user\n";
    echo "   User: {$user['name']} ({$user['email']})\n";
    echo "   Provider: {$user['provider']}\n";
} else {
    echo "❌ FAILED: Login failed for email provider user\n";
}

echo "\n";

// Test invalid credentials
echo "Testing invalid credentials:\n";
$user = authenticateWithEmail('googleuser@example.com', 'wrongpassword');
if (!$user) {
    echo "✅ SUCCESS: Invalid password correctly rejected\n";
} else {
    echo "❌ FAILED: Invalid password was accepted\n";
}

echo "\n🎉 Basic email login testing completed!\n";
?>