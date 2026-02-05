<?php
/**
 * Letters API Endpoint
 * Handles CRUD operations for letters with bulk support
 */

// Start session with minimal configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS headers for API requests
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/validation.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        if (isset($_GET['bulk'])) {
            handleBulkCreate();
        } else {
            handlePost();
        }
        break;
    case 'PUT':
    case 'PATCH':
        if (isset($_GET['bulk'])) {
            handleBulkUpdate();
        } else {
            handleUpdate();
        }
        break;
    case 'DELETE':
        if (isset($_GET['bulk'])) {
            handleBulkDelete();
        } else {
            handleDelete();
        }
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch letters with optional filters
 */
function handleGet() {
    try {
        global $pdo, $user;
    
    // Handle export
    if (isset($_GET['export'])) {
        handleExport();
        return;
    }
    
    // Handle calendar view
    if (isset($_GET['calendar'])) {
        handleCalendarView();
        return;
    }
    
    $filters = [];
    $params = [];
    
    // Apply permission filters
    $scope = getUserScope();
    if ($scope === 'department' && $user['department_id']) {
        $filters[] = "l.department_id = ?";
        $params[] = $user['department_id'];
    } elseif ($scope === 'own') {
        $filters[] = "(l.uploaded_by = ? OR EXISTS (SELECT 1 FROM tasks t WHERE t.letter_id = l.id AND t.assigned_to = ?))";
        $params[] = $user['id'];
        $params[] = $user['id'];
    }
    
    // Search filter
    if (!empty($_GET['search'])) {
        $filters[] = "(l.reference_no LIKE ? OR l.subject LIKE ? OR l.description LIKE ?)";
        $searchTerm = '%' . $_GET['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Stakeholder filter
    if (!empty($_GET['stakeholder']) && $_GET['stakeholder'] !== 'ALL') {
        $filters[] = "l.stakeholder_id = ?";
        $params[] = $_GET['stakeholder'];
    }
    
    // Priority filter
    if (!empty($_GET['priority']) && $_GET['priority'] !== 'ALL') {
        $filters[] = "l.priority = ?";
        $params[] = $_GET['priority'];
    }
    
    // Status filter
    if (!empty($_GET['status']) && $_GET['status'] !== 'ALL') {
        $filters[] = "l.status = ?";
        $params[] = $_GET['status'];
    } else {
        $filters[] = "l.status != 'DELETED'";
    }
    
    // Department filter
    if (!empty($_GET['department']) && $_GET['department'] !== 'ALL') {
        $filters[] = "l.department_id = ?";
        $params[] = $_GET['department'];
    }
    
    // Date range filter
    if (!empty($_GET['date_from'])) {
        $filters[] = "l.received_date >= ?";
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $filters[] = "l.received_date <= ?";
        $params[] = $_GET['date_to'];
    }
    
    // Build WHERE clause
    $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    // Fetch specific letter by ID
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   s.name as stakeholder_name, s.code as stakeholder_code, s.color as stakeholder_color,
                   u.name as uploaded_by_name, u.email as uploaded_by_email,
                   d.name as department_name
            FROM letters l
            LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
            LEFT JOIN users u ON l.uploaded_by = u.id
            LEFT JOIN departments d ON l.department_id = d.id
            WHERE l.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $letter = $stmt->fetch();
        
        if (!$letter) {
            jsonError('Letter not found', 404);
        }
        
        // Check access permission
        if (!canAccessEntity('letter', $letter['id'])) {
            jsonError('Access denied', 403);
        }
        
        // Fetch associated tasks
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u.name as assigned_to_name, u.avatar_url as assigned_to_avatar,
                   d.name as department_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN departments d ON t.assigned_department = d.id
            WHERE t.letter_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$_GET['id']]);
        $letter['tasks'] = $stmt->fetchAll();
        
        // Parse JSON fields
        if ($letter['tags']) {
            $letter['tags'] = json_decode($letter['tags'], true);
        }
        if ($letter['metadata']) {
            $letter['metadata'] = json_decode($letter['metadata'], true);
        }
        
        jsonResponse($letter);
    }
    
    // Fetch all letters with pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;
    
    // Sorting
    $sortField = $_GET['sort'] ?? 'received_date';
    $sortDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $allowedSorts = ['received_date', 'created_at', 'reference_no', 'priority', 'subject'];
    if (!in_array($sortField, $allowedSorts)) {
        $sortField = 'received_date';
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM letters l $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = (int)$stmt->fetchColumn();
    
    // Get paginated results
    $sql = "
        SELECT l.*, 
               s.name as stakeholder_name, s.code as stakeholder_code, s.color as stakeholder_color,
               u.name as uploaded_by_name,
               d.name as department_name,
               (SELECT COUNT(*) FROM tasks WHERE letter_id = l.id) as task_count,
               (SELECT COUNT(*) FROM tasks WHERE letter_id = l.id AND status = 'COMPLETED') as completed_tasks
        FROM letters l
        LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
        LEFT JOIN users u ON l.uploaded_by = u.id
        LEFT JOIN departments d ON l.department_id = d.id
        $whereClause
        ORDER BY l.$sortField $sortDir
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $letters = $stmt->fetchAll();
    
    jsonResponse([
        'letters' => $letters,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRecords,
            'total_pages' => ceil($totalRecords / $perPage)
        ]
    ]);
    } catch (PDOException $e) {
        error_log("Letters GET error: " . $e->getMessage());
        jsonError('Failed to fetch letters', 500);
    } catch (Exception $e) {
        error_log("Letters GET unexpected error: " . $e->getMessage());
        jsonError('An unexpected error occurred', 500);
    }
}

/**
 * POST - Create new letter
 */
function handlePost() {
    global $pdo, $user;
    
    // Check permission
    if (!hasPermission('letters', 'create')) {
        jsonError('Permission denied', 403);
    }
    
    // Handle file upload
    $pdfFilename = null;
    $pdfOriginalName = null;
    $pdfSize = null;
    
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = handleFileUpload($_FILES['pdf']);
        if ($uploadResult['success']) {
            $pdfFilename = $uploadResult['filename'];
            $pdfOriginalName = $uploadResult['original_name'];
            $pdfSize = $uploadResult['size'];
        } else {
            jsonError($uploadResult['error']);
        }
    }
    
    // Validate required fields using new validation functions
    try {
        $referenceNo = validateString($_POST['reference_no'] ?? '', 100, true);
        $stakeholderId = validateULID($_POST['stakeholder_id'] ?? '', true);
        $subject = validateString($_POST['subject'] ?? '', 500, true);
        $receivedDate = validateDate($_POST['received_date'] ?? '', true);
        $description = validateString($_POST['description'] ?? '', 2000);
        $priority = validateEnum($_POST['priority'] ?? 'MEDIUM', ['LOW', 'MEDIUM', 'HIGH', 'URGENT']);
        $departmentId = isset($_POST['department_id']) ? validateULID($_POST['department_id']) : null;
        $tencentDocUrl = isset($_POST['tencent_doc_url']) ? validateUrl($_POST['tencent_doc_url']) : null;
    } catch (Exception $e) {
        jsonError($e->getMessage());
    }
    
    // Check for duplicate reference number
    $stmt = $pdo->prepare("SELECT id FROM letters WHERE reference_no = ?");
    $stmt->execute([$referenceNo]);
    if ($stmt->fetch()) {
        jsonError('A letter with this reference number already exists');
    }
    
    // Validate stakeholder exists
    $stmt = $pdo->prepare("SELECT id FROM stakeholders WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$stakeholderId]);
    if (!$stmt->fetch()) {
        jsonError('Invalid stakeholder');
    }
    
    try {
        $letterId = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO letters (id, reference_no, stakeholder_id, subject, description, pdf_filename, pdf_original_name, pdf_size, tencent_doc_url, received_date, priority, department_id, tags, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $tags = isset($_POST['tags']) ? json_encode($_POST['tags']) : null;
        
        $stmt->execute([
            $letterId,
            $referenceNo,
            $stakeholderId,
            $subject,
            $description,
            $pdfFilename,
            $pdfOriginalName,
            $pdfSize,
            $tencentDocUrl,
            $receivedDate,
            $priority,
            $departmentId,
            $tags,
            $user['id']
        ]);
        
        // Log activity
        logActivity(
            $user['id'],
            'letter_created',
            'letter',
            $letterId,
            "Letter '{$_POST['reference_no']}' created",
            ['stakeholder_id' => $_POST['stakeholder_id']]
        );
        
        // Send notifications
        notifyLetterCreated($letterId, $_POST['department_id'] ?? null);
        
        jsonResponse([
            'success' => true,
            'message' => 'Letter created successfully',
            'letter_id' => $letterId
        ], 201);
        
    } catch (PDOException $e) {
        // Clean up uploaded file if database insert fails
        if ($pdfFilename) {
            @unlink(__DIR__ . '/../assets/uploads/' . $pdfFilename);
        }
        error_log("Letter create error: " . $e->getMessage());
        jsonError('Failed to create letter', 500);
    }
}

/**
 * POST with bulk=true - Bulk create letters
 */
function handleBulkCreate() {
    global $pdo, $user;
    
    // Check permission
    if (!hasPermission('letters', 'bulk_import')) {
        jsonError('Permission denied for bulk import', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['letters']) || !is_array($input['letters'])) {
        jsonError('Letters array is required', 400);
    }
    
    $batchId = generateULID();
    $created = 0;
    $errors = [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($input['letters'] as $index => $letterData) {
            $rowNum = $index + 1;
            
            // Validate required fields
            if (empty($letterData['reference_no']) || empty($letterData['stakeholder_id']) || 
                empty($letterData['subject']) || empty($letterData['received_date'])) {
                $errors[] = "Row $rowNum: Missing required fields";
                continue;
            }
            
            // Check for duplicate reference number
            $stmt = $pdo->prepare("SELECT id FROM letters WHERE reference_no = ?");
            $stmt->execute([$letterData['reference_no']]);
            if ($stmt->fetch()) {
                $errors[] = "Row $rowNum: Duplicate reference number '{$letterData['reference_no']}'";
                continue;
            }
            
            // Insert letter
            $letterId = generateULID();
            $stmt = $pdo->prepare("
                INSERT INTO letters (id, reference_no, stakeholder_id, subject, description, received_date, priority, department_id, import_batch_id, import_row_number, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $letterId,
                $letterData['reference_no'],
                $letterData['stakeholder_id'],
                $letterData['subject'],
                $letterData['description'] ?? null,
                $letterData['received_date'],
                $letterData['priority'] ?? 'MEDIUM',
                $letterData['department_id'] ?? $user['department_id'],
                $batchId,
                $rowNum,
                $user['id']
            ]);
            
            $created++;
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity(
            $user['id'],
            'letters_bulk_imported',
            'letter',
            $batchId,
            "Bulk imported $created letters",
            ['batch_id' => $batchId, 'total' => count($input['letters']), 'created' => $created]
        );
        
        jsonResponse([
            'success' => true,
            'message' => "Bulk import completed",
            'batch_id' => $batchId,
            'created' => $created,
            'errors' => $errors
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Bulk create error: " . $e->getMessage());
        jsonError('Bulk import failed', 500);
    }
}

/**
 * PATCH/PUT - Update letter
 */
function handleUpdate() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('Letter ID is required');
    }
    
    // Check if letter exists
    $stmt = $pdo->prepare("SELECT * FROM letters WHERE id = ?");
    $stmt->execute([$input['id']]);
    $letter = $stmt->fetch();
    
    if (!$letter) {
        jsonError('Letter not found', 404);
    }
    
    // Check permission
    if (!canModifyEntity('letter', $input['id'])) {
        jsonError('Permission denied', 403);
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    $allowedFields = ['subject', 'description', 'tencent_doc_url', 'priority', 'stakeholder_id', 'department_id', 'status', 'tags'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if ($field === 'tags') {
                $updates[] = "$field = ?";
                $params[] = json_encode($input[$field]);
            } else {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
    }
    
    if (empty($updates)) {
        jsonError('No fields to update');
    }
    
    $params[] = $input['id'];
    
    $sql = "UPDATE letters SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Log activity
    logActivity(
        $user['id'],
        'letter_updated',
        'letter',
        $input['id'],
        "Letter '{$letter['reference_no']}' updated",
        ['changes' => array_keys($input)]
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Letter updated successfully'
    ]);
}

/**
 * PATCH with bulk=true - Bulk update letters
 */
function handleBulkUpdate() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['ids']) || !is_array($input['ids'])) {
        jsonError('Letter IDs array is required', 400);
    }
    
    if (empty($input['updates'])) {
        jsonError('Updates object is required', 400);
    }
    
    // Check permission for bulk operations
    if ($user['role'] !== 'ADMIN' && $user['role'] !== 'MANAGER') {
        jsonError('Permission denied for bulk update', 403);
    }
    
    $allowedFields = ['status', 'priority', 'department_id'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input['updates'][$field])) {
            $updates[] = "$field = ?";
            $params[] = $input['updates'][$field];
        }
    }
    
    if (empty($updates)) {
        jsonError('No valid fields to update');
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($input['ids']), '?'));
        $params = array_merge($params, $input['ids']);
        
        $sql = "UPDATE letters SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $updated = $stmt->rowCount();
        
        // Log activity
        logActivity(
            $user['id'],
            'letters_bulk_updated',
            'letter',
            'bulk',
            "Bulk updated $updated letters",
            ['ids' => $input['ids'], 'updates' => $input['updates']]
        );
        
        jsonResponse([
            'success' => true,
            'message' => "Updated $updated letters",
            'updated' => $updated
        ]);
        
    } catch (PDOException $e) {
        error_log("Bulk update error: " . $e->getMessage());
        jsonError('Bulk update failed', 500);
    }
}

/**
 * DELETE - Delete letter
 */
function handleDelete() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('Letter ID is required');
    }
    
    // Get letter details
    $stmt = $pdo->prepare("SELECT * FROM letters WHERE id = ?");
    $stmt->execute([$input['id']]);
    $letter = $stmt->fetch();
    
    if (!$letter) {
        jsonError('Letter not found', 404);
    }
    
    // Check permission
    if (!canModifyEntity('letter', $input['id'])) {
        jsonError('Permission denied', 403);
    }
    
    // Soft delete (change status to DELETED)
    $stmt = $pdo->prepare("UPDATE letters SET status = 'DELETED', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    // Log activity
    logActivity(
        $user['id'],
        'letter_deleted',
        'letter',
        $input['id'],
        "Letter '{$letter['reference_no']}' deleted",
        null
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Letter deleted successfully'
    ]);
}

/**
 * DELETE with bulk=true - Bulk delete letters
 */
function handleBulkDelete() {
    global $pdo, $user;
    
    // Only admins can bulk delete
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can bulk delete', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['ids']) || !is_array($input['ids'])) {
        jsonError('Letter IDs array is required', 400);
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($input['ids']), '?'));
        
        // Soft delete
        $stmt = $pdo->prepare("UPDATE letters SET status = 'DELETED', updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($input['ids']);
        
        $deleted = $stmt->rowCount();
        
        // Log activity
        logActivity(
            $user['id'],
            'letters_bulk_deleted',
            'letter',
            'bulk',
            "Bulk deleted $deleted letters",
            ['ids' => $input['ids']]
        );
        
        jsonResponse([
            'success' => true,
            'message' => "Deleted $deleted letters",
            'deleted' => $deleted
        ]);
        
    } catch (PDOException $e) {
        error_log("Bulk delete error: " . $e->getMessage());
        jsonError('Bulk delete failed', 500);
    }
}

/**
 * Handle file upload
 */
function handleFileUpload($file) {
    $uploadDir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file type
    $fileType = mime_content_type($file['tmp_name']);
    if ($fileType !== 'application/pdf') {
        return ['success' => false, 'error' => 'Only PDF files are allowed'];
    }
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size must be less than 10MB'];
    }
    
    // Generate unique filename
    $filename = generateULID() . '.pdf';
    $uploadPath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
    
    return [
        'success' => true,
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => $file['size']
    ];
}

/**
 * Handle export
 */
function handleExport() {
    global $pdo, $user;
    
    // Check permission
    if (!hasPermission('letters', 'export')) {
        jsonError('Permission denied for export', 403);
    }
    
    $format = $_GET['export'];
    
    // Build query with filters
    $sql = "
        SELECT l.reference_no, l.subject, l.received_date, l.priority, l.status, l.created_at,
               s.name as stakeholder, d.name as department, u.name as uploaded_by
        FROM letters l
        LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
        LEFT JOIN departments d ON l.department_id = d.id
        LEFT JOIN users u ON l.uploaded_by = u.id
        WHERE l.status != 'DELETED'
        ORDER BY l.received_date DESC
    ";
    
    $stmt = $pdo->query($sql);
    $letters = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="letters_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
        
        // Headers
        fputcsv($output, ['Reference No', 'Subject', 'Received Date', 'Priority', 'Status', 'Stakeholder', 'Department', 'Uploaded By', 'Created At']);
        
        // Data
        foreach ($letters as $letter) {
            fputcsv($output, [
                $letter['reference_no'],
                $letter['subject'],
                $letter['received_date'],
                $letter['priority'],
                $letter['status'],
                $letter['stakeholder'],
                $letter['department'],
                $letter['uploaded_by'],
                $letter['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    jsonError('Unknown export format', 400);
}

/**
 * Handle calendar view
 */
function handleCalendarView() {
    global $pdo, $user;
    
    $month = $_GET['month'] ?? date('Y-m');
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Get letters for the month
    $stmt = $pdo->prepare("
        SELECT l.id, l.reference_no, l.subject, l.received_date, l.priority,
               s.code as stakeholder_code, s.color as stakeholder_color
        FROM letters l
        LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
        WHERE l.received_date BETWEEN ? AND ?
        AND l.status != 'DELETED'
        ORDER BY l.received_date
    ");
    $stmt->execute([$startDate, $endDate]);
    $letters = $stmt->fetchAll();
    
    // Get tasks with due dates for the month
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.due_date, t.status, t.priority,
               l.reference_no
        FROM tasks t
        JOIN letters l ON t.letter_id = l.id
        WHERE t.due_date BETWEEN ? AND ?
        AND t.status != 'CANCELLED'
        ORDER BY t.due_date
    ");
    $stmt->execute([$startDate, $endDate]);
    $tasks = $stmt->fetchAll();
    
    // Group by date
    $events = [];
    
    foreach ($letters as $letter) {
        $date = $letter['received_date'];
        if (!isset($events[$date])) {
            $events[$date] = ['letters' => [], 'tasks' => []];
        }
        $events[$date]['letters'][] = $letter;
    }
    
    foreach ($tasks as $task) {
        $date = $task['due_date'];
        if (!isset($events[$date])) {
            $events[$date] = ['letters' => [], 'tasks' => []];
        }
        $events[$date]['tasks'][] = $task;
    }
    
    jsonResponse([
        'month' => $month,
        'events' => $events
    ]);
}
