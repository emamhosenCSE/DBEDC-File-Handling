<?php
/**
 * Generate VAPID Keys for Web Push Notifications
 * Run this script and add the keys to your database
 */

header('Content-Type: text/plain');

// Generate elliptic curve key pair
$key = openssl_pkey_new([
    'private_key_bits' => 256,
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1'
]);

if (!$key) {
    die("Failed to generate key\n");
}

// Export private key
openssl_pkey_export($key, $privateKey);

// Get public key
$details = openssl_pkey_get_details($key);
$publicKey = $details['key'];

// Convert to URL-safe base64
function vapidEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Parse the public key
$publicKeyData = trim($publicKey);
$publicKeyData = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $publicKeyData);
$binaryPublicKey = base64_decode($publicKeyData);
$urlSafePublicKey = vapidEncode($binaryPublicKey);

// Parse the private key  
$privateKeyData = trim($privateKey);
$privateKeyData = str_replace(['-----BEGIN EC PRIVATE KEY-----', '-----END EC PRIVATE KEY-----', "\n", "\r"], '', $privateKeyData);
$privateKeyDecoded = base64_decode($privateKeyData);

// Extract the private key octet (last 32 bytes after header)
$privateKeyBinary = substr($privateKeyDecoded, -32);
$urlSafePrivateKey = vapidEncode($privateKeyBinary);

echo "=== VAPID KEYS GENERATED ===\n\n";
echo "PUBLIC KEY (Add to settings table as 'vapid_public_key'):\n";
echo $urlSafePublicKey . "\n\n";
echo "PRIVATE KEY (Add to settings table as 'vapid_private_key'):\n";
echo $urlSafePrivateKey . "\n\n";

echo "=== SQL TO INSERT KEYS ===\n\n";
echo "INSERT INTO settings (setting_key, setting_value, setting_group) VALUES\n";
echo "('vapid_public_key', '" . $urlSafePublicKey . "', 'push'),\n";
echo "('vapid_private_key', '" . $urlSafePrivateKey . "', 'push');\n\n";

echo "=== OR UPDATE EXISTING ===\n\n";
echo "UPDATE settings SET setting_value = '" . $urlSafePublicKey . "' WHERE setting_key = 'vapid_public_key' AND setting_group = 'push';\n";
echo "UPDATE settings SET setting_value = '" . $urlSafePrivateKey . "' WHERE setting_key = 'vapid_private_key' AND setting_group = 'push';\n";
