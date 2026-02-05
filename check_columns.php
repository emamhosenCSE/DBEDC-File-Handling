<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query('DESCRIBE users');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Users table columns:\n";
    echo str_repeat('=', 50) . "\n";
    foreach ($columns as $col) {
        echo "{$col['Field']} - {$col['Type']}\n";
    }

    echo "\n\nChecking for auth_provider column...\n";
    $hasAuthProvider = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'auth_provider') {
            $hasAuthProvider = true;
            break;
        }
    }

    if (!$hasAuthProvider) {
        echo "auth_provider column not found. Running auth enhancement migration manually...\n";

        // Run the auth enhancement SQL manually
        $authSql = "
            ALTER TABLE users
            ADD COLUMN provider ENUM('google', 'wechat', 'email') DEFAULT 'google' AFTER google_id,
            ADD COLUMN wechat_id VARCHAR(100) UNIQUE DEFAULT NULL AFTER provider,
            ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER wechat_id,
            ADD COLUMN wechat_unionid VARCHAR(100) DEFAULT NULL AFTER wechat_id,
            ADD COLUMN wechat_openid VARCHAR(100) DEFAULT NULL AFTER wechat_unionid;

            UPDATE users SET provider = 'google' WHERE google_id IS NOT NULL;

            ALTER TABLE users MODIFY COLUMN google_id VARCHAR(100) DEFAULT NULL;

            ALTER TABLE users ADD INDEX idx_provider (provider);
            ALTER TABLE users ADD INDEX idx_wechat_id (wechat_id);
            ALTER TABLE users ADD INDEX idx_wechat_unionid (wechat_unionid);
        ";

        $pdo->exec($authSql);
        echo "✓ Auth enhancement migration completed\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>