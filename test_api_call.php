<?php
// Test the exact API call that the dashboard makes
// This simulates: GET /api/activities.php?limit=10

// Start session and include necessary files
session_start();
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

// Check if user is authenticated
if (!isAuthenticated()) {
    echo "User not authenticated\n";
    exit;
}

$user = getCurrentUser();
echo "User authenticated: " . $user['email'] . "\n";

try {
    // Include API bootstrap
    require_once 'includes/api-bootstrap.php';

    // This should work just like the real API call
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $sql = "SELECT a.*, u.name as user_name, u.avatar_url as user_avatar
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1";

    $params = [];

    // Apply permission filters
    $scope = getUserScope();
    if ($scope === 'department') {
        $sql .= " AND (a.user_id IN (SELECT id FROM users WHERE department_id = ?) OR a.user_id = ?)";
        $params[] = $user['department_id'];
        $params[] = $user['id'];
    } elseif ($scope === 'own') {
        $sql .= " AND a.user_id = ?";
        $params[] = $user['id'];
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['activities' => $activities]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>