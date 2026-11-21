<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$excludeId = max(0, (int) ($_GET['exclude'] ?? 0));
$selectedId = max(0, (int) ($_GET['id'] ?? 0));

if ($selectedId > 0) {
    $statement = $conn->prepare(
        'SELECT staff_id, first_name, last_name, job_title
         FROM staff
         WHERE staff_id = ? AND staff_id <> ?'
    );
    $statement->bind_param('ii', $selectedId, $excludeId);
} else {
    $query = trim((string) ($_GET['q'] ?? ''));
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    $searchTerm = '%' . substr($query, 0, 80) . '%';
    $statement = $conn->prepare(
        'SELECT staff_id, first_name, last_name, job_title
         FROM staff
         WHERE staff_id <> ?
           AND (first_name LIKE ? OR last_name LIKE ? OR job_title LIKE ?
                OR CAST(staff_id AS CHAR) LIKE ?)
         ORDER BY last_name, first_name
         LIMIT 15'
    );
    $statement->bind_param(
        'issss',
        $excludeId,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
}

$statement->execute();
echo json_encode($statement->get_result()->fetch_all(MYSQLI_ASSOC), JSON_INVALID_UTF8_SUBSTITUTE);
