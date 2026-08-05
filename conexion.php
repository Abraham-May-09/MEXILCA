<?php
$config = include(__DIR__ . '/config.php');

$conn = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name']
);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>