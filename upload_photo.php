<?php
session_start();

// Cargar config que retorna un array con credenciales
$config = include __DIR__ . '/config.php';

$host = $config['db_host'] ?? 'localhost';
$db   = $config['db_name'];
$user = $config['db_user'];
$pass = $config['db_pass'];

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die('Error de conexión: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// La sesión debe contener el UUID del usuario
if (!isset($_SESSION['user_uuid'])) {
    die('No hay sesión de usuario.');
}
$userUuid = $_SESSION['user_uuid'];

// Validar archivo
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    die('No se recibió una imagen válida.');
}

// Carpeta de subida
$destDir = __DIR__ . '/uploads/';
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

// Validar MIME y generar nombre seguro
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['foto']['tmp_name']);
$allow = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
if (!isset($allow[$mime])) {
    die('Formato de imagen no permitido.');
}
$filename = bin2hex(random_bytes(8)) . '.' . $allow[$mime];
$pathFs   = $destDir . $filename;      // ruta en disco
$pathRel  = 'uploads/' . $filename;    // ruta que se guarda en BD

if (!move_uploaded_file($_FILES['foto']['tmp_name'], $pathFs)) {
    die('No se pudo guardar la imagen.');
}

// Actualizar columna photo_url en tabla users usando uuid
$stmt = $mysqli->prepare('UPDATE `users` SET `photo_url` = ? WHERE `uuid` = ?');
$stmt->bind_param('ss', $pathRel, $userUuid);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    $_SESSION['photo_url'] = $pathRel;
    header('Location: index.php');
    exit;
} else {
    die('No se pudo actualizar la foto en la base de datos.');
}
