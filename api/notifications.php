<?php
/**
 * Notifications API Endpoint
 * Handles in-app notification operations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
ensureAuthenticated();
ensureCSRFValid();

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
 * GET - Fetch notifications
 */
function handleGet() {
    global $pdo, $user;
    
    // Get unread count
    if (isset($_GET['count'])) {
        $count = getUnreadCount($user['id']);
        jsonResponse(['unread_count' => $count]);
        return;
    }
    
    // Get single notification
    if (isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user['id']]);
            $notification = $stmt->fetch();
            
            if (!$notification) {
                jsonError('Notification not found', 404);
            }
            
            jsonResponse($notification);
        } catch (PDOException $e) {
            jsonError('Failed to fetch notification', 500);
        }
        return;
    }
    
    // Get notifications list
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $unreadOnly = isset($_GET['unread']);
    $type = $_GET['type'] ?? null;
    
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user['id']];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        // Get total count
        $countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // Get notifications
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll();
        
        // Get unread count
        $unreadCount = getUnreadCount($user['id']);
        
        jsonResponse([
            'notifications' => $notifications,
            'total' => $total,
            'unread_count' => $unreadCount,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (PDOException $e) {
        error_log("Notifications GET error: " . $e->getMessage());
        jsonError('Failed to fetch notifications', 500);
    }
}

/**
 * PATCH/PUT - Update notification (mark as read)
 */
function handleUpdate() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Mark all as read
    if (isset($_GET['read_all']) || (isset($input['action']) && $input['action'] === 'read_all')) {
        $count = markAllNotificationsRead($user['id']);
        jsonResponse([
            'success' => true,
            'marked_read' => $count,
            'message' => "Marked {$count} notifications as read"
        ]);
        return;
    }
    
    // Mark single notification as read
    if (isset($_GET['read'])) {
        $notificationId = $_GET['read'];
    } elseif (isset($input['id'])) {
        $notificationId = $input['id'];
    } else {
        jsonError('Notification ID is required', 400);
    }
    
    if (markNotificationRead($notificationId, $user['id'])) {
        jsonResponse([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        jsonError('Failed to mark notification as read', 500);
    }
}

/**
 * DELETE - Delete notification
 */
function handleDelete() {
    global $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Delete all read notifications
    if (isset($input['action']) && $input['action'] === 'clear_read') {
        global $pdo;
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
            $stmt->execute([$user['id']]);
            $count = $stmt->rowCount();
            
            jsonResponse([
                'success' => true,
                'deleted' => $count,
                'message' => "Deleted {$count} read notifications"
            ]);
        } catch (PDOException $e) {
            jsonError('Failed to clear notifications', 500);
        }
        return;
    }
    
    // Delete single notification
    if (empty($input['id'])) {
        jsonError('Notification ID is required', 400);
    }
    
    if (deleteNotification($input['id'], $user['id'])) {
        jsonResponse([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    } else {
        jsonError('Failed to delete notification', 500);
    }
}
