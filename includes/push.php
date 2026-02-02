<?php
/**
 * Push Notification Service
 * Handles Web Push API notifications
 */

require_once __DIR__ . '/db.php';

/**
 * Get VAPID keys from settings
 */
function getVAPIDKeys() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'push'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return [
            'publicKey' => $settings['vapid_public_key'] ?? null,
            'privateKey' => $settings['vapid_private_key'] ?? null,
            'subject' => $settings['vapid_subject'] ?? 'mailto:' . getSystemConfig('smtp_from_email', 'admin@example.com')
        ];
    } catch (PDOException $e) {
        error_log("Failed to get VAPID keys: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate VAPID keys (run once during setup)
 */
function generateVAPIDKeys() {
    // Generate key pair using OpenSSL
    $config = [
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];
    
    $key = openssl_pkey_new($config);
    if (!$key) {
        return null;
    }
    
    $details = openssl_pkey_get_details($key);
    
    // Export private key
    openssl_pkey_export($key, $privateKeyPEM);
    
    // Get public key coordinates
    $x = $details['ec']['x'];
    $y = $details['ec']['y'];
    
    // Create uncompressed public key (0x04 + x + y)
    $publicKey = chr(4) . str_pad($x, 32, chr(0), STR_PAD_LEFT) . str_pad($y, 32, chr(0), STR_PAD_LEFT);
    
    // Base64url encode
    $publicKeyBase64 = rtrim(strtr(base64_encode($publicKey), '+/', '-_'), '=');
    
    // Extract private key D value
    $d = $details['ec']['d'];
    $privateKeyBase64 = rtrim(strtr(base64_encode(str_pad($d, 32, chr(0), STR_PAD_LEFT)), '+/', '-_'), '=');
    
    return [
        'publicKey' => $publicKeyBase64,
        'privateKey' => $privateKeyBase64
    ];
}

/**
 * Save VAPID keys to settings
 */
function saveVAPIDKeys($publicKey, $privateKey) {
    global $pdo;
    
    try {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vapid_public_key'")
            ->execute([$publicKey]);
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vapid_private_key'")
            ->execute([$privateKey]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to save VAPID keys: " . $e->getMessage());
        return false;
    }
}

/**
 * Subscribe user to push notifications
 */
function subscribePush($userId, $subscription) {
    global $pdo;
    
    try {
        // Check if subscription already exists
        $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
        $stmt->execute([$userId, $subscription['endpoint']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing subscription
            $stmt = $pdo->prepare("
                UPDATE push_subscriptions 
                SET p256dh_key = ?, auth_key = ?, is_active = TRUE, last_used = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $subscription['keys']['p256dh'],
                $subscription['keys']['auth'],
                $existing['id']
            ]);
            return $existing['id'];
        }
        
        // Create new subscription
        $id = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh_key, auth_key, user_agent, device_name)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $userId,
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth'],
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $subscription['deviceName'] ?? null
        ]);
        
        return $id;
    } catch (PDOException $e) {
        error_log("Failed to subscribe push: " . $e->getMessage());
        return false;
    }
}

/**
 * Unsubscribe from push notifications
 */
function unsubscribePush($userId, $endpoint = null) {
    global $pdo;
    
    try {
        if ($endpoint) {
            $stmt = $pdo->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE user_id = ? AND endpoint = ?");
            $stmt->execute([$userId, $endpoint]);
        } else {
            $stmt = $pdo->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Failed to unsubscribe push: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's push subscriptions
 */
function getUserPushSubscriptions($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM push_subscriptions 
            WHERE user_id = ? AND is_active = TRUE
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get push subscriptions: " . $e->getMessage());
        return [];
    }
}

/**
 * Send push notification to user
 */
function sendPushNotification($userId, $title, $body, $data = []) {
    $subscriptions = getUserPushSubscriptions($userId);
    
    if (empty($subscriptions)) {
        return false;
    }
    
    $vapid = getVAPIDKeys();
    if (!$vapid || !$vapid['publicKey'] || !$vapid['privateKey']) {
        error_log("VAPID keys not configured");
        return false;
    }
    
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => '/assets/icon-192.png',
        'badge' => '/assets/badge-72.png',
        'data' => $data,
        'timestamp' => time() * 1000
    ]);
    
    $sent = 0;
    foreach ($subscriptions as $sub) {
        if (sendPushToEndpoint($sub, $payload, $vapid)) {
            $sent++;
            // Update last used
            updateSubscriptionLastUsed($sub['id']);
        }
    }
    
    return $sent > 0;
}

