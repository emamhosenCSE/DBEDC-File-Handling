<?php
/**
 * Installation Wizard
 * Sets up the File Tracker system on first run
 */

session_start();

// Prevent access if already installed
if (isset($_SESSION['user_id']) || (isset($_GET['check']) && isSystemInstalled())) {
    header('Location: dashboard.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleInstallStep($step);
}

function isSystemInstalled() {
    try {
        require_once __DIR__ . '/includes/config.php';
        require_once __DIR__ . '/includes/db.php';
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'system_installed'");
        return $stmt->fetchColumn() === '1';
    } catch (Exception $e) {
        return false;
    }
}

function handleInstallStep($step) {
    global $error, $success;
    
    switch ($step) {
        case 1: // Database Setup
            handleDatabaseSetup();
            break;
        case 2: // OAuth Setup
            handleOAuthSetup();
            break;
        case 3: // Admin Setup
            handleAdminSetup();
            break;
        case 4: // Branding
            handleBrandingSetup();
            break;
        case 5: // Email Setup
            handleEmailSetup();
            break;
        case 6: // Final Setup
            handleFinalSetup();
            break;
    }
}

function handleDatabaseSetup() {
    global $error, $success;
    
    $host = $_POST['db_host'] ?? '';
    $name = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    
    if (empty($host) || empty($name) || empty($user)) {
        $error = 'All database fields are required';
        return;
    }
    
    try {
        // Test connection
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Check if tables already exist and offer to clear them
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() > 0) {
            // Drop all existing tables in correct order (child tables first)
            $tables = [
                'task_updates', 'push_subscriptions', 'email_queue', 'notifications',
                'activities', 'tasks', 'user_preferences', 'users', 'letters',
                'departments', 'stakeholders', 'settings'
            ];

            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }
        }
        
        // Save database config
        $configContent = "<?php
define('DB_HOST', '" . addslashes($host) . "');
define('DB_NAME', '" . addslashes($name) . "');
define('DB_USER', '" . addslashes($user) . "');
define('DB_PASS', '" . addslashes($pass) . "');
";
        
        file_put_contents(__DIR__ . '/includes/db_config.php', $configContent);
        
        // Run migration (use cleaned version)
        $migration = file_get_contents(__DIR__ . '/sql/migration.sql');
        $pdo->exec($migration);
        
        $success = 'Database setup completed successfully!';
        $_SESSION['install_step'] = 2;
        header('Location: install.php?step=2');
        exit;
        
    } catch (Exception $e) {
        $error = 'Database connection failed: ' . $e->getMessage();
    }
}

function handleAdminSetup() {
    global $error, $success;
    
    $adminEmail = trim($_POST['admin_email'] ?? '');
    
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid Google email address';
        return;
    }
    
    try {
        require_once __DIR__ . '/includes/db_config.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Save admin email for later verification
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
        $stmt->execute(['admin_email', $adminEmail, 'system']);
        
        $success = 'Admin email configured successfully!';
        $_SESSION['install_step'] = 4;
        header('Location: install.php?step=4');
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to save admin email: ' . $e->getMessage();
    }
}

function handleOAuthSetup() {
    global $error, $success;
    
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $redirectUri = trim($_POST['redirect_uri'] ?? '');
    
    if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
        $error = 'All OAuth fields are required';
        return;
    }
    
    try {
        require_once __DIR__ . '/includes/db_config.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Save OAuth settings
        $settings = [
            ['google_client_id', $clientId, 'auth'],
            ['google_client_secret', $clientSecret, 'auth'],
            ['google_redirect_uri', $redirectUri, 'auth']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (id, setting_key, setting_value, setting_group) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $setting) {
            $id = generateULID();
            $stmt->execute([$id, $setting[0], $setting[1], $setting[2]]);
        }
        
        $success = 'OAuth configuration saved successfully!';
        $_SESSION['install_step'] = 4;
        header('Location: install.php?step=4');
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to save OAuth settings: ' . $e->getMessage();
    }
}

function handleBrandingSetup() {
    global $error, $success;
    
    $companyName = trim($_POST['company_name'] ?? '');
    $primaryColor = trim($_POST['primary_color'] ?? '#667eea');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#764ba2');
    
    if (empty($companyName)) {
        $error = 'Company name is required';
        return;
    }
    
    try {
        require_once __DIR__ . '/includes/db_config.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Save branding settings
        $settings = [
            ['company_name', $companyName, 'branding'],
            ['primary_color', $primaryColor, 'branding'],
            ['secondary_color', $secondaryColor, 'branding']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
        
        $success = 'Branding setup completed successfully!';
        $_SESSION['install_step'] = 5;
        header('Location: install.php?step=5');
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to save branding settings: ' . $e->getMessage();
    }
}

function handleEmailSetup() {
    global $error, $success;
    
    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = trim($_POST['smtp_port'] ?? '587');
    $smtpSecure = trim($_POST['smtp_secure'] ?? 'tls');
    $smtpUsername = trim($_POST['smtp_username'] ?? '');
    $smtpPassword = trim($_POST['smtp_password'] ?? '');
    $fromEmail = trim($_POST['from_email'] ?? '');
    $fromName = trim($_POST['from_name'] ?? '');
    
    try {
        require_once __DIR__ . '/includes/db_config.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Save email settings
        $settings = [
            ['smtp_host', $smtpHost, 'email'],
            ['smtp_port', $smtpPort, 'email'],
            ['smtp_secure', $smtpSecure, 'email'],
            ['smtp_username', $smtpUsername, 'email'],
            ['smtp_password', $smtpPassword, 'email'],
            ['smtp_from_email', $fromEmail, 'email'],
            ['smtp_from_name', $fromName, 'email']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
        
        $success = 'Email configuration saved successfully!';
        $_SESSION['install_step'] = 6;
        header('Location: install.php?step=6');
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to save email settings: ' . $e->getMessage();
    }
}

function handleFinalSetup() {
    global $error, $success;
    
    try {
        require_once __DIR__ . '/includes/db_config.php';
        require_once __DIR__ . '/includes/db.php';
        
        // Generate VAPID keys
        require_once __DIR__ . '/generate-vapid.php';
        
        // Set up default notification settings
        $notificationSettings = [
            ['email_notifications_enabled', '1', 'notifications'],
            ['push_notifications_enabled', '1', 'notifications'],
            ['task_assignment_notification', '1', 'notifications'],
            ['task_completion_notification', '1', 'notifications'],
            ['letter_upload_notification', '1', 'notifications'],
            ['deadline_reminder_notification', '1', 'notifications'],
            ['system_maintenance_notification', '1', 'notifications']
        ];
        
        // Set up default push notification settings
        $pushSettings = [
            ['push_enabled', '1', 'push'],
            ['push_task_assignments', '1', 'push'],
            ['push_task_updates', '1', 'push'],
            ['push_deadlines', '1', 'push'],
            ['push_system_alerts', '1', 'push']
        ];
        
        // Set up default system settings
        $systemSettings = [
            ['timezone', 'UTC', 'system'],
            ['date_format', 'Y-m-d', 'system'],
            ['time_format', 'H:i', 'system'],
            ['items_per_page', '25', 'system'],
            ['file_upload_max_size', '10485760', 'system'], // 10MB
            ['session_timeout', '3600', 'system'], // 1 hour
            ['maintenance_mode', '0', 'system']
        ];
        
        // Set up default security settings
        $securitySettings = [
            ['password_min_length', '8', 'security'],
            ['login_attempts_max', '5', 'security'],
            ['account_lockout_duration', '900', 'security'], // 15 minutes
            ['session_regenerate_frequency', '300', 'security'], // 5 minutes
            ['csrf_protection_enabled', '1', 'security'],
            ['xss_protection_enabled', '1', 'security']
        ];
        
        // Insert all default settings
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $allSettings = array_merge($notificationSettings, $pushSettings, $systemSettings, $securitySettings);
        foreach ($allSettings as $setting) {
            $stmt->execute($setting);
        }
        
        // Mark system as installed
        $stmt->execute(['system_installed', '1', 'system']);
        
        // Create default stakeholders
        $stakeholders = [
            ['IE', 'Internal Affairs', '#3B82F6'],
            ['JV', 'Joint Venture', '#10B981'],
            ['RHD', 'Roads & Highways', '#F59E0B']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO stakeholders (id, name, code, color, display_order) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
        foreach ($stakeholders as $i => $stakeholder) {
            $stmt->execute([generateULID(), $stakeholder[1], $stakeholder[0], $stakeholder[2], $i + 1]);
        }
        
        // Create default departments
        $departments = [
            ['Administration', 'Administrative department'],
            ['Operations', 'Operations department'],
            ['Finance', 'Financial department'],
            ['IT', 'Information Technology department']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO departments (id, name, description, display_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
        foreach ($departments as $i => $dept) {
            $stmt->execute([generateULID(), $dept[0], $dept[1], $i + 1]);
        }
        
        $success = 'Installation completed successfully! All settings configured. Redirecting to login...';
        
        // Clear install session
        unset($_SESSION['install_step']);
        
        // Redirect after 3 seconds
        header('Refresh: 3; url=login.php');
        
    } catch (Exception $e) {
        $error = 'Final setup failed: ' . $e->getMessage();
    }
}

function renderStep($step) {
    global $error, $success;
    
    $steps = [
        1 => 'Database Setup',
    2 => 'OAuth Configuration',
    3 => 'Admin Setup',
        6 => 'Final Setup'
    ];
    
    $title = $steps[$step] ?? 'Installation';
    
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$title - File Tracker Installation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 48px; max-width: 600px; width: 100%; }
        .logo { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 32px; color: white; font-weight: bold; }
        h1 { text-align: center; color: #1a202c; margin-bottom: 8px; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 32px; }
        .step { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; color: #718096; display: flex; align-items: center; justify-content: center; font-weight: bold; margin: 0 8px; }
        .step.active { background: #667eea; color: white; }
        .step.completed { background: #10b981; color: white; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #374151; font-weight: 500; }
        input, select { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        .btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 16px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .error { background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #f0fdf4; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .note { background: #fefce8; color: #ca8a04; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='logo'>FT</div>
        <h1>$title</h1>
        
        <div class='step-indicator'>";
    
    for ($i = 1; $i <= 6; $i++) {
        $class = 'step';
        if ($i < $step) $class .= ' completed';
        elseif ($i == $step) $class .= ' active';
        echo "<div class='$class'>$i</div>";
    }
    
    echo "</div>";
    
    if ($error) echo "<div class='error'>$error</div>";
    if ($success) echo "<div class='success'>$success</div>";
    
    renderStepContent($step);
    
    echo "</div></body></html>";
}

function renderStepContent($step) {
    switch ($step) {
        case 1:
            echo "
            <div class='note'>
                <strong>Database Setup:</strong> Enter your MySQL database credentials. The installer will create all necessary tables.
            </div>
            <form method='post'>
                <div class='form-group'>
                    <label>Database Host</label>
                    <input type='text' name='db_host' value='localhost' required>
                </div>
                <div class='form-group'>
                    <label>Database Name</label>
                    <input type='text' name='db_name' placeholder='file_tracker' required>
                </div>
                <div class='form-group'>
                    <label>Database Username</label>
                    <input type='text' name='db_user' value='root' required>
                </div>
                <div class='form-group'>
                    <label>Database Password</label>
                    <input type='password' name='db_pass'>
                </div>
                <button type='submit' class='btn'>Setup Database</button>
            </form>";
            break;
            
        case 2:
            echo "
            <div class='note'>
                <strong>Google OAuth Setup:</strong> Configure Google OAuth for user authentication. 
                <a href='https://console.cloud.google.com/' target='_blank'>Create credentials here</a>.
            </div>
            <form method='post'>
                <div class='form-group'>
                    <label>Client ID</label>
                    <input type='text' name='client_id' required>
                </div>
                <div class='form-group'>
                    <label>Client Secret</label>
                    <input type='password' name='client_secret' required>
                </div>
                <div class='form-group'>
                    <label>Redirect URI</label>
                    <input type='url' name='redirect_uri' placeholder='https://yourdomain.com/callback.php' required>
                </div>
                <button type='submit' class='btn'>Save OAuth Settings</button>
            </form>";
            break;
            
        case 3:
            echo "
            <div class='note'>
                <strong>Admin Setup:</strong> Enter the Google email address that will be the system administrator. The first person to log in with this email will become the admin.
            </div>
            <form method='post'>
                <div class='form-group'>
                    <label>Admin Google Email</label>
                    <input type='email' name='admin_email' placeholder='admin@yourcompany.com' required>
                </div>
                <button type='submit' class='btn'>Set Admin Email</button>
            </form>";
            break;
            
        case 4:
            echo "
            <div class='note'>
                <strong>Branding:</strong> Customize the appearance of your File Tracker system.
            </div>
            <form method='post'>
                <div class='form-group'>
                    <label>Company Name</label>
                    <input type='text' name='company_name' placeholder='DBEDC File Tracker' required>
                </div>
                <div class='form-group'>
                    <label>Primary Color</label>
                    <input type='color' name='primary_color' value='#667eea'>
                </div>
                <div class='form-group'>
                    <label>Secondary Color</label>
                    <input type='color' name='secondary_color' value='#764ba2'>
                </div>
                <button type='submit' class='btn'>Save Branding</button>
            </form>";
            break;
            
        case 5:
            echo "
            <div class='note'>
                <strong>Email Configuration:</strong> Setup SMTP for sending notifications and reports. Leave blank to skip.
            </div>
            <form method='post'>
                <div class='form-group'>
                    <label>SMTP Host</label>
                    <input type='text' name='smtp_host' placeholder='smtp.gmail.com'>
                </div>
                <div class='form-group'>
                    <label>SMTP Port</label>
                    <input type='number' name='smtp_port' value='587'>
                </div>
                <div class='form-group'>
                    <label>Security</label>
                    <select name='smtp_secure'>
                        <option value='tls'>TLS</option>
                        <option value='ssl'>SSL</option>
                    </select>
                </div>
                <div class='form-group'>
                    <label>SMTP Username</label>
                    <input type='text' name='smtp_username'>
                </div>
                <div class='form-group'>
                    <label>SMTP Password</label>
                    <input type='password' name='smtp_password'>
                </div>
                <div class='form-group'>
                    <label>From Email</label>
                    <input type='email' name='from_email' placeholder='noreply@yourdomain.com'>
                </div>
                <div class='form-group'>
                    <label>From Name</label>
                    <input type='text' name='from_name' placeholder='File Tracker'>
                </div>
                <button type='submit' class='btn'>Save Email Settings</button>
            </form>";
            break;
            
        case 6:
            echo "
            <div class='note'>
                <strong>Final Setup:</strong> This will configure all system settings and complete the installation.
            </div>
            <form method='post'>
                <p>Click the button below to complete the installation. This will:</p>
                <ul style='margin: 20px 0; padding-left: 20px;'>
                    <li>Generate VAPID keys for push notifications</li>
                    <li>Configure notification and email settings</li>
                    <li>Set up security and system preferences</li>
                    <li>Create default stakeholders and departments</li>
                    <li>Mark the system as installed</li>
                </ul>
                <p style='margin-top: 20px;'><strong>Note:</strong> After installation, log in with your Google account to become the system administrator.</p>
                <button type='submit' class='btn'>Complete Installation</button>
            </form>";
            break;
    }
}

renderStep($step);
?>