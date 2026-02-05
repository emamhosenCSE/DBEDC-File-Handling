<?php
require_once 'includes/db.php';

echo "<h1>👥 List All Users</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";

if (!$pdo) {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}

echo "<p class='success'>✅ Database connected</p>";

// Get all users
$stmt = $pdo->query("SELECT id, name, email, provider, is_active, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "<p>No users found in database</p>";
} else {
    echo "<p><strong>Total users:</strong> " . count($users) . "</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Provider</th><th>Active</th><th>Created</th></tr>";

    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['provider']}</td>";
        echo "<td>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
        echo "<td>{$user['created_at']}</td>";
        echo "</tr>";
    }

    echo "</table>";
}
?>