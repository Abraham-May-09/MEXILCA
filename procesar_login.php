php
<?php
session_start();
require_once 'conexion.php';

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header("Location: login.php?error=Completa todos los campos");
    exit;
}

// ✅ AGREGAR 'contributes' AL SELECT
$stmt = $conn->prepare("SELECT uuid, name, password_hash, role, photo_url, contributes FROM users WHERE email = ?");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    header("Location: login.php?error=Error interno");
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    header("Location: login.php?error=No existe usuario con ese correo");
    exit;
}

// ✅ AGREGAR $contributes A LAS VARIABLES
$stmt->bind_result($uuid, $name, $hash, $role, $photo_url, $contributes);
$stmt->fetch();

$login_ok = false;

// Caso 1: contraseña hasheada (normal)
if ($hash && password_verify($password, $hash)) {
    $login_ok = true;
// Caso 2: contraseña guardada en texto plano (migramos a hash y permitimos login)
} elseif ($hash !== null && $hash === $password) {
    // Re-hash seguro
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE uuid = ?");
    if ($upd) {
        $upd->bind_param("ss", $newHash, $uuid);
        $upd->execute();
        $upd->close();
        $hash = $newHash;
    } else {
        error_log("Failed to prepare update hash: " . $conn->error);
    }
    $login_ok = true;
}

if ($login_ok) {
    // Regenerar id de sesión
    session_regenerate_id(true);
    
    $_SESSION["user_uuid"] = $uuid;
    $_SESSION["name"] = $name;
    $_SESSION["email"] = $email;
    $_SESSION["role"] = $role;
    $_SESSION["photo_url"] = $photo_url;
    $_SESSION["contributes"] = $contributes; 
    // Actualizar último login
    $upd2 = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE uuid = ?");
    if ($upd2) {
        $upd2->bind_param("s", $uuid);
        $upd2->execute();
        $upd2->close();
    }
    
    header("Location: index.php");
    exit();
} else {
    header("Location: login.php?error=Contraseña incorrecta");
    exit();
}
?>