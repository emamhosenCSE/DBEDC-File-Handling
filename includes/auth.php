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
        // Create new user
        $userId = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO users (id, google_id, email, name, avatar_url) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $googleData['id'],
            $googleData['email'],
            $googleData['name'],
            $googleData['picture'] ?? null
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
