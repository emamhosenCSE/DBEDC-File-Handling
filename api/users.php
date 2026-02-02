<?php
/**
 * Users API Endpoint
 * Handles user profile and user listing
 */

require_once __DIR__ . '/../includes/auth.php';
ensureAuthenticated();

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'PATCH':
        handleUpdate();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch users or current profile
 */
function handleGet() {
    global $pdo, $user;
    
    // Get current user profile
    if (isset($_GET['me'])) {
        jsonResponse($user);
    }
    
    // Search users (for assignment dropdown)
    if (isset($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $stmt = $pdo->prepare("
            SELECT id, name, email, department
            FROM users
            WHERE name LIKE ? OR email LIKE ?
            ORDER BY name
            LIMIT 20
        ");
        $stmt->execute([$search, $search]);
        jsonResponse($stmt->fetchAll());
    }
    
    // Get all users
    $stmt = $pdo->query("
        SELECT id, name, email, department, role, avatar_url
        FROM users
        ORDER BY name
    ");
    
    jsonResponse($stmt->fetchAll());
}

/**
 * PATCH - Update user profile
 */
function handleUpdate() {
    global $pdo, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Users can only update their own profile
    $userId = $data['id'] ?? $user['id'];
    
    if ($userId !== $user['id'] && $user['role'] !== 'ADMIN') {
        jsonError('Unauthorized', 403);
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    $allowedFields = ['department', 'name'];
    
    // Admins can update role
    if ($user['role'] === 'ADMIN') {
        $allowedFields[] = 'role';
    }
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        jsonError('No fields to update');
    }
    
    $params[] = $userId;
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
}
