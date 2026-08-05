<?php
session_start();
require_once 'conexion.php';

$process_uuid = $_GET['process_uuid'] ?? '';
$type = $_GET['type'] ?? 'input';

if (empty($process_uuid)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

if ($type === 'input') {
    $stmt = $conn->prepare("SELECT * FROM process_inputs WHERE processuuid = ? ORDER BY created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM process_outputs WHERE processuuid = ? ORDER BY created_at DESC");
}

$stmt->bind_param("s", $process_uuid);
$stmt->execute();
$result = $stmt->get_result();

$flows = [];
while ($row = $result->fetch_assoc()) {
    $flows[] = $row;
}

header('Content-Type: application/json');
echo json_encode($flows);
$stmt->close();
$conn->close();
?>
