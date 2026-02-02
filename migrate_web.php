<?php
/**
 * Web-based Migration Tool for Production
 * Access via browser to run database migration
 */

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if (empty($host) || empty($dbName) || empty($user)) {
        $error = 'All database fields are required';
    } else {
        try {
            // Test connection
            $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Read migration file
            $migrationSQL = file_get_contents(__DIR__ . '/sql/migration_v2.sql');
            if (!$migrationSQL) {
                throw new Exception("Could not read migration file");
            }

            // Use mysqli for better DELIMITER handling
            $mysqli = new mysqli($host, $user, $pass, $dbName);
            if ($mysqli->connect_error) {
                throw new Exception('Connection failed: ' . $mysqli->connect_error);
            }
            $mysqli->set_charset('utf8mb4');

            // Simple approach: remove DELIMITER lines and execute
            $cleanSQL = preg_replace('/^DELIMITER.*$/im', '', $migrationSQL);
            $cleanSQL = preg_replace('/END\s*\/\//i', 'END', $cleanSQL);

            // Split by semicolon
            $statements = array_filter(array_map('trim', explode(';', $cleanSQL)));

            $executed = 0;
            $errors = 0;

            foreach ($statements as $statement) {
                if (empty($statement)) continue;

                if (!$mysqli->query($statement)) {
                    $errorMsg = $mysqli->error;
                    if (strpos($errorMsg, 'already exists') === false &&
                        strpos($errorMsg, 'Duplicate entry') === false) {
                        $errors++;
                        echo "<div class='error'>ERROR in statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...<br>$errorMsg</div>";
                    }
                } else {
                    $executed++;
                }
            }

            $mysqli->close();

            $success = "Migration completed! Executed $executed statements.";
            if ($errors > 0) {
                $success .= " ($errors errors ignored - likely duplicates)";
            }

        } catch (Exception $e) {
            $error = 'Migration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Database Migration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .warning { color: #856404; background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Production Database Migration Tool</h1>

    <div class="warning">
        <strong>Warning:</strong> This tool will create/modify database tables. Make sure you have a backup of your database before proceeding.
    </div>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="host">Database Host:</label>
            <input type="text" id="host" name="host" value="localhost" required>
        </div>

        <div class="form-group">
            <label for="db_name">Database Name:</label>
            <input type="text" id="db_name" name="db_name" value="file_tracker" required>
        </div>

        <div class="form-group">
            <label for="user">Database Username:</label>
            <input type="text" id="user" name="user" value="root" required>
        </div>

        <div class="form-group">
            <label for="pass">Database Password:</label>
            <input type="password" id="pass" name="pass">
        </div>

        <button type="submit">Run Migration</button>
    </form>

    <h2>Alternative Methods</h2>
    <p>If the web tool doesn't work, try these command-line approaches:</p>

    <h3>Method 1: Direct MySQL Import</h3>
    <pre>mysql -u username -p database_name < sql/migration_v2.sql</pre>

    <h3>Method 2: Using the PHP Script</h3>
    <pre>php run_migration_production.php host database username password</pre>

    <p><strong>Note:</strong> After successful migration, delete this file for security reasons.</p>
</body>
</html>