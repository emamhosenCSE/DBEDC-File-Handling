<?php
/**
 * Notifications Service
 * Handles in-app, email, and push notifications
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/push.php';

/**
 * Create a notification
 */
function createNotification($userId, $title, $message, $type = 'info', $entityType = null, $entityId = null, $actionUrl = null) {
    global $pdo;
    
    try {
        $id = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (id, user_id, title, message, type, entity_type, entity_id, action_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $userId,
            $title,
            $message,
            $type,
            $entityType,
            $entityId,
            $actionUrl
        ]);
        
        return $id;
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification through all channels
 */
function sendNotification($userId, $title, $message, $type = 'info', $entityType = null, $entityId = null, $actionUrl = null, $emailData = null) {
    global $pdo;
    
    // Create in-app notification
    $notificationId = createNotification($userId, $title, $message, $type, $entityType, $entityId, $actionUrl);
    
    if (!$notificationId) {
        return false;
    }
    
    // Get user preferences
    try {
        $stmt = $pdo->prepare("SELECT email, name, email_notifications, push_notifications FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return $notificationId;
        }
        
        // Send email notification
        if ($user['email_notifications'] && $emailData) {
            $emailId = sendNotificationEmail(
                $user['email'],
                $user['name'],
                $emailData['template'],
                $emailData['data']
            );
            
            if ($emailId) {
                $pdo->prepare("UPDATE notifications SET is_email_sent = TRUE, email_sent_at = NOW() WHERE id = ?")
                    ->execute([$notificationId]);
            }
        }
        
        // Send push notification
        if ($user['push_notifications']) {
            $pushData = [
                'type' => $type,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'url' => $actionUrl
            ];
            
            if (sendPushNotification($userId, $title, $message, $pushData)) {
                $pdo->prepare("UPDATE notifications SET is_push_sent = TRUE, push_sent_at = NOW() WHERE id = ?")
                    ->execute([$notificationId]);
            }
        }
        
        return $notificationId;
        
    } catch (PDOException $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return $notificationId;
    }
}

/**
 * Get user notifications
 */
function getUserNotifications($userId, $limit = 50, $offset = 0, $unreadOnly = false) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count
 */
function getUnreadCount($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Failed to get unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 */
function markNotificationRead($notificationId, $userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Failed to mark notification read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsRead($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to mark all notifications read: " . $e->getMessage());
        return 0;
    }
}

/**
 * Delete notification
 */
function deleteNotification($notificationId, $userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Failed to delete notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete old notifications
 */
function cleanupOldNotifications($daysOld = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = TRUE");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to cleanup notifications: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// NOTIFICATION TRIGGERS
// ============================================

/**
 * Notify user of task assignment
 */
function notifyTaskAssigned($taskId, $assignedToUserId, $assignedByUserId) {
    global $pdo;
    
    try {
        // Get task details
        $stmt = $pdo->prepare("
            SELECT t.*, l.reference_no, u.name as assigned_by_name
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u ON u.id = ?
            WHERE t.id = ?
        ");
        $stmt->execute([$assignedByUserId, $taskId]);
        $task = $stmt->fetch();
        
        if (!$task) return false;
        
        $title = "New Task Assigned";
        $message = "You have been assigned a new task: {$task['title']}";
        $actionUrl = "/dashboard.php#task/{$taskId}";
        
        $emailData = [
            'template' => 'task_assigned',
            'data' => [
                'task_title' => $task['title'],
                'letter_reference' => $task['reference_no'],
                'priority' => $task['priority'],
                'due_date' => $task['due_date'] ?? 'Not set',
                'assigned_by' => $task['assigned_by_name'] ?? 'System',
                'action_url' => $actionUrl
            ]
        ];
        
        return sendNotification(
            $assignedToUserId,
            $title,
            $message,
            'task_assigned',
            'task',
            $taskId,
            $actionUrl,
            $emailData
        );
        
    } catch (PDOException $e) {
        error_log("Failed to notify task assigned: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify user of task status update
 */
function notifyTaskUpdated($taskId, $oldStatus, $newStatus, $updatedByUserId, $comment = null) {
    global $pdo;
    
    try {
        // Get task details and related users
        $stmt = $pdo->prepare("
            SELECT t.*, l.reference_no, 
                   u1.name as assigned_to_name, u1.id as assigned_to_id,
                   u2.name as created_by_name, u2.id as created_by_id,
                   u3.name as updated_by_name
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            LEFT JOIN users u3 ON u3.id = ?
            WHERE t.id = ?
        ");
        $stmt->execute([$updatedByUserId, $taskId]);
        $task = $stmt->fetch();
        
        if (!$task) return false;
        
        $title = "Task Status Updated";
        $message = "Task '{$task['title']}' status changed from {$oldStatus} to {$newStatus}";
        $actionUrl = "/dashboard.php#task/{$taskId}";
        
        $emailData = [
            'template' => 'task_updated',
            'data' => [
                'task_title' => $task['title'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $task['updated_by_name'] ?? 'System',
                'comment_section' => $comment ? "<p style='margin: 5px 0; color: #6b7280;'><strong>Comment:</strong> {$comment}</p>" : '',
                'action_url' => $actionUrl
            ]
        ];
        
        // Notify assigned user (if not the one who updated)
        if ($task['assigned_to_id'] && $task['assigned_to_id'] !== $updatedByUserId) {
            sendNotification(
                $task['assigned_to_id'],
                $title,
                $message,
                'task_updated',
                'task',
                $taskId,
                $actionUrl,
                $emailData
            );
        }
        
        // Notify creator (if different from assigned and updater)
        if ($task['created_by_id'] && 
            $task['created_by_id'] !== $updatedByUserId && 
            $task['created_by_id'] !== $task['assigned_to_id']) {
            sendNotification(
                $task['created_by_id'],
                $title,
                $message,
                'task_updated',
                'task',
                $taskId,
                $actionUrl,
                $emailData
            );
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Failed to notify task updated: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify users of approaching deadline
 */
function notifyDeadlineApproaching($taskId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, l.reference_no, u.id as user_id, u.name as user_name
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ? AND t.status NOT IN ('COMPLETED', 'CANCELLED')
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        if (!$task || !$task['user_id']) return false;
        
        $title = "⚠️ Task Deadline Approaching";
        $message = "Task '{$task['title']}' is due on {$task['due_date']}";
        $actionUrl = "/dashboard.php#task/{$taskId}";
        
        $emailData = [
            'template' => 'deadline_reminder',
            'data' => [
                'task_title' => $task['title'],
                'due_date' => $task['due_date'],
                'status' => $task['status'],
                'action_url' => $actionUrl
            ]
        ];
        
        return sendNotification(
            $task['user_id'],
            $title,
            $message,
            'deadline',
            'task',
            $taskId,
            $actionUrl,
            $emailData
        );
        
    } catch (PDOException $e) {
        error_log("Failed to notify deadline: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify department of new letter
 */
function notifyLetterCreated($letterId, $departmentId = null) {
    global $pdo;
    
    try {
        // Get letter details
        $stmt = $pdo->prepare("
            SELECT l.*, s.name as stakeholder_name, u.name as uploaded_by_name
            FROM letters l
            LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
            LEFT JOIN users u ON l.uploaded_by = u.id
            WHERE l.id = ?
        ");
        $stmt->execute([$letterId]);
        $letter = $stmt->fetch();
        
        if (!$letter) return false;
        
        $title = "New Letter Added";
        $message = "New letter '{$letter['reference_no']}' has been added";
        $actionUrl = "/dashboard.php#letter/{$letterId}";
        
        $emailData = [
            'template' => 'letter_created',
            'data' => [
                'reference_no' => $letter['reference_no'],
                'subject' => $letter['subject'],
                'stakeholder' => $letter['stakeholder_name'] ?? 'Unknown',
                'priority' => $letter['priority'],
                'action_url' => $actionUrl
            ]
        ];
        
        // Get users to notify (department managers or admins)
        $targetDeptId = $departmentId ?? $letter['department_id'];
        
        if ($targetDeptId) {
            // Notify department manager
            $stmt = $pdo->prepare("SELECT manager_id FROM departments WHERE id = ?");
            $stmt->execute([$targetDeptId]);
            $dept = $stmt->fetch();
            
            if ($dept && $dept['manager_id'] && $dept['manager_id'] !== $letter['uploaded_by']) {
                sendNotification(
                    $dept['manager_id'],
                    $title,
                    $message,
                    'letter_created',
                    'letter',
                    $letterId,
                    $actionUrl,
                    $emailData
                );
            }
        }
        
        // Notify admins
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'ADMIN' AND is_active = TRUE AND id != ?");
        $stmt->execute([$letter['uploaded_by']]);
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $adminId) {
            sendNotification(
                $adminId,
                $title,
                $message,
                'letter_created',
                'letter',
                $letterId,
                $actionUrl,
                null // Don't send email to all admins
            );
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Failed to notify letter created: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and send deadline reminders (call from cron)
 */
function processDeadlineReminders() {
    global $pdo;
    
    try {
        // Get tasks due in 24 hours that haven't been reminded
        $stmt = $pdo->query("
            SELECT t.id 
            FROM tasks t
            LEFT JOIN notifications n ON n.entity_type = 'task' AND n.entity_id = t.id AND n.type = 'deadline' AND DATE(n.created_at) = CURDATE()
            WHERE t.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND t.status NOT IN ('COMPLETED', 'CANCELLED')
            AND n.id IS NULL
        ");
        
        $tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $sent = 0;
        
        foreach ($tasks as $taskId) {
            if (notifyDeadlineApproaching($taskId)) {
                $sent++;
            }
        }
        
        return $sent;
        
    } catch (PDOException $e) {
        error_log("Failed to process deadline reminders: " . $e->getMessage());
        return 0;
    }
}

/**
 * Send bulk notification to users
 */
function sendBulkNotification($userIds, $title, $message, $type = 'info', $entityType = null, $entityId = null, $actionUrl = null) {
    $sent = 0;
    foreach ($userIds as $userId) {
        if (createNotification($userId, $title, $message, $type, $entityType, $entityId, $actionUrl)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Send notification to department
 */
function notifyDepartment($departmentId, $title, $message, $type = 'info', $entityType = null, $entityId = null, $actionUrl = null, $excludeUserId = null) {
    global $pdo;
    
    try {
        $sql = "SELECT id FROM users WHERE department_id = ? AND is_active = TRUE";
        $params = [$departmentId];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return sendBulkNotification($userIds, $title, $message, $type, $entityType, $entityId, $actionUrl);
        
    } catch (PDOException $e) {
        error_log("Failed to notify department: " . $e->getMessage());
        return 0;
    }
}
