<?php
/**
 * API Bootstrap
 * Common initialization for all API endpoints
 * Ensures system is installed and user is authenticated
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Ensure system is installed before any API access
ensureSystemInstalled();

// Ensure user is authenticated for API access
ensureAuthenticated();

// Validate CSRF token for state-changing operations
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    ensureCSRFValid();
}

// Get current user for API operations
$user = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

// Cache Control Headers - Disable all caching for API responses
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");