<?php
/**
 * Add VAPID Keys to Database
 * Run this file to add the push notification keys
 */

require_once __DIR__ . '/includes/db.php';

// VAPID Keys
$vapidPublicKey = 'U7IWDu2IO7ptDCAJ-qGAMdJUk2RI8Pgc4jxoLvcDQig';
$vapidPrivateKey = '2oKHil7_AEbajSBpqoCgdi9LjCDHFixUbywmxk83-8pTshYO7Yg7um0MIAn6oYAx0lSTZEjw-BziPGgu9wNCKA';

try {
    // Insert or update public key
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_group) 
        VALUES ('vapid_public_key', ?, 'push')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$vapidPublicKey]);
    
    // Insert or update private key
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_group) 
        VALUES ('vapid_private_key', ?, 'push')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$vapidPrivateKey]);
    
    // Verify
    $stmt = $pdo->query("SELECT * FROM settings WHERE setting_group = 'push'");
    $keys = $stmt->fetchAll();
    
    echo "=== VAPID Keys Added Successfully ===\n\n";
    foreach ($keys as $key) {
        echo $key['setting_key'] . ': ' . substr($key['setting_value'], 0, 20) . "...\n";
    }
    echo "\nPush notifications are now enabled!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
