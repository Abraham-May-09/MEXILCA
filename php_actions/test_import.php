<?php
// test_import.php - Archivo de prueba
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== TEST DE PROCESS_JSON_IMPORT ===\n\n";

// Test 1: Conexión
try {
    require_once 'conexion.php';
    echo "✓ Conexión a BD: OK\n";
} catch (Exception $e) {
    echo "✗ Error en conexión: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Verificar columna is_imported
$result = $conn->query("SHOW COLUMNS FROM processes LIKE 'is_imported'");
if ($result->num_rows > 0) {
    echo "✓ Columna is_imported: EXISTE\n";
} else {
    echo "✗ Columna is_imported: NO EXISTE (necesita crearla)\n";
}

// Test 3: Probar INSERT básico
$test_uuid = 'test-' . uniqid();
$stmt = $conn->prepare("
    INSERT INTO processes (
        uuid, name, process_type, category, description, version, 
        last_change, approval_status, is_draft, created_by_uuid, is_imported
    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
");

if (!$stmt) {
    echo "✗ Error preparando query: " . $conn->error . "\n";
    exit;
}

$name = "Test Process";
$type = "UNIT_PROCESS";
$cat = "Test";
$desc = "Test description";
$ver = "01.00.000";
$status = "draft";
$draft = 1;
$user = "test-user";
$imported = 1;

$stmt->bind_param("ssssssssii", 
    $test_uuid, $name, $type, $cat, $desc, $ver, $status, $draft, $user, $imported
);

if ($stmt->execute()) {
    echo "✓ INSERT de prueba: OK\n";
    // Limpiar
    $conn->query("DELETE FROM processes WHERE uuid = '$test_uuid'");
} else {
    echo "✗ Error en INSERT: " . $stmt->error . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
?>
