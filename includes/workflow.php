<?php
/**
 * Workflow Automation Service
 * Handles task escalation, deadline reminders, and automated notifications
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

/**
 * Process overdue tasks and send reminders
 */
function processOverdueTasks() {
    global $pdo;

    try {
        // Find tasks that are overdue and not completed
        $stmt = $pdo->prepare("
            SELECT t.*, l.reference_no, l.subject,
                   u.name as assigned_to_name, u.email as assigned_to_email,
                   DATEDIFF(CURDATE(), t.due_date) as days_overdue
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.due_date < CURDATE()
            AND t.status IN ('PENDING', 'IN_PROGRESS')
            AND t.due_date IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM notifications n
                WHERE n.entity_type = 'task'
                AND n.entity_id = t.id
                AND n.type = 'deadline'
                AND DATE(n.created_at) = CURDATE()
            )
        ");

        $stmt->execute();
        $overdueTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($overdueTasks as $task) {
            // Create deadline notification
            $message = "Task '{$task['title']}' for letter {$task['reference_no']} is {$task['days_overdue']} day(s) overdue.";

            if ($task['assigned_to']) {
                sendNotification(
                    $task['assigned_to'],
                    'Task Overdue',
                    $message,
                    'deadline',
                    'task',
                    $task['id'],
                    "/dashboard.php#my-tasks"
                );
            }

            // Escalate to manager if more than configured days overdue
            $escalationDays = getSystemConfig('workflow_escalation_days', 3);
            if ($task['days_overdue'] >= $escalationDays) {
                escalateTaskToManager($task);
            }

            // Log the escalation
            logActivity(
                $task['assigned_to'] ?: null,
                'task_escalated',
                'task',
                $task['id'],
                "Task escalated due to {$task['days_overdue']} days overdue",
                ['days_overdue' => $task['days_overdue']]
            );
        }

        return count($overdueTasks);
    } catch (Exception $e) {
        error_log("Error processing overdue tasks: " . $e->getMessage());
        return 0;
    }
}

/**
 * Escalate task to department manager
 */
function escalateTaskToManager($task) {
    global $pdo;

    try {
        // Find department manager
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN departments d ON u.id = d.manager_id
            JOIN users task_user ON task_user.department_id = d.id
            WHERE task_user.id = ?
        ");

        $stmt->execute([$task['assigned_to']]);
        $manager = $stmt->fetch();

        if ($manager) {
            $message = "Task '{$task['title']}' assigned to {$task['assigned_to_name']} is {$task['days_overdue']} days overdue and requires attention.";

            sendNotification(
                $manager['id'],
                'Task Escalation Required',
                $message,
                'warning',
                'task',
                $task['id'],
                "/dashboard.php#all-tasks"
            );
        }
    } catch (Exception $e) {
        error_log("Error escalating task to manager: " . $e->getMessage());
    }
}

/**
 * Send upcoming deadline reminders
 */
function sendDeadlineReminders() {
    global $pdo;

    try {
        // Find tasks due in the next configured days
        $reminderDays = getSystemConfig('workflow_reminder_days', 2);
        $stmt = $pdo->prepare("
            SELECT t.*, l.reference_no, l.subject,
                   u.name as assigned_to_name, u.email as assigned_to_email,
                   DATEDIFF(t.due_date, CURDATE()) as days_until_due
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$reminderDays} DAY)
            AND t.status IN ('PENDING', 'IN_PROGRESS')
            AND t.due_date IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM notifications n
                WHERE n.entity_type = 'task'
                AND n.entity_id = t.id
                AND n.type = 'deadline'
                AND DATE(n.created_at) = CURDATE()
            )
        ");

        $stmt->execute();
        $upcomingTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($upcomingTasks as $task) {
            $urgency = $task['days_until_due'] === 0 ? 'due today' : "due in {$task['days_until_due']} day(s)";
            $message = "Task '{$task['title']}' for letter {$task['reference_no']} is {$urgency}.";

            if ($task['assigned_to']) {
                sendNotification(
                    $task['assigned_to'],
                    'Upcoming Deadline',
                    $message,
                    $task['days_until_due'] === 0 ? 'error' : 'warning',
                    'task',
                    $task['id'],
                    "/dashboard.php#my-tasks"
                );
            }
        }

        return count($upcomingTasks);
    } catch (Exception $e) {
        error_log("Error sending deadline reminders: " . $e->getMessage());
        return 0;
    }
}

/**
 * Auto-assign tasks based on department and workload
 */
function autoAssignTask($letterId, $taskData) {
    global $pdo;

    try {
        // Get letter department
        $stmt = $pdo->prepare("SELECT department_id FROM letters WHERE id = ?");
        $stmt->execute([$letterId]);
        $letter = $stmt->fetch();

        if (!$letter['department_id']) {
            return null; // No department assigned
        }

        // Find user with least workload in the department
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, COUNT(t.id) as task_count
            FROM users u
            LEFT JOIN tasks t ON t.assigned_to = u.id AND t.status IN ('PENDING', 'IN_PROGRESS')
            WHERE u.department_id = ?
            AND u.is_active = TRUE
            AND u.role IN ('MEMBER', 'MANAGER')
            GROUP BY u.id, u.name
            ORDER BY task_count ASC, u.name ASC
            LIMIT 1
        ");

        $stmt->execute([$letter['department_id']]);
        $user = $stmt->fetch();

        if ($user) {
            // Log the auto-assignment
            logActivity(
                null,
                'task_auto_assigned',
                'task',
                $letterId,
                "Task auto-assigned to {$user['name']} (least workload in department)",
                ['assigned_to' => $user['id'], 'auto_assigned' => true]
            );

            return $user['id'];
        }

        return null;
    } catch (Exception $e) {
        error_log("Error in auto-assignment: " . $e->getMessage());
        return null;
    }
}

/**
 * Process automated workflows (to be called by cron job)
 */
function processAutomatedWorkflows() {
    $results = [
        'overdue_processed' => processOverdueTasks(),
        'reminders_sent' => sendDeadlineReminders(),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Log the automation run
    logActivity(
        null,
        'automation_run',
        'system',
        null,
        'Automated workflow processing completed',
        $results
    );

    return $results;
}

/**
 * Create recurring tasks for periodic reviews
 */
function createRecurringTasks() {
    global $pdo;

    try {
        // Find letters that need periodic review (every 6 months)
        $stmt = $pdo->query("
            SELECT l.*,
                   MAX(t.created_at) as last_review,
                   DATEDIFF(CURDATE(), MAX(t.created_at)) as days_since_review
            FROM letters l
            LEFT JOIN tasks t ON t.letter_id = l.id AND t.title LIKE '%review%'
            WHERE l.status = 'ACTIVE'
            AND l.created_at < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY l.id
            HAVING days_since_review > 180 OR days_since_review IS NULL
        ");

        $letters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($letters as $letter) {
            // Create review task
            $taskId = generateULID();
            $stmt = $pdo->prepare("
                INSERT INTO tasks (id, letter_id, title, description, priority, created_at)
                VALUES (?, ?, ?, ?, 'MEDIUM', NOW())
            ");

            $stmt->execute([
                $taskId,
                $letter['id'],
                'Periodic Review Required',
                "This letter requires periodic review as it was created over 6 months ago.",
            ]);

            // Log the creation
            logActivity(
                null,
                'recurring_task_created',
                'task',
                $taskId,
                'Recurring review task created for letter',
                ['letter_id' => $letter['id']]
            );
        }

        return count($letters);
    } catch (Exception $e) {
        error_log("Error creating recurring tasks: " . $e->getMessage());
        return 0;
    }
}
?>