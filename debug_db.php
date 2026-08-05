<?php
// debug_db.php (temporal)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_debug.log');

echo "<h3>DEBUG: inicio</h3>";

// 1) Comprobar sintaxis del config (solo include)
$configPath = '/config.php';
if (!file_exists($configPath)) {
    echo "<p style='color:red'>ERROR: config.php NO ENCONTRADO en: $configPath</p>";
    exit;
}

try {
    $config = require $configPath;
    if (!is_array($config)) {
        echo "<p style='color:red'>ERROR: config.php no devolvió un array.</p>";
        var_dump($config);
        exit;
    }
    echo "<p style='color:green'>OK: config.php incluido.</p>";
    // Mostrar (parcial) host/db/user (no mostrar pass)
    echo "<p>db_host: ".htmlspecialchars($config['db_host'] ?? 'N/A')." - db_name: ".htmlspecialchars($config['db_name'] ?? 'N/A')." - db_user: ".htmlspecialchars($config['db_user'] ?? 'N/A')."</p>";
} catch(Throwable $e) {
    echo "<p style='color:red'>EXCEPCIÓN al require: ".htmlspecialchars($e->getMessage())."</p>";
    error_log("DEBUG require config: ".$e->getMessage());
    exit;
}

// 2) Probar conexión MySQL usando esos valores
$mysqli = @new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($mysqli->connect_errno) {
    echo "<p style='color:red'><strong>Fallo conexión MySQL</strong></p>";
    echo "<p>Código: ".htmlspecialchars($mysqli->connect_errno)."</p>";
    echo "<p>Mensaje: ".htmlspecialchars($mysqli->connect_error)."</p>";
    error_log("DEBUG MySQL connect error ({$mysqli->connect_errno}): {$mysqli->connect_error}");
    exit;
}
echo "<p style='color:green'>OK: Conexión MySQL establecida. Versión MySQL: ".htmlspecialchars($mysqli->server_info)."</p>";

// 3) Hacer una consulta simple
if ($res = $mysqli->query("SHOW TABLES LIMIT 5")) {
    echo "<p>OK: SHOW TABLES returned rows: ".$res->num_rows."</p>";
    $res->close();
} else {
    echo "<p style='color:orange'>WARN: SHOW TABLES falló: ".htmlspecialchars($mysqli->error)."</p>";
    error_log("DEBUG SHOW TABLES error: ".$mysqli->error);
}
$mysqli->close();
echo "<p>DEBUG: fin</p>";
