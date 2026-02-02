<?php
/**
 * Stakeholders API Endpoint
 * Handles stakeholder CRUD operations
 */

require_once __DIR__ . '/../includes/api-bootstrap.php';

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
 * GET - Fetch stakeholders
 */
function handleGet() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    $activeOnly = !isset($_GET['all']);
    
    try {
        if ($id) {
            // Get single stakeholder
            $stmt = $pdo->prepare("SELECT * FROM stakeholders WHERE id = ?");
            $stmt->execute([$id]);
            $stakeholder = $stmt->fetch();
            
            if (!$stakeholder) {
                jsonError('Stakeholder not found', 404);
            }
            
            // Get letter count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM letters WHERE stakeholder_id = ? AND status != 'DELETED'");
            $stmt->execute([$id]);
            $stakeholder['letter_count'] = (int)$stmt->fetchColumn();
            
            jsonResponse($stakeholder);
        }
        
        // Get all stakeholders
        $sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM letters l WHERE l.stakeholder_id = s.id AND l.status != 'DELETED') as letter_count
                FROM stakeholders s";
        
        if ($activeOnly) {
            $sql .= " WHERE s.is_active = TRUE";
        }
        
        $sql .= " ORDER BY s.display_order ASC, s.name ASC";
        
        $stmt = $pdo->query($sql);
        $stakeholders = $stmt->fetchAll();
        
        jsonResponse([
            'stakeholders' => $stakeholders,
            'total' => count($stakeholders)
        ]);
        
    } catch (PDOException $e) {
        error_log("Stakeholders GET error: " . $e->getMessage());
        jsonError('Failed to fetch stakeholders', 500);
    }
}

/**
 * POST - Create stakeholder
 */
function handlePost() {
    global $pdo, $user;
    
    // Only admins can create stakeholders
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can create stakeholders', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['name']) || empty($input['code'])) {
        jsonError('Name and code are required', 400);
    }
    
    // Validate code format (uppercase, no spaces)
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input['code']));
    if (strlen($code) < 2 || strlen($code) > 20) {
        jsonError('Code must be 2-20 alphanumeric characters', 400);
    }
    
    // Validate color format
    $color = $input['color'] ?? '#6B7280';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $color = '#6B7280';
    }
    
    try {
        $id = generateULID();
        
        // Get next display order
        $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM stakeholders");
        $displayOrder = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            INSERT INTO stakeholders (id, name, code, color, icon, description, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            trim($input['name']),
            $code,
            $color,
            $input['icon'] ?? null,
            $input['description'] ?? null,
            $input['display_order'] ?? $displayOrder
        ]);
        
        // Log activity
        logActivity(
            $user['id'],
            'stakeholder_created',
            'stakeholder',
            $id,
            "Stakeholder '{$input['name']}' created",
            ['code' => $code]
        );
        
        jsonResponse([
            'success' => true,
            'id' => $id,
            'message' => 'Stakeholder created successfully'
        ]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonError('Stakeholder name or code already exists', 409);
        }
        error_log("Stakeholders POST error: " . $e->getMessage());
        jsonError('Failed to create stakeholder', 500);
    }
}

/**
 * PATCH/PUT - Update stakeholder
 */
function handleUpdate() {
    global $pdo, $user;
    
    // Only admins can update stakeholders
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can update stakeholders', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('Stakeholder ID is required', 400);
    }
    
    try {
        // Check if stakeholder exists
        $stmt = $pdo->prepare("SELECT * FROM stakeholders WHERE id = ?");
        $stmt->execute([$input['id']]);
        $stakeholder = $stmt->fetch();
        
        if (!$stakeholder) {
            jsonError('Stakeholder not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if (isset($input['name'])) {
            $updates[] = "name = ?";
            $params[] = trim($input['name']);
        }
        
        if (isset($input['code'])) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input['code']));
            $updates[] = "code = ?";
            $params[] = $code;
        }
        
        if (isset($input['color'])) {
            $color = $input['color'];
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#6B7280';
            }
            $updates[] = "color = ?";
            $params[] = $color;
        }
        
        if (isset($input['icon'])) {
            $updates[] = "icon = ?";
            $params[] = $input['icon'];
        }
        
        if (isset($input['description'])) {
            $updates[] = "description = ?";
            $params[] = $input['description'];
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
        
        $params[] = $input['id'];
        $sql = "UPDATE stakeholders SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log activity
        logActivity(
            $user['id'],
            'stakeholder_updated',
            'stakeholder',
            $input['id'],
            "Stakeholder '{$stakeholder['name']}' updated",
            ['changes' => array_keys($input)]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Stakeholder updated successfully'
        ]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonError('Stakeholder name or code already exists', 409);
        }
        error_log("Stakeholders UPDATE error: " . $e->getMessage());
        jsonError('Failed to update stakeholder', 500);
    }
}

/**
 * DELETE - Delete stakeholder
 */
function handleDelete() {
    global $pdo, $user;
    
    // Only admins can delete stakeholders
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can delete stakeholders', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonError('Stakeholder ID is required', 400);
    }
    
    try {
        // Check if stakeholder exists
        $stmt = $pdo->prepare("SELECT * FROM stakeholders WHERE id = ?");
        $stmt->execute([$input['id']]);
        $stakeholder = $stmt->fetch();
        
        if (!$stakeholder) {
            jsonError('Stakeholder not found', 404);
        }
        
        // Check if stakeholder has letters
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM letters WHERE stakeholder_id = ?");
        $stmt->execute([$input['id']]);
        $letterCount = (int)$stmt->fetchColumn();
        
        if ($letterCount > 0) {
            // Soft delete - just deactivate
            $stmt = $pdo->prepare("UPDATE stakeholders SET is_active = FALSE, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$input['id']]);
            
            jsonResponse([
                'success' => true,
                'message' => "Stakeholder deactivated (has {$letterCount} associated letters)"
            ]);
        } else {
            // Hard delete
            $stmt = $pdo->prepare("DELETE FROM stakeholders WHERE id = ?");
            $stmt->execute([$input['id']]);
            
            // Log activity
            logActivity(
                $user['id'],
                'stakeholder_deleted',
                'stakeholder',
                $input['id'],
                "Stakeholder '{$stakeholder['name']}' deleted",
                null
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Stakeholder deleted successfully'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Stakeholders DELETE error: " . $e->getMessage());
        jsonError('Failed to delete stakeholder', 500);
    }
}

/**
 * Reorder stakeholders
 */
function handleReorder() {
    global $pdo, $user;
    
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can reorder stakeholders', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['order']) || !is_array($input['order'])) {
        jsonError('Order array is required', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        foreach ($input['order'] as $index => $id) {
            $stmt = $pdo->prepare("UPDATE stakeholders SET display_order = ? WHERE id = ?");
            $stmt->execute([$index, $id]);
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Stakeholders reordered successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Stakeholders REORDER error: " . $e->getMessage());
        jsonError('Failed to reorder stakeholders', 500);
    }
}
