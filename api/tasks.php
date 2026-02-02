<?php
/**
 * Tasks API Endpoint
 * Handles CRUD operations for tasks
 */

require_once __DIR__ . '/../includes/auth.php';
ensureAuthenticated();

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
        handleUpdate();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch tasks with filters
 */
function handleGet() {
    global $pdo, $user;
    
    $view = $_GET['view'] ?? 'my'; // 'my' or 'all'
    
    $filters = [];
    $params = [];
    
    // View-based filtering
    if ($view === 'my') {
        // Show tasks assigned to current user or their department
        $filters[] = "(t.assigned_to = ? OR t.assigned_group = ?)";
        $params[] = $user['id'];
        $params[] = $user['department'];
    }
    
    // Status filter
    if (!empty($_GET['status']) && $_GET['status'] !== 'ALL') {
        $filters[] = "t.status = ?";
        $params[] = $_GET['status'];
    }
    
    // Stakeholder filter (from letter)
    if (!empty($_GET['stakeholder']) && $_GET['stakeholder'] !== 'ALL') {
        $filters[] = "l.stakeholder = ?";
        $params[] = $_GET['stakeholder'];
    }
    
    // Priority filter (from letter)
    if (!empty($_GET['priority']) && $_GET['priority'] !== 'ALL') {
        $filters[] = "l.priority = ?";
        $params[] = $_GET['priority'];
    }
    
    // Search filter
    if (!empty($_GET['search'])) {
        $filters[] = "(t.title LIKE ? OR l.reference_no LIKE ? OR l.subject LIKE ?)";
        $searchTerm = '%' . $_GET['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Assigned to filter (for all tasks view)
    if (!empty($_GET['assigned_to'])) {
        $filters[] = "t.assigned_to = ?";
        $params[] = $_GET['assigned_to'];
    }
    
    $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    // Fetch specific task by ID
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   l.reference_no, l.subject, l.stakeholder, l.priority, l.pdf_filename, l.tencent_doc_url,
                   u1.name as assigned_to_name, u1.email as assigned_to_email,
                   u2.name as created_by_name
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            WHERE t.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            jsonError('Task not found', 404);
        }
        
        // Fetch task updates/history
        $stmt = $pdo->prepare("
            SELECT tu.*, u.name as user_name, u.avatar_url
            FROM task_updates tu
            LEFT JOIN users u ON tu.user_id = u.id
            WHERE tu.task_id = ?
            ORDER BY tu.created_at DESC
        ");
        $stmt->execute([$_GET['id']]);
        $task['updates'] = $stmt->fetchAll();
        
        jsonResponse($task);
    }
    
    // Fetch all tasks with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) 
        FROM tasks t 
        JOIN letters l ON t.letter_id = l.id 
        $whereClause
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    
    // Get paginated results
    $sql = "
        SELECT t.*, 
               l.reference_no, l.subject, l.stakeholder, l.priority,
               u1.name as assigned_to_name,
               u2.name as created_by_name,
               (SELECT COUNT(*) FROM task_updates WHERE task_id = t.id) as update_count
        FROM tasks t
        JOIN letters l ON t.letter_id = l.id
        LEFT JOIN users u1 ON t.assigned_to = u1.id
        LEFT JOIN users u2 ON t.created_by = u2.id
        $whereClause
        ORDER BY 
            CASE t.status 
                WHEN 'PENDING' THEN 1
                WHEN 'IN_PROGRESS' THEN 2
                WHEN 'COMPLETED' THEN 3
            END,
            l.priority = 'URGENT' DESC,
            l.priority = 'HIGH' DESC,
            t.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    jsonResponse([
        'tasks' => $tasks,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRecords,
            'total_pages' => ceil($totalRecords / $perPage)
        ]
    ]);
}

/**
 * POST - Create new task
 */
function handlePost() {
    global $pdo, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['letter_id']) || empty($data['title'])) {
        jsonError('letter_id and title are required');
    }
    
    // Verify letter exists
    $stmt = $pdo->prepare("SELECT id FROM letters WHERE id = ?");
    $stmt->execute([$data['letter_id']]);
    if (!$stmt->fetch()) {
        jsonError('Letter not found', 404);
    }
    
    // Validate assigned_to if provided
    if (!empty($data['assigned_to'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$data['assigned_to']]);
        if (!$stmt->fetch()) {
            jsonError('Assigned user not found', 404);
        }
    }
    
    // Insert task
    try {
        $taskId = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO tasks (id, letter_id, title, assigned_to, assigned_group, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $taskId,
            $data['letter_id'],
            $data['title'],
            $data['assigned_to'] ?? null,
            $data['assigned_group'] ?? null,
            $data['status'] ?? 'PENDING',
            $data['notes'] ?? null,
            $user['id']
        ]);
        
        // Log initial creation in task_updates
        $stmt = $pdo->prepare("
            INSERT INTO task_updates (id, task_id, user_id, old_status, new_status, comment)
            VALUES (?, ?, ?, NULL, 'PENDING', 'Task created')
        ");
        $stmt->execute([generateULID(), $taskId, $user['id']]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ], 201);
        
    } catch (PDOException $e) {
        jsonError('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * PATCH - Update task (status, assignment, etc.)
 */
function handleUpdate() {
    global $pdo, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        jsonError('Task ID is required');
    }
    
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$data['id']]);
    $task = $stmt->fetch();
    
    if (!$task) {
        jsonError('Task not found', 404);
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    $allowedFields = ['title', 'assigned_to', 'assigned_group', 'status', 'notes'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            
            // Handle status change
            if ($field === 'status' && $data['status'] !== $task['status']) {
                // Log status change
                $logStmt = $pdo->prepare("
                    INSERT INTO task_updates (id, task_id, user_id, old_status, new_status, comment)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $logStmt->execute([
                    generateULID(),
                    $data['id'],
                    $user['id'],
                    $task['status'],
                    $data['status'],
                    $data['comment'] ?? null
                ]);
                
                // Set completed_at if status is COMPLETED
                if ($data['status'] === 'COMPLETED') {
                    $updates[] = "completed_at = CURRENT_TIMESTAMP";
                }
            }
        }
    }
    
    if (empty($updates)) {
        jsonError('No fields to update');
    }
    
    $params[] = $data['id'];
    
    $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'message' => 'Task updated successfully'
    ]);
}

/**
 * DELETE - Delete task
 */
function handleDelete() {
    global $pdo;
    
    parse_str(file_get_contents('php://input'), $_DELETE);
    
    if (empty($_DELETE['id'])) {
        jsonError('Task ID is required');
    }
    
    // Check if task exists
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ?");
    $stmt->execute([$_DELETE['id']]);
    if (!$stmt->fetch()) {
        jsonError('Task not found', 404);
    }
    
    // Delete task (cascade will delete updates)
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$_DELETE['id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Task deleted successfully'
    ]);
}
