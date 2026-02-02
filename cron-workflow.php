<?php
/**
 * Cron Job Script for Workflow Automation
 * Run this script periodically (e.g., hourly) to process automated workflows
 *
 * Usage: php cron-workflow.php
 * Or via cron: 0 * * * * /usr/bin/php /path/to/cron-workflow.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/workflow.php';

// Set execution time limit
set_time_limit(300); // 5 minutes

// Log start of execution
$startTime = microtime(true);
error_log("Workflow automation cron started at " . date('Y-m-d H:i:s'));

try {
    // Process automated workflows
    $results = processAutomatedWorkflows();

    // Log results
    $executionTime = round(microtime(true) - $startTime, 2);
    error_log("Workflow automation completed in {$executionTime}s: " .
              "Overdue tasks processed: {$results['overdue_processed']}, " .
              "Reminders sent: {$results['reminders_sent']}");

    // Output results for cron logging
    echo "Workflow automation completed successfully\n";
    echo "Overdue tasks processed: {$results['overdue_processed']}\n";
    echo "Reminders sent: {$results['reminders_sent']}\n";
    echo "Execution time: {$executionTime}s\n";

} catch (Exception $e) {
    // Log error
    error_log("Workflow automation cron failed: " . $e->getMessage());

    // Output error for cron logging
    echo "Workflow automation failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>