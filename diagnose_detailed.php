<?php
echo "=== Detailed OAuth Configuration Diagnostic ===\n\n";

// Check config.php content
echo "Checking config.php content:\n";
$configContent = file_get_contents(__DIR__ . '/includes/config.php');
if (strpos($configContent, '$clientId = $oauthSettings[') !== false) {
    echo "✓ config.php contains the fixed code\n";
} else {
    echo "✗ config.php still has old code\n";
}

// Test database query directly
echo "\nTesting database query directly:\n";
try {
    require_once __DIR__ . '/includes/db_config.php';
    require_once __DIR__ . '/includes/db.php';

    if ($pdo !== null) {
        echo "✓ Database connection successful\n";

        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_group = ?");
        $stmt->execute(['auth']);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo "Direct database query results:\n";
        foreach ($results as $key => $value) {
            if (strpos($key, 'secret') !== false) {
                echo "  $key: [HIDDEN]\n";
            } else {
                echo "  $key: $value\n";
            }
        }

        // Test the exact query from config.php
        $stmt2 = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
        $oauthSettings = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);

        echo "\nOAuth settings array from config.php query:\n";
        var_dump($oauthSettings);

    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Test config loading step by step
echo "\nTesting config loading step by step:\n";
try {
    $oauthSettings = [];
    if (file_exists(__DIR__ . '/includes/db_config.php')) {
        echo "✓ db_config.php exists\n";
        require_once __DIR__ . '/includes/db_config.php';
        require_once __DIR__ . '/includes/db.php';

        if ($pdo !== null) {
            echo "✓ Database connection available\n";
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'auth'");
            $oauthSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            echo "✓ Query executed, " . count($oauthSettings) . " settings loaded\n";
        } else {
            echo "✗ Database connection is null\n";
        }
    } else {
        echo "✗ db_config.php not found\n";
    }

    $clientId = $oauthSettings['google_client_id'] ?? 'DEFAULT_CLIENT_ID';
    $redirectUri = $oauthSettings['google_redirect_uri'] ?? 'DEFAULT_REDIRECT_URI';

    echo "\nFinal values:\n";
    echo "clientId: $clientId\n";
    echo "redirectUri: $redirectUri\n";

} catch (Exception $e) {
    echo "✗ Error in step-by-step test: " . $e->getMessage() . "\n";
}

echo "\n=== End Detailed Diagnostic ===\n";
?>