<?php
/**
 * Analytics API Endpoint
 * Provides statistical data for dashboard
 */

require_once __DIR__ . '/../includes/auth.php';
ensureAuthenticated();
ensureCSRFValid();

$type = $_GET['type'] ?? 'overview';

switch ($type) {
    case 'overview':
        getOverview();
        break;
    case 'status_distribution':
        getStatusDistribution();
        break;
    case 'stakeholder_distribution':
        getStakeholderDistribution();
        break;
    case 'priority_distribution':
        getPriorityDistribution();
        break;
    case 'completion_rate':
        getCompletionRate();
        break;
    case 'recent_activity':
        getRecentActivity();
        break;
    default:
        jsonError('Invalid analytics type');
}

/**
 * Overview stats
 */
function getOverview() {
    global $pdo, $user;
    
    $view = $_GET['view'] ?? 'my'; // 'my' or 'all'
    
    // Tasks stats
    if ($view === 'my') {
        $taskQuery = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed
            FROM tasks
            WHERE assigned_to = ? OR assigned_group = ?
        ";
        $stmt = $pdo->prepare($taskQuery);
        $stmt->execute([$user['id'], $user['department']]);
    } else {
        $taskQuery = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed
            FROM tasks
        ";
        $stmt = $pdo->query($taskQuery);
    }
    
    $taskStats = $stmt->fetch();
    
    // Letters stats
    $letterQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN priority = 'URGENT' THEN 1 ELSE 0 END) as urgent,
            SUM(CASE WHEN priority = 'HIGH' THEN 1 ELSE 0 END) as high
        FROM letters
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $letterStats = $pdo->query($letterQuery)->fetch();
    
    // Average completion time (in days)
    $completionQuery = "
        SELECT AVG(DATEDIFF(completed_at, created_at)) as avg_days
        FROM tasks
        WHERE status = 'COMPLETED' 
        AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $avgCompletion = $pdo->query($completionQuery)->fetchColumn();
    
    jsonResponse([
        'tasks' => $taskStats,
        'letters' => $letterStats,
        'avg_completion_days' => round($avgCompletion, 1)
    ]);
}

/**
 * Task status distribution
 */
function getStatusDistribution() {
    global $pdo, $user;
    
    $view = $_GET['view'] ?? 'my';
    
    if ($view === 'my') {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM tasks
            WHERE assigned_to = ? OR assigned_group = ?
            GROUP BY status
        ");
        $stmt->execute([$user['id'], $user['department']]);
    } else {
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM tasks
            GROUP BY status
        ");
    }
    
    $data = $stmt->fetchAll();
    
    jsonResponse($data);
}

/**
 * Stakeholder distribution
 */
function getStakeholderDistribution() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT l.stakeholder, COUNT(DISTINCT l.id) as letter_count, COUNT(t.id) as task_count
        FROM letters l
        LEFT JOIN tasks t ON l.id = t.letter_id
        WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY l.stakeholder
        ORDER BY task_count DESC
    ");
    
    jsonResponse($stmt->fetchAll());
}

/**
 * Priority distribution
 */
function getPriorityDistribution() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT l.priority, COUNT(*) as count
        FROM letters l
        WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY l.priority
        ORDER BY 
            CASE l.priority
                WHEN 'URGENT' THEN 1
                WHEN 'HIGH' THEN 2
                WHEN 'MEDIUM' THEN 3
                WHEN 'LOW' THEN 4
            END
    ");
    
    jsonResponse($stmt->fetchAll());
}

/**
 * Completion rate over time (last 30 days)
 */
function getCompletionRate() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            DATE(completed_at) as date,
            COUNT(*) as completed
        FROM tasks
        WHERE status = 'COMPLETED' 
        AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(completed_at)
        ORDER BY date ASC
    ");
    
    jsonResponse($stmt->fetchAll());
}

/**
 * Recent activity feed
 */
function getRecentActivity() {
    global $pdo;
    
    $limit = $_GET['limit'] ?? 10;
    
    $stmt = $pdo->prepare("
        SELECT 
            tu.id,
            tu.old_status,
            tu.new_status,
            tu.comment,
            tu.created_at,
            t.title as task_title,
            l.reference_no,
            u.name as user_name,
            u.avatar_url
        FROM task_updates tu
        JOIN tasks t ON tu.task_id = t.id
        JOIN letters l ON t.letter_id = l.id
        LEFT JOIN users u ON tu.user_id = u.id
        ORDER BY tu.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    
    jsonResponse($stmt->fetchAll());
}
