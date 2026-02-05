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

// Cache Control Headers - Disable all caching for login page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com https://open.weixin.qq.com https://res.wx.qq.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://accounts.google.com https://oauth2.googleapis.com https://api.weixin.qq.com https://open.weixin.qq.com; frame-src 'self' https://open.weixin.qq.com");

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

// Build WeChat OAuth URL
$wechatAuthUrl = getWeChatAuthUrl();
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
        
        /* Login Tabs */
        .login-tabs {
            display: flex;
            margin-bottom: 24px;
            border-radius: 8px;
            overflow: hidden;
            background: #f7fafc;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: transparent;
            color: #718096;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .login-tab {
            display: none;
        }
        
        .login-tab.active {
            display: block;
        }
        
        /* OAuth Buttons */
        .oauth-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .oauth-btn {
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
        
        .oauth-btn:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .oauth-icon {
            width: 20px;
            height: 20px;
        }
        
        .google-btn .oauth-icon path {
            fill: #4285F4;
        }
        
        .wechat-btn .oauth-icon path {
            fill: #07C160;
        }
        
        /* Email Login Form */
        .email-login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }
        
        .form-group input {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .login-btn {
            background: var(--gradient);
            border: none;
            border-radius: 8px;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            color: #e53e3e;
            font-size: 14px;
            text-align: center;
            padding: 8px;
            background: #fed7d7;
            border-radius: 4px;
            border: 1px solid #feb2b2;
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
        
        <?php if ((GOOGLE_CLIENT_ID === 'your_google_client_id' && WECHAT_APP_ID === 'your_wechat_app_id') && !EMAIL_LOGIN_ENABLED): ?>
        <div class="setup-notice">
            <strong>⚠️ Setup Required</strong>
            Please configure OAuth credentials in the settings or config files before using the system.
        </div>
        <?php endif; ?>

        <!-- Login Tabs -->
        <div class="login-tabs">
            <button class="tab-btn active" data-tab="oauth">Sign In</button>
            <?php if (EMAIL_LOGIN_ENABLED): ?>
            <button class="tab-btn" data-tab="email">Email Login</button>
            <?php endif; ?>
        </div>

        <!-- OAuth Login Tab -->
        <div id="oauth-tab" class="login-tab active">
            <div class="oauth-buttons">
                <?php if (GOOGLE_CLIENT_ID !== 'your_google_client_id'): ?>
                <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="oauth-btn google-btn">
                    <svg class="oauth-icon" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign in with Google
                </a>
                <?php endif; ?>

                <?php if (WECHAT_APP_ID !== 'your_wechat_app_id'): ?>
                <a href="<?php echo htmlspecialchars($wechatAuthUrl); ?>" class="oauth-btn wechat-btn">
                    <svg class="oauth-icon" viewBox="0 0 24 24">
                        <path fill="#07C160" d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.924-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18 0 .659-.52 1.188-1.162 1.188-.642 0-1.162-.529-1.162-1.188 0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18 0 .659-.52 1.188-1.162 1.188-.642 0-1.162-.529-1.162-1.188 0-.651.52-1.18 1.162-1.18z"/>
                        <path fill="#07C160" d="M23.718 18.003c0-2.206-1.036-4.159-2.694-5.458a.62.62 0 0 0-.262-.135l-1.41-.39a.292.292 0 0 0-.213.048.298.298 0 0 0-.141.181l-.48 1.41a.857.857 0 0 0 .096.717c.723.9 1.146 2.018 1.146 3.207 0 .276-.027.543-.05.811 2.578-.857 4.972.157 6.446 1.932 1.415 1.703 1.98 3.882 1.838 5.853 3.583-.576 6.348-4.196 6.348-8.924C24.312 18.545 24.03 18.003 23.718 18.003z"/>
                    </svg>
                    Sign in with WeChat
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Login Tab -->
        <?php if (EMAIL_LOGIN_ENABLED): ?>
        <div id="email-tab" class="login-tab">
            <form id="email-login-form" class="email-login-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="login-btn">Sign In</button>
                <div id="login-error" class="error-message" style="display: none;"></div>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            By signing in, you agree to use this system responsibly<br>
            © <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all tabs and buttons
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked button and corresponding tab
                btn.classList.add('active');
                const tabId = btn.dataset.tab + '-tab';
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Email login functionality
        const emailForm = document.getElementById('email-login-form');
        if (emailForm) {
            emailForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const submitBtn = emailForm.querySelector('.login-btn');
                const errorDiv = document.getElementById('login-error');
                
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing in...';
                errorDiv.style.display = 'none';
                
                try {
                    const formData = new FormData(emailForm);
                    const response = await fetch('api/auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: formData.get('email'),
                            password: formData.get('password')
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        window.location.href = result.redirect || 'dashboard.php';
                    } else {
                        throw new Error(result.error || 'Login failed');
                    }
                    
                } catch (error) {
                    errorDiv.textContent = error.message || 'Login failed. Please try again.';
                    errorDiv.style.display = 'block';
                } finally {
                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Sign In';
                }
            });
        }
    </script>
</body>
</html>
