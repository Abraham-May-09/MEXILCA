<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_uuid'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$config = require __DIR__ . '/../config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$uuid = $data['uuid'] ?? null;

if (!$uuid) {
    echo json_encode(['success' => false, 'message' => 'UUID no proporcionado']);
    exit;
}

$user_uuid = $_SESSION['user_uuid'];

try {
    // Solo eliminar si es borrador Y es del usuario
    $sql = "DELETE FROM processes 
            WHERE uuid = ? 
            AND created_by_uuid = ? 
            AND is_draft = 1";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ss", $uuid, $user_uuid);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Borrador eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar. Verifica que sea tu borrador.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$mysqli->close();
?>
