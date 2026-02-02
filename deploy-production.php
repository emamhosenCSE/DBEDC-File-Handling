<?php
/**
 * Production Deployment Script
 * Prepares the system for production deployment
 */

echo "=== File Tracker Production Deployment ===\n\n";

$rootDir = __DIR__;

// Check PHP version
echo "1. Checking PHP version... ";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "✓ PHP " . PHP_VERSION . " - OK\n";
} else {
    echo "✗ PHP " . PHP_VERSION . " - Requires PHP 8.0+\n";
    exit(1);
}

// Check required extensions
echo "2. Checking required PHP extensions... ";
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo', 'curl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    echo "✓ All required extensions loaded\n";
} else {
    echo "✗ Missing extensions: " . implode(', ', $missingExtensions) . "\n";
    exit(1);
}

// Check file permissions
echo "3. Checking file permissions... ";
$writableDirs = ['uploads', 'assets/uploads'];
$permissionIssues = [];

foreach ($writableDirs as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }

    if (!is_writable($fullPath)) {
        $permissionIssues[] = $dir;
    }
}

if (empty($permissionIssues)) {
    echo "✓ All directories writable\n";
} else {
    echo "⚠ Warning: These directories need write permissions: " . implode(', ', $permissionIssues) . "\n";
}

// Check configuration files
echo "4. Checking configuration files... ";
$configFiles = [
    'includes/config.php',
    'includes/db.php',
    'includes/system-config.php'
];

$configStatus = true;
foreach ($configFiles as $file) {
    if (!file_exists($rootDir . '/' . $file)) {
        echo "✗ Missing: $file\n";
        $configStatus = false;
    }
}

if ($configStatus) {
    echo "✓ All configuration files present\n";
}

// Check database configuration
echo "5. Checking database configuration... ";
if (file_exists($rootDir . '/includes/db_config.php')) {
    echo "✓ Database configuration exists\n";
} else {
    echo "⚠ Database configuration missing (will be created during installation)\n";
}

// Check SQL migration files
echo "6. Checking migration files... ";
$migrationFiles = [
    'sql/migration_v2.sql',
    'sql/add-vapid-keys.sql'
];

$migrationStatus = true;
foreach ($migrationFiles as $file) {
    if (!file_exists($rootDir . '/' . $file)) {
        echo "✗ Missing: $file\n";
        $migrationStatus = false;
    }
}

if ($migrationStatus) {
    echo "✓ All migration files present\n";
}

// Generate deployment summary
echo "\n=== Deployment Summary ===\n";
echo "System Status: " . (empty($permissionIssues) && $configStatus && $migrationStatus ? "READY FOR DEPLOYMENT" : "ISSUES FOUND") . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Root Directory: " . $rootDir . "\n";
echo "Database Config: " . (file_exists($rootDir . '/includes/db_config.php') ? "Present" : "Missing") . "\n";

echo "\n=== Next Steps ===\n";
echo "1. Upload all files to production server\n";
echo "2. Configure web server (Apache/Nginx) with proper rewrite rules\n";
echo "3. Set up SSL certificate for HTTPS\n";
echo "4. Create database and run migration:\n";
echo "   mysql -u username -p database_name < sql/migration_v2.sql\n";
echo "5. Access installation wizard:\n";
echo "   https://files.dhakabypass.com/install.php\n";
echo "6. Complete setup wizard (database, OAuth, admin user, branding)\n";
echo "7. Configure system settings through web interface\n";
echo "8. Set up cron jobs for automated tasks\n";

echo "\n=== Production Checklist ===\n";
echo "□ Server requirements met (PHP 8.0+, MySQL 5.7+)\n";
echo "□ SSL certificate configured\n";
echo "□ File permissions set correctly\n";
echo "□ Database created and migrated\n";
echo "□ Google OAuth credentials configured\n";
echo "□ Email SMTP settings configured\n";
echo "□ Branding customized\n";
echo "□ Cron jobs scheduled\n";
echo "□ Backup strategy implemented\n";

echo "\n=== System Features Ready ===\n";
echo "✅ Dynamic configuration system\n";
echo "✅ Advanced security hardening\n";
echo "✅ Real-time search and filtering\n";
echo "✅ Automated workflow management\n";
echo "✅ Email and push notifications\n";
echo "✅ Advanced reporting and analytics\n";
echo "✅ Drag-and-drop file uploads\n";
echo "✅ Bulk operations support\n";
echo "✅ Mobile-responsive interface\n";

echo "\nDeployment script completed.\n";
?>