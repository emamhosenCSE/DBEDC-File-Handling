<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query('SELECT id, email, provider, created_at FROM users ORDER BY created_at DESC LIMIT 10');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Current users in database:\n";
    echo str_repeat('=', 80) . "\n";

    if (empty($users)) {
        echo "No users found in database.\n";
        echo "\nTo test email login with Google OAuth users, you need to:\n";
        echo "1. First register/login with Google OAuth\n";
        echo "2. Then test email login with the same email\n";
    } else {
        foreach ($users as $user) {
            echo "ID: {$user['id']} | Email: {$user['email']} | Provider: {$user['provider']} | Created: {$user['created_at']}\n";
        }
        echo "\nTotal users: " . count($users) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>