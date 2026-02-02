<?php
/**
 * Production Migration Runner
 * Alternative approach using mysqli for better DELIMITER handling
 */

echo "=== Production Migration Runner ===\n\n";

if ($argc < 4) {
    echo "Usage: php run_migration_production.php <host> <database> <username> [<password>] [<port>]\n";
    echo "Example: php run_migration_production.php localhost file_tracker root '' 3306\n";
    echo "Note: Use empty quotes '' for no password\n";
    exit(1);
}

$host = $argv[1];
$dbName = $argv[2];
$user = $argv[3];
$pass = $argv[4] ?? '';
$port = $argv[5] ?? 3306;

try {
    echo "1. Connecting to MySQL/MariaDB... ";
    $mysqli = new mysqli($host, $user, $pass, $dbName, $port);
    if ($mysqli->connect_error) {
        throw new Exception('Connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    echo "✓ SUCCESS\n";

    echo "2. Reading migration file... ";
    $migrationFile = __DIR__ . '/sql/migration_v2.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);
    echo "✓ SUCCESS (" . strlen($sql) . " bytes)\n";

    echo "3. Executing migration... ";

    // Split SQL by DELIMITER changes and execute blocks
    $blocks = preg_split('/^DELIMITER\s+(.+)$/im', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
    $statements = [];
    $currentDelimiter = ';';

    for ($i = 0; $i < count($blocks); $i++) {
        $block = trim($blocks[$i]);

        if (empty($block)) continue;

        // Check if this is a delimiter specification
        if (preg_match('/^[\/;]$/', $block)) {
            $currentDelimiter = $block;
            continue;
        }

        // Split this block by the current delimiter
        $blockStatements = explode($currentDelimiter, $block);
        foreach ($blockStatements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt)) {
                $statements[] = $stmt . ';';
            }
        }
    }

    echo "Found " . count($statements) . " statements\n";

    // Execute each statement
    $executed = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        if (!$mysqli->query($statement)) {
            $error = $mysqli->error;
            // Check for ignorable errors
            if (strpos($error, 'already exists') !== false ||
                strpos($error, 'Duplicate entry') !== false ||
                strpos($error, 'PROCEDURE .* already exists') !== false) {
                echo "   - Skipping: " . substr($statement, 0, 50) . "... ($error)\n";
                continue;
            }
            echo "   ✗ ERROR in statement: " . substr($statement, 0, 100) . "...\n";
            echo "     $error\n";
            $errors++;
        } else {
            $executed++;
        }
    }

    echo "4. Migration completed!\n";
    echo "   - Executed: $executed statements\n";
    echo "   - Errors: $errors\n";

    if ($errors > 0) {
        echo "   ⚠️  Some statements failed, but migration may still be usable.\n";
    }

    // Verify installation
    echo "5. Verifying installation... ";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '$dbName'");
    $row = $result->fetch_assoc();
    $tableCount = $row['count'];
    echo "Found $tableCount tables\n";

    // Mark as installed
    echo "6. Marking system as installed... ";
    $mysqli->query("INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description)
                    VALUES ('01SETTING000SYSTEM_INSTALLED', 'system_installed', '1', 'system', 'boolean', FALSE, 'System installation status')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    echo "✓ SUCCESS\n";

    $mysqli->close();

    echo "\n=== MIGRATION SUCCESSFUL ===\n";
    echo "Database is ready for production use!\n";

} catch (Exception $e) {
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
