<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','1'); ini_set('error_log', __DIR__.'/php_error.log'); error_reporting(E_ALL);

$config = require __DIR__.'/config.php';
require_once __DIR__ . '/php_actions/send_email.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name']);
$mysqli->set_charset('utf8mb4');

function clean($s){ return trim((string)$s); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }

$first = clean($_POST['nombres'] ?? '');
$last  = clean($_POST['apellidos'] ?? '');
$email = strtolower(clean($_POST['email'] ?? ''));
$pass  = (string)($_POST['password'] ?? '');
$pass2 = (string)($_POST['confirm_password'] ?? '');
$roleLabel = clean($_POST['rol'] ?? '');
$inst  = clean($_POST['institucion'] ?? '');
$pais  = clean($_POST['pais'] ?? '');
$edo   = clean($_POST['estado'] ?? '');
$sectores = (array)($_POST['sectores'] ?? []);
$contrib = clean($_POST['contribuye'] ?? 'No');
$tipo  = clean($_POST['tipo'] ?? '');
$formato = clean($_POST['formato'] ?? '');
$terms = isset($_POST['terms']);

if ($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)|| !$terms) {
  http_response_code(400); exit('Datos inválidos');
}
if ($pass !== $pass2) { http_response_code(400); exit('Las contraseñas no coinciden'); }
$strong = (strlen($pass)>=8 && preg_match('/[a-z]/',$pass) && preg_match('/[A-Z]/',$pass) && preg_match('/\d/',$pass));
if (!$strong) { http_response_code(400); exit('Contraseña débil'); }

// Opcional: restringir dominio
$ENFORCE_DOMAIN = false; $ALLOWED_DOMAINS = ['unam.mx','edu.mx'];
if ($ENFORCE_DOMAIN) {
  $dom = substr(strrchr($email,'@')?:'',1);
  $ok = false; foreach($ALLOWED_DOMAINS as $d){ if ($dom && str_ends_with($dom, $d)) { $ok=true; break; } }
  if (!$ok) { http_response_code(400); exit('Dominio de correo no permitido'); }
}

$allowedSectors = ['Construcción','Residuos','Energía','Agua','Alimentos'];
$sectores = array_values(array_unique(array_intersect($sectores, $allowedSectors)));
$roleMap = ['Investigador/a'=>'USER','Gobierno'=>'USER','Industria'=>'USER','Academia/Estudiante'=>'USER','Otro'=>'USER'];
$appRole = $roleMap[$roleLabel] ?? 'USER';

$hash = password_hash($pass, PASSWORD_BCRYPT);
$token = bin2hex(random_bytes(32));
$expires = (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s');
$now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

$mysqli->begin_transaction();
try {
    
// Inserta usuario (status PENDING hasta verificar)
$fullName = "$first $last";
$contribStr = ($contrib === 'Si') ? '1' : '0';

// Variables referenciables para opcionales
$stateParam   = ($edo !== '') ? $edo : null;
$tipoParam    = ($tipo !== '') ? $tipo : null;
$formatoParam = ($formato !== '') ? $formato : null;

$sql = "
  INSERT INTO users (
    uuid, first_name, last_name, name, email, password_hash, role, status,
    institution, country, state, profile_role_label, contributes, contribute_type, contribute_format,
    created_at, updated_at, terms_accepted_at, verification_token, verification_expires_at
  ) VALUES (
    UUID(), ?, ?, ?, ?, ?, ?, 'PENDING',
    ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?
  )
";
$stmt = $mysqli->prepare($sql);

$types = 'ssssssssssssssssss'; // 18 's'
$stmt->bind_param(
  $types,
  $first, $last, $fullName, $email, $hash, $appRole,
  $inst, $pais, $stateParam, $roleLabel, $contribStr, $tipoParam, $formatoParam,
  $now, $now, $now, $token, $expires
);

$stmt->execute();
$stmt->close();

  // Obtén UUID recién creado
  $uid = $mysqli->insert_id;
  $stmt = $mysqli->prepare("SELECT uuid FROM users WHERE email=? LIMIT 1");
  $stmt->bind_param('s',$email); $stmt->execute();
  $user_uuid = (string)($stmt->get_result()->fetch_column() ?? '');
  $stmt->close();
  if ($user_uuid==='') { throw new RuntimeException('No se pudo obtener UUID'); }

  // Sectores (múltiples)
  if (!empty($sectores)) {
    $ins = $mysqli->prepare("INSERT IGNORE INTO user_sectors (user_uuid, sector) VALUES (?, ?)");
    foreach ($sectores as $sec) { $ins->bind_param('ss', $user_uuid, $sec); $ins->execute(); }
    $ins->close();
  }

  $mysqli->commit();
  
  // ← CAMBIO 2: AGREGADO
  session_start();
  $_SESSION['pending_verification_email'] = $email;
  
} catch (Throwable $e) {
  $mysqli->rollback();
  error_log('Registro fallido: '.$e->getMessage());
  http_response_code(500); exit('Error al registrar');
}

// ← CAMBIO 3: REEMPLAZADO mail() por PHPMailer
if (!enviarCorreoVerificacion($email, $fullName, $token)) {
    error_log("Error al enviar correo de verificación a: $email");
}

header('Location: registro_ok.html');
exit;
