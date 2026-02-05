<?php
/**
 * Session Debug Script
 */
session_start();
require_once 'includes/auth.php';

echo "Session Debug:\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Is authenticated: " . (isAuthenticated() ? 'YES' : 'NO') . "\n";

if (isAuthenticated()) {
    $user = getCurrentUser();
    echo "Current user: " . ($user ? $user['name'] . ' (' . $user['email'] . ')' : 'NOT FOUND') . "\n";
}
?>