<?php
/**
 * Login Page - Google OAuth
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to https://console.cloud.google.com
 * 2. Create a new project or select existing
 * 3. Enable Google+ API
 * 4. Create OAuth 2.0 credentials
 * 5. Add authorized redirect URI: https://yourdomain.com/callback.php
 * 6. Download JSON and add credentials below
 */

require_once __DIR__ . '/includes/auth.php';
ensureSystemInstalled();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://accounts.google.com https://oauth2.googleapis.com");

// Include configuration
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/system-config.php';

// Load branding if database is available
$companyName = getSystemConfig('company_name', 'File Tracker');
$primaryColor = getSystemConfig('primary_color', '#667eea');
$secondaryColor = getSystemConfig('secondary_color', '#764ba2');

try {
    if (file_exists(__DIR__ . '/includes/db_config.php')) {
        require_once __DIR__ . '/includes/db.php';
        if ($pdo !== null) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'branding' AND is_public = TRUE");
            $branding = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $companyName = $branding['company_name'] ?? getSystemConfig('company_name', 'File Tracker');
            $primaryColor = $branding['primary_color'] ?? getSystemConfig('primary_color', '#667eea');
            $secondaryColor = $branding['secondary_color'] ?? getSystemConfig('secondary_color', '#764ba2');
        }
    }
} catch (Exception $e) {
    // Database not ready, use defaults
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Generate state token for security
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

// Build Google OAuth URL
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $_SESSION['oauth_state'],
    'access_type' => 'online',
    'prompt' => 'select_account'
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($primaryColor); ?>;
            --secondary-color: <?php echo htmlspecialchars($secondaryColor); ?>;
            --gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 48px;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
            color: white;
            font-weight: bold;
        }
        
        h1 {
            font-size: 28px;
            color: #1a202c;
            margin-bottom: 8px;
        }
        
        p {
            color: #718096;
            margin-bottom: 32px;
            font-size: 14px;
        }
        
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            color: #1a202c;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            width: 100%;
        }
        
        .google-btn:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .google-icon {
            width: 20px;
            height: 20px;
        }
        
        .footer {
            margin-top: 32px;
            color: #718096;
            font-size: 12px;
        }
        
        .setup-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #856404;
            text-align: left;
        }
        
        .setup-notice strong {
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">FT</div>
        <h1><?php echo htmlspecialchars($companyName); ?></h1>
        <p>Sign in to manage your documents and tasks</p>
        
        <?php if (GOOGLE_CLIENT_ID === 'YOUR_CLIENT_ID.apps.googleusercontent.com'): ?>
        <div class="setup-notice">
            <strong>⚠️ Setup Required</strong>
            Please update Google OAuth credentials in login.php before using the system.
        </div>
        <?php endif; ?>
        
        <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="google-btn">
            <svg class="google-icon" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Sign in with Google
        </a>
        
        <div class="footer">
            By signing in, you agree to use this system responsibly<br>
            © <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>
        </div>
    </div>
</body>
</html>
