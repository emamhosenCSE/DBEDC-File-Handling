<?php
require_once 'includes/db.php';

try {
    // Create a test user that simulates someone who registered with Google OAuth
    // but now has email login capability
    $userId = '01HXXXXXXXXXXXXXXXXXXXXZ';
    $googleId = 'google_test_user_123';
    $email = 'googleuser@example.com';
    $name = 'Google OAuth User';
    $password = password_hash('password123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (id, google_id, provider, email, name, password_hash, role, is_active)
        VALUES (?, ?, 'google', ?, ?, ?, 'MEMBER', TRUE)
        ON DUPLICATE KEY UPDATE
            password_hash = VALUES(password_hash),
            provider = VALUES(provider)
    ");
    $stmt->execute([$userId, $googleId, $email, $name, $password]);

    echo "✓ Created test user with Google OAuth + email login capability\n";
    echo "Email: googleuser@example.com\n";
    echo "Password: password123\n";
    echo "Google ID: google_test_user_123\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>