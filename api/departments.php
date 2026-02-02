<?php
/**
 * Departments API Endpoint
 * Handles hierarchical department management with CRUD operations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

ensureAuthenticated();

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        checkPermission('departments', 'create');
        handlePost();
        break;
    case 'PATCH':
        checkPermission('departments', 'update');
        handleUpdate();
        break;
    case 'DELETE':
        checkPermission('departments', 'delete');
        handleDelete();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch departments with hierarchy
 */
function handleGet() {
    global $pdo;
    
    // Get specific department
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   u.name as manager_name, u.email as manager_email,
                   p.name as parent_name,
                   (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count,
                   (SELECT COUNT(*) FROM departments WHERE parent_id = d.id) as child_count
            FROM departments d
            LEFT JOIN users u ON d.manager_id = u.id
            LEFT JOIN departments p ON d.parent_id = p.id
            WHERE d.id = ? AND d.is_active = TRUE
        ");
        $stmt->execute([$_GET['id']]);
        $dept = $stmt->fetch();
        
        if (!$dept) {
            jsonError('Department not found', 404);
        }
        
        // Get children
        $stmt = $pdo->prepare("
            SELECT d.*, u.name as manager_name,
                   (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count
            FROM departments d
            LEFT JOIN users u ON d.manager_id = u.id
            WHERE d.parent_id = ? AND d.is_active = TRUE
            ORDER BY d.name
        ");
        $stmt->execute([$_GET['id']]);
        $dept['children'] = $stmt->fetchAll();
        
        // Get users
        $stmt = $pdo->prepare("
            SELECT id, name, email, role, avatar_url
            FROM users
            WHERE department_id = ? AND is_active = TRUE
            ORDER BY name
        ");
        $stmt->execute([$_GET['id']]);
        $dept['users'] = $stmt->fetchAll();
        
        jsonResponse($dept);
    }
    
    // Get tree structure
    if (isset($_GET['tree'])) {
        $stmt = $pdo->query("
            SELECT d.*, 
                   u.name as manager_name,
                   (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count,
                   (SELECT COUNT(*) FROM departments WHERE parent_id = d.id) as child_count
            FROM departments d
            LEFT JOIN users u ON d.manager_id = u.id
            WHERE d.is_active = TRUE
            ORDER BY d.parent_id, d.name
        ");
        $allDepts = $stmt->fetchAll();
        
        $tree = buildTree($allDepts);
        jsonResponse($tree);
    }
    
    // Get all departments (flat list)
    $search = $_GET['search'] ?? '';
    $filters = [];
    $params = [];
    
    if ($search) {
        $filters[] = "(d.name LIKE ? OR d.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) . ' AND d.is_active = TRUE' : 'WHERE d.is_active = TRUE';
    
    $stmt = $pdo->prepare("
        SELECT d.*, 
               u.name as manager_name,
               p.name as parent_name,
               (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = TRUE) as user_count,
               (SELECT COUNT(*) FROM departments WHERE parent_id = d.id) as child_count
        FROM departments d
        LEFT JOIN users u ON d.manager_id = u.id
        LEFT JOIN departments p ON d.parent_id = p.id
        $whereClause
        ORDER BY d.parent_id, d.name
    ");
    
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

/**
 * POST - Create new department
 */
function handlePost() {
    global $pdo, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate
    if (empty($data['name'])) {
        jsonError('Department name is required');
    }
    
    // Check for duplicate name
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND is_active = TRUE");
    $stmt->execute([$data['name']]);
    if ($stmt->fetch()) {
        jsonError('Department with this name already exists');
    }
    
    // Validate parent_id
    if (!empty($data['parent_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$data['parent_id']]);
        if (!$stmt->fetch()) {
            jsonError('Parent department not found');
        }
    }
    
    // Validate manager_id
    if (!empty($data['manager_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$data['manager_id']]);
        if (!$stmt->fetch()) {
            jsonError('Manager user not found');
        }
    }
    
    try {
        $deptId = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO departments (id, name, description, parent_id, manager_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $deptId,
            $data['name'],
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $data['manager_id'] ?? null
        ]);
        
        // Log activity
        logActivity($user['id'], 'department_created', 'department', $deptId, "Created department: {$data['name']}");
        
        jsonResponse([
            'success' => true,
            'message' => 'Department created successfully',
            'department_id' => $deptId
        ], 201);
        
    } catch (PDOException $e) {
        jsonError('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * PATCH - Update department
 */
function handleUpdate() {
    global $pdo, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        jsonError('Department ID is required');
    }
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$data['id']]);
    $dept = $stmt->fetch();
    
    if (!$dept) {
        jsonError('Department not found', 404);
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    $allowedFields = ['name', 'description', 'parent_id', 'manager_id'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            // Prevent circular parent reference
            if ($field === 'parent_id' && $data[$field] === $data['id']) {
                jsonError('Department cannot be its own parent');
            }
            
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        jsonError('No fields to update');
    }
    
    $params[] = $data['id'];
    
    $sql = "UPDATE departments SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Log activity
    logActivity($user['id'], 'department_updated', 'department', $data['id'], "Updated department: {$dept['name']}");
    
    jsonResponse([
        'success' => true,
        'message' => 'Department updated successfully'
    ]);
}

/**
 * DELETE - Soft delete department
 */
function handleDelete() {
    global $pdo, $user;
    
    parse_str(file_get_contents('php://input'), $_DELETE);
    
    if (empty($_DELETE['id'])) {
        jsonError('Department ID is required');
    }
    
    // Check if has children
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE parent_id = ? AND is_active = TRUE");
    $stmt->execute([$_DELETE['id']]);
    if ($stmt->fetchColumn() > 0) {
        jsonError('Cannot delete department with sub-departments. Delete or move children first.');
    }
    
    // Check if has users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = TRUE");
    $stmt->execute([$_DELETE['id']]);
    $userCount = $stmt->fetchColumn();
    
    if ($userCount > 0 && empty($_DELETE['force'])) {
        jsonError("Department has $userCount active users. Set users to another department first, or use force=true to unassign them.");
    }
    
    // Soft delete
    $stmt = $pdo->prepare("UPDATE departments SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$_DELETE['id']]);
    
    // Unassign users if forced
    if ($userCount > 0 && !empty($_DELETE['force'])) {
        $stmt = $pdo->prepare("UPDATE users SET department_id = NULL WHERE department_id = ?");
        $stmt->execute([$_DELETE['id']]);
    }
    
    // Log activity
    logActivity($user['id'], 'department_deleted', 'department', $_DELETE['id'], "Deleted department");
    
    jsonResponse([
        'success' => true,
        'message' => 'Department deleted successfully'
    ]);
}

/**
 * Build hierarchical tree structure
 */
function buildTree($elements, $parentId = null) {
    $branch = [];
    
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    
    return $branch;
}
