<?php
session_start();
header('Content-Type: application/json');

// Verificar que sea admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$config = include __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

$mysqli->set_charset('utf8mb4');

$stmt = $mysqli->prepare("SELECT uuid, name, email, role, created_at FROM users ORDER BY role DESC, name ASC");
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
$mysqli->close();

echo json_encode(['success' => true, 'users' => $users]);
