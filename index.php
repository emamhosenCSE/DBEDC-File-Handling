<?php
/**
 * Index - Root Entry Point
 * Redirects to installer, login or dashboard based on installation and authentication status
 */

require_once __DIR__ . '/includes/auth.php';

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
