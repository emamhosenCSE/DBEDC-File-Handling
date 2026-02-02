<?php
/**
 * Main Dashboard
 * Single Page Application interface for file tracking
 * Enhanced with Letters, Departments, Users, Settings, Notifications tabs
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
ensureAuthenticated();

$user = getCurrentUser();
$csrfToken = generateCSRFToken();

// Get branding settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'branding' AND is_public = TRUE");
    $branding = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $branding = [];
}

$companyName = $branding['company_name'] ?? 'DBEDC File Tracker';
$companyLogo = $branding['company_logo'] ?? null;
$primaryColor = $branding['primary_color'] ?? '#667eea';
$secondaryColor = $branding['secondary_color'] ?? '#764ba2';

// Get stakeholders for forms
try {
    $stmt = $pdo->query("SELECT id, name, code, color FROM stakeholders WHERE is_active = TRUE ORDER BY display_order");
    $stakeholders = $stmt->fetchAll();
} catch (Exception $e) {
    $stakeholders = [];
}

// Get departments for forms
try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = TRUE ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (Exception $e) {
    $departments = [];
}

// Get unread notification count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user['id']]);
    $unreadCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $unreadCount = 0;
}

// Get VAPID public key for push notifications
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'");
    $vapidPublicKey = $stmt->fetchColumn() ?: null;
} catch (Exception $e) {
    $vapidPublicKey = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo htmlspecialchars($primaryColor); ?>">
    <title><?php echo htmlspecialchars($companyName); ?> - Dashboard</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/icon-192.png">
    <link rel="apple-touch-icon" href="assets/icon-192.png">
    
    <!-- Dynamic Theme Colors -->
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($primaryColor); ?>;
            --secondary-color: <?php echo htmlspecialchars($secondaryColor); ?>;
            --gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
    </style>
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/app.css?v=2.0">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <?php if ($companyLogo): ?>
                        <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo" class="logo-image">
                    <?php else: ?>
                        <div class="logo-icon">FT</div>
                    <?php endif; ?>
                    <h1 class="logo-text"><?php echo htmlspecialchars($companyName); ?></h1>
                </div>
                
                <div class="header-actions">
                    <!-- Quick Search -->
                    <div class="header-search">
                        <input type="text" id="global-search" placeholder="Search letters, tasks..." class="search-input-sm">
                        <svg class="search-icon" width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    
                    <!-- Notifications Bell -->
                    <button class="btn-icon notification-btn" onclick="switchTab('notifications')" title="Notifications">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                        </svg>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge" id="notification-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- User Section -->
                    <div class="user-section">
                        <div class="user-info" onclick="showUserMenu()">
                            <img src="<?php echo htmlspecialchars($user['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=' . substr($primaryColor, 1) . '&color=fff'); ?>" 
                                 alt="Avatar" 
                                 class="user-avatar">
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                            </div>
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        
                        <!-- User Menu Dropdown -->
                        <div class="dropdown-menu" id="user-menu" style="display: none;">
                            <div class="dropdown-header">
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <a href="#" onclick="editProfile(); return false;">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                </svg>
                                My Profile
                            </a>
                            <a href="#" onclick="switchTab('notifications'); hideUserMenu(); return false;">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"/>
                                </svg>
                                Notifications
                                <?php if ($unreadCount > 0): ?>
                                    <span class="menu-badge"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <?php if ($user['role'] === 'ADMIN'): ?>
                            <a href="#" onclick="switchTab('settings'); hideUserMenu(); return false;">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                                </svg>
                                Settings
                            </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="logout-link">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="tabs-nav">
        <div class="container">
            <div class="tabs" id="main-tabs">
                <!-- Dashboard -->
                <button class="tab active" data-view="dashboard" onclick="switchTab('dashboard')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                    <span>Dashboard</span>
                </button>
                
                <!-- Letters -->
                <button class="tab" data-view="letters" onclick="switchTab('letters')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                    </svg>
                    <span>Letters</span>
                </button>
                
                <!-- My Tasks -->
                <button class="tab" data-view="my-tasks" onclick="switchTab('my-tasks')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>My Tasks</span>
                </button>
                
                <!-- All Tasks (Admin/Manager only) -->
                <?php if (in_array($user['role'], ['ADMIN', 'MANAGER'])): ?>
                <button class="tab" data-view="all-tasks" onclick="switchTab('all-tasks')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 3a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>All Tasks</span>
                </button>
                <?php endif; ?>
                
                <!-- Departments -->
                <button class="tab" data-view="departments" onclick="switchTab('departments')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                    </svg>
                    <span>Departments</span>
                </button>
                
                <!-- Users (Admin/Manager only) -->
                <?php if (in_array($user['role'], ['ADMIN', 'MANAGER'])): ?>
                <button class="tab" data-view="users" onclick="switchTab('users')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                    <span>Users</span>
                </button>
                <?php endif; ?>
                
                <!-- Analytics -->
                <button class="tab" data-view="analytics" onclick="switchTab('analytics')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                    </svg>
                    <span>Analytics</span>
                </button>
                
                <!-- Settings (Admin only) -->
                <?php if ($user['role'] === 'ADMIN'): ?>
                <button class="tab" data-view="settings" onclick="switchTab('settings')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                    <span>Settings</span>
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container">
            <div id="app-content">
                <!-- Dynamic content loaded here -->
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Notifications -->
    <div id="toast-container"></div>

    <!-- Modal Container -->
    <div id="modal-container"></div>

    <!-- Pass data to JavaScript -->
    <script>
        window.USER_DATA = <?php echo json_encode([
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'department_id' => $user['department_id'] ?? null,
            'role' => $user['role'],
            'avatar' => $user['avatar_url'],
            'email_notifications' => $user['email_notifications'] ?? true,
            'push_notifications' => $user['push_notifications'] ?? true
        ]); ?>;
        window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        window.STAKEHOLDERS = <?php echo json_encode($stakeholders); ?>;
        window.DEPARTMENTS = <?php echo json_encode($departments); ?>;
        window.VAPID_PUBLIC_KEY = <?php echo json_encode($vapidPublicKey); ?>;
        window.BRANDING = <?php echo json_encode([
            'company_name' => $companyName,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor
        ]); ?>;
    </script>

    <!-- Main Application Script -->
    <script src="assets/js/app.js?v=2.0"></script>
    
    <!-- Register Service Worker for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(reg => {
                    console.log('Service Worker registered');
                    // Check for push notification support
                    if ('PushManager' in window && window.VAPID_PUBLIC_KEY) {
                        initPushNotifications(reg);
                    }
                })
                .catch(err => console.error('Service Worker registration failed:', err));
        }
        
        // Initialize push notifications
        async function initPushNotifications(registration) {
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted' && window.USER_DATA.push_notifications) {
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY)
                    });
                    
                    // Send subscription to server
                    await fetch('api/push-subscribe.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN
                        },
                        body: JSON.stringify(subscription)
                    });
                }
            } catch (err) {
                console.log('Push notification setup failed:', err);
            }
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
    </script>
</body>
</html>