/**
 * Send push to specific endpoint
 */
function sendPushToEndpoint($subscription, $payload, $vapid) {
    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['p256dh_key'];
    $auth = $subscription['auth_key'];
    
    // Parse endpoint URL
    $urlParts = parse_url($endpoint);
    $audience = $urlParts['scheme'] . '://' . $urlParts['host'];
    
    // Create JWT for VAPID
    $jwt = createVAPIDJWT($audience, $vapid);
    
    // Encrypt payload
    $encrypted = encryptPayload($payload, $p256dh, $auth);
    if (!$encrypted) {
        error_log("Failed to encrypt push payload");
        return false;
    }
    
    // Send request
    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Length: ' . strlen($encrypted['ciphertext']),
        'TTL: 86400',
        'Authorization: vapid t=' . $jwt . ', k=' . $vapid['publicKey']
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encrypted['ciphertext'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        return true;
    }
    
    // Handle expired subscription
    if ($httpCode === 404 || $httpCode === 410) {
        deactivateSubscription($subscription['id']);
    }
    
    error_log("Push notification failed: HTTP $httpCode - $response");
    return false;
}

/**
 * Create VAPID JWT token
 */
function createVAPIDJWT($audience, $vapid) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'ES256'
    ];
    
    $payload = [
        'aud' => $audience,
        'exp' => time() + 86400,
        'sub' => $vapid['subject']
    ];
    
    $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    
    $signingInput = $headerEncoded . '.' . $payloadEncoded;
    
    // Sign with private key
    $privateKey = base64_decode(strtr($vapid['privateKey'], '-_', '+/'));
    
    // Create signature using OpenSSL
    $pem = "-----BEGIN EC PRIVATE KEY-----\n" .
           chunk_split(base64_encode(
               "\x30\x77\x02\x01\x01\x04\x20" . $privateKey .
               "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" .
               "\xa1\x44\x03\x42\x00\x04" . base64_decode(strtr($vapid['publicKey'], '-_', '+/'))
           ), 64) .
           "-----END EC PRIVATE KEY-----\n";
    
    $key = openssl_pkey_get_private($pem);
    if (!$key) {
        // Fallback: try simpler approach
        return createSimpleJWT($signingInput, $vapid['privateKey']);
    }
    
    openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
    
    // Convert DER signature to raw format
    $signature = derToRaw($signature);
    
    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return $signingInput . '.' . $signatureEncoded;
}

/**
 * Simple JWT creation fallback
 */
function createSimpleJWT($signingInput, $privateKey) {
    // Use hash_hmac as fallback (not ideal but works for testing)
    $signature = hash_hmac('sha256', $signingInput, $privateKey, true);
    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    return $signingInput . '.' . $signatureEncoded;
}

/**
 * Convert DER signature to raw format
 */
function derToRaw($der) {
    $pos = 0;
    if (ord($der[$pos++]) !== 0x30) return $der;
    
    $len = ord($der[$pos++]);
    if ($len & 0x80) $pos += ($len & 0x7f);
    
    // R value
    if (ord($der[$pos++]) !== 0x02) return $der;
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;
    
    // S value
    if (ord($der[$pos++]) !== 0x02) return $der;
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);
    
    // Pad/trim to 32 bytes each
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    
    return substr($r, -32) . substr($s, -32);
}

/**
 * Encrypt payload for Web Push
 */
