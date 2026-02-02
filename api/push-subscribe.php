<?php
/**
 * Push Subscription API Endpoint
 * Handles Web Push subscription management
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push.php';
ensureAuthenticated();
ensureCSRFValid();

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Get VAPID public key or subscriptions
 */
function handleGet() {
    global $user;
    
    // Get VAPID public key
    if (isset($_GET['vapid'])) {
        $vapid = getVAPIDKeys();
        
        if (!$vapid || !$vapid['publicKey']) {
            jsonResponse([
                'configured' => false,
                'message' => 'Push notifications not configured'
            ]);
        }
        
        jsonResponse([
            'configured' => true,
            'publicKey' => $vapid['publicKey']
        ]);
    }
    
    // Get user's subscriptions
    $subscriptions = getUserPushSubscriptions($user['id']);
    
    // Remove sensitive data
    foreach ($subscriptions as &$sub) {
        unset($sub['p256dh_key']);
        unset($sub['auth_key']);
    }
    
    jsonResponse([
        'subscriptions' => $subscriptions,
        'count' => count($subscriptions)
    ]);
}

/**
 * POST - Subscribe to push notifications
 */
function handlePost() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate subscription data
    if (empty($input['endpoint']) || empty($input['keys']['p256dh']) || empty($input['keys']['auth'])) {
        jsonError('Invalid subscription data', 400);
    }
    
    // Subscribe
    $subscriptionId = subscribePush($user['id'], $input);
    
    if ($subscriptionId) {
        // Update user preference
        $pdo->prepare("UPDATE users SET push_notifications = TRUE WHERE id = ?")
            ->execute([$user['id']]);
        
        jsonResponse([
            'success' => true,
            'subscription_id' => $subscriptionId,
            'message' => 'Successfully subscribed to push notifications'
        ]);
    } else {
        jsonError('Failed to subscribe', 500);
    }
}

/**
 * DELETE - Unsubscribe from push notifications
 */
function handleDelete() {
    global $pdo, $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $endpoint = $input['endpoint'] ?? null;
    
    if (unsubscribePush($user['id'], $endpoint)) {
        // If unsubscribing all, update user preference
        if (!$endpoint) {
            $pdo->prepare("UPDATE users SET push_notifications = FALSE WHERE id = ?")
                ->execute([$user['id']]);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Successfully unsubscribed from push notifications'
        ]);
    } else {
        jsonError('Failed to unsubscribe', 500);
    }
}
