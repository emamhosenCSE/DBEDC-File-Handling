<?php
/**
 * Users API Endpoint
 * Handles user management operations
 */

require_once __DIR__ . '/../includes/api-bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'PATCH':
    case 'PUT':
        handleUpdate();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch users
 */
function handleGet() {
    global $pdo, $user;
    
    // Get current user profile
    if (isset($_GET['me'])) {
        $stmt = $pdo->prepare("
            SELECT u.*, d.name as department_name,
                   (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status != 'CANCELLED') as total_tasks,
                   (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'PENDING') as pending_tasks,
                   (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'COMPLETED') as completed_tasks
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        
        // Get user preferences
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $preferences = $stmt->fetch();
        
        if ($preferences) {
            $profile['preferences'] = [
                'quick_actions' => json_decode($preferences['quick_actions'], true),
                'dashboard_layout' => json_decode($preferences['dashboard_layout'], true),
                'theme_preference' => $preferences['theme_preference'],
                'default_view' => $preferences['default_view'],
                'items_per_page' => $preferences['items_per_page']
            ];
        }
        
        jsonResponse($profile);
    }
    
    // Search users
    if (isset($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $limit = min((int)($_GET['limit'] ?? 10), 50);
        
        $sql = "SELECT id, name, email, avatar_url, department_id FROM users WHERE is_active = TRUE AND (name LIKE ? OR email LIKE ?)";
        $params = [$search, $search];
        
        // Restrict to department for non-admins
        if ($user['role'] !== 'ADMIN') {
            $sql .= " AND department_id = ?";
            $params[] = $user['department_id'];
        }
        
        $sql .= " ORDER BY name LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        jsonResponse(['users' => $users]);
    }
    
    // Get single user
    if (isset($_GET['id'])) {
        // Check permission
        if ($user['role'] !== 'ADMIN' && $_GET['id'] !== $user['id']) {
            if ($user['role'] === 'MANAGER') {
                // Managers can view users in their department
                $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $targetUser = $stmt->fetch();
                if (!$targetUser || $targetUser['department_id'] !== $user['department_id']) {
                    jsonError('Access denied', 403);
                }
            } else {
                jsonError('Access denied', 403);
            }
        }
        
        $stmt = $pdo->prepare("
            SELECT u.*, d.name as department_name,
                   (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks,
                   (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'COMPLETED') as completed_tasks
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            jsonError('User not found', 404);
        }
        
        jsonResponse($targetUser);
    }
    
    // Get workload stats
    if (isset($_GET['workload'])) {
        $stmt = $pdo->query("SELECT * FROM view_user_workload ORDER BY total_tasks DESC");
        $workload = $stmt->fetchAll();
        jsonResponse(['workload' => $workload]);
    }
    
    // List all users (with permission check)
    $scope = getUserScope();
    
    $sql = "
        SELECT u.id, u.name, u.email, u.role, u.avatar_url, u.is_active, u.last_login, u.created_at,
               d.name as department_name, d.id as department_id,
               (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status != 'CANCELLED') as task_count
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE 1=1
    ";
    $params = [];
    
    // Apply scope filter
    if ($scope === 'department') {
        $sql .= " AND u.department_id = ?";
        $params[] = $user['department_id'];
    } elseif ($scope === 'own') {
        jsonError('Access denied', 403);
    }
    
    // Apply filters
    if (isset($_GET['role']) && $_GET['role'] !== 'ALL') {
        $sql .= " AND u.role = ?";
        $params[] = $_GET['role'];
    }
    
    if (isset($_GET['department']) && $_GET['department'] !== 'ALL') {
        $sql .= " AND u.department_id = ?";
        $params[] = $_GET['department'];
    }
    
    if (isset($_GET['active'])) {
        $sql .= " AND u.is_active = ?";
        $params[] = $_GET['active'] === 'true';
    }
    
    // Search
    if (!empty($_GET['search'])) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }
    
    $sql .= " ORDER BY u.name";
    
    // Pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countSql = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) FROM', $sql);
    $countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    
    // Get paginated results
    $sql .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    jsonResponse([
        'users' => $users,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);
}

/**
 * PATCH/PUT - Update user
 */
function handleUpdate() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Handle preferences update
    if (isset($input['preferences'])) {
        handlePreferencesUpdate($input['preferences']);
        return;
    }
    
    $targetUserId = $input['id'] ?? $user['id'];
    
    // Check if updating self or has permission
    $isSelf = $targetUserId === $user['id'];
    
    if (!$isSelf && $user['role'] !== 'ADMIN') {
        jsonError('Only administrators can update other users', 403);
    }
    
    // Get target user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        jsonError('User not found', 404);
    }
    
    $updates = [];
    $params = [];
    
    // Fields that users can update for themselves
    $selfFields = ['email_notifications', 'push_notifications'];
    
    // Fields that only admins can update
    $adminFields = ['role', 'department_id', 'is_active'];
    
    foreach ($selfFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = is_bool($input[$field]) ? $input[$field] : ($input[$field] === 'true');
        }
    }
    
    if ($user['role'] === 'ADMIN') {
        foreach ($adminFields as $field) {
            if (isset($input[$field])) {
                // Prevent admin from demoting themselves
                if ($field === 'role' && $isSelf && $input[$field] !== 'ADMIN') {
                    jsonError('Cannot change your own admin role', 400);
                }
                
                // Prevent deactivating self
                if ($field === 'is_active' && $isSelf && !$input[$field]) {
                    jsonError('Cannot deactivate your own account', 400);
                }
                
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
    }
    
    if (empty($updates)) {
        jsonError('No fields to update', 400);
    }
    
    try {
        $params[] = $targetUserId;
        $sql = "UPDATE users SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log activity
        logActivity(
            $user['id'],
            'user_updated',
            'user',
            $targetUserId,
            "User '{$targetUser['name']}' updated",
            ['changes' => array_keys($input)]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("User update error: " . $e->getMessage());
        jsonError('Failed to update user', 500);
    }
}

/**
 * Handle user preferences update
 */
function handlePreferencesUpdate($preferences) {
    global $pdo, $user;
    
    try {
        // Check if preferences exist
        $stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $existing = $stmt->fetch();
        
        $updates = [];
        $params = [];
        
        if (isset($preferences['quick_actions'])) {
            $updates[] = "quick_actions = ?";
            $params[] = json_encode($preferences['quick_actions']);
        }
        
        if (isset($preferences['dashboard_layout'])) {
            $updates[] = "dashboard_layout = ?";
            $params[] = json_encode($preferences['dashboard_layout']);
        }
        
        if (isset($preferences['theme_preference'])) {
            $updates[] = "theme_preference = ?";
            $params[] = $preferences['theme_preference'];
        }
        
        if (isset($preferences['default_view'])) {
            $updates[] = "default_view = ?";
            $params[] = $preferences['default_view'];
        }
        
        if (isset($preferences['items_per_page'])) {
            $updates[] = "items_per_page = ?";
            $params[] = (int)$preferences['items_per_page'];
        }
        
        if (empty($updates)) {
            jsonError('No preferences to update', 400);
        }
        
        if ($existing) {
            $params[] = $user['id'];
            $sql = "UPDATE user_preferences SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE user_id = ?";
        } else {
            $id = generateULID();
            $sql = "INSERT INTO user_preferences (id, user_id, " . 
                   implode(", ", array_map(function($u) { return explode(" = ", $u)[0]; }, $updates)) . 
                   ") VALUES (?, ?, " . implode(", ", array_fill(0, count($updates), "?")) . ")";
            $params = array_merge([$id, $user['id']], $params);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse([
            'success' => true,
            'message' => 'Preferences updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Preferences update error: " . $e->getMessage());
        jsonError('Failed to update preferences', 500);
    }
}

/**
 * DELETE - Deactivate user
 */
function handleDelete() {
    global $pdo, $user;
    
    // Only admins can deactivate users
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can deactivate users', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('User ID is required', 400);
    }
    
    // Prevent self-deactivation
    if ($input['id'] === $user['id']) {
        jsonError('Cannot deactivate your own account', 400);
    }
    
    // Get target user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$input['id']]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        jsonError('User not found', 404);
    }
    
    try {
        // Soft delete (deactivate)
        $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        // Unassign from tasks
        $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ? AND status = 'PENDING'");
        $stmt->execute([$input['id']]);
        
        // Log activity
        logActivity(
            $user['id'],
            'user_deactivated',
            'user',
            $input['id'],
            "User '{$targetUser['name']}' deactivated",
            null
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'User deactivated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("User deactivate error: " . $e->getMessage());
        jsonError('Failed to deactivate user', 500);
    }
}
