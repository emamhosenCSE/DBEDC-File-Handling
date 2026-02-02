<?php
/**
 * Permissions Helper
 * Role-based Access Control (RBAC) for File Tracker
 * 
 * Roles: ADMIN, MANAGER, MEMBER, VIEWER
 */

require_once __DIR__ . '/db.php';

/**
 * Permission Matrix
 * Defines what each role can do
 */
$PERMISSIONS = [
    'ADMIN' => [
        'dashboard' => ['view_all'],
        'letters' => ['view_all', 'create', 'update_all', 'delete_all', 'bulk_import', 'export'],
        'tasks' => ['view_all', 'create', 'assign_any', 'update_all', 'delete_all'],
        'departments' => ['view', 'create', 'update', 'delete'],
        'users' => ['view', 'create', 'update', 'delete', 'change_role'],
        'settings' => ['view', 'update'],
        'stakeholders' => ['view', 'create', 'update', 'delete'],
        'notifications' => ['view_own', 'send_any'],
        'analytics' => ['view_all'],
    ],
    'MANAGER' => [
        'dashboard' => ['view_department'],
        'letters' => ['view_department', 'create', 'update_department', 'delete_own', 'export'],
        'tasks' => ['view_department', 'create', 'assign_department', 'update_department', 'delete_own'],
        'departments' => ['view'],
        'users' => ['view_department'],
        'settings' => ['view'],
        'stakeholders' => ['view'],
        'notifications' => ['view_own'],
        'analytics' => ['view_department'],
    ],
    'MEMBER' => [
        'dashboard' => ['view_own'],
        'letters' => ['view_assigned', 'create', 'update_own'],
        'tasks' => ['view_assigned', 'create', 'update_own'],
        'departments' => ['view'],
        'users' => [],
        'settings' => ['view'],
        'stakeholders' => ['view'],
        'notifications' => ['view_own'],
        'analytics' => ['view_own'],
    ],
    'VIEWER' => [
        'dashboard' => ['view_own'],
        'letters' => ['view_assigned'],
        'tasks' => ['view_assigned'],
        'departments' => ['view'],
        'users' => [],
        'settings' => ['view'],
        'stakeholders' => ['view'],
        'notifications' => ['view_own'],
        'analytics' => ['view_own'],
    ],
];

/**
 * Check if current user has permission
 */
function hasPermission($resource, $action) {
    global $PERMISSIONS;
    
    $user = getCurrentUser();
    $role = $user['role'];
    
    if (!isset($PERMISSIONS[$role][$resource])) {
        return false;
    }
    
    return in_array($action, $PERMISSIONS[$role][$resource]);
}

/**
 * Check permission and throw error if not allowed
 */
function checkPermission($resource, $action) {
    if (!hasPermission($resource, $action)) {
        jsonError('Insufficient permissions', 403);
    }
}

/**
 * Get user's scope for viewing data
 */
function getUserScope() {
    $user = getCurrentUser();
    
    switch ($user['role']) {
        case 'ADMIN':
            return 'all';
        case 'MANAGER':
            return 'department';
        case 'MEMBER':
        case 'VIEWER':
        default:
            return 'own';
    }
}

/**
 * Filter query based on user permissions
 */
function applyPermissionFilter($baseQuery, $table, $userIdColumn = null, $deptIdColumn = null) {
    $user = getCurrentUser();
    $scope = getUserScope();
    
    if ($scope === 'all') {
        return $baseQuery; // Admin sees all
    }
    
    if ($scope === 'department' && $deptIdColumn) {
        return $baseQuery . " AND $deptIdColumn = '{$user['department_id']}'";
    }
    
    if ($scope === 'own' && $userIdColumn) {
        return $baseQuery . " AND ($userIdColumn = '{$user['id']}' OR {$table}.created_by = '{$user['id']}')";
    }
    
    return $baseQuery;
}

/**
 * Check if user can access specific entity
 */
function canAccessEntity($entityType, $entityId) {
    global $pdo;
    $user = getCurrentUser();
    $scope = getUserScope();
    
    if ($scope === 'all') {
        return true;
    }
    
    switch ($entityType) {
        case 'letter':
            if ($scope === 'department') {
                $stmt = $pdo->prepare("SELECT id FROM letters WHERE id = ? AND department_id = ?");
                $stmt->execute([$entityId, $user['department_id']]);
                return (bool)$stmt->fetch();
            } else {
                // Check if user is assigned to any task on this letter
                $stmt = $pdo->prepare("
                    SELECT l.id FROM letters l
                    INNER JOIN tasks t ON l.id = t.letter_id
                    WHERE l.id = ? AND (t.assigned_to = ? OR l.uploaded_by = ?)
                ");
                $stmt->execute([$entityId, $user['id'], $user['id']]);
                return (bool)$stmt->fetch();
            }
            
        case 'task':
            if ($scope === 'department') {
                $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_department = ?");
                $stmt->execute([$entityId, $user['department_id']]);
                return (bool)$stmt->fetch();
            } else {
                $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND (assigned_to = ? OR created_by = ?)");
                $stmt->execute([$entityId, $user['id'], $user['id']]);
                return (bool)$stmt->fetch();
            }
            
        case 'department':
            if ($scope === 'department') {
                return $entityId === $user['department_id'];
            }
            return false;
            
        default:
            return false;
    }
}

/**
 * Get user's accessible departments
 */
function getAccessibleDepartments() {
    global $pdo;
    $user = getCurrentUser();
    $scope = getUserScope();
    
    if ($scope === 'all') {
        $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = TRUE ORDER BY name");
        return $stmt->fetchAll();
    }
    
    if ($scope === 'department' && $user['department_id']) {
        $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$user['department_id']]);
        return $stmt->fetchAll();
    }
    
    return [];
}

/**
 * Check if user can modify entity
 */
function canModifyEntity($entityType, $entityId) {
    global $pdo;
    $user = getCurrentUser();
    
    // Admins can modify anything
    if ($user['role'] === 'ADMIN') {
        return true;
    }
    
    switch ($entityType) {
        case 'letter':
            if ($user['role'] === 'MANAGER') {
                // Managers can modify letters in their department
                $stmt = $pdo->prepare("SELECT id FROM letters WHERE id = ? AND department_id = ?");
                $stmt->execute([$entityId, $user['department_id']]);
                return (bool)$stmt->fetch();
            } else {
                // Members can modify their own letters
                $stmt = $pdo->prepare("SELECT id FROM letters WHERE id = ? AND uploaded_by = ?");
                $stmt->execute([$entityId, $user['id']]);
                return (bool)$stmt->fetch();
            }
            
        case 'task':
            if ($user['role'] === 'MANAGER') {
                // Managers can modify tasks in their department
                $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_department = ?");
                $stmt->execute([$entityId, $user['department_id']]);
                return (bool)$stmt->fetch();
            } else {
                // Members can modify tasks assigned to them or created by them
                $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND (assigned_to = ? OR created_by = ?)");
                $stmt->execute([$entityId, $user['id'], $user['id']]);
                return (bool)$stmt->fetch();
            }
            
        default:
            return false;
    }
}

/**
 * Log activity to activities table
 */
function logActivity($userId, $activityType, $entityType, $entityId, $description, $metadata = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activities (id, user_id, activity_type, entity_type, entity_id, description, metadata, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            generateULID(),
            $userId,
            $activityType,
            $entityType,
            $entityId,
            $description,
            $metadata ? json_encode($metadata) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Get role badge color
 */
function getRoleBadgeColor($role) {
    $colors = [
        'ADMIN' => '#EF4444',
        'MANAGER' => '#F59E0B',
        'MEMBER' => '#3B82F6',
        'VIEWER' => '#6B7280'
    ];
    
    return $colors[$role] ?? '#6B7280';
}

/**
 * Get permission summary for role
 */
function getPermissionSummary($role) {
    global $PERMISSIONS;
    
    return $PERMISSIONS[$role] ?? [];
}
