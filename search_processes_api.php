<?php
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'processes' => []]);
    exit;
}

$config = require __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

$search = '%' . $mysqli->real_escape_string($query) . '%';

$sql = "SELECT p.uuid, p.name, p.category, l.name as location
        FROM processes p
        LEFT JOIN locations l ON l.uuid = p.location_uuid
        WHERE p.name LIKE ? OR p.category LIKE ?
        ORDER BY p.name ASC
        LIMIT 20";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$processes = [];
while ($row = $result->fetch_assoc()) {
    $processes[] = $row;
}

$stmt->close();
$mysqli->close();

echo json_encode(['success' => true, 'processes' => $processes]);
