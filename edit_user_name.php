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
$new_name = trim($data['new_name'] ?? '');

if (empty($user_uuid) || empty($new_name)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$config = include __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

$mysqli->set_charset('utf8mb4');

// Verificar que el usuario NO sea ADMIN
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
    echo json_encode(['success' => false, 'message' => 'No se puede editar un administrador']);
    exit;
}

// Actualizar nombre
$stmt = $mysqli->prepare("UPDATE users SET name = ? WHERE uuid = ?");
$stmt->bind_param('ss', $new_name, $user_uuid);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Nombre actualizado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar nombre']);
}

$stmt->close();
$mysqli->close();
