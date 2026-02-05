<?php
/**
 * Database Migration Runner
 * Automatically runs the auth enhancement migration on the production database
 */

echo "<h1>🔄 Database Migration Runner</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow-x:auto;}</style>";

// Check if already run
if (isset($_GET['run']) && $_GET['run'] === 'migration') {
    runMigration();
    exit;
}

echo "<p>This script will run the authentication enhancement migration on your database.</p>";
echo "<p><strong>What it does:</strong></p>";
echo "<ul>";
echo "<li>Adds new columns to users table (provider, password_hash, wechat_*)</li>";
echo "<li>Updates existing users to have provider = 'google'</li>";
echo "<li>Adds database indexes for performance</li>";
echo "<li>Inserts OAuth and email login settings</li>";
echo "</ul>";

echo "<p><strong>⚠️ Important:</strong> This will modify your database structure. Make sure you have a backup!</p>";

echo "<p><a href='?run=migration' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>🚀 Run Migration</a></p>";

echo "<hr>";
echo "<h3>SQL Commands That Will Be Executed:</h3>";
echo "<pre>" . htmlspecialchars(implode(";\n", getMigrationSQL())) . ";</pre>";

function getMigrationSQL() {
    return [
        // Add columns one by one to avoid syntax issues
        "ALTER TABLE users ADD COLUMN provider ENUM('google', 'wechat', 'email') DEFAULT 'google' AFTER google_id",
        "ALTER TABLE users ADD COLUMN wechat_id VARCHAR(100) UNIQUE DEFAULT NULL AFTER provider",
        "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER wechat_id",
        "ALTER TABLE users ADD COLUMN wechat_unionid VARCHAR(100) DEFAULT NULL AFTER password_hash",
        "ALTER TABLE users ADD COLUMN wechat_openid VARCHAR(100) DEFAULT NULL AFTER wechat_unionid",

        // Update existing users
        "UPDATE users SET provider = 'google' WHERE google_id IS NOT NULL",
        "ALTER TABLE users MODIFY COLUMN google_id VARCHAR(100) DEFAULT NULL",

        // Add indexes (only if columns exist)
        "ALTER TABLE users ADD INDEX idx_provider (provider)",
        "ALTER TABLE users ADD INDEX idx_wechat_id (wechat_id)",
        "ALTER TABLE users ADD INDEX idx_wechat_unionid (wechat_unionid)",

        // Insert settings
        "INSERT INTO settings (setting_key, setting_value, setting_group, is_public) VALUES
        ('wechat_app_id', '', 'auth', FALSE),
        ('wechat_app_secret', '', 'auth', FALSE),
        ('wechat_redirect_uri', 'https://files.dhakabypass.com/wechat_callback.php', 'auth', FALSE),
        ('email_login_enabled', 'true', 'auth', TRUE),
        ('password_min_length', '8', 'auth', TRUE),
        ('password_require_uppercase', 'true', 'auth', TRUE),
        ('password_require_numbers', 'true', 'auth', TRUE),
        ('password_require_special', 'false', 'auth', TRUE)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    ];
}

function runMigration() {
    echo "<h2>🔄 Running Migration...</h2>";

    try {
        require_once 'includes/db.php';

        if (!$pdo) {
            throw new Exception("Database connection failed");
        }

        echo "<p class='success'>✅ Database connected successfully</p>";

        // Get SQL statements array
        $statements = getMigrationSQL();

        $successCount = 0;
        $errors = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue; // Skip comments and empty statements
            }

            try {
                $pdo->exec($statement);
                echo "<p class='success'>✅ Executed: " . substr($statement, 0, 60) . "...</p>";
                $successCount++;
            } catch (Exception $e) {
                $errorMsg = "❌ Failed: " . substr($statement, 0, 60) . "... - " . $e->getMessage();
                echo "<p class='error'>$errorMsg</p>";
                $errors[] = $errorMsg;
            }
        }

        echo "<hr>";
        echo "<h3>📊 Migration Results:</h3>";
        echo "<p class='success'>✅ Successful statements: $successCount</p>";

        if (!empty($errors)) {
            echo "<p class='error'>❌ Failed statements: " . count($errors) . "</p>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='success'>🎉 Migration completed successfully!</p>";
            echo "<p><strong>Next steps:</strong></p>";
            echo "<ul>";
            echo "<li><a href='../verify_deployment.php'>Test the verification script</a></li>";
            echo "<li><a href='../quick_test.php'>Test the quick test interface</a></li>";
            echo "<li><a href='../login.php'>Test the main login page</a></li>";
            echo "</ul>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ Migration failed: " . $e->getMessage() . "</p>";
        echo "<p>Please check your database configuration and try again.</p>";
    }

    echo "<hr>";
    echo "<p><a href='run_migration.php' style='background:#6c757d;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>← Back</a></p>";
}
?>