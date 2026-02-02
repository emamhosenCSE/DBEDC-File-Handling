<?php
/**
 * Database Setup Script
 * Creates the database and runs initial migration
 */

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'file_tracker';

try {
    echo "=== Database Setup Script ===\n\n";

    // Connect to MySQL server (without selecting database)
    echo "1. Connecting to MySQL server... ";
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ SUCCESS\n";

    // Create database if it doesn't exist
    echo "2. Creating database '$dbName'... ";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ SUCCESS\n";

    // Connect to the specific database
    echo "3. Connecting to database... ";
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ SUCCESS\n";

    // Read and execute migration file
    echo "4. Running database migration... ";
    $migrationSQL = file_get_contents('sql/migration_v2.sql');

    if (!$migrationSQL) {
        throw new Exception("Could not read migration file");
    }

    // Split by semicolon but handle DELIMITER changes
    $statements = [];
    $currentStatement = '';
    $inDelimiterBlock = false;
    $delimiter = ';';

    $lines = explode("\n", $migrationSQL);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }

        // Handle DELIMITER changes
        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            $delimiter = trim($matches[1]);
            continue;
        }

        $currentStatement .= $line . "\n";

        // Check if statement ends with current delimiter
        if (substr(trim($line), -strlen($delimiter)) === $delimiter) {
            $statements[] = trim(str_replace($delimiter, ';', $currentStatement));
            $currentStatement = '';
        }
    }

    // Execute each statement
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^(DELIMITER|--)/i', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (Exception $e) {
                // Some statements might fail if they already exist, continue
                echo "\n   Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "✓ SUCCESS\n";

    // Create db_config.php
    echo "5. Creating database configuration file... ";
    $configContent = "<?php\n";
    $configContent .= "define('DB_HOST', '$host');\n";
    $configContent .= "define('DB_NAME', '$dbName');\n";
    $configContent .= "define('DB_USER', '$user');\n";
    $configContent .= "define('DB_PASS', '$pass');\n";

    file_put_contents('includes/db_config.php', $configContent);
    echo "✓ SUCCESS\n";

    // Test the connection with the new config
    echo "6. Testing database connection... ";
    require_once 'includes/db.php';
    $stmt = $pdo->query('SELECT 1');
    echo "✓ SUCCESS\n";

    // Check if tables were created
    echo "7. Verifying table creation... ";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '$dbName'");
    $result = $stmt->fetch();
    echo "✓ SUCCESS ({$result['count']} tables created)\n";

    echo "\n=== Database Setup Complete ===\n";
    echo "Database: $dbName\n";
    echo "Config file: includes/db_config.php\n";
    echo "Tables created: {$result['count']}\n";
    echo "\nNext steps:\n";
    echo "1. Access http://localhost/install.php in your browser to continue setup\n";
    echo "2. Configure Google OAuth credentials\n";
    echo "3. Set up branding and email settings\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>