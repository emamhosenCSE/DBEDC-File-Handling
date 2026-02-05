<?php
require_once 'includes/db.php';

echo "<h1>Settings Check</h1>";

if (!$pdo) {
    echo "<p>❌ Database connection failed</p>";
    exit;
}

echo "<p>✅ Database connected</p>";

// Check settings table
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "<h2>Auth Settings:</h2>";
echo "<pre>";
print_r($settings);
echo "</pre>";

echo "<h2>EMAIL_LOGIN_ENABLED:</h2>";
echo "<p>Defined: " . (defined('EMAIL_LOGIN_ENABLED') ? 'Yes' : 'No') . "</p>";
echo "<p>Value: " . (defined('EMAIL_LOGIN_ENABLED') ? (EMAIL_LOGIN_ENABLED ? 'true' : 'false') : 'undefined') . "</p>";
?>