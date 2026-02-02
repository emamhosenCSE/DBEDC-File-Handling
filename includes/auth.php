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
    // Try to connect to database
    try {
        // Check if database config exists
        if (!file_exists(__DIR__ . '/db_config.php')) {
            return false;
        }

        require_once __DIR__ . '/db_config.php';
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
 * Get or create user from Google data
 */
function getOrCreateUser($googleData) {
    global $pdo;
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$googleData['id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update existing user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, avatar_url = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE google_id = ?
        ");
        $stmt->execute([
            $googleData['name'],
            $googleData['email'],
            $googleData['picture'] ?? null,
            $googleData['id']
        ]);
        
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
            
            if ($adminEmail && $googleData['email'] === $adminEmail) {
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
        $stmt = $pdo->prepare("
            INSERT INTO users (id, google_id, email, name, avatar_url, role) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $googleData['id'],
            $googleData['email'],
            $googleData['name'],
            $googleData['picture'] ?? null,
            $role
        ]);
        
        return $userId;
    }
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    header('Location: /login.php');
    exit;
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
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCSRFToken($token)) {
            jsonError('Invalid CSRF token', 403);
        }
    }
}
