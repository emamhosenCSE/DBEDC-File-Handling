<?php
/**
 * Scheduled Reports Processor
 * Processes automated report generation and email delivery
 *
 * Usage: php cron-reports.php
 * Or via cron: 0 9 * * 1 /usr/bin/php /path/to/your/site/cron-reports.php (weekly on Mondays at 9 AM)
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email.php';

// Set execution time limit
set_time_limit(600); // 10 minutes

// Log start of execution
$startTime = microtime(true);
error_log("Scheduled reports processor started at " . date('Y-m-d H:i:s'));

try {
    // Get active scheduled reports that are due
    $stmt = $pdo->query("
        SELECT * FROM scheduled_reports
        WHERE is_active = TRUE
        AND (next_run IS NULL OR next_run <= NOW())
    ");

    $scheduledReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $errors = 0;

    foreach ($scheduledReports as $report) {
        try {
            // Generate the report
            $reportData = generateScheduledReport($report);

            if ($reportData) {
                // Send email with report
                sendScheduledReportEmail($report, $reportData);
                $processed++;

                // Update next run time
                updateNextRunTime($report['id'], $report['schedule_type']);

                // Log successful execution
                logReportExecution($report['id'], 'scheduled', null, count($reportData), 'success');
            }

        } catch (Exception $e) {
            error_log("Failed to process scheduled report {$report['id']}: " . $e->getMessage());
            logReportExecution($report['id'], 'scheduled', null, 0, 'error', $e->getMessage());
            $errors++;
        }
    }

    // Log completion
    $executionTime = round(microtime(true) - $startTime, 2);
    error_log("Scheduled reports processing completed in {$executionTime}s: {$processed} processed, {$errors} errors");

    // Output results for cron logging
    echo "Scheduled reports processing completed\n";
    echo "Reports processed: {$processed}\n";
    echo "Errors: {$errors}\n";
    echo "Execution time: {$executionTime}s\n";

} catch (Exception $e) {
    // Log error
    error_log("Scheduled reports processor failed: " . $e->getMessage());

    // Output error for cron logging
    echo "Scheduled reports processor failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Generate report data for scheduled report
 */
function generateScheduledReport($report) {
    global $pdo;

    $config = json_decode($report['config'], true);
    $entity = $config['entity'] ?? 'letters';
    $filters = $config['filters'] ?? [];
    $groupBy = $config['group_by'] ?? null;
    $metrics = $config['metrics'] ?? ['count'];

    // Build WHERE clause
    $whereClause = [];
    $params = [];

    if (isset($filters['date_range'])) {
        $dateRange = getDateRange($filters['date_range']);
        if ($dateRange) {
            $whereClause[] = "created_at BETWEEN ? AND ?";
            $params[] = $dateRange['start'];
            $params[] = $dateRange['end'];
        }
    }

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

    $whereSql = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

    // Build query
    if ($entity === 'letters') {
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
    } else {
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
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send scheduled report via email
 */
function sendScheduledReportEmail($report, $data) {
    $recipients = json_decode($report['recipients'], true);

    if (empty($recipients)) {
        return; // No recipients
    }

    // Generate CSV content
    $csvContent = generateCSVContent($data);

    // Email subject and body
    $subject = "Scheduled Report: {$report['name']} - " . date('M j, Y');
    $systemConfig = getSystemConfig();
    $companyName = $systemConfig['company_name'] ?? 'File Tracker';
    $emailSignature = $systemConfig['email_signature'] ?? 'File Tracking System';
    
    $body = "
        <h2>{$report['name']}</h2>
        <p>This is your scheduled report generated on " . date('F j, Y \a\t g:i A') . ".</p>
        <p>Report contains " . count($data) . " records.</p>
        <p><em>This is an automated message from the {$emailSignature}.</em></p>
    ";

    // Send email to each recipient
    foreach ($recipients as $email) {
        sendEmail(
            $email,
            $subject,
            $body,
            [
                [
                    'name' => 'report.csv',
                    'content' => $csvContent,
                    'type' => 'text/csv'
                ]
            ]
        );
    }
}

/**
 * Update next run time for scheduled report
 */
function updateNextRunTime($reportId, $scheduleType) {
    global $pdo;

    $nextRun = null;

    switch ($scheduleType) {
        case 'daily':
            $nextRun = date('Y-m-d H:i:s', strtotime('+1 day'));
            break;
        case 'weekly':
            $nextRun = date('Y-m-d H:i:s', strtotime('next monday'));
            break;
        case 'monthly':
            $nextRun = date('Y-m-d H:i:s', strtotime('first day of next month'));
            break;
    }

    if ($nextRun) {
        $stmt = $pdo->prepare("UPDATE scheduled_reports SET next_run = ?, last_run = NOW() WHERE id = ?");
        $stmt->execute([$nextRun, $reportId]);
    }
}

/**
 * Log report execution
 */
function logReportExecution($reportId, $reportType, $parameters, $recordCount, $status, $errorMessage = null) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO report_executions
        (id, report_id, report_type, parameters, record_count, status, error_message, executed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        generateULID(),
        $reportId,
        $reportType,
        $parameters ? json_encode($parameters) : null,
        $recordCount,
        $status,
        $errorMessage
    ]);
}

/**
 * Generate CSV content from data
 */
function generateCSVContent($data) {
    if (empty($data)) return '';

    $output = fopen('php://temp', 'r+');

    // Write headers
    fputcsv($output, array_keys($data[0]));

    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

/**
 * Get date range from preset (duplicate from advanced-reports.php)
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
 * Get group by field for entity (duplicate from advanced-reports.php)
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
?>