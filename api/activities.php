<?php
/**
 * Activities API Endpoint
 * Handles activity timeline operations
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
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch activities
 */
function handleGet() {
    global $pdo, $user;
    
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $entityType = $_GET['entity_type'] ?? null;
    $entityId = $_GET['entity_id'] ?? null;
    $activityType = $_GET['activity_type'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    try {
        $sql = "SELECT a.*, u.name as user_name, u.avatar_url as user_avatar
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        $params = [];
        
        // Apply permission filters
        $scope = getUserScope();
        if ($scope === 'department') {
            // Manager can see department activities
            $sql .= " AND (a.user_id IN (SELECT id FROM users WHERE department_id = ?) OR a.user_id = ?)";
            $params[] = $user['department_id'];
            $params[] = $user['id'];
        } elseif ($scope === 'own') {
            // Member/Viewer can only see own activities
            $sql .= " AND a.user_id = ?";
            $params[] = $user['id'];
        }
        // Admin sees all
        
        // Apply filters
        if ($entityType) {
            $sql .= " AND a.entity_type = ?";
            $params[] = $entityType;
        }
        
        if ($entityId) {
            $sql .= " AND a.entity_id = ?";
            $params[] = $entityId;
        }
        
        if ($activityType) {
            $sql .= " AND a.activity_type = ?";
            $params[] = $activityType;
        }
        
        if ($userId && $user['role'] === 'ADMIN') {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        
        if ($dateFrom) {
            $sql .= " AND DATE(a.created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND DATE(a.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        // Get total count
        $countSql = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) FROM', $sql);
        $countSql = preg_replace('/LEFT JOIN users.*ON a\.user_id = u\.id/', '', $countSql);
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // Get activities
        $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $activities = $stmt->fetchAll();
        
        // Parse metadata JSON
        foreach ($activities as &$activity) {
            if ($activity['metadata']) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            }
        }
        
        jsonResponse([
            'activities' => $activities,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (PDOException $e) {
        error_log("Activities GET error: " . $e->getMessage());
        jsonError('Failed to fetch activities', 500);
    }
}

/**
 * Get activity types for filtering
 */
function getActivityTypes() {
    return [
        'letter_created' => 'Letter Created',
        'letter_updated' => 'Letter Updated',
        'letter_deleted' => 'Letter Deleted',
        'task_created' => 'Task Created',
        'task_assigned' => 'Task Assigned',
        'task_status_changed' => 'Task Status Changed',
        'task_completed' => 'Task Completed',
        'task_deleted' => 'Task Deleted',
        'user_login' => 'User Login',
        'user_updated' => 'User Updated',
        'department_created' => 'Department Created',
        'department_updated' => 'Department Updated',
        'settings_updated' => 'Settings Updated',
        'stakeholder_created' => 'Stakeholder Created',
        'stakeholder_updated' => 'Stakeholder Updated',
        'stakeholder_deleted' => 'Stakeholder Deleted'
    ];
}

/**
 * Get recent activities for dashboard
 */
function getRecentActivities($limit = 10, $userId = null) {
    global $pdo;
    
    try {
        $sql = "SELECT a.*, u.name as user_name, u.avatar_url as user_avatar
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id";
        $params = [];
        
        if ($userId) {
            $sql .= " WHERE a.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY a.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Failed to get recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get activity summary for dashboard
 */
function getActivitySummary($days = 7) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                activity_type,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM activities
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY activity_type, DATE(created_at)
            ORDER BY date DESC, count DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Failed to get activity summary: " . $e->getMessage());
        return [];
    }
}
