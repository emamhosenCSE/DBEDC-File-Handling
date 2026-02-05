<?php
/**
 * Database Session Handler
 * Stores session data in database instead of files
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table = 'user_sessions';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName) {
        // Create sessions table if it doesn't exist
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    session_id VARCHAR(128) PRIMARY KEY,
                    session_data TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Exception $e) {
            // Table might already exist, continue
        }
        return true;
    }

    public function close() {
        return true;
    }

    public function read($sessionId) {
        try {
            $stmt = $this->pdo->prepare("SELECT session_data FROM {$this->table} WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $data = $stmt->fetchColumn();
            return $data ? $data : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function write($sessionId, $data) {
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (session_id, session_data, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                session_data = VALUES(session_data),
                updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$sessionId, $data]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function destroy($sessionId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE session_id = ?");
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function gc($maxLifetime) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
            return $stmt->execute([$maxLifetime]);
        } catch (Exception $e) {
            return false;
        }
    }
}

// Initialize database session handler
function initDatabaseSessions() {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    // Get database connection
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/db.php';
    }

    if ($pdo) {
        $handler = new DatabaseSessionHandler($pdo);
        session_set_save_handler($handler, true);

        // Set session configuration
        ini_set('session.gc_maxlifetime', 3600); // 1 hour
        ini_set('session.cookie_lifetime', 3600);
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.cookie_httponly', true);
        ini_set('session.cookie_samesite', 'Lax');
    }

    $initialized = true;
}
?>