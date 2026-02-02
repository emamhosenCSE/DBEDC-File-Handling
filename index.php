<?php
/**
 * Index - Root Entry Point
 * Redirects to login or dashboard based on authentication
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
