<?php
// Cargar configuración (mismo directorio)
$config = require __DIR__ . '/config.php';

// Crear conexión
$conn = new mysqli(
    $config['db_host'], 
    $config['db_user'], 
    $config['db_pass'], 
    $config['db_name']
);

// Verificar conexión
if ($conn->connect_error) {
    $mensaje = date('Y-m-d H:i:s') . " - Error de conexión: " . $conn->connect_error . "\n";
    error_log($mensaje);
    die($mensaje);
}

// Establecer charset
$conn->set_charset("utf8mb4");

// Eliminar usuarios NO verificados después de 24 horas
$sql = "DELETE FROM users 
        WHERE email_verified_at IS NULL 
        AND created_at < (NOW() - INTERVAL 24 HOUR)";

if ($conn->query($sql)) {
    $eliminados = $conn->affected_rows;
    $fecha = date('Y-m-d H:i:s');
    $mensaje = "[$fecha] Limpieza ejecutada: $eliminados usuarios no verificados eliminados.\n";
    
    // Registrar en log
    error_log($mensaje);
    echo $mensaje;
} else {
    $mensaje = date('Y-m-d H:i:s') . " - Error al eliminar: " . $conn->error . "\n";
    error_log($mensaje);
    echo $mensaje;
}

$conn->close();
?>
