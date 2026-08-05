<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$uuid = $data['uuid'] ?? '';
$changed_amounts = $data['changed_amounts'] ?? [];
$deleted_rows = $data['deleted_rows'] ?? [];
$linked_processes = $data['linked_processes'] ?? [];

if (empty($uuid)) {
    echo json_encode(['success' => false, 'message' => 'UUID no proporcionado']);
    exit;
}

$config = require __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

try {
    $mysqli->begin_transaction();

    // 1. Actualizar amounts
    foreach ($changed_amounts as $change) {
        $table = ($change['type'] === 'input') ? 'process_inputs' : 'process_outputs';
        $stmt = $mysqli->prepare("UPDATE $table SET amount = ? WHERE id = ?");
        $stmt->bind_param('di', $change['amount'], $change['id']);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Eliminar filas
    foreach ($deleted_rows as $row) {
        list($type, $id) = explode('_', $row);
        $table = ($type === 'input') ? 'process_inputs' : 'process_outputs';
        $stmt = $mysqli->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    // 3. Actualizar procesos ligados
    foreach ($linked_processes as $link) {
        $table = ($link['type'] === 'input') ? 'process_inputs' : 'process_outputs';
        $stmt = $mysqli->prepare("UPDATE $table SET provider_process_uuid = ? WHERE id = ?");
        $stmt->bind_param('si', $link['target_uuid'], $link['id']);
        $stmt->execute();
        $stmt->close();
    }

    // 4. Actualizar fecha de modificación
    $stmt = $mysqli->prepare("UPDATE processes SET last_change = NOW() WHERE uuid = ?");
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'Cambios guardados correctamente']);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Error saving changes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar cambios']);
}

$mysqli->close();
