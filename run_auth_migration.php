<?php
/**
 * Run Authentication Enhancement Migration
 */

require_once __DIR__ . '/includes/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/sql/auth_enhancement.sql');
    $pdo->exec($sql);
    echo "Database migration completed successfully\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}