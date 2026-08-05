<?php
session_start();
require_once 'conexion.php';

$uuid = $_GET['uuid'] ?? '';
$type = $_GET['type'] ?? 'input';

if (empty($uuid)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'UUID requerido']);
    exit;
}

if ($type === 'input') {
    $stmt = $conn->prepare("SELECT * FROM process_inputs WHERE uuid = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM process_outputs WHERE uuid = ?");
}

$stmt->bind_param("s", $uuid);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    $data = ['error' => 'Flujo no encontrado'];
}

header('Content-Type: application/json');
echo json_encode($data);
$stmt->close();
$conn->close();
?>
