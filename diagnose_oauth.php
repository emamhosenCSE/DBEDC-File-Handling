<?php
echo "=== OAuth Configuration Diagnostic ===\n\n";

// Check if config.php exists
if (file_exists(__DIR__ . '/includes/config.php')) {
    echo "✓ config.php exists\n";
} else {
    echo "✗ config.php does not exist\n";
}

// Check if db_config.php exists
if (file_exists(__DIR__ . '/includes/db_config.php')) {
    echo "✓ db_config.php exists\n";
    require_once __DIR__ . '/includes/db_config.php';
    echo "  DB_HOST: " . DB_HOST . "\n";
    echo "  DB_NAME: " . DB_NAME . "\n";
    echo "  DB_USER: " . DB_USER . "\n";
} else {
    echo "✗ db_config.php does not exist\n";
}

// Test database connection
try {
    require_once __DIR__ . '/includes/db.php';
    if ($pdo !== null) {
        echo "✓ Database connection successful\n";

        // Check OAuth settings in database
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo "Database OAuth settings:\n";
        foreach ($settings as $key => $value) {
            if (strpos($key, 'secret') !== false) {
                echo "  $key: [HIDDEN]\n";
            } else {
                echo "  $key: $value\n";
            }
        }

        if (empty($settings)) {
            echo "  No OAuth settings found in database!\n";
        }
    } else {
        echo "✗ Database connection failed - \$pdo is null\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Test config loading
echo "\nTesting config loading:\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "✓ config.php loaded successfully\n";
    echo "  GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : 'NOT DEFINED') . "\n";
    echo "  GOOGLE_REDIRECT_URI: " . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : 'NOT DEFINED') . "\n";
} catch (Exception $e) {
    echo "✗ Error loading config.php: " . $e->getMessage() . "\n";
}

echo "\n=== End Diagnostic ===\n";
?>