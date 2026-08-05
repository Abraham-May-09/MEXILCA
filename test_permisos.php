<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

echo "Antes de cargar verificar_permisos.php<br>";

try {
    require_once 'php_actions/verificar_permisos.php';
    echo "✓ verificar_permisos.php cargado correctamente<br>";
    
    // Verificar si existe la función
    if (function_exists('puede_añadir_datasets')) {
        echo "✓ Función puede_añadir_datasets existe<br>";
        $puede = puede_añadir_datasets();
        echo "Resultado: " . ($puede ? "SÍ" : "NO") . "<br>";
    } else {
        echo "✗ Función puede_añadir_datasets NO existe<br>";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "<br>";
}

echo "Test completado";
?>
