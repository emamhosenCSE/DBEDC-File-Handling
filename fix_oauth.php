<?php
// Fix OAuth settings in database
require_once 'includes/db_config.php';
require_once 'includes/db.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // OAuth settings to insert/update
    $settings = [
        ['google_client_id', '551140686722-8q9j8q9j8q9j8q9j8q9j8q9j8q9j8q9j8q9j.apps.googleusercontent.com', 'auth'],
        ['google_client_secret', 'GOCSPX-j417j417j417j417j417j417j417j417', 'auth'],
        ['google_redirect_uri', 'https://files.dhakabypass.com/callback.php', 'auth']
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (id, setting_key, setting_value, setting_group) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $setting) {
        $id = generateULID();
        $stmt->execute([$id, $setting[0], $setting[1], $setting[2]]);
    }

    echo "OAuth settings updated successfully!";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>