<?php
session_start();
require_once 'conexion.php';

$uuid = $_POST['uuid'] ?? '';
$type = $_POST['type'] ?? 'input';

if (empty($uuid)) {
    echo "Error: UUID requerido";
    exit;
}

if ($type === 'input') {
    $stmt = $conn->prepare("DELETE FROM process_inputs WHERE uuid = ?");
} else {
    $stmt = $conn->prepare("DELETE FROM process_outputs WHERE uuid = ?");
}

$stmt->bind_param("s", $uuid);

if ($stmt->execute()) {
    echo "Flujo eliminado exitosamente";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
