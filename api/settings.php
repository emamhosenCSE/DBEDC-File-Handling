<?php
/**
 * Settings API Endpoint
 * Handles system settings CRUD operations
 */

require_once __DIR__ . '/../includes/api-bootstrap.php';

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'PATCH':
    case 'PUT':
        handleUpdate();
        break;
    case 'POST':
        handlePost();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Fetch settings
 */
function handleGet() {
    global $pdo, $user;
    
    $group = $_GET['group'] ?? null;
    $key = $_GET['key'] ?? null;
    
    try {
        // Build query based on user role
        $isAdmin = $user['role'] === 'ADMIN';
        
        if ($key) {
            // Get single setting
            $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $setting = $stmt->fetch();
            
            if (!$setting) {
                jsonError('Setting not found', 404);
            }
            
            // Non-admins can only see public settings
            if (!$isAdmin && !$setting['is_public']) {
                jsonError('Access denied', 403);
            }
            
            jsonResponse($setting);
        }
        
        // Get multiple settings
        $sql = "SELECT * FROM settings";
        $params = [];
        $conditions = [];
        
        if ($group) {
            $conditions[] = "setting_group = ?";
            $params[] = $group;
        }
        
        // Non-admins can only see public settings
        if (!$isAdmin) {
            $conditions[] = "is_public = TRUE";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY setting_group, setting_key";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $settings = $stmt->fetchAll();
        
        // Group settings by group
        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting['setting_group']][$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['data_type'],
                'description' => $setting['description']
            ];
        }
        
        jsonResponse([
            'settings' => $settings,
            'grouped' => $grouped
        ]);
        
    } catch (PDOException $e) {
        error_log("Settings GET error: " . $e->getMessage());
        jsonError('Failed to fetch settings', 500);
    }
}

/**
 * PATCH/PUT - Update settings
 */
function handleUpdate() {
    global $pdo, $user;
    
    // Only admins can update settings
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can update settings', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['settings'])) {
        jsonError('Invalid input', 400);
    }
    
    try {
        $pdo->beginTransaction();
        $updated = 0;
        
        foreach ($input['settings'] as $key => $value) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
            $updated += $stmt->rowCount();
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity(
            $user['id'],
            'settings_updated',
            'settings',
            'system',
            'System settings updated',
            ['keys' => array_keys($input['settings'])]
        );
        
        jsonResponse([
            'success' => true,
            'updated' => $updated,
            'message' => 'Settings updated successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Settings UPDATE error: " . $e->getMessage());
        jsonError('Failed to update settings', 500);
    }
}

/**
 * POST - Create new setting or special actions
 */
function handlePost() {
    global $pdo, $user;
    
    // Only admins can create settings
    if ($user['role'] !== 'ADMIN') {
        jsonError('Only administrators can create settings', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Handle special actions
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'test_email':
                handleTestEmail($input);
                break;
            case 'generate_vapid':
                handleGenerateVAPID();
                break;
            case 'upload_logo':
                handleLogoUpload();
                break;
            default:
                jsonError('Unknown action', 400);
        }
        return;
    }
    
    // Create new setting
    if (!isset($input['key']) || !isset($input['value'])) {
        jsonError('Key and value are required', 400);
    }
    
    try {
        $id = generateULID();
        $stmt = $pdo->prepare("
            INSERT INTO settings (id, setting_key, setting_value, setting_group, data_type, is_public, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $input['key'],
            $input['value'],
            $input['group'] ?? 'general',
            $input['type'] ?? 'string',
            $input['is_public'] ?? false,
            $input['description'] ?? null
        ]);
        
        jsonResponse([
            'success' => true,
            'id' => $id,
            'message' => 'Setting created successfully'
        ]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonError('Setting key already exists', 409);
        }
        error_log("Settings POST error: " . $e->getMessage());
        jsonError('Failed to create setting', 500);
    }
}

/**
 * Test email configuration
 */
function handleTestEmail($input) {
    global $user;
    
    require_once __DIR__ . '/../includes/email.php';
    
    $testEmail = $input['email'] ?? $user['email'];
    
    // Test SMTP connection first
    $connectionTest = testSMTPConnection();
    
    if (!$connectionTest['success']) {
        jsonResponse([
            'success' => false,
            'message' => $connectionTest['message']
        ]);
        return;
    }
    
    // Send test email
    $systemConfig = getSystemConfig();
    $companyName = $systemConfig['company_name'] ?? 'File Tracker';
    $primaryColor = $systemConfig['primary_color'] ?? '#667eea';
    
    $result = sendEmail(
        $testEmail,
        $user['name'],
        $companyName . ' - Test Email',
        '<div style="font-family: Arial, sans-serif; padding: 20px;">
            <h2 style="color: ' . $primaryColor . ';">Test Email</h2>
            <p>This is a test email from ' . $companyName . '.</p>
            <p>If you received this email, your SMTP configuration is working correctly.</p>
            <p style="color: #6b7280; font-size: 12px;">Sent at: ' . date('Y-m-d H:i:s') . '</p>
        </div>'
    );
    
    jsonResponse([
        'success' => $result,
        'message' => $result ? 'Test email sent successfully' : 'Failed to send test email'
    ]);
}

/**
 * Generate VAPID keys for push notifications
 */
function handleGenerateVAPID() {
    require_once __DIR__ . '/../includes/push.php';
    
    $keys = generateVAPIDKeys();
    
    if (!$keys) {
        jsonError('Failed to generate VAPID keys', 500);
    }
    
    // Save keys to settings
    if (saveVAPIDKeys($keys['publicKey'], $keys['privateKey'])) {
        jsonResponse([
            'success' => true,
            'publicKey' => $keys['publicKey'],
            'message' => 'VAPID keys generated and saved successfully'
        ]);
    } else {
        jsonError('Failed to save VAPID keys', 500);
    }
}

/**
 * Handle logo upload
 */
function handleLogoUpload() {
    global $pdo;
    
    if (!isset($_FILES['logo'])) {
        jsonError('No file uploaded', 400);
    }
    
    $file = $_FILES['logo'];
    
    // Validate file
    $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        jsonError('Invalid file type. Allowed: PNG, JPEG, GIF, SVG, WebP', 400);
    }
    
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        jsonError('File too large. Maximum size: 2MB', 400);
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '.' . $extension;
    $uploadPath = __DIR__ . '/../assets/uploads/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Update setting
        $logoUrl = 'assets/uploads/' . $filename;
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_logo'")
            ->execute([$logoUrl]);
        
        jsonResponse([
            'success' => true,
            'url' => $logoUrl,
            'message' => 'Logo uploaded successfully'
        ]);
    } else {
        jsonError('Failed to upload logo', 500);
    }
}

/**
 * Get branding settings (public endpoint)
 */
function getBrandingSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'branding' AND is_public = TRUE");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}
