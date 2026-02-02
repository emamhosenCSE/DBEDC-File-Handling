<?php
// Test the activities API directly
require_once '../includes/api-bootstrap.php';

$user = getCurrentUser();
echo "Current user: " . json_encode($user) . "\n";

$scope = getUserScope();
echo "User scope: $scope\n";

try {
    $limit = 10;
    $sql = "SELECT a.*, u.name as user_name, u.avatar_url as user_avatar
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1";

    $params = [];

    // Apply permission filters
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

    echo "SQL: $sql\n";
    echo "Params: " . json_encode($params) . "\n";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();

    echo "Found " . count($activities) . " activities\n";
    echo "Response: " . json_encode($activities);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>