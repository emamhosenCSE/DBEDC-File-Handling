<?php
/**
 * Generate VAPID Keys for Web Push Notifications
 */

header('Content-Type: text/plain');

$privateKey = '';
$publicKey = '';

// Try sodium first (PHP 7.2+)
if (function_exists('sodium_crypto_sign_seed_keypair')) {
    echo "Using sodium with seed...\n";
    $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
    $keyPair = sodium_crypto_sign_seed_keypair($seed);
    $privateKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);
} elseif (function_exists('openssl_pkey_new')) {
    echo "Using openssl...\n";
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $key = openssl_pkey_new($config);
    if ($key) {
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        $publicKey = $details['key'];
    }
} else {
    // Fallback - generate simple random keys
    echo "Using random fallback...\n";
    $privateKey = random_bytes(32);
    $publicKey = 'MHYwEAYHKoZIzj0CAQYFK4EEACIDYgAE' . base64_encode(random_bytes(32));
}

if (empty($privateKey) || empty($publicKey)) {
    die("Failed to generate keys\n");
}

// Convert to URL-safe base64
function vapidEncodeRaw($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

echo "\n=== VAPID KEYS GENERATED ===\n\n";

// Format keys for VAPID
if (is_string($privateKey) && strpos($privateKey, '-----') !== false) {
    // PEM format
    $privateKeyData = str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r", ' '], '', $privateKey);
    $privateKeyBin = base64_decode($privateKeyData);
    $urlPrivateKey = vapidEncodeRaw($privateKeyBin);
    
    $publicKeyData = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r", ' '], '', $publicKey);
    $publicKeyBin = base64_decode($publicKeyData);
    $urlPublicKey = vapidEncodeRaw($publicKeyBin);
} else {
    // Binary format
    $urlPrivateKey = vapidEncodeRaw($privateKey);
    $urlPublicKey = vapidEncodeRaw($publicKey);
}

echo "PUBLIC KEY (vapid_public_key):\n";
echo $urlPublicKey . "\n\n";

echo "PRIVATE KEY (vapid_private_key):\n";
echo $urlPrivateKey . "\n\n";

echo "=== SQL TO RUN IN PHPMyADMIN ===\n\n";
echo "INSERT INTO settings (setting_key, setting_value, setting_group) VALUES\n";
echo "('vapid_public_key', '" . $urlPublicKey . "', 'push'),\n";
echo "('vapid_private_key', '" . $urlPrivateKey . "', 'push');\n\n";

echo "=== UPDATE EXISTING ===\n\n";
echo "UPDATE settings SET setting_value = '" . $urlPublicKey . "'\n";
echo "WHERE setting_key = 'vapid_public_key' AND setting_group = 'push';\n\n";

echo "UPDATE settings SET setting_value = '" . $urlPrivateKey . "'\n";
echo "WHERE setting_key = 'vapid_private_key' AND setting_group = 'push';\n";
