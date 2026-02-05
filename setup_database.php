<?php
/**
 * Database Setup Script for Local Development
 * Creates database and runs migrations
 */

echo "Setting up database for File Tracker...\n\n";

try {
    // Connect to MySQL without specifying a database
    $pdo = new PDO(
        "mysql:host=localhost;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Create database if it doesn't exist
    echo "1. Creating database 'file_tracker'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS file_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✓ Database created\n";

    // Select the database
    $pdo->exec("USE file_tracker");

    // Run the main migration
    echo "\n2. Running main migration...\n";
    $migrationSql = file_get_contents(__DIR__ . '/sql/migration.sql');
    $pdo->exec($migrationSql);
    echo "   ✓ Main migration completed\n";

    // Run the auth enhancement migration
    echo "\n3. Running auth enhancement migration...\n";
    $authSql = file_get_contents(__DIR__ . '/sql/auth_enhancement.sql');
    $pdo->exec($authSql);
    echo "   ✓ Auth enhancement completed\n";

    // Create a test user for testing
    echo "\n4. Creating test user...\n";
    $testUserId = '01HXXXXXXXXXXXXXXXXXXXXX'; // Simple ID for testing
    $testEmail = 'test@example.com';
    $testPassword = password_hash('password123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (id, email, name, provider, password_hash, role, is_active)
        VALUES (?, ?, ?, 'email', ?, 'ADMIN', TRUE)
        ON DUPLICATE KEY UPDATE email = VALUES(email)
    ");
    $stmt->execute([$testUserId, $testEmail, 'Test User', $testPassword]);
    echo "   ✓ Test user created (email: test@example.com, password: password123)\n";

    // Create a sample department
    echo "\n5. Creating sample department...\n";
    $deptId = '01HXXXXXXXXXXXXXXXXXXXXY';
    $stmt = $pdo->prepare("
        INSERT INTO departments (id, name, description, is_active)
        VALUES (?, 'IT Department', 'Information Technology Department', TRUE)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");
    $stmt->execute([$deptId]);
    echo "   ✓ Sample department created\n";

    echo "\n🎉 Database setup completed successfully!\n";
    echo "\nTest credentials:\n";
    echo "Email: test@example.com\n";
    echo "Password: password123\n";
    echo "\nYou can now test the login functionality.\n";

} catch (Exception $e) {
    echo "\n❌ Error during setup: " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "1. MySQL is running in Laragon\n";
    echo "2. Root user has no password (default Laragon setup)\n";
    echo "3. MySQL service is started\n";
}
?>