<?php
/**
 * Letters API Endpoint
 * Handles CRUD operations for letters
 */

require_once __DIR__ . '/../includes/auth.php';
ensureAuthenticated();

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
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
 * GET - Fetch letters with optional filters
 */
function handleGet() {
    global $pdo;
    
    $filters = [];
    $params = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $filters[] = "(l.reference_no LIKE ? OR l.subject LIKE ?)";
        $searchTerm = '%' . $_GET['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Stakeholder filter
    if (!empty($_GET['stakeholder']) && $_GET['stakeholder'] !== 'ALL') {
        $filters[] = "l.stakeholder = ?";
        $params[] = $_GET['stakeholder'];
    }
    
    // Priority filter
    if (!empty($_GET['priority']) && $_GET['priority'] !== 'ALL') {
        $filters[] = "l.priority = ?";
        $params[] = $_GET['priority'];
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
            SELECT l.*, u.name as uploaded_by_name, u.email as uploaded_by_email
            FROM letters l
            LEFT JOIN users u ON l.uploaded_by = u.id
            WHERE l.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $letter = $stmt->fetch();
        
        if (!$letter) {
            jsonError('Letter not found', 404);
        }
        
        // Fetch associated tasks
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as assigned_to_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.letter_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$_GET['id']]);
        $letter['tasks'] = $stmt->fetchAll();
        
        jsonResponse($letter);
    }
    
    // Fetch all letters with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM letters l $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    
    // Get paginated results
    $sql = "
        SELECT l.*, u.name as uploaded_by_name,
               (SELECT COUNT(*) FROM tasks WHERE letter_id = l.id) as task_count,
               (SELECT COUNT(*) FROM tasks WHERE letter_id = l.id AND status = 'COMPLETED') as completed_tasks
        FROM letters l
        LEFT JOIN users u ON l.uploaded_by = u.id
        $whereClause
        ORDER BY l.received_date DESC, l.created_at DESC
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
}

/**
 * POST - Create new letter
 */
function handlePost() {
    global $pdo, $user;
    
    // Handle file upload
    $pdfFilename = null;
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type
        $fileType = mime_content_type($_FILES['pdf']['tmp_name']);
        if ($fileType !== 'application/pdf') {
            jsonError('Only PDF files are allowed');
        }
        
        // Validate file size (max 10MB)
        if ($_FILES['pdf']['size'] > 10 * 1024 * 1024) {
            jsonError('File size must be less than 10MB');
        }
        
        // Generate unique filename
        $pdfFilename = generateULID() . '.pdf';
        $uploadPath = $uploadDir . $pdfFilename;
        
        if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $uploadPath)) {
            jsonError('Failed to upload file');
        }
    }
    
    // Validate required fields
    $required = ['reference_no', 'stakeholder', 'subject', 'received_date'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            jsonError("Field '$field' is required");
        }
    }
    
    // Check for duplicate reference number
    $stmt = $pdo->prepare("SELECT id FROM letters WHERE reference_no = ?");
    $stmt->execute([$_POST['reference_no']]);
    if ($stmt->fetch()) {
        jsonError('A letter with this reference number already exists');
    }
    
    // Insert letter
    try {
        $letterId = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO letters (id, reference_no, stakeholder, subject, pdf_filename, tencent_doc_url, received_date, priority, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $letterId,
            $_POST['reference_no'],
            $_POST['stakeholder'],
            $_POST['subject'],
            $pdfFilename,
            $_POST['tencent_doc_url'] ?? null,
            $_POST['received_date'],
            $_POST['priority'] ?? 'MEDIUM',
            $user['id']
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Letter created successfully',
            'letter_id' => $letterId
        ], 201);
        
    } catch (PDOException $e) {
        // Clean up uploaded file if database insert fails
        if ($pdfFilename) {
            @unlink($uploadDir . $pdfFilename);
        }
        jsonError('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * PUT/PATCH - Update letter
 */
function handleUpdate() {
    global $pdo;
    
    parse_str(file_get_contents('php://input'), $_PUT);
    
    if (empty($_PUT['id'])) {
        jsonError('Letter ID is required');
    }
    
    // Check if letter exists
    $stmt = $pdo->prepare("SELECT * FROM letters WHERE id = ?");
    $stmt->execute([$_PUT['id']]);
    $letter = $stmt->fetch();
    
    if (!$letter) {
        jsonError('Letter not found', 404);
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    $allowedFields = ['subject', 'tencent_doc_url', 'priority', 'stakeholder'];
    foreach ($allowedFields as $field) {
        if (isset($_PUT[$field])) {
            $updates[] = "$field = ?";
            $params[] = $_PUT[$field];
        }
    }
    
    if (empty($updates)) {
        jsonError('No fields to update');
    }
    
    $params[] = $_PUT['id'];
    
    $sql = "UPDATE letters SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'message' => 'Letter updated successfully'
    ]);
}

/**
 * DELETE - Delete letter (and associated tasks)
 */
function handleDelete() {
    global $pdo;
    
    parse_str(file_get_contents('php://input'), $_DELETE);
    
    if (empty($_DELETE['id'])) {
        jsonError('Letter ID is required');
    }
    
    // Get letter details
    $stmt = $pdo->prepare("SELECT * FROM letters WHERE id = ?");
    $stmt->execute([$_DELETE['id']]);
    $letter = $stmt->fetch();
    
    if (!$letter) {
        jsonError('Letter not found', 404);
    }
    
    // Delete PDF file if exists
    if ($letter['pdf_filename']) {
        $filePath = __DIR__ . '/../assets/uploads/' . $letter['pdf_filename'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
    
    // Delete letter (cascade will delete tasks)
    $stmt = $pdo->prepare("DELETE FROM letters WHERE id = ?");
    $stmt->execute([$_DELETE['id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Letter deleted successfully'
    ]);
}
