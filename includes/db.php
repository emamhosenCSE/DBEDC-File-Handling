<?php
/**
 * Database Connection Handler
 */

// Load database config if it exists (from installer), otherwise use defaults
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} else {
    // Default/fallback credentials
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'file_tracker');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Development mode (set to false in production)
define('DEV_MODE', true);

// Only establish database connection if config exists (system is installed)
if (file_exists(__DIR__ . '/db_config.php')) {
    $pdo = null; // Initialize to null
    try {
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
    } catch (PDOException $e) {
        // Instead of dying, throw the exception so calling code can handle it
        throw $e;
    }
} else {
    // System not installed, $pdo remains null
    $pdo = null;
}

/**
 * Generate ULID (Universally Unique Lexicographically Sortable Identifier)
 * Compatible with MySQL CHAR(26)
 */
function generateULID() {
    // Timestamp (10 chars)
    $time = str_pad(base_convert((int)(microtime(true) * 1000), 10, 32), 10, '0', STR_PAD_LEFT);
    
    // Randomness (16 chars)
    $random = '';
    $chars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford's Base32
    for ($i = 0; $i < 16; $i++) {
        $random .= $chars[random_int(0, 31)];
    }
    
    return strtoupper($time . $random);
}

/**
 * Sanitize input to prevent XSS
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    global $pdo;
    if (!isAuthenticated()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Return JSON response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Return error response
 */
function jsonError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}
