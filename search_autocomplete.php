<?php
session_start();
header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    echo json_encode([]);
    exit;
}

$config = require $configPath;
$mysqli = @new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_errno) {
    echo json_encode([]);
    exit;
}

$mysqli->set_charset('utf8mb4');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search)) {
    echo json_encode([]);
    exit;
}

$search_escaped = $mysqli->real_escape_string($search);

// Buscar solo en datasets de México
$sql = "SELECT DISTINCT p.name, p.uuid
        FROM processes p
        WHERE (p.name LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%')
          AND LOWER(p.geo_desc) LIKE '%mexico%'
        ORDER BY p.name ASC
        LIMIT 10";

$result = $mysqli->query($sql);
$suggestions = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'name' => $row['name'],
            'uuid' => $row['uuid']
        ];
    }
    $result->free();
}

$mysqli->close();
echo json_encode($suggestions);
