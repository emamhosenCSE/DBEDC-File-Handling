<?php
require_once 'includes/db_config.php';
require_once 'includes/db.php';

try {
    $stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = ?');
    $stmt->execute(['emam.hosen@gmail.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo 'User found: ' . ($user ? 'YES' : 'NO') . PHP_EOL;
    if ($user) {
        echo 'Has password: ' . (!empty($user['password_hash']) ? 'YES' : 'NO') . PHP_EOL;
        echo 'User ID: ' . $user['id'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>