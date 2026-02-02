<?php
/**
 * Departments API Endpoint
 * Handles department CRUD operations with hierarchy support
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
ensureAuthenticated();
ensureCSRFValid();

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
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
 * GET - Fetch departments
 */
function handleGet() {
    global $pdo, $user;
    
    $id = $_GET['id'] ?? null;
    $tree = isset($_GET['tree']);
    $stats = isset($_GET['stats']);
    
    try {
        if ($id) {
            // Get single department with details
            $stmt = $pdo->prepare("
                SELECT d.*, 
                       m.name as manager_name, m.email as manager_email, m.avatar_url as manager_avatar,
                       p.name as parent_name,
                       (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count,
                       (SELECT COUNT(*) FROM letters WHERE department_id = d.id AND status != 'DELETED') as letter_count,
                       (SELECT COUNT(*) FROM tasks WHERE assigned_department = d.id) as task_count
                FROM departments d
                LEFT JOIN users m ON d.manager_id = m.id
                LEFT JOIN departments p ON d.parent_id = p.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $department = $stmt->fetch();
            
            if (!$department) {
                jsonError('Department not found', 404);
            }
            
            // Get child departments
            $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE parent_id = ? AND is_active = TRUE ORDER BY name");
            $stmt->execute([$id]);
            $department['children'] = $stmt->fetchAll();
            
            // Get department users
            $stmt = $pdo->prepare("
                SELECT id, name, email, role, avatar_url 
                FROM users 
                WHERE department_id = ? AND is_active = TRUE 
                ORDER BY name
            ");
            $stmt->execute([$id]);
            $department['users'] = $stmt->fetchAll();
            
            jsonResponse($department);
        }
        
        // Get hierarchy tree
        if ($tree) {
            $departments = getDepartmentTree();
            jsonResponse(['tree' => $departments]);
        }
        
        // Get with stats
        if ($stats) {
            $stmt = $pdo->query("SELECT * FROM view_department_stats ORDER BY name");
            $departments = $stmt->fetchAll();
            jsonResponse(['departments' => $departments]);
        }
        
        // Get all departments (flat list)
        $activeOnly = !isset($_GET['all']);
        
        $sql = "
            SELECT d.*, 
                   m.name as manager_name,
                   p.name as parent_name,
                   (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count
            FROM departments d
            LEFT JOIN users m ON d.manager_id = m.id
            LEFT JOIN departments p ON d.parent_id = p.id
        ";
        
        if ($activeOnly) {
            $sql .= " WHERE d.is_active = TRUE";
        }
        
        $sql .= " ORDER BY d.display_order, d.name";
        
        $stmt = $pdo->query($sql);
        $departments = $stmt->fetchAll();
        
        jsonResponse([
            'departments' => $departments,
            'total' => count($departments)
        ]);
        
    } catch (PDOException $e) {
        error_log("Departments GET error: " . $e->getMessage());
        jsonError('Failed to fetch departments', 500);
    }
}

/**
 * Get department hierarchy tree
 */
function getDepartmentTree($parentId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.description, d.manager_id,
               m.name as manager_name,
               (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count
        FROM departments d
        LEFT JOIN users m ON d.manager_id = m.id
        WHERE d.is_active = TRUE AND " . ($parentId ? "d.parent_id = ?" : "d.parent_id IS NULL") . "
        ORDER BY d.display_order, d.name
    ");
    
    $stmt->execute($parentId ? [$parentId] : []);
    $departments = $stmt->fetchAll();
    
    foreach ($departments as &$dept) {
        $dept['children'] = getDepartmentTree($dept['id']);
    }
    
    return $departments;
}

/**
 * POST - Create department
 */
function handlePost() {
    global $pdo, $user;
    
    // Only admins can create departments
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can create departments', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['name'])) {
        jsonError('Department name is required', 400);
    }
    
    // Validate parent exists if provided
    if (!empty($input['parent_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
        $stmt->execute([$input['parent_id']]);
        if (!$stmt->fetch()) {
            jsonError('Parent department not found', 400);
        }
    }
    
    // Validate manager exists if provided
    if (!empty($input['manager_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$input['manager_id']]);
        if (!$stmt->fetch()) {
            jsonError('Manager user not found', 400);
        }
    }
    
    try {
        $id = generateULID();
        
        // Get next display order
        $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM departments");
        $displayOrder = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            INSERT INTO departments (id, name, description, parent_id, manager_id, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            trim($input['name']),
            $input['description'] ?? null,
            $input['parent_id'] ?? null,
            $input['manager_id'] ?? null,
            $input['display_order'] ?? $displayOrder
        ]);
        
        // Log activity
        logActivity(
            $user['id'],
            'department_created',
            'department',
            $id,
            "Department '{$input['name']}' created",
            null
        );
        
        jsonResponse([
            'success' => true,
            'id' => $id,
            'message' => 'Department created successfully'
        ], 201);
        
    } catch (PDOException $e) {
        error_log("Department create error: " . $e->getMessage());
        jsonError('Failed to create department', 500);
    }
}

/**
 * PATCH/PUT - Update department
 */
function handleUpdate() {
    global $pdo, $user;
    
    // Only admins can update departments
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can update departments', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('Department ID is required', 400);
    }
    
    // Check if department exists
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$input['id']]);
    $department = $stmt->fetch();
    
    if (!$department) {
        jsonError('Department not found', 404);
    }
    
    // Prevent circular parent reference
    if (!empty($input['parent_id'])) {
        if ($input['parent_id'] === $input['id']) {
            jsonError('Department cannot be its own parent', 400);
        }
        
        // Check if new parent is a child of this department
        if (isChildDepartment($input['id'], $input['parent_id'])) {
            jsonError('Cannot set a child department as parent', 400);
        }
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    if (isset($input['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($input['name']);
    }
    
    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = $input['description'];
    }
    
    if (array_key_exists('parent_id', $input)) {
        $updates[] = "parent_id = ?";
        $params[] = $input['parent_id'];
    }
    
    if (array_key_exists('manager_id', $input)) {
        $updates[] = "manager_id = ?";
        $params[] = $input['manager_id'];
    }
    
    if (isset($input['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = (bool)$input['is_active'];
    }
    
    if (isset($input['display_order'])) {
        $updates[] = "display_order = ?";
        $params[] = (int)$input['display_order'];
    }
    
    if (empty($updates)) {
        jsonError('No fields to update', 400);
    }
    
    try {
        $params[] = $input['id'];
        $sql = "UPDATE departments SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log activity
        logActivity(
            $user['id'],
            'department_updated',
            'department',
            $input['id'],
            "Department '{$department['name']}' updated",
            ['changes' => array_keys($input)]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Department updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Department update error: " . $e->getMessage());
        jsonError('Failed to update department', 500);
    }
}

/**
 * Check if targetId is a child of parentId
 */
function isChildDepartment($parentId, $targetId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT parent_id FROM departments WHERE id = ?");
    $stmt->execute([$targetId]);
    $result = $stmt->fetch();
    
    if (!$result || !$result['parent_id']) {
        return false;
    }
    
    if ($result['parent_id'] === $parentId) {
        return true;
    }
    
    return isChildDepartment($parentId, $result['parent_id']);
}

/**
 * DELETE - Delete department
 */
function handleDelete() {
    global $pdo, $user;
    
    // Only admins can delete departments
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can delete departments', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('Department ID is required', 400);
    }
    
    // Check if department exists
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$input['id']]);
    $department = $stmt->fetch();
    
    if (!$department) {
        jsonError('Department not found', 404);
    }
    
    // Check if department has users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = TRUE");
    $stmt->execute([$input['id']]);
    $userCount = (int)$stmt->fetchColumn();
    
    if ($userCount > 0) {
        jsonError("Cannot delete department with $userCount active users. Reassign users first.", 400);
    }
    
    // Check if department has child departments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE parent_id = ? AND is_active = TRUE");
    $stmt->execute([$input['id']]);
    $childCount = (int)$stmt->fetchColumn();
    
    if ($childCount > 0) {
        jsonError("Cannot delete department with $childCount child departments. Delete or reassign children first.", 400);
    }
    
    try {
        // Soft delete
        $stmt = $pdo->prepare("UPDATE departments SET is_active = FALSE, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        // Log activity
        logActivity(
            $user['id'],
            'department_deleted',
            'department',
            $input['id'],
            "Department '{$department['name']}' deleted",
            null
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Department deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Department delete error: " . $e->getMessage());
        jsonError('Failed to delete department', 500);
    }
}
