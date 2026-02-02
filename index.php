<?php
/**
 * Index - Root Entry Point
 * Redirects to installer, login or dashboard based on installation and authentication status
 */

session_start();

// Check if system is installed
if (!isSystemInstalled()) {
    header('Location: install.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;

/**
 * Check if the system has been installed
 */
function isSystemInstalled() {
    // Try to connect to database
    try {
        require_once __DIR__ . '/includes/config.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Check if settings table exists and has installation flag
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'system_installed'");
        $installed = $stmt->fetchColumn();
        
        return $installed === '1';
    } catch (Exception $e) {
        // Database not set up or connection failed
        return false;
    }
}
