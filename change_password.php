<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método no permitido.');
}

// Cargar credenciales desde config.php
$config = include __DIR__ . '/config.php';
$host = $config['db_host'] ?? 'localhost';
$db   = $config['db_name'];
$user = $config['db_user'];
$pass = $config['db_pass'];

// Verificar sesión con UUID
if (!isset($_SESSION['user_uuid'])) {
    die('No estás logueado.');
}
$userUuid = $_SESSION['user_uuid'];

// Validar entradas
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($newPassword === '' || $confirmPassword === '') {
    die('Por favor llena ambos campos.');
}
if ($newPassword !== $confirmPassword) {
    die('Las contraseñas no coinciden.');
}
if (strlen($newPassword) < 6) {
    die('La contraseña debe tener al menos 6 caracteres.');
}

// Conexión
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die('Error de conexión: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// Hashear contraseña
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Actualizar en BD: tabla users, columna password_hash, filtro por uuid
$stmt = $mysqli->prepare('UPDATE `users` SET `password_hash` = ? WHERE `uuid` = ?');
if (!$stmt) {
    die('Error de preparación: ' . $mysqli->error);
}
$stmt->bind_param('ss', $passwordHash, $userUuid);

if ($stmt->execute()) {
    header('Location: index.php?msg=contraseña_cambiada');
    exit;
} else {
    echo 'Error al actualizar la contraseña: ' . $stmt->error;
}

$stmt->close();
$mysqli->close();
