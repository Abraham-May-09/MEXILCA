<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/conexion.php';

try {
  $process_uuid = $_POST['process_uuid'] ?? $_POST['uuid'] ?? '';
  if (!$process_uuid) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Falta process_uuid']);
    exit;
  }

  if (!$conn) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Sin conexión a la BD']);
    exit;
  }

  // VALIDAR QUE EL PROCESO PADRE EXISTE
  $stmt = $conn->prepare("SELECT uuid FROM processes WHERE uuid = ? LIMIT 1");
  $stmt->bind_param("s", $process_uuid);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'El proceso padre no existe. Guarda primero el proceso.']);
    exit;
  }

  // Recibir exchanges del frontend
  $exchanges = $_POST['exchanges'] ?? [];
  if (!is_array($exchanges) || !count($exchanges)) {
    echo json_encode(['ok'=>true,'saved'=>0,'note'=>'Sin intercambios que guardar']);
    exit;
  }

  $conn->begin_transaction();
  $saved = 0;

  foreach ($exchanges as $key => $row) {
    $type_of_exchange = $row['type_of_exchange'] ?? $row['direction'] ?? null;
    $linked_process   = $row['linked_process']   ?? $row['linked_uuid'] ?? null;
    $commentary       = $row['commentary']       ?? $row['comment'] ?? '';

    if (!$type_of_exchange || !$linked_process) continue;

    // OBTENER EL OUTPUT DE REFERENCIA DEL PROCESO VINCULADO
    $stmt = $conn->prepare("
      SELECT 
        po.flow_uuid,
        po.amount,
        po.unit_uuid,
        po.flow_property_uuid,
        po.category,
        f.name as flow_name
      FROM process_outputs po
      INNER JOIN flows f ON po.flow_uuid = f.uuid
      WHERE po.process_uuid = ? AND po.is_reference = 1
      LIMIT 1
    ");
    
    $stmt->bind_param("s", $linked_process);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
      // Si el proceso vinculado no tiene output de referencia, saltarlo
      continue;
    }
    
    $linked_data = $result->fetch_assoc();

    // 3. Insertar según el tipo
    if ($type_of_exchange === 'input') {
      // Obtener internal_id
      $stmt = $conn->prepare("SELECT COALESCE(MAX(internal_id), 0) + 1 as next_id FROM process_inputs WHERE process_uuid = ?");
      $stmt->bind_param("s", $process_uuid);
      $stmt->execute();
      $internal_id = $stmt->get_result()->fetch_assoc()['next_id'];

      // Insertar input CON DATOS DEL PROCESO VINCULADO
      $exchange_uuid = bin2hex(random_bytes(16));
      $is_avoided = 0;
      
      $stmt = $conn->prepare("
        INSERT INTO process_inputs (
          uuid, process_uuid, internal_id, flow_uuid, category,
          amount, unit_uuid, flow_property_uuid, is_avoided, 
          provider_process_uuid, provider_name, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      
      $stmt->bind_param("ssissdssssss",
        $exchange_uuid, 
        $process_uuid, 
        $internal_id, 
        $linked_data['flow_uuid'],
        $linked_data['category'],
        $linked_data['amount'],
        $linked_data['unit_uuid'],
        $linked_data['flow_property_uuid'],
        $is_avoided,
        $linked_process, 
        $linked_data['flow_name'],
        $commentary
      );
      
      if ($stmt->execute()) {
        $saved++;
      }
      
    } elseif ($type_of_exchange === 'output') {
      // Obtener internal_id
      $stmt = $conn->prepare("SELECT COALESCE(MAX(internal_id), 0) + 1 as next_id FROM process_outputs WHERE process_uuid = ?");
      $stmt->bind_param("s", $process_uuid);
      $stmt->execute();
      $internal_id = $stmt->get_result()->fetch_assoc()['next_id'];

      // Insertar output CON DATOS DEL PROCESO VINCULADO
      $exchange_uuid = bin2hex(random_bytes(16));
      $is_reference = 0;
      
      $stmt = $conn->prepare("
        INSERT INTO process_outputs (
          uuid, process_uuid, internal_id, flow_uuid, category,
          amount, unit_uuid, flow_property_uuid, is_reference,
          provider_process_uuid, provider_name, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      
      $stmt->bind_param("ssissdssssss",
        $exchange_uuid,
        $process_uuid,
        $internal_id,
        $linked_data['flow_uuid'],
        $linked_data['category'],
        $linked_data['amount'],
        $linked_data['unit_uuid'],
        $linked_data['flow_property_uuid'],
        $is_reference,
        $linked_process,
        $linked_data['flow_name'],
        $commentary
      );
      
      if ($stmt->execute()) {
        $saved++;
      }
    }
  }

  $conn->commit();
  echo json_encode(['ok'=>true, 'saved'=>$saved], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($conn)) {
    $conn->rollback();
  }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
