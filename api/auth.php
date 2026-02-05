<?php
/**
 * Authentication API Endpoint
 * Handles email login and related auth operations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Ensure system is installed before any API access
ensureSystemInstalled();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        handleLogin();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * Handle email login
 */
function handleLogin() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        jsonError('Email and password are required', 400);
    }

    $email = trim($data['email']);
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email format', 400);
    }

    if (empty($password)) {
        jsonError('Password is required', 400);
    }

    try {
        $user = authenticateWithEmail($email, $password);

        if (!$user) {
            jsonError('Invalid email or password', 401);
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_avatar'] = $user['avatar_url'];

        // Return user info (without sensitive data)
        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'avatar' => $user['avatar_url']
            ],
            'redirect' => 'dashboard.php'
        ]);

    } catch (Exception $e) {
        jsonError('Login failed: ' . $e->getMessage(), 500);
    }
}