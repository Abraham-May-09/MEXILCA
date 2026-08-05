<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. Verificando archivos...<br>";

if (file_exists('php_actions/verificar_permisos.php')) {
    echo "✓ verificar_permisos.php existe<br>";
} else {
    echo "✗ verificar_permisos.php NO EXISTE<br>";
}

if (file_exists('config.php')) {
    echo "✓ config.php existe<br>";
    $config = include 'config.php';
    echo "✓ config.php se cargó: " . (is_array($config) ? "SÍ" : "NO") . "<br>";
} else {
    echo "✗ config.php NO EXISTE<br>";
}

echo "<br>2. Verificando sesión...<br>";
session_start();
echo "✓ Sesión iniciada<br>";
echo "Usuario UUID: " . ($_SESSION['user_uuid'] ?? 'NO DEFINIDO') . "<br>";
echo "Rol: " . ($_SESSION['role'] ?? 'NO DEFINIDO') . "<br>";

echo "<br>3. Verificando conexión...<br>";
if (isset($config)) {
    $conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    if ($conn->connect_error) {
        echo "✗ ERROR: " . $conn->connect_error . "<br>";
    } else {
        echo "✓ Conexión exitosa<br>";
        $result = $conn->query("SELECT COUNT(*) as total FROM processes");
        if ($result) {
            echo "✓ Tabla processes existe<br>";
        } else {
            echo "✗ Error en query: " . $conn->error . "<br>";
        }
    }
}

echo "<br>Todas las pruebas completadas.";
?>
