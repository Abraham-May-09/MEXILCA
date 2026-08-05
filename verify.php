<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','1'); ini_set('error_log', __DIR__.'/php_error.log'); error_reporting(E_ALL);
$config = require __DIR__.'/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name']);
$mysqli->set_charset('utf8mb4');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Token inválido'); }

$now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

$stmt = $mysqli->prepare("
  SELECT uuid, verification_expires_at FROM users
  WHERE verification_token = ? LIMIT 1
");
$stmt->bind_param('s',$token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { http_response_code(400); exit('Token no encontrado'); }
if ($row['verification_expires_at'] !== null && $row['verification_expires_at'] < $now) {
  http_response_code(400); exit('Token expirado');
}

$stmt = $mysqli->prepare("
  UPDATE users
  SET email_verified_at = ?, status = 'ACTIVE',
      verification_token = NULL, verification_expires_at = NULL, updated_at = ?
  WHERE uuid = ?
");
$stmt->bind_param('sss', $now, $now, $row['uuid']);
$stmt->execute();
$stmt->close();

echo 'Correo verificado. Ya puedes iniciar sesión.';