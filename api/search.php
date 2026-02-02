<?php
/**
 * Search API Endpoint
 * Provides unified search across letters, tasks, stakeholders, departments, and users
 */

require_once __DIR__ . '/../includes/api-bootstrap.php';

$query = trim($_GET['q'] ?? '');
$limit = min((int)($_GET['limit'] ?? 10), 20); // Max 20 results

if (empty($query) || strlen($query) < 2) {
    jsonResponse(['results' => []]);
}

$user = getCurrentUser();
$results = [];

// Search letters
try {
    $letterQuery = "
        SELECT
            'letter' as type,
            id,
            reference_no as title,
            CONCAT('Subject: ', subject, ' | Stakeholder: ', s.name) as meta,
            received_date as date,
            priority
        FROM letters l
        JOIN stakeholders s ON l.stakeholder_id = s.id
        WHERE (l.reference_no LIKE ? OR l.subject LIKE ? OR l.description LIKE ?)
        AND l.status = 'ACTIVE'
    ";

    $searchTerm = '%' . $query . '%' ;
    $params = [$searchTerm, $searchTerm, $searchTerm];

    // Apply permission filters
    $scope = getUserScope();
    if ($scope === 'department' && $user['department_id']) {
        $letterQuery .= " AND l.department_id = ?";
        $params[] = $user['department_id'];
    } elseif ($scope === 'own') {
        $letterQuery .= " AND (l.uploaded_by = ? OR EXISTS (SELECT 1 FROM tasks t WHERE t.letter_id = l.id AND t.assigned_to = ?))";
        $params[] = $user['id'];
        $params[] = $user['id'];
    }

    $letterQuery .= " ORDER BY l.received_date DESC LIMIT ?";

    $stmt = $pdo->prepare($letterQuery);
    $stmt->execute(array_merge($params, [$limit]));
    $letterResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($letterResults as $result) {
        $results[] = $result;
    }
} catch (Exception $e) {
    error_log("Letter search error: " . $e->getMessage());
}

// Search tasks
try {
    $taskQuery = "
        SELECT
            'task' as type,
            t.id,
            t.title,
            CONCAT('Letter: ', l.reference_no, ' | Status: ', t.status) as meta,
            t.created_at as date,
            t.priority,
            t.assigned_to
        FROM tasks t
        JOIN letters l ON t.letter_id = l.id
        WHERE (t.title LIKE ? OR t.description LIKE ?)
        AND t.status != 'CANCELLED'
    ";

    $params = [$searchTerm, $searchTerm];

    // Apply permission filters
    if ($scope === 'department' && $user['department_id']) {
        $taskQuery .= " AND (t.assigned_department = ? OR l.department_id = ?)";
        $params[] = $user['department_id'];
        $params[] = $user['department_id'];
    } elseif ($scope === 'own') {
        $taskQuery .= " AND t.assigned_to = ?";
        $params[] = $user['id'];
    }

    $taskQuery .= " ORDER BY t.created_at DESC LIMIT ?";

    $stmt = $pdo->prepare($taskQuery);
    $stmt->execute(array_merge($params, [$limit]));
    $taskResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($taskResults as $result) {
        $results[] = $result;
    }
} catch (Exception $e) {
    error_log("Task search error: " . $e->getMessage());
}

// Search stakeholders
try {
    $stmt = $pdo->prepare("
        SELECT
            'stakeholder' as type,
            id,
            name as title,
            CONCAT('Code: ', code, ' | ', description) as meta,
            created_at as date
        FROM stakeholders
        WHERE (name LIKE ? OR code LIKE ? OR description LIKE ?)
        AND is_active = TRUE
        ORDER BY name
        LIMIT ?
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
    $stakeholderResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stakeholderResults as $result) {
        $results[] = $result;
    }
} catch (Exception $e) {
    error_log("Stakeholder search error: " . $e->getMessage());
}

// Search departments (if user has permission)
if (hasPermission('departments', 'view')) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                'department' as type,
                id,
                name as title,
                description as meta,
                created_at as date
            FROM departments
            WHERE (name LIKE ? OR description LIKE ?)
            AND is_active = TRUE
            ORDER BY name
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        $departmentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($departmentResults as $result) {
            $results[] = $result;
        }
    } catch (Exception $e) {
        error_log("Department search error: " . $e->getMessage());
    }
}

// Search users (if user has permission)
if (hasPermission('users', 'view')) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                'user' as type,
                id,
                name as title,
                CONCAT('Email: ', email, ' | Role: ', role) as meta,
                last_login as date
            FROM users
            WHERE (name LIKE ? OR email LIKE ?)
            AND is_active = TRUE
            ORDER BY name
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        $userResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($userResults as $result) {
            $results[] = $result;
        }
    } catch (Exception $e) {
        error_log("User search error: " . $e->getMessage());
    }
}

// Sort results by relevance (letters and tasks first, then others)
usort($results, function($a, $b) {
    $priority = ['letter' => 1, 'task' => 2, 'stakeholder' => 3, 'department' => 4, 'user' => 5];
    $aPriority = $priority[$a['type']] ?? 6;
    $bPriority = $priority[$b['type']] ?? 6;

    if ($aPriority !== $bPriority) {
        return $aPriority - $bPriority;
    }

    // Within same type, sort by date (newest first)
    return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
});

// Limit total results
$results = array_slice($results, 0, $limit);

jsonResponse(['results' => $results]);
?>