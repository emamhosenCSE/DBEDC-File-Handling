<?php
// Test script to check if system is installed
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'system_installed'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "system_installed setting: " . ($result ? $result['setting_value'] : 'NOT FOUND') . "\n";

    // Also check OAuth settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
    $oauth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "OAuth settings found: " . count($oauth) . "\n";
    foreach ($oauth as $setting) {
        echo "- " . $setting['setting_key'] . ": " . (strlen($setting['setting_value']) > 10 ? substr($setting['setting_value'], 0, 10) . "..." : $setting['setting_value']) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>