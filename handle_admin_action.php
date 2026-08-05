<?php
session_start();
header('Content-Type: application/json');

// ========== INCLUIR PHPMAILER ==========
require_once __DIR__ . '/php_actions/send_email.php';  

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
  exit;
}

$config = include __DIR__ . '/config.php';
$host = $config['db_host'] ?? 'localhost';
$db   = $config['db_name'];
$user = $config['db_user'];
$pass = $config['db_pass'];

if (!isset($_SESSION['user_uuid'], $_SESSION['role'])) {
  echo json_encode(['success' => false, 'message' => 'No estás logueado.']);
  exit;
}
if (strtoupper((string)$_SESSION['role']) !== 'ADMIN') {
  echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
  exit;
}
$adminUuid = (string)$_SESSION['user_uuid'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['user_uuid'], $input['action'])) {
  echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
  exit;
}
$targetUuid = (string)$input['user_uuid'];
$action     = (string)$input['action'];
$note       = isset($input['note']) ? trim((string)$input['note']) : '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
  exit;
}
$mysqli->set_charset('utf8mb4');

$stmt = $mysqli->prepare('SELECT `role`, `admin_request`, `email`, `name` FROM `users` WHERE `uuid` = ? LIMIT 1');
$stmt->bind_param('s', $targetUuid);
$stmt->execute();
$res = $stmt->get_result();
$current = $res->fetch_assoc();
$stmt->close();

if (!$current) {
  echo json_encode(['success' => false, 'message' => 'Usuario objetivo no encontrado.']);
  exit;
}

$currRole = strtoupper((string)$current['role']);
$toEmail  = (string)$current['email'];
$toName   = (string)$current['name'];

// ========== APROBAR SOLICITUD ==========
if ($action === 'approve') {
  if ($currRole === 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'El usuario ya es administrador.']);
    exit;
  }

  $upd = $mysqli->prepare('UPDATE `users`
      SET `role` = \'ADMIN\',
          `admin_request` = 0,
          `admin_reviewed_at` = NOW(),
          `admin_review_note` = ?,
          `admin_reviewed_by` = ?
      WHERE `uuid` = ?');
  $upd->bind_param('sss', $note, $adminUuid, $targetUuid);
  $ok = $upd->execute();
  $upd->close();

  // ========== ENVIAR CORREO CON PHPMAILER ==========
  if ($ok && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    enviarNotificacionAdmin($toEmail, $toName, 'aprobado', $note);
  }

  echo json_encode([
    'success' => (bool)$ok, 
    'message' => $ok ? 'Solicitud aprobada y notificación enviada por correo.' : 'Error al aprobar la solicitud.'
  ]);
  exit;

// ========== RECHAZAR SOLICITUD ==========
} elseif ($action === 'reject') {
  $upd = $mysqli->prepare('UPDATE `users`
      SET `admin_request` = 0,
          `admin_reviewed_at` = NOW(),
          `admin_review_note` = ?,
          `admin_reviewed_by` = ?
      WHERE `uuid` = ?');
  $upd->bind_param('sss', $note, $adminUuid, $targetUuid);
  $ok = $upd->execute();
  $upd->close();

  // ========== ENVIAR CORREO CON PHPMAILER ==========
  if ($ok && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    enviarNotificacionAdmin($toEmail, $toName, 'rechazado', $note);
  }

  echo json_encode([
    'success' => (bool)$ok, 
    'message' => $ok ? 'Solicitud rechazada y notificación enviada por correo.' : 'Error al rechazar la solicitud.'
  ]);
  exit;

} else {
  echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
  exit;
}
?>
