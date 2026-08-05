<?php
// Cargar configuración
$config = require_once __DIR__ . '/../config.php';

$servername = $config['db_host'];
$username   = $config['db_user'];
$password   = $config['db_pass'];
$database   = $config['db_name'];

// Crear conexión
$conn = new mysqli($servername, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset UTF-8
$conn->set_charset("utf8mb4");
?>