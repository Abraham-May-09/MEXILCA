<?php
$db_host = 'localhost';
$db_user = 'u303404040_IvanMendoza';    
$db_pass = '*hba3Qn=';
$db_name = 'u303404040_BASE_ACV_LCA';    

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log("Error de conexión MySQL: " . $conn->connect_error);
    die("Error de conexión a la base de datos");
}

$conn->set_charset("utf8mb4");
?>