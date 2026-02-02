<?php
/**
 * Debug API - Test database and API endpoints
 */

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Test authentication
if (!isAuthenticated()) {
    echo json_encode(['error' => 'Not authenticated', 'session' => $_SESSION]);
    exit;
}

$user = getCurrentUser();

try {
    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch();
    
    // Test letters table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM letters");
    $letterCount = $stmt->fetch();
    
    // Test tasks table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $taskCount = $stmt->fetch();
    
    // Test activities table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM activities");
        $activityCount = $stmt->fetch();
    } catch (Exception $e) {
        $activityCount = ['count' => 0, 'error' => $e->getMessage()];
    }
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'role' => $user['role'] ?? null,
            'department_id' => $user['department_id'] ?? null
        ],
        'counts' => [
            'users' => $userCount['count'],
            'letters' => $letterCount['count'],
            'tasks' => $taskCount['count'],
            'activities' => $activityCount['count'] ?? 0
        ],
        'activity_error' => $activityCount['error'] ?? null,
        'tables_exist' => true
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
