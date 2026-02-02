<?php
/**
 * Advanced Reports API
 * Custom report builder, analytics, and export functionality
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

// Verify user is authenticated
$user = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'report-builder-options':
                    // Get options for report builder (filters, fields, etc.)
                    $options = [
                        'date_ranges' => [
                            ['value' => 'today', 'label' => 'Today'],
                            ['value' => 'yesterday', 'label' => 'Yesterday'],
                            ['value' => 'this_week', 'label' => 'This Week'],
                            ['value' => 'last_week', 'label' => 'Last Week'],
                            ['value' => 'this_month', 'label' => 'This Month'],
                            ['value' => 'last_month', 'label' => 'Last Month'],
                            ['value' => 'this_quarter', 'label' => 'This Quarter'],
                            ['value' => 'last_quarter', 'label' => 'Last Quarter'],
                            ['value' => 'this_year', 'label' => 'This Year'],
                            ['value' => 'last_year', 'label' => 'Last Year'],
                            ['value' => 'custom', 'label' => 'Custom Range']
                        ],
                        'entities' => [
                            ['value' => 'letters', 'label' => 'Letters'],
                            ['value' => 'tasks', 'label' => 'Tasks'],
                            ['value' => 'activities', 'label' => 'Activities'],
                            ['value' => 'users', 'label' => 'Users'],
                            ['value' => 'departments', 'label' => 'Departments']
                        ],
                        'metrics' => [
                            ['value' => 'count', 'label' => 'Count'],
                            ['value' => 'avg_completion_days', 'label' => 'Average Completion Days'],
                            ['value' => 'completion_rate', 'label' => 'Completion Rate (%)'],
                            ['value' => 'overdue_count', 'label' => 'Overdue Count'],
                            ['value' => 'on_time_rate', 'label' => 'On-Time Rate (%)']
                        ],
                        'group_by' => [
                            ['value' => 'department', 'label' => 'Department'],
                            ['value' => 'stakeholder', 'label' => 'Stakeholder'],
                            ['value' => 'priority', 'label' => 'Priority'],
                            ['value' => 'status', 'label' => 'Status'],
                            ['value' => 'month', 'label' => 'Month'],
                            ['value' => 'quarter', 'label' => 'Quarter'],
                            ['value' => 'year', 'label' => 'Year']
                        ]
                    ];

                    // Get dynamic options from database
                    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = TRUE ORDER BY name");
                    $options['departments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $stmt = $pdo->query("SELECT id, name, code FROM stakeholders WHERE is_active = TRUE ORDER BY display_order");
                    $options['stakeholders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $stmt = $pdo->query("SELECT id, name FROM users WHERE is_active = TRUE ORDER BY name");
                    $options['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $options
                    ]);
                    break;

                case 'generate-report':
                    // Generate custom report based on filters
                    $filters = json_decode($_GET['filters'] ?? '{}', true);
                    $entity = $_GET['entity'] ?? 'letters';
                    $groupBy = $_GET['group_by'] ?? null;
                    $metrics = $_GET['metrics'] ?? ['count'];

                    $report = generateCustomReport($entity, $filters, $groupBy, $metrics);

                    echo json_encode([
                        'success' => true,
                        'data' => $report
                    ]);
                    break;

                case 'performance-kpis':
                    // Get key performance indicators
                    $period = $_GET['period'] ?? 'month';
                    $kpis = getPerformanceKPIs($period);

                    echo json_encode([
                        'success' => true,
                        'data' => $kpis
                    ]);
                    break;

                case 'trend-analysis':
                    // Get trend analysis data
                    $metric = $_GET['metric'] ?? 'completion_rate';
                    $period = $_GET['period'] ?? '6months';
                    $trends = getTrendAnalysis($metric, $period);

                    echo json_encode([
                        'success' => true,
                        'data' => $trends
                    ]);
                    break;

                case 'department-performance':
                    // Get department performance comparison
                    $period = $_GET['period'] ?? 'month';
                    $performance = getDepartmentPerformance($period);

                    echo json_encode([
                        'success' => true,
                        'data' => $performance
                    ]);
                    break;

                case 'saved-reports':
                    // Get user's saved reports
                    $stmt = $pdo->prepare("
                        SELECT * FROM saved_reports
                        WHERE created_by = ? OR is_public = TRUE
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$user['id']]);
                    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'data' => $reports
                    ]);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
            break;

        case 'POST':
            switch ($action) {
                case 'save-report':
                    // Save a custom report configuration
                    $input = json_decode(file_get_contents('php://input'), true);
                    $name = $input['name'] ?? '';
                    $config = $input['config'] ?? [];
                    $isPublic = $input['is_public'] ?? false;

                    if (!$name || empty($config)) {
                        throw new Exception('Report name and configuration required');
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO saved_reports (id, name, config, created_by, is_public, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");

                    $reportId = generateULID();
                    $stmt->execute([
                        $reportId,
                        $name,
                        json_encode($config),
                        $user['id'],
                        $isPublic
                    ]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Report saved successfully',
                        'data' => ['id' => $reportId]
                    ]);
                    break;

                case 'export-report':
                    // Export report to various formats
                    $input = json_decode(file_get_contents('php://input'), true);
                    $format = $input['format'] ?? 'csv';
                    $data = $input['data'] ?? [];
                    $filename = $input['filename'] ?? 'report_' . date('Y-m-d_H-i-s');

                    $exportFile = exportReport($data, $format, $filename);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'filename' => $exportFile,
                            'url' => 'api/reports/' . $exportFile
                        ]
                    ]);
                    break;

                case 'schedule-report':
                    // Schedule automated report generation
                    $input = json_decode(file_get_contents('php://input'), true);
                    $reportName = $input['report_name'] ?? '';
                    $config = $input['config'] ?? [];
                    $schedule = $input['schedule'] ?? 'weekly'; // daily, weekly, monthly
                    $recipients = $input['recipients'] ?? [];

                    if (!$reportName || empty($config)) {
                        throw new Exception('Report name and configuration required');
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO scheduled_reports (id, name, config, schedule_type, recipients, created_by, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())
                    ");

                    $scheduleId = generateULID();
                    $stmt->execute([
                        $scheduleId,
                        $reportName,
                        json_encode($config),
                        $schedule,
                        json_encode($recipients),
                        $user['id']
                    ]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Report scheduled successfully',
                        'data' => ['id' => $scheduleId]
                    ]);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'delete-saved-report':
                    $reportId = $_GET['id'] ?? '';

                    if (!$reportId) {
                        throw new Exception('Report ID required');
                    }

                    // Check ownership
                    $stmt = $pdo->prepare("
                        SELECT created_by FROM saved_reports WHERE id = ?
                    ");
                    $stmt->execute([$reportId]);
                    $report = $stmt->fetch();

                    if (!$report) {
                        throw new Exception('Report not found');
                    }

                    if ($report['created_by'] !== $user['id'] && !hasPermission($user['id'], 'manage_system')) {
                        throw new Exception('Access denied');
                    }

                    $stmt = $pdo->prepare("DELETE FROM saved_reports WHERE id = ?");
                    $stmt->execute([$reportId]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Report deleted successfully'
                    ]);
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

/**
 * Generate custom report based on filters
 */
function generateCustomReport($entity, $filters, $groupBy, $metrics) {
    global $pdo;

    $whereClause = [];
    $params = [];

    // Build date filters
    if (isset($filters['date_range'])) {
        $dateRange = getDateRange($filters['date_range']);
        if ($dateRange) {
            $whereClause[] = "created_at BETWEEN ? AND ?";
            $params[] = $dateRange['start'];
            $params[] = $dateRange['end'];
        }
    }

    // Build other filters
    if (isset($filters['department_id'])) {
        $whereClause[] = "department_id = ?";
        $params[] = $filters['department_id'];
    }

    if (isset($filters['stakeholder_id'])) {
        $whereClause[] = "stakeholder_id = ?";
        $params[] = $filters['stakeholder_id'];
    }

    if (isset($filters['status'])) {
        $whereClause[] = "status = ?";
        $params[] = $filters['status'];
    }

    if (isset($filters['priority'])) {
        $whereClause[] = "priority = ?";
        $params[] = $filters['priority'];
    }

    $whereSql = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

    // Build query based on entity
    switch ($entity) {
        case 'letters':
            $query = "
                SELECT
                    COUNT(*) as count,
                    AVG(DATEDIFF(COALESCE(completed_at, NOW()), created_at)) as avg_completion_days,
                    (COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) * 100.0 / COUNT(*)) as completion_rate
                    " . ($groupBy ? ", " . getGroupByField($groupBy, 'letters') . " as group_field" : "") . "
                FROM letters l
                $whereSql
                " . ($groupBy ? "GROUP BY " . getGroupByField($groupBy, 'letters') : "") . "
                ORDER BY " . ($groupBy ? "group_field" : "count DESC");
            break;

        case 'tasks':
            $query = "
                SELECT
                    COUNT(*) as count,
                    AVG(DATEDIFF(COALESCE(completed_at, NOW()), created_at)) as avg_completion_days,
                    (COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) * 100.0 / COUNT(*)) as completion_rate,
                    COUNT(CASE WHEN due_date < NOW() AND status IN ('PENDING', 'IN_PROGRESS') THEN 1 END) as overdue_count
                    " . ($groupBy ? ", " . getGroupByField($groupBy, 'tasks') . " as group_field" : "") . "
                FROM tasks t
                $whereSql
                " . ($groupBy ? "GROUP BY " . getGroupByField($groupBy, 'tasks') : "") . "
                ORDER BY " . ($groupBy ? "group_field" : "count DESC");
            break;

        default:
            throw new Exception('Unsupported entity');
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get date range from preset
 */
function getDateRange($range) {
    $now = new DateTime();

    switch ($range) {
        case 'today':
            return [
                'start' => $now->format('Y-m-d 00:00:00'),
                'end' => $now->format('Y-m-d 23:59:59')
            ];
        case 'yesterday':
            $now->modify('-1 day');
            return [
                'start' => $now->format('Y-m-d 00:00:00'),
                'end' => $now->format('Y-m-d 23:59:59')
            ];
        case 'this_week':
            $start = clone $now;
            $start->modify('monday this week');
            return [
                'start' => $start->format('Y-m-d 00:00:00'),
                'end' => $now->format('Y-m-d 23:59:59')
            ];
        case 'last_week':
            $start = clone $now;
            $start->modify('monday last week');
            $end = clone $start;
            $end->modify('+6 days');
            return [
                'start' => $start->format('Y-m-d 00:00:00'),
                'end' => $end->format('Y-m-d 23:59:59')
            ];
        case 'this_month':
            $start = new DateTime($now->format('Y-m-01'));
            return [
                'start' => $start->format('Y-m-d 00:00:00'),
                'end' => $now->format('Y-m-d 23:59:59')
            ];
        case 'last_month':
            $now->modify('first day of last month');
            $start = clone $now;
            $end = new DateTime($now->format('Y-m-t'));
            return [
                'start' => $start->format('Y-m-d 00:00:00'),
                'end' => $end->format('Y-m-d 23:59:59')
            ];
        default:
            return null;
    }
}

/**
 * Get group by field for entity
 */
function getGroupByField($groupBy, $entity) {
    $fields = [
        'letters' => [
            'department' => 'd.name',
            'stakeholder' => 's.name',
            'priority' => 'l.priority',
            'status' => 'l.status',
            'month' => 'DATE_FORMAT(l.created_at, "%Y-%m")',
            'quarter' => 'CONCAT(YEAR(l.created_at), "-Q", QUARTER(l.created_at))',
            'year' => 'YEAR(l.created_at)'
        ],
        'tasks' => [
            'department' => 'd.name',
            'stakeholder' => 's.name',
            'priority' => 't.priority',
            'status' => 't.status',
            'month' => 'DATE_FORMAT(t.created_at, "%Y-%m")',
            'quarter' => 'CONCAT(YEAR(t.created_at), "-Q", QUARTER(t.created_at))',
            'year' => 'YEAR(t.created_at)'
        ]
    ];

    return $fields[$entity][$groupBy] ?? 'NULL';
}

/**
 * Get performance KPIs
 */
function getPerformanceKPIs($period) {
    global $pdo;

    $dateRange = getDateRange($period);
    $whereClause = $dateRange ? "WHERE created_at BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'" : "";

    // Overall metrics
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_letters,
            COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_letters,
            AVG(CASE WHEN status = 'COMPLETED' THEN DATEDIFF(completed_at, created_at) END) as avg_completion_days,
            COUNT(CASE WHEN due_date < NOW() AND status != 'COMPLETED' THEN 1 END) as overdue_letters
        FROM letters l
        $whereClause
    ");
    $letterMetrics = $stmt->fetch();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_tasks,
            AVG(CASE WHEN status = 'COMPLETED' THEN DATEDIFF(completed_at, created_at) END) as avg_task_completion_days,
            COUNT(CASE WHEN due_date < NOW() AND status IN ('PENDING', 'IN_PROGRESS') THEN 1 END) as overdue_tasks
        FROM tasks t
        $whereClause
    ");
    $taskMetrics = $stmt->fetch();

    return [
        'letters' => [
            'total' => (int)$letterMetrics['total_letters'],
            'completed' => (int)$letterMetrics['completed_letters'],
            'completion_rate' => $letterMetrics['total_letters'] > 0 ?
                round(($letterMetrics['completed_letters'] / $letterMetrics['total_letters']) * 100, 1) : 0,
            'avg_completion_days' => round($letterMetrics['avg_completion_days'] ?? 0, 1),
            'overdue' => (int)$letterMetrics['overdue_letters']
        ],
        'tasks' => [
            'total' => (int)$taskMetrics['total_tasks'],
            'completed' => (int)$taskMetrics['completed_tasks'],
            'completion_rate' => $taskMetrics['total_tasks'] > 0 ?
                round(($taskMetrics['completed_tasks'] / $taskMetrics['total_tasks']) * 100, 1) : 0,
            'avg_completion_days' => round($taskMetrics['avg_task_completion_days'] ?? 0, 1),
            'overdue' => (int)$taskMetrics['overdue_tasks']
        ],
        'period' => $period
    ];
}

/**
 * Get trend analysis data
 */
function getTrendAnalysis($metric, $period) {
    global $pdo;

    $months = 6; // Default 6 months
    if ($period === '12months') $months = 12;
    if ($period === '3months') $months = 3;

    $query = "
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_count,
            COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_count,
            AVG(CASE WHEN status = 'COMPLETED' THEN DATEDIFF(completed_at, created_at) END) as avg_completion_days
        FROM letters
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $months MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ";

    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate rates
    foreach ($data as &$row) {
        $row['completion_rate'] = $row['total_count'] > 0 ?
            round(($row['completed_count'] / $row['total_count']) * 100, 1) : 0;
        $row['avg_completion_days'] = round($row['avg_completion_days'] ?? 0, 1);
    }

    return $data;
}

/**
 * Get department performance comparison
 */
function getDepartmentPerformance($period) {
    global $pdo;

    $dateRange = getDateRange($period);
    $whereClause = $dateRange ? "AND l.created_at BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'" : "";

    $stmt = $pdo->query("
        SELECT
            d.name as department_name,
            COUNT(l.id) as total_letters,
            COUNT(CASE WHEN l.status = 'COMPLETED' THEN 1 END) as completed_letters,
            AVG(CASE WHEN l.status = 'COMPLETED' THEN DATEDIFF(l.completed_at, l.created_at) END) as avg_completion_days,
            COUNT(t.id) as total_tasks,
            COUNT(CASE WHEN t.status = 'COMPLETED' THEN 1 END) as completed_tasks
        FROM departments d
        LEFT JOIN letters l ON l.department_id = d.id $whereClause
        LEFT JOIN tasks t ON t.letter_id = l.id
        WHERE d.is_active = TRUE
        GROUP BY d.id, d.name
        ORDER BY total_letters DESC
    ");

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate rates
    foreach ($data as &$row) {
        $row['letter_completion_rate'] = $row['total_letters'] > 0 ?
            round(($row['completed_letters'] / $row['total_letters']) * 100, 1) : 0;
        $row['task_completion_rate'] = $row['total_tasks'] > 0 ?
            round(($row['completed_tasks'] / $row['total_tasks']) * 100, 1) : 0;
        $row['avg_completion_days'] = round($row['avg_completion_days'] ?? 0, 1);
    }

    return $data;
}

/**
 * Export report to file
 */
function exportReport($data, $format, $filename) {
    $exportDir = __DIR__ . '/../exports/';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    $filepath = $exportDir . $filename;

    switch ($format) {
        case 'csv':
            $filepath .= '.csv';
            exportToCSV($data, $filepath);
            break;
        case 'json':
            $filepath .= '.json';
            file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
            break;
        default:
            throw new Exception('Unsupported export format');
    }

    return basename($filepath);
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filepath) {
    if (empty($data)) return;

    $fp = fopen($filepath, 'w');

    // Write headers
    fputcsv($fp, array_keys($data[0]));

    // Write data
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
}
?>