<?php
/**
 * API Endpoint Test for Email Login
 * Test the /api/auth.php endpoint directly
 */

// Test data - replace with real user credentials
$testUsers = [
    [
        'email' => 'test@example.com',
        'password' => 'password123',
        'description' => 'Test user (if exists)'
    ],
    [
        'email' => 'googleuser@example.com',
        'password' => 'password123',
        'description' => 'Google OAuth user with password'
    ]
];

echo "🧪 Testing Email Login API Endpoint\n";
echo "===================================\n\n";

foreach ($testUsers as $i => $user) {
    echo "Test " . ($i + 1) . ": {$user['description']}\n";
    echo "Email: {$user['email']}\n";

    // Prepare POST data
    $postData = json_encode([
        'email' => $user['email'],
        'password' => $user['password']
    ]);

    // API endpoint URL
    $url = 'https://files.dhakabypass.com/api/auth.php';

    // Initialize cURL
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ],
        CURLOPT_SSL_VERIFYPEER => false, // For testing - remove in production
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    echo "HTTP Code: $httpCode\n";

    if ($error) {
        echo "cURL Error: $error\n";
    } else {
        $responseData = json_decode($response, true);

        if ($responseData) {
            if (isset($responseData['error'])) {
                echo "API Response: ❌ {$responseData['error']}\n";
            } else {
                echo "API Response: ✅ Success\n";
                if (isset($responseData['message'])) {
                    echo "Message: {$responseData['message']}\n";
                }
            }
        } else {
            echo "Raw Response: $response\n";
        }
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "📋 Manual Testing Instructions:\n";
echo "===============================\n";
echo "1. Open browser and go to: https://files.dhakabypass.com/login.php\n";
echo "2. Click the 'Email Login' tab\n";
echo "3. Enter credentials from above\n";
echo "4. Click 'Sign In'\n";
echo "5. Should redirect to dashboard on success\n";
echo "6. Should show error message on failure\n\n";

echo "🔧 If tests fail:\n";
echo "================\n";
echo "• Check that users exist in database\n";
echo "• Verify users have password_hash set\n";
echo "• Ensure EMAIL_LOGIN_ENABLED is true in config\n";
echo "• Check database connection in includes/db.php\n\n";

echo "✅ API testing complete!\n";
?>