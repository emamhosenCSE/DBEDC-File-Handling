<?php
/**
 * Google OAuth Callback Handler
 * Processes the authorization code and creates/updates user session
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

// Error handling
if (isset($_GET['error'])) {
    die('Google OAuth Error: ' . htmlspecialchars($_GET['error']));
}

// Verify state token to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid state parameter. Possible CSRF attack.');
}

// Get authorization code
if (!isset($_GET['code'])) {
    die('No authorization code received from Google.');
}

$authCode = $_GET['code'];

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $authCode,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die('Failed to get access token from Google. HTTP Code: ' . $httpCode);
}

$tokenJson = json_decode($tokenResponse, true);
if (!isset($tokenJson['access_token'])) {
    die('No access token in Google response.');
}

$accessToken = $tokenJson['access_token'];

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$userInfoResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die('Failed to get user info from Google. HTTP Code: ' . $httpCode);
}

$userInfo = json_decode($userInfoResponse, true);

if (!isset($userInfo['id']) || !isset($userInfo['email'])) {
    die('Invalid user info received from Google.');
}

// Create or update user in database
try {
    $userId = getOrCreateUser($userInfo);
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $userInfo['email'];
    $_SESSION['user_name'] = $userInfo['name'];
    $_SESSION['user_avatar'] = $userInfo['picture'] ?? null;
    
    // Clean up OAuth state
    unset($_SESSION['oauth_state']);
    
    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;
    
} catch (Exception $e) {
    if (DEV_MODE) {
        die('Database error: ' . $e->getMessage());
    } else {
        die('An error occurred during login. Please try again.');
    }
}
