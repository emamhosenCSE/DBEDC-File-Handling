<?php
/**
 * Workflow Automation API
 * Handles automated task management, escalation, and workflow controls
 */

require_once __DIR__ . '/../includes/api-bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workflow.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'automation-status':
                    // Get current automation status and recent runs
                    if (!hasPermission($user['id'], 'manage_system')) {
                        throw new Exception('Insufficient permissions');
                    }

                    $stmt = $pdo->query("
                        SELECT * FROM activities
                        WHERE type = 'automation_run'
                        ORDER BY created_at DESC
                        LIMIT 10
                    ");
                    $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'last_run' => $runs[0] ?? null,
                            'recent_runs' => $runs,
                            'next_scheduled' => date('Y-m-d H:i:s', strtotime('+1 hour'))
                        ]
                    ]);
                    break;

                case 'overdue-tasks':
                    // Get overdue tasks for current user or all if admin
                    $query = "
                        SELECT t.*, l.reference_no, l.subject,
                               u.name as assigned_to_name,
                               DATEDIFF(CURDATE(), t.due_date) as days_overdue
                        FROM tasks t
                        JOIN letters l ON t.letter_id = l.id
                        LEFT JOIN users u ON t.assigned_to = u.id
                        WHERE t.due_date < CURDATE()
                        AND t.status IN ('PENDING', 'IN_PROGRESS')
                        AND t.due_date IS NOT NULL
                    ";

                    $params = [];
                    if (!hasPermission($user['id'], 'view_all_tasks')) {
                        $query .= " AND t.assigned_to = ?";
                        $params[] = $user['id'];
                    }

                    $query .= " ORDER BY t.due_date ASC";

                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);

                    echo json_encode([
                        'success' => true,
                        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'upcoming-deadlines':
                    // Get tasks due soon
                    $query = "
                        SELECT t.*, l.reference_no, l.subject,
                               u.name as assigned_to_name,
                               DATEDIFF(t.due_date, CURDATE()) as days_until_due
                        FROM tasks t
                        JOIN letters l ON t.letter_id = l.id
                        LEFT JOIN users u ON t.assigned_to = u.id
                        WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        AND t.status IN ('PENDING', 'IN_PROGRESS')
                        AND t.due_date IS NOT NULL
                    ";

                    $params = [];
                    if (!hasPermission($user['id'], 'view_all_tasks')) {
                        $query .= " AND t.assigned_to = ?";
                        $params[] = $user['id'];
                    }

                    $query .= " ORDER BY t.due_date ASC";

                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);

                    echo json_encode([
                        'success' => true,
                        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
            break;

        case 'POST':
            switch ($action) {
                case 'run-automation':
                    // Manually trigger automation (admin only)
                    if (!hasPermission($user['id'], 'manage_system')) {
                        throw new Exception('Insufficient permissions');
                    }

                    $results = processAutomatedWorkflows();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Automation completed successfully',
                        'data' => $results
                    ]);
                    break;

                case 'escalate-task':
                    // Manually escalate a task
                    $input = json_decode(file_get_contents('php://input'), true);
                    $taskId = $input['task_id'] ?? '';

                    if (!$taskId) {
                        throw new Exception('Task ID required');
                    }

                    // Verify user can access this task
                    $stmt = $pdo->prepare("
                        SELECT t.*, l.department_id
                        FROM tasks t
                        JOIN letters l ON t.letter_id = l.id
                        WHERE t.id = ?
                    ");
                    $stmt->execute([$taskId]);
                    $task = $stmt->fetch();

                    if (!$task) {
                        throw new Exception('Task not found');
                    }

                    if (!hasPermission($user['id'], 'view_all_tasks') &&
                        $task['assigned_to'] !== $user['id']) {
                        throw new Exception('Access denied');
                    }

                    // Find department manager
                    $stmt = $pdo->prepare("
                        SELECT u.id, u.name
                        FROM users u
                        WHERE u.id = (
                            SELECT manager_id FROM departments WHERE id = ?
                        )
                    ");
                    $stmt->execute([$task['department_id']]);
                    $manager = $stmt->fetch();

                    if (!$manager) {
                        throw new Exception('No department manager found');
                    }

                    // Create escalation notification
                    sendNotification(
                        $manager['id'],
                        'Manual Task Escalation',
                        "Task has been manually escalated by {$user['name']}",
                        'warning',
                        'task',
                        $taskId,
                        "/dashboard.php#all-tasks"
                    );

                    // Log the escalation
                    logActivity(
                        $user['id'],
                        'task_manually_escalated',
                        'task',
                        $taskId,
                        "Task manually escalated to {$manager['name']}",
                        ['escalated_to' => $manager['id']]
                    );

                    echo json_encode([
                        'success' => true,
                        'message' => 'Task escalated successfully'
                    ]);
                    break;

                case 'auto-assign':
                    // Auto-assign a task
                    $input = json_decode(file_get_contents('php://input'), true);
                    $letterId = $input['letter_id'] ?? '';
                    $taskData = $input['task_data'] ?? [];

                    if (!$letterId) {
                        throw new Exception('Letter ID required');
                    }

                    // Verify user can manage tasks for this letter
                    $stmt = $pdo->prepare("
                        SELECT l.* FROM letters l
                        LEFT JOIN departments d ON l.department_id = d.id
                        WHERE l.id = ?
                        AND (l.created_by = ? OR d.manager_id = ? OR ? IN (
                            SELECT user_id FROM user_permissions WHERE permission = 'manage_tasks'
                        ))
                    ");
                    $stmt->execute([$letterId, $user['id'], $user['id'], $user['id']]);
                    $letter = $stmt->fetch();

                    if (!$letter) {
                        throw new Exception('Access denied or letter not found');
                    }

                    $assignedTo = autoAssignTask($letterId, $taskData);

                    if ($assignedTo) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Task auto-assigned successfully',
                            'data' => ['assigned_to' => $assignedTo]
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Could not auto-assign task - no suitable users found'
                        ]);
                    }
                    break;

                default:
                    throw new Exception('Invalid action');
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>