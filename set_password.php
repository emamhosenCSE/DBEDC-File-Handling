<?php
/**
 * Simple Password Setter for Live Server
 * Run this to set a password for a user
 */

require_once 'includes/db.php';

echo "<h1>🔑 Set User Password</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;}</style>";

if (!$pdo) {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}

echo "<p class='success'>✅ Database connected</p>";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo "<p class='error'>❌ Email and password are required</p>";
    } else {
        // Find user
        $stmt = $pdo->prepare("SELECT id, name, email, provider FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo "<p class='error'>❌ User not found: $email</p>";
        } else {
            // Set password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $result = $updateStmt->execute([$passwordHash, $user['id']]);

            if ($result) {
                echo "<p class='success'>✅ Password set successfully!</p>";
                echo "<p><strong>User:</strong> {$user['name']} ({$user['email']})</p>";
                echo "<p><strong>Provider:</strong> {$user['provider']}</p>";
                echo "<p><strong>Password:</strong> $password</p>";
                echo "<hr>";
                echo "<p>Now you can test email login at: <a href='login.php' target='_blank'>login.php</a></p>";
            } else {
                echo "<p class='error'>❌ Failed to update password</p>";
            }
        }
    }
}

echo "<hr>";
echo "<form method='POST'>";
echo "<p><label>Email: <input type='email' name='email' required value='emamhsajeeb@gmail.com'></label></p>";
echo "<p><label>Password: <input type='password' name='password' required value='TestPass123'></label></p>";
echo "<button type='submit'>Set Password</button>";
echo "</form>";
?>