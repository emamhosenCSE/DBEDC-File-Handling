<?php
/**
 * Authentication Helper
 * Handles Google OAuth and session management
 */

require_once __DIR__ . '/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if system is installed, redirect to installer if not
 */
function ensureSystemInstalled() {
    if (!isSystemInstalled()) {
        header('Location: install.php');
        exit;
    }
}

/**
 * Check if the system has been installed
 */
function isSystemInstalled() {
    // Check for installation flag file (more reliable than database query)
    if (file_exists(__DIR__ . '/../.installed')) {
        return true;
    }
    
    // Fallback to database check
    try {
        // Check if database config exists
        if (!file_exists(__DIR__ . '/db_config.php')) {
            return false;
        }

        require_once __DIR__ . '/db_config.php';
        
        // Create database connection directly (don't use global $pdo)
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'system_installed'");
        $installed = $stmt->fetchColumn();

        // If database shows installed but flag file doesn't exist, create it
        if ($installed === '1' && !file_exists(__DIR__ . '/../.installed')) {
            file_put_contents(__DIR__ . '/../.installed', 'installed');
        }
        
        return $installed === '1';
    } catch (Exception $e) {
        // Database not set up or connection failed
        return false;
    }
}

/**
 * Set security headers for API responses
 */
function setSecurityHeaders() {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'");
    header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization");
    header("Access-Control-Allow-Credentials: true");
}

/**
 * Ensure user is authenticated, redirect to login if not
 */
function ensureAuthenticated() {
    if (!isAuthenticated()) {
        if (isAjaxRequest()) {
            jsonError('Unauthorized', 401);
        } else {
            header('Location: /login.php');
            exit;
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get or create user from OAuth data (Google or WeChat)
 */
function getOrCreateUser($oauthData, $provider = 'google') {
    global $pdo;

    $idField = $provider . '_id';
    $idValue = $oauthData['id'];

    // Check if user exists by provider ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE {$idField} = ? AND provider = ?");
    $stmt->execute([$idValue, $provider]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $updateFields = [
            'name' => $oauthData['name'] ?? $oauthData['nickname'] ?? '',
            'email' => $oauthData['email'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Add avatar for Google, headimgurl for WeChat
        if ($provider === 'google' && isset($oauthData['picture'])) {
            $updateFields['avatar_url'] = $oauthData['picture'];
        } elseif ($provider === 'wechat' && isset($oauthData['headimgurl'])) {
            $updateFields['avatar_url'] = $oauthData['headimgurl'];
        }

        // WeChat specific fields
        if ($provider === 'wechat') {
            if (isset($oauthData['unionid'])) {
                $updateFields['wechat_unionid'] = $oauthData['unionid'];
            }
            if (isset($oauthData['openid'])) {
                $updateFields['wechat_openid'] = $oauthData['openid'];
            }
        }

        $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updateFields)));
        $values = array_values($updateFields);

        $stmt = $pdo->prepare("UPDATE users SET {$setClause} WHERE {$idField} = ? AND provider = ?");
        $values[] = $idValue;
        $values[] = $provider;
        $stmt->execute($values);

        return $user['id'];
    } else {
        // Check if this should be an admin user
        $isAdmin = false;
        $role = 'MEMBER';

        try {
            // Check if this email is designated as admin
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_email'");
            $stmt->execute();
            $adminEmail = $stmt->fetchColumn();

            if ($adminEmail && $oauthData['email'] === $adminEmail) {
                $isAdmin = true;
                $role = 'ADMIN';
            }

            // If no users exist yet, make this person admin
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $stmt->fetchColumn();
            if ($userCount == 0) {
                $isAdmin = true;
                $role = 'ADMIN';
            }
        } catch (Exception $e) {
            // If settings table doesn't exist yet, continue
        }

        // Create new user
        $userId = generateULID();
        $insertData = [
            'id' => $userId,
            'provider' => $provider,
            'email' => $oauthData['email'] ?? '',
            'name' => $oauthData['name'] ?? $oauthData['nickname'] ?? '',
            'role' => $role,
            $idField => $idValue
        ];

        // Add avatar for Google, headimgurl for WeChat
        if ($provider === 'google' && isset($oauthData['picture'])) {
            $insertData['avatar_url'] = $oauthData['picture'];
        } elseif ($provider === 'wechat' && isset($oauthData['headimgurl'])) {
            $insertData['avatar_url'] = $oauthData['headimgurl'];
        }

        // WeChat specific fields
        if ($provider === 'wechat') {
            if (isset($oauthData['unionid'])) {
                $insertData['wechat_unionid'] = $oauthData['unionid'];
            }
            if (isset($oauthData['openid'])) {
                $insertData['wechat_openid'] = $oauthData['openid'];
            }
        }

        $columns = implode(', ', array_keys($insertData));
        $placeholders = str_repeat('?, ', count($insertData) - 1) . '?';
        $values = array_values($insertData);

        $stmt = $pdo->prepare("INSERT INTO users ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($values);

        return $userId;
    }
}

/**
 * Authenticate user with email and password
 */
function authenticateWithEmail($email, $password) {
    global $pdo;

    // Check if user exists with a password set (allows login for OAuth users who have set passwords)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password_hash IS NOT NULL AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);

    return $user;
}

/**
 * Create user with email and password (for future use, currently disabled)
 */
function createUserWithEmail($email, $password, $name) {
    global $pdo;

    // Check if email login is enabled
    if (!EMAIL_LOGIN_ENABLED) {
        throw new Exception('Email registration is not enabled');
    }

    // Validate password
    validatePassword($password);

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already registered');
    }

    // Create new user
    $userId = generateULID();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (id, provider, email, name, password_hash, role)
        VALUES (?, 'email', ?, ?, ?, 'MEMBER')
    ");
    $stmt->execute([$userId, $email, $name, $passwordHash]);

    return $userId;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }

    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    if (!empty($errors)) {
        throw new Exception(implode(', ', $errors));
    }
}

/**
 * Get WeChat OAuth URL
 */
function getWeChatAuthUrl() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['wechat_oauth_state'] = $state;

    $params = [
        'appid' => WECHAT_APP_ID,
        'redirect_uri' => urlencode(WECHAT_REDIRECT_URI),
        'response_type' => 'code',
        'scope' => 'snsapi_login',
        'state' => $state
    ];

    return 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query($params) . '#wechat_redirect';
}

/**
 * Get WeChat user info from authorization code
 */
function getWeChatUserInfo($authCode) {
    // Step 1: Exchange code for access token
    $tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token';
    $tokenParams = [
        'appid' => WECHAT_APP_ID,
        'secret' => WECHAT_APP_SECRET,
        'code' => $authCode,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl . '?' . http_build_query($tokenParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to get WeChat access token');
    }

    $tokenData = json_decode($tokenResponse, true);

    if (isset($tokenData['errcode'])) {
        throw new Exception('WeChat OAuth error: ' . $tokenData['errmsg']);
    }

    $accessToken = $tokenData['access_token'];
    $openId = $tokenData['openid'];
    $unionId = $tokenData['unionid'] ?? null;

    // Step 2: Get user info
    $userUrl = 'https://api.weixin.qq.com/sns/userinfo';
    $userParams = [
        'access_token' => $accessToken,
        'openid' => $openId,
        'lang' => 'zh_CN'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userUrl . '?' . http_build_query($userParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $userResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to get WeChat user info');
    }

    $userData = json_decode($userResponse, true);

    if (isset($userData['errcode'])) {
        throw new Exception('WeChat user info error: ' . $userData['errmsg']);
    }

    // Return normalized user data
    return [
        'id' => $openId,
        'unionid' => $unionId,
        'openid' => $openId,
        'name' => $userData['nickname'] ?? '',
        'nickname' => $userData['nickname'] ?? '',
        'headimgurl' => $userData['headimgurl'] ?? '',
        'sex' => $userData['sex'] ?? 0,
        'province' => $userData['province'] ?? '',
        'city' => $userData['city'] ?? '',
        'country' => $userData['country'] ?? ''
        // Note: WeChat doesn't provide email, so we'll use a placeholder
    ];
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Ensure CSRF token is valid for state-changing requests
 */
function ensureCSRFValid() {
    $method = $_SERVER['REQUEST_METHOD'];
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($token)) {
            jsonError('Invalid CSRF token', 403);
        }
    }
}