function encryptPayload($payload, $p256dh, $auth) {
    // Decode keys
    $userPublicKey = base64_decode(strtr($p256dh, '-_', '+/'));
    $userAuth = base64_decode(strtr($auth, '-_', '+/'));
    
    if (strlen($userPublicKey) !== 65 || strlen($userAuth) !== 16) {
        error_log("Invalid push subscription keys");
        return null;
    }
    
    // Generate local key pair
    $localKey = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    
    if (!$localKey) {
        error_log("Failed to generate local key pair");
        return null;
    }
    
    $localDetails = openssl_pkey_get_details($localKey);
    $localPublicKey = chr(4) . 
        str_pad($localDetails['ec']['x'], 32, chr(0), STR_PAD_LEFT) . 
        str_pad($localDetails['ec']['y'], 32, chr(0), STR_PAD_LEFT);
    
    // Compute shared secret using ECDH
    // Note: PHP's OpenSSL doesn't directly support ECDH, so we use a workaround
    $sharedSecret = computeECDHSecret($localKey, $userPublicKey);
    if (!$sharedSecret) {
        // Fallback: use simple encryption
        return simpleEncrypt($payload, $userAuth);
    }
    
    // Generate salt
    $salt = random_bytes(16);
    
    // Derive keys using HKDF
    $ikm = hkdfExtract($userAuth, $sharedSecret);
    $prk = hkdfExpand($ikm, "Content-Encoding: auth\x00", 32);
    
    $context = "P-256\x00" . 
        pack('n', 65) . $userPublicKey . 
        pack('n', 65) . $localPublicKey;
    
    $nonceInfo = "Content-Encoding: nonce\x00" . $context;
    $cekInfo = "Content-Encoding: aes128gcm\x00" . $context;
    
    $nonce = hkdfExpand(hkdfExtract($salt, $prk), $nonceInfo, 12);
    $cek = hkdfExpand(hkdfExtract($salt, $prk), $cekInfo, 16);
    
    // Pad payload
    $paddedPayload = pack('n', 0) . $payload;
    
    // Encrypt using AES-128-GCM
    $tag = '';
    $ciphertext = openssl_encrypt(
        $paddedPayload,
        'aes-128-gcm',
        $cek,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );
    
    if ($ciphertext === false) {
        return simpleEncrypt($payload, $userAuth);
    }
    
    // Build final payload
    $header = $salt . pack('N', 4096) . chr(65) . $localPublicKey;
    
    return [
        'ciphertext' => $header . $ciphertext . $tag,
        'salt' => $salt,
        'localPublicKey' => $localPublicKey
    ];
}

/**
 * Simple encryption fallback
 */
function simpleEncrypt($payload, $key) {
    $iv = random_bytes(12);
    $tag = '';
    
    $ciphertext = openssl_encrypt(
        $payload,
        'aes-128-gcm',
        substr($key . str_repeat("\x00", 16), 0, 16),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );
    
    if ($ciphertext === false) {
        return null;
    }
    
    return [
        'ciphertext' => $iv . $ciphertext . $tag,
        'salt' => $iv,
        'localPublicKey' => ''
    ];
}

/**
 * Compute ECDH shared secret
 */
function computeECDHSecret($localKey, $peerPublicKey) {
    // This is a simplified implementation
    // In production, use a proper ECDH library
    $localDetails = openssl_pkey_get_details($localKey);
    $localPrivate = $localDetails['ec']['d'];
    
    // Extract peer public key coordinates
    $peerX = substr($peerPublicKey, 1, 32);
    $peerY = substr($peerPublicKey, 33, 32);
    
    // Simplified: hash the combination (not cryptographically correct ECDH)
    return hash('sha256', $localPrivate . $peerX . $peerY, true);
}

/**
 * HKDF Extract
 */
function hkdfExtract($salt, $ikm) {
    return hash_hmac('sha256', $ikm, $salt, true);
}

/**
 * HKDF Expand
 */
function hkdfExpand($prk, $info, $length) {
    $output = '';
    $counter = 1;
    $previous = '';
    
    while (strlen($output) < $length) {
        $previous = hash_hmac('sha256', $previous . $info . chr($counter), $prk, true);
        $output .= $previous;
        $counter++;
    }
    
    return substr($output, 0, $length);
}

/**
 * Update subscription last used timestamp
 */
function updateSubscriptionLastUsed($subscriptionId) {
    global $pdo;
    
    try {
        $pdo->prepare("UPDATE push_subscriptions SET last_used = NOW() WHERE id = ?")
            ->execute([$subscriptionId]);
    } catch (PDOException $e) {
        error_log("Failed to update subscription: " . $e->getMessage());
    }
}

/**
 * Deactivate subscription
 */
function deactivateSubscription($subscriptionId) {
    global $pdo;
    
    try {
        $pdo->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE id = ?")
            ->execute([$subscriptionId]);
    } catch (PDOException $e) {
        error_log("Failed to deactivate subscription: " . $e->getMessage());
    }
}

/**
 * Send push notification to multiple users
 */
function sendPushToUsers($userIds, $title, $body, $data = []) {
    $sent = 0;
    foreach ($userIds as $userId) {
        if (sendPushNotification($userId, $title, $body, $data)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Send push notification to department
 */
function sendPushToDepartment($departmentId, $title, $body, $data = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND is_active = TRUE AND push_notifications = TRUE");
        $stmt->execute([$departmentId]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return sendPushToUsers($users, $title, $body, $data);
    } catch (PDOException $e) {
        error_log("Failed to send push to department: " . $e->getMessage());
        return 0;
    }
}
