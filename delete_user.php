<?php
session_start();
header('Content-Type: application/json');

// Verificar que sea admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_uuid = $data['user_uuid'] ?? '';

if (empty($user_uuid)) {
    echo json_encode(['success' => false, 'message' => 'UUID no proporcionado']);
    exit;
}

$config = include __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

$mysqli->set_charset('utf8mb4');

// Verificar que el usuario a eliminar NO sea ADMIN
$stmt = $mysqli->prepare("SELECT role FROM users WHERE uuid = ?");
$stmt->bind_param('s', $user_uuid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

if ($user['role'] === 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'No se puede eliminar un administrador']);
    exit;
}

// Eliminar usuario
$stmt = $mysqli->prepare("DELETE FROM users WHERE uuid = ?");
$stmt->bind_param('s', $user_uuid);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario']);
}

$stmt->close();
$mysqli->close();
