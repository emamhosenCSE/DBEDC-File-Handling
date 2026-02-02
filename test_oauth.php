<?php
// Test OAuth configuration
require_once 'includes/config.php';

echo "GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : 'NOT DEFINED') . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (defined('GOOGLE_CLIENT_SECRET') ? 'DEFINED (hidden)' : 'NOT DEFINED') . "\n";
echo "GOOGLE_REDIRECT_URI: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : 'NOT DEFINED') . "\n";

// Check database settings
try {
    if (file_exists('includes/db_config.php')) {
        require_once 'includes/db_config.php';
        require_once 'includes/db.php';
        if ($pdo !== null) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            echo "\nDatabase OAuth settings:\n";
            foreach ($settings as $key => $value) {
                echo "$key: " . (strpos($key, 'secret') !== false ? 'DEFINED (hidden)' : $value) . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "\nDatabase error: " . $e->getMessage() . "\n";
}
?>