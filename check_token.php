<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';

$email = $_GET['email'] ?? '';

if (empty($email)) {
    die("Uso: check_token.php?email=usuario@ejemplo.com");
}

$stmt = $conn->prepare("
    SELECT uuid, name, email, verification_token, verification_expires_at, 
           email_verified_at, created_at
    FROM users 
    WHERE email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("❌ Usuario no encontrado: " . htmlspecialchars($email));
}

echo "<h2>Estado del usuario: " . htmlspecialchars($email) . "</h2>";

echo "<h3>Token de verificación:</h3>";
if ($user['verification_token']) {
    $token = $user['verification_token'];
    echo "<p><strong>Token:</strong> <code style='background: #f0f0f0; padding: 5px;'>$token</code></p>";
    echo "<p><strong>Longitud:</strong> " . strlen($token) . " caracteres " . (strlen($token) === 64 ? "✅" : "❌ DEBE SER 64") . "</p>";
    echo "<p><strong>Formato:</strong> " . (preg_match('/^[a-f0-9]{64}$/', $token) ? "✅ Válido" : "❌ Inválido (debe ser solo a-f y 0-9)") . "</p>";
    
    // Generar el link correcto
    $link = "https://ciclodevida.mx/verify.php?token=" . $token;
    echo "<h3>Link de verificación correcto:</h3>";
    echo "<p><a href='$link' target='_blank'>$link</a></p>";
    echo "<p><button onclick=\"navigator.clipboard.writeText('$link')\">📋 Copiar link</button></p>";
    
} else {
    echo "<p style='color: red;'>❌ No tiene token de verificación</p>";
}

echo "<h3>Fecha de expiración:</h3>";
if ($user['verification_expires_at']) {
    $expiration = new DateTime($user['verification_expires_at']);
    $now = new DateTime();
    
    echo "<p><strong>Expira:</strong> " . $expiration->format('Y-m-d H:i:s') . "</p>";
    echo "<p><strong>Ahora:</strong> " . $now->format('Y-m-d H:i:s') . "</p>";
    
    if ($now > $expiration) {
        echo "<p style='color: red;'>❌ TOKEN EXPIRADO</p>";
        echo "<p>👉 El usuario necesita solicitar un nuevo token</p>";
    } else {
        $diff = $expiration->diff($now);
        echo "<p style='color: green;'>✅ Token válido (expira en {$diff->h} horas, {$diff->i} minutos)</p>";
    }
} else {
    echo "<p>Sin fecha de expiración configurada</p>";
}

echo "<h3>Estado de verificación:</h3>";
if ($user['email_verified_at']) {
    echo "<p style='color: green;'>✅ Email ya verificado el: {$user['email_verified_at']}</p>";
    echo "<p>⚠️ El token ya fue usado y no funciona más</p>";
} else {
    echo "<p style='color: orange;'>⏳ Email pendiente de verificación</p>";
}

$conn->close();
?>
<script>
lucide.createIcons();
</script>
