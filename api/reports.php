<?php
/**
 * Reports API Endpoint
 * Handles report generation and export
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
ensureAuthenticated();
ensureCSRFValid();

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
 * GET - Generate reports
 */
function handleGet() {
    global $user;
    
    $reportType = $_GET['type'] ?? 'overview';
    $export = $_GET['export'] ?? null;
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    // Check permissions
    $scope = getUserScope();
    
    switch ($reportType) {
        case 'overview':
            $data = generateOverviewReport($dateFrom, $dateTo, $scope);
            break;
        case 'letters':
            $data = generateLettersReport($dateFrom, $dateTo, $scope);
            break;
        case 'tasks':
            $data = generateTasksReport($dateFrom, $dateTo, $scope);
            break;
        case 'users':
            if ($scope !== 'all') {
                jsonError('Insufficient permissions for user reports', 403);
            }
            $data = generateUsersReport($dateFrom, $dateTo);
            break;
        case 'departments':
            $data = generateDepartmentsReport($dateFrom, $dateTo, $scope);
            break;
        case 'stakeholders':
            $data = generateStakeholdersReport($dateFrom, $dateTo, $scope);
            break;
        default:
            jsonError('Unknown report type', 400);
    }
    
    // Handle export
    if ($export) {
        switch ($export) {
            case 'csv':
                exportCSV($data, $reportType);
                break;
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="report_' . $reportType . '_' . date('Y-m-d') . '.json"');
                echo json_encode($data, JSON_PRETTY_PRINT);
                exit;
            default:
                jsonError('Unknown export format', 400);
        }
    }
    
    jsonResponse($data);
}

/**
 * Generate overview report
 */
function generateOverviewReport($dateFrom, $dateTo, $scope) {
    global $pdo, $user;
    
    $data = [
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [],
        'trends' => [],
        'top_performers' => []
    ];
    
    try {
        // Build scope filter
        $scopeFilter = '';
        $scopeParams = [];
        if ($scope === 'department') {
            $scopeFilter = " AND (l.department_id = ? OR t.assigned_department = ?)";
            $scopeParams = [$user['department_id'], $user['department_id']];
        } elseif ($scope === 'own') {
            $scopeFilter = " AND (l.uploaded_by = ? OR t.assigned_to = ?)";
            $scopeParams = [$user['id'], $user['id']];
        }
        
        // Summary stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_letters,
                COUNT(DISTINCT t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN t.due_date < CURDATE() AND t.status NOT IN ('COMPLETED', 'CANCELLED') THEN 1 ELSE 0 END) as overdue_tasks
            FROM letters l
            LEFT JOIN tasks t ON l.id = t.letter_id
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            AND l.status != 'DELETED'
            $scopeFilter
        ");
        $params = array_merge([$dateFrom, $dateTo], $scopeParams);
        $stmt->execute($params);
        $data['summary'] = $stmt->fetch();
        
        // Calculate completion rate
        $total = $data['summary']['total_tasks'] ?? 0;
        $completed = $data['summary']['completed_tasks'] ?? 0;
        $data['summary']['completion_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        
        // Daily trends
        $stmt = $pdo->prepare("
            SELECT 
                DATE(l.created_at) as date,
                COUNT(DISTINCT l.id) as letters,
                COUNT(DISTINCT t.id) as tasks,
                SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed
            FROM letters l
            LEFT JOIN tasks t ON l.id = t.letter_id
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            AND l.status != 'DELETED'
            $scopeFilter
            GROUP BY DATE(l.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute($params);
        $data['trends'] = $stmt->fetchAll();
        
        // Top performers (only for admin/manager)
        if ($scope !== 'own') {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.name,
                    u.avatar_url,
                    COUNT(DISTINCT t.id) as total_tasks,
                    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks
                FROM users u
                JOIN tasks t ON u.id = t.assigned_to
                WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                " . ($scope === 'department' ? "AND u.department_id = ?" : "") . "
                GROUP BY u.id, u.name, u.avatar_url
                HAVING completed_tasks > 0
                ORDER BY completed_tasks DESC
                LIMIT 10
            ");
            $topParams = [$dateFrom, $dateTo];
            if ($scope === 'department') {
                $topParams[] = $user['department_id'];
            }
            $stmt->execute($topParams);
            $data['top_performers'] = $stmt->fetchAll();
        }
        
        return $data;
        
    } catch (PDOException $e) {
        error_log("Overview report error: " . $e->getMessage());
        return $data;
    }
}

/**
 * Generate letters report
 */
function generateLettersReport($dateFrom, $dateTo, $scope) {
    global $pdo, $user;
    
    $data = [
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'generated_at' => date('Y-m-d H:i:s'),
        'by_stakeholder' => [],
        'by_priority' => [],
        'by_status' => [],
        'letters' => []
    ];
    
    try {
        // Build scope filter
        $scopeFilter = '';
        $scopeParams = [];
        if ($scope === 'department') {
            $scopeFilter = " AND l.department_id = ?";
            $scopeParams = [$user['department_id']];
        } elseif ($scope === 'own') {
            $scopeFilter = " AND l.uploaded_by = ?";
            $scopeParams = [$user['id']];
        }
        
        // By stakeholder
        $stmt = $pdo->prepare("
            SELECT 
                s.name as stakeholder,
                s.code,
                s.color,
                COUNT(*) as count
            FROM letters l
            JOIN stakeholders s ON l.stakeholder_id = s.id
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            AND l.status != 'DELETED'
            $scopeFilter
            GROUP BY s.id, s.name, s.code, s.color
            ORDER BY count DESC
        ");
        $params = array_merge([$dateFrom, $dateTo], $scopeParams);
        $stmt->execute($params);
        $data['by_stakeholder'] = $stmt->fetchAll();
        
        // By priority
        $stmt = $pdo->prepare("
            SELECT 
                priority,
                COUNT(*) as count
            FROM letters l
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            AND l.status != 'DELETED'
            $scopeFilter
            GROUP BY priority
            ORDER BY FIELD(priority, 'URGENT', 'HIGH', 'MEDIUM', 'LOW')
        ");
        $stmt->execute($params);
        $data['by_priority'] = $stmt->fetchAll();
        
        // By status
        $stmt = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM letters l
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            $scopeFilter
            GROUP BY status
        ");
        $stmt->execute($params);
        $data['by_status'] = $stmt->fetchAll();
        
        // Letter list
        $stmt = $pdo->prepare("
            SELECT 
                l.id,
                l.reference_no,
                l.subject,
                l.received_date,
                l.priority,
                l.status,
                l.created_at,
                s.name as stakeholder,
                s.code as stakeholder_code,
                d.name as department,
                u.name as uploaded_by,
                (SELECT COUNT(*) FROM tasks t WHERE t.letter_id = l.id) as task_count
            FROM letters l
            LEFT JOIN stakeholders s ON l.stakeholder_id = s.id
            LEFT JOIN departments d ON l.department_id = d.id
            LEFT JOIN users u ON l.uploaded_by = u.id
            WHERE l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            AND l.status != 'DELETED'
            $scopeFilter
            ORDER BY l.created_at DESC
        ");
        $stmt->execute($params);
        $data['letters'] = $stmt->fetchAll();
        
        return $data;
        
    } catch (PDOException $e) {
        error_log("Letters report error: " . $e->getMessage());
        return $data;
    }
}

/**
 * Generate tasks report
 */
function generateTasksReport($dateFrom, $dateTo, $scope) {
    global $pdo, $user;
    
    $data = [
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'generated_at' => date('Y-m-d H:i:s'),
        'by_status' => [],
        'by_priority' => [],
        'by_assignee' => [],
        'completion_time' => [],
        'tasks' => []
    ];
    
    try {
        // Build scope filter
        $scopeFilter = '';
        $scopeParams = [];
        if ($scope === 'department') {
            $scopeFilter = " AND t.assigned_department = ?";
            $scopeParams = [$user['department_id']];
        } elseif ($scope === 'own') {
            $scopeFilter = " AND t.assigned_to = ?";
            $scopeParams = [$user['id']];
        }
        
        // By status
        $stmt = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM tasks t
            WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            $scopeFilter
            GROUP BY status
        ");
        $params = array_merge([$dateFrom, $dateTo], $scopeParams);
        $stmt->execute($params);
        $data['by_status'] = $stmt->fetchAll();
        
        // By priority
        $stmt = $pdo->prepare("
            SELECT 
                priority,
                COUNT(*) as count
            FROM tasks t
            WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            $scopeFilter
            GROUP BY priority
            ORDER BY FIELD(priority, 'URGENT', 'HIGH', 'MEDIUM', 'LOW')
        ");
        $stmt->execute($params);
        $data['by_priority'] = $stmt->fetchAll();
        
        // By assignee (only for admin/manager)
        if ($scope !== 'own') {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.name,
                    COUNT(*) as total,
                    SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress
                FROM tasks t
                JOIN users u ON t.assigned_to = u.id
                WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                " . ($scope === 'department' ? "AND u.department_id = ?" : "") . "
                GROUP BY u.id, u.name
                ORDER BY total DESC
            ");
            $assigneeParams = [$dateFrom, $dateTo];
            if ($scope === 'department') {
                $assigneeParams[] = $user['department_id'];
            }
            $stmt->execute($assigneeParams);
            $data['by_assignee'] = $stmt->fetchAll();
        }
        
        // Average completion time
        $stmt = $pdo->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.completed_at)) as avg_hours,
                MIN(TIMESTAMPDIFF(HOUR, t.created_at, t.completed_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, t.created_at, t.completed_at)) as max_hours
            FROM tasks t
            WHERE t.status = 'COMPLETED'
            AND t.completed_at IS NOT NULL
            AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            $scopeFilter
        ");
        $stmt->execute($params);
        $data['completion_time'] = $stmt->fetch();
        
        // Task list
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.title,
                t.status,
                t.priority,
                t.due_date,
                t.created_at,
                t.completed_at,
                l.reference_no,
                u.name as assigned_to,
                d.name as department
            FROM tasks t
            JOIN letters l ON t.letter_id = l.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN departments d ON t.assigned_department = d.id
            WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            $scopeFilter
            ORDER BY t.created_at DESC
        ");
        $stmt->execute($params);
        $data['tasks'] = $stmt->fetchAll();
        
        return $data;
        
    } catch (PDOException $e) {
        error_log("Tasks report error: " . $e->getMessage());
        return $data;
    }
}

/**
 * Generate users report (admin only)
 */
function generateUsersReport($dateFrom, $dateTo) {
    global $pdo;
    
    $data = [
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'generated_at' => date('Y-m-d H:i:s'),
        'by_role' => [],
        'by_department' => [],
        'activity' => [],
        'users' => []
    ];
    
    try {
        // By role
        $stmt = $pdo->query("
            SELECT role, COUNT(*) as count
            FROM users
            WHERE is_active = TRUE
            GROUP BY role
        ");
        $data['by_role'] = $stmt->fetchAll();
        
        // By department
        $stmt = $pdo->query("
            SELECT 
                d.name as department,
                COUNT(u.id) as count
            FROM departments d
            LEFT JOIN users u ON d.id = u.department_id AND u.is_active = TRUE
            WHERE d.is_active = TRUE
            GROUP BY d.id, d.name
            ORDER BY count DESC
        ");
        $data['by_department'] = $stmt->fetchAll();
        
        // User activity
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                d.name as department,
                u.last_login,
                COUNT(DISTINCT a.id) as activity_count,
                COUNT(DISTINCT t.id) as tasks_assigned,
                SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as tasks_completed
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN activities a ON u.id = a.user_id AND a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN tasks t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            WHERE u.is_active = TRUE
            GROUP BY u.id, u.name, u.email, u.role, d.name, u.last_login
            ORDER BY activity_count DESC
        ");
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        $data['users'] = $stmt->fetchAll();
        
        return $data;
        
    } catch (PDOException $e) {
        error_log("Users report error: " . $e->getMessage());
        return $data;
    }
}

/**
 * Generate departments report
 */
function generateDepartmentsReport($dateFrom, $dateTo, $scope) {
    global $pdo, $user;
    
    $data = [
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'generated_at' => date('Y-m-d H:i:s'),
        'departments' => []
    ];
    
    try {
        $sql = "
            SELECT 
                d.id,
                d.name,
                d.description,
                m.name as manager_name,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT l.id) as letter_count,
                COUNT(DISTINCT t.id) as task_count,
                SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END) as pending_tasks
            FROM departments d
            LEFT JOIN users m ON d.manager_id = m.id
            LEFT JOIN users u ON d.id = u.department_id AND u.is_active = TRUE
            LEFT JOIN letters l ON d.id = l.department_id AND l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN tasks t ON d.id = t.assigned_department AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            WHERE d.is_active = TRUE
        ";
        
        $params = [$dateFrom, $dateTo, $dateFrom, $dateTo];
        
        if ($scope === 'department') {
            $sql .= " AND d.id = ?";
            $params[] = $user['department_id'];
        }
        
        $sql .= " GROUP BY d.id, d.name, d.description, m.name ORDER BY d.name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data['departments'] = $stmt->fetchAll();
        
        // Calculate completion rates
        foreach ($data['departments'] as &$dept) {
            $total = $dept['task_count'] ?? 0;
            $completed = $dept['completed_tasks'] ?? 0;
            $dept['completion_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        }
        
        return $data;
        
    } catch (PDOException $e) {
        error_log("Departments report error: " . $e->getMessage());
        return $data;
    }
}

/**
 * Generate stakeholders report
 */
function generateStakeholdersReport($dateFrom, $dateTo, $scope) {
    global $pdo, $user;
    
    $data = [
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'generated_at' => date('Y-m-d H:i:s'),
        'stakeholders' => []
    ];
    
    try {
        $scopeFilter = '';
        $scopeParams = [];
        if ($scope === 'department') {
            $scopeFilter = " AND l.department_id = ?";
            $scopeParams = [$user['department_id']];
        } elseif ($scope === 'own') {
            $scopeFilter = " AND l.uploaded_by = ?";
            $scopeParams = [$user['id']];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.name,
                s.code,
                s.color,
                COUNT(DISTINCT l.id) as letter_count,
                COUNT(DISTINCT t.id) as task_count,
                SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(CASE WHEN t.status = 'COMPLETED' AND t.completed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, t.created_at, t.completed_at) END) as avg_completion_hours
            FROM stakeholders s
            LEFT JOIN letters l ON s.id = l.stakeholder_id 
                AND l.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                AND l.status != 'DELETED'
                $scopeFilter
            LEFT JOIN tasks t ON l.id = t.letter_id
            WHERE s.is_active = TRUE
            GROUP BY s.id, s.name, s.code, s.color
            ORDER BY letter_count DESC
        ");
        $params = array_merge([$dateFrom, $dateTo], $scopeParams);
        $stmt->execute($params);
        $data['stakeholders'] = $stmt->fetchAll();
        
        return $data;
        
    } catch (PDOException $e) {
        error_log("Stakeholders report error: " . $e->getMessage());
        return $data;
    }
}

/**
 * Export data as CSV
 */
function exportCSV($data, $reportType) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Determine which data to export based on report type
    $rows = [];
    switch ($reportType) {
        case 'letters':
            $rows = $data['letters'] ?? [];
            break;
        case 'tasks':
            $rows = $data['tasks'] ?? [];
            break;
        case 'users':
            $rows = $data['users'] ?? [];
            break;
        case 'departments':
            $rows = $data['departments'] ?? [];
            break;
        case 'stakeholders':
            $rows = $data['stakeholders'] ?? [];
            break;
        default:
            // For overview, create summary rows
            $rows = [
                ['Metric', 'Value'],
                ['Total Letters', $data['summary']['total_letters'] ?? 0],
                ['Total Tasks', $data['summary']['total_tasks'] ?? 0],
                ['Completed Tasks', $data['summary']['completed_tasks'] ?? 0],
                ['Pending Tasks', $data['summary']['pending_tasks'] ?? 0],
                ['Completion Rate', ($data['summary']['completion_rate'] ?? 0) . '%']
            ];
    }
    
    if (!empty($rows)) {
        // Write headers
        if (isset($rows[0]) && is_array($rows[0])) {
            fputcsv($output, array_keys($rows[0]));
        }
        
        // Write data
        foreach ($rows as $row) {
            if (is_array($row)) {
                fputcsv($output, $row);
            }
        }
    }
    
    fclose($output);
    exit;
}
