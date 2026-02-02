<?php
/**
 * Logout Handler
 * Destroys session and redirects to login
 */

session_start();
require_once __DIR__ . '/includes/auth.php';

logout();
