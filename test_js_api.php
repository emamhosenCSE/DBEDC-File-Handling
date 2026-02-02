<?php
// Test API call with JavaScript-like headers
// Simulate what happens when JavaScript calls the API

// Start session
session_start();

// Set headers like JavaScript does
header('Content-Type: application/json');
header('X-CSRF-Token: test-token'); // This should be ignored for GET

// Try to call the activities API logic directly
try {
    require_once 'includes/api-bootstrap.php';

    $user = getCurrentUser();
    echo "User: " . json_encode($user) . "\n";

    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $sql = "SELECT a.*, u.name as user_name, u.avatar_url as user_avatar
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1";

    $params = [];
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

    // Return JSON
    header('Content-Type: application/json');
    echo json_encode(['activities' => $activities]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>