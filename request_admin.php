<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
  exit;
}

// Cargar credenciales desde config.php
$config = include __DIR__ . '/config.php';
$host = $config['db_host'] ?? 'localhost';
$db   = $config['db_name'];
$user = $config['db_user'];
$pass = $config['db_pass'];

// Verificar sesión: solo que esté logueado con UUID
if (!isset($_SESSION['user_uuid'])) {
  echo json_encode(['success' => false, 'message' => 'No estás logueado.']);
  exit;
}
$userUuid = $_SESSION['user_uuid'];

// Conexión
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  echo json_encode(['success' => false, 'message' => 'Error de conexión.']);
  exit;
}
$mysqli->set_charset('utf8mb4');

// Estado actual del solicitante
$stmt = $mysqli->prepare('SELECT `role`, `admin_request` FROM `users` WHERE `uuid` = ? LIMIT 1');
$stmt->bind_param('s', $userUuid);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
  exit;
}

// Regla: solo usuarios no-ADMIN pueden solicitar y no debe haber solicitud pendiente
if (strtoupper((string)$user['role']) === 'ADMIN') {
  echo json_encode(['success' => false, 'message' => 'Ya tienes permisos de administrador.']);
  exit;
}
if ((string)$user['admin_request'] === '1') {
  echo json_encode(['success' => false, 'message' => 'Ya existe una solicitud pendiente.']);
  exit;
}

// Registrar solicitud
$upd = $mysqli->prepare('UPDATE `users` SET `admin_request` = 1, `admin_requested_at` = NOW() WHERE `uuid` = ?');
$upd->bind_param('s', $userUuid);
$ok = $upd->execute();
$upd->close();

echo json_encode([
  'success' => (bool)$ok,
  'message' => $ok ? 'Solicitud enviada correctamente.' : 'Error al enviar la solicitud.'
]);
