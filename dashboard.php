<?php
/**
 * Main Dashboard
 * Single Page Application interface for file tracking
 */

require_once __DIR__ . '/includes/auth.php';
ensureAuthenticated();

$user = getCurrentUser();
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#667eea">
    <title>File Tracker - Dashboard</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/icon-192.png">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/app.css?v=1.0">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <div class="logo-icon">FT</div>
                    <h1 class="logo-text">File Tracker</h1>
                </div>
                
                <div class="user-section">
                    <div class="user-info">
                        <img src="<?php echo htmlspecialchars($user['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['name'])); ?>" 
                             alt="Avatar" 
                             class="user-avatar">
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                    <button class="btn-icon" onclick="showUserMenu()" id="user-menu-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/>
                        </svg>
                    </button>
                    
                    <!-- User Menu Dropdown -->
                    <div class="dropdown-menu" id="user-menu" style="display: none;">
                        <a href="#" onclick="editProfile(); return false;">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                            Edit Profile
                        </a>
                        <a href="logout.php">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="tabs-nav">
        <div class="container">
            <div class="tabs">
                <button class="tab active" data-view="my-tasks" onclick="switchTab('my-tasks')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    My Tasks
                </button>
                <button class="tab" data-view="all-tasks" onclick="switchTab('all-tasks')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 3a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 3a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                    All Tasks
                </button>
                <button class="tab" data-view="add-letter" onclick="switchTab('add-letter')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                    </svg>
                    Add Letter
                </button>
                <button class="tab" data-view="analytics" onclick="switchTab('analytics')">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                    </svg>
                    Analytics
                </button>
            </div>
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

    <!-- Pass user data to JavaScript -->
    <script>
        window.USER_DATA = <?php echo json_encode([
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'department' => $user['department'],
            'role' => $user['role'],
            'avatar' => $user['avatar_url']
        ]); ?>;
        window.CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    </script>

    <!-- Main Application Script -->
    <script src="assets/js/app.js?v=1.0"></script>
    
    <!-- Register Service Worker for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.error('Service Worker registration failed:', err));
        }
    </script>
</body>
</html>
