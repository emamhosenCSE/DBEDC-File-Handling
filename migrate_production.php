<?php
/**
 * Production Migration Script
 * Handles DELIMITER statements properly for both MySQL and MariaDB
 */

echo "=== Production Database Migration ===\n\n";

if ($argc < 2) {
    echo "Usage: php migrate_production.php <db_host> <db_name> <db_user> <db_pass>\n";
    echo "Example: php migrate_production.php localhost mydb root mypass\n";
    exit(1);
}

$host = $argv[1];
$dbName = $argv[2];
$user = $argv[3];
$pass = $argv[4] ?? '';

try {
    echo "1. Connecting to database... ";
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );
    echo "✓ SUCCESS\n";

    echo "2. Reading migration file... ";
    $migrationSQL = file_get_contents(__DIR__ . '/sql/migration_v2.sql');
    if (!$migrationSQL) {
        throw new Exception("Could not read migration file");
    }
    echo "✓ SUCCESS\n";

    echo "3. Parsing and executing SQL statements... ";

    // Split SQL into statements, handling DELIMITER
    $statements = [];
    $currentStatement = '';
    $delimiter = ';';
    $lines = explode("\n", $migrationSQL);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0) continue; // Skip comments and empty lines

        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            $delimiter = $matches[1];
            continue;
        }

        $currentStatement .= $line . "\n";

        if (substr($line, -strlen($delimiter)) === $delimiter) {
            $statement = trim(str_replace($delimiter, ';', $currentStatement));
            if (!empty($statement)) {
                $statements[] = $statement;
            }
            $currentStatement = '';
        }
    }

    echo "Found " . count($statements) . " statements\n";

    // Execute each statement
    $executed = 0;
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (Exception $e) {
            // Check if it's a "already exists" error, which we can ignore
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "   - Skipping duplicate: " . substr($statement, 0, 50) . "...\n";
                continue;
            }
            throw $e;
        }
    }

    echo "4. Migration completed successfully! Executed $executed statements.\n";

    // Verify tables were created
    echo "5. Verifying tables... ";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables\n";

    // Mark system as installed
    echo "6. Marking system as installed... ";
    $pdo->exec("INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description)
                VALUES ('01SETTING000SYSTEM_INSTALLED', 'system_installed', '1', 'system', 'boolean', FALSE, 'System installation status')
                ON DUPLICATE KEY UPDATE setting_value = '1'");
    echo "✓ SUCCESS\n";

    echo "\n=== MIGRATION COMPLETE ===\n";
    echo "The database has been successfully migrated and is ready for use.\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
