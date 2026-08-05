<?php
header('Content-Type: application/json');
require 'conexion.php'; // conexión a la Base de Datos

$input = json_decode(file_get_contents('php://input'), true);
$process_uuid = $input['process_uuid'];
$inputs = $input['inputs'];
$outputs = $input['outputs'];

if (!$process_uuid || !is_array($inputs) || !is_array($outputs)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmtIn = $conn->prepare("INSERT INTO inputs (process_uuid, nombre, categoria, cantidad, unidad) VALUES (?, ?, ?, ?, ?)");
    foreach ($inputs as $in) {
        $stmtIn->bind_param("sssss", $process_uuid, $in['Flow'], $in['Category'], $in['Amount'], $in['Unit']);
        $stmtIn->execute();
    }

    $stmtOut = $conn->prepare("INSERT INTO outputs (process_uuid, nombre, categoria, cantidad, unidad) VALUES (?, ?, ?, ?, ?)");
    foreach ($outputs as $out) {
        $stmtOut->bind_param("sssss", $process_uuid, $out['Flow'], $out['Category'], $out['Amount'], $out['Unit']);
        $stmtOut->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>