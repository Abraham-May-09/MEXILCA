<?php
session_start();
header('Content-Type: application/json');

// Incluir PHPMailer
require_once __DIR__ . '/php_actions/send_email.php';

// Verificar que el email esté en sesión
if (!isset($_SESSION['pending_verification_email'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'No hay correo pendiente de verificación en esta sesión.'
    ]);
    exit;
}

$email = $_SESSION['pending_verification_email'];

// Cargar configuración
$config = include __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error de conexión a la base de datos.'
    ]);
    exit;
}

$mysqli->set_charset('utf8mb4');

// Buscar usuario por email (columnas correctas)
$stmt = $mysqli->prepare("SELECT uuid, name, email, verification_token, verification_expires_at 
                          FROM users 
                          WHERE email = ? AND email_verified_at IS NULL");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode([
        'success' => false, 
        'message' => 'Usuario no encontrado o la cuenta ya está verificada. Puedes intentar iniciar sesión.'
    ]);
    exit;
}

// Verificar si el token está expirado
$token_expired = false;
if (!empty($user['verification_expires_at'])) {
    $expires_date = new DateTime($user['verification_expires_at']);
    $now = new DateTime();
    
    // Si la fecha de expiración ya pasó
    if ($now > $expires_date) {
        $token_expired = true;
    }
}

// Si no hay token o está expirado, generar uno nuevo
if (empty($user['verification_token']) || $token_expired) {
    $verification_token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s');
    
    $stmt = $mysqli->prepare("UPDATE users 
                             SET verification_token = ?, 
                                 verification_expires_at = ? 
                             WHERE uuid = ?");
    $stmt->bind_param('sss', $verification_token, $expires, $user['uuid']);
    $stmt->execute();
    $stmt->close();
} else {
    $verification_token = $user['verification_token'];
}

$mysqli->close();

// Enviar correo con PHPMailer
if (enviarCorreoVerificacion($user['email'], $user['name'], $verification_token)) {
    echo json_encode([
        'success' => true, 
        'message' => 'Correo de verificación reenviado exitosamente. Revisa tu bandeja de entrada y spam.'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'No se pudo enviar el correo. Por favor intenta más tarde o contacta al administrador.'
    ]);
}
?>
