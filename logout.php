<?php
/**
 * Logout Handler
 * Destroys session and redirects to login
 */

session_start();
require_once __DIR__ . '/includes/auth.php';

// Cache Control Headers - Disable all caching for logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Destroy session
$_SESSION = array();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
