<?php
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // ✅ Soportar ambos nombres de parámetro
    $process_uuid = $_POST['processuuid'] ?? $_POST['process_uuid'] ?? null;
    if (!$process_uuid) {
      throw new Exception("Falta process_uuid");
    }

    // Validar que el proceso existe
    $stmt = $conn->prepare("SELECT uuid FROM processes WHERE uuid = ?");
    $stmt->bind_param("s", $process_uuid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
      throw new Exception("El proceso padre no existe. Guárdalo primero e intenta de nuevo.");
    }

    $conn->begin_transaction();
    $saved_count = 0;

    // ✅ INPUT PRINCIPAL (soportar nombres con y sin guión bajo)
    $resourceName = $_POST['resourcename'] ?? $_POST['resource_name'] ?? null;
    if (isset($resourceName) && !is_array($resourceName)) {
      $saved_count += guardarInput(
        $conn, $process_uuid,
        $_POST['uuid'] ?? bin2hex(random_bytes(16)),
        $_POST['category'] ?? null,
        $resourceName,
        $_POST['quantity'] ?? null,
        $_POST['unit'] ?? null,
        $_POST['datasource'] ?? '',  // ✅ AGREGADO
        $_POST['commentary'] ?? ''
      );
    }
    // ✅ INPUTS DINÁMICOS
    elseif (isset($resourceName) && is_array($resourceName)) {
      $count = count($resourceName);
      for ($i = 0; $i < $count; $i++) {
        $saved_count += guardarInput(
          $conn, $process_uuid,
          $_POST['uuid'][$i] ?? bin2hex(random_bytes(16)),
          $_POST['category'][$i] ?? null,
          $resourceName[$i] ?? null,
          $_POST['quantity'][$i] ?? null,
          $_POST['unit'][$i] ?? null,
          $_POST['datasource'][$i] ?? '',  // ✅ AGREGADO
          $_POST['commentary'][$i] ?? ''
        );
      }
    }

    $conn->commit();
    echo "✓ Inputs guardados exitosamente: $saved_count registro(s)";
  } catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
      $conn->rollback();
    }
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
  }
}

function guardarInput($conn, $process_uuid, $input_uuid, $flow_category, $flow_name, $amount, $unit_name, $data_source, $commentary) {
  // ✅ VALIDAR CAMPOS OBLIGATORIOS
  if (!$flow_category || !$flow_name || !$amount || !$unit_name) {
    throw new Exception("Faltan campos obligatorios en Input: Categoría, Nombre, Cantidad y Unidad");
  }

  $flow_name = trim($flow_name);
  $unit_name = trim($unit_name);

  // ✅ flow_type por defecto
  $flow_type = 'PRODUCT_FLOW';

  // 1. Buscar o crear flow
  $stmt = $conn->prepare("SELECT uuid FROM flows WHERE name = ? LIMIT 1");
  $stmt->bind_param("s", $flow_name);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $flow_uuid = $result->fetch_assoc()['uuid'];
  } else {
    $flow_uuid = bin2hex(random_bytes(16));
    $stmt = $conn->query("SELECT uuid FROM flow_properties LIMIT 1");
    if (!$stmt || $stmt->num_rows === 0) {
      throw new Exception("No hay flow_properties en la base de datos");
    }
    $ref_flow_property_uuid = $stmt->fetch_assoc()['uuid'];
    $stmt = $conn->prepare("INSERT INTO flows (uuid, name, category, flow_type, reference_flow_property_uuid) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $flow_uuid, $flow_name, $flow_category, $flow_type, $ref_flow_property_uuid);
    $stmt->execute();
    $factor_uuid = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("INSERT INTO flow_property_factors (uuid, flow_uuid, flow_property_uuid, conversion_factor, is_ref_flow_property) VALUES (?, ?, ?, 1.0, 1)");
    $stmt->bind_param("sss", $factor_uuid, $flow_uuid, $ref_flow_property_uuid);
    $stmt->execute();
  }

  // 2. Buscar unidad
  $stmt = $conn->prepare("SELECT uuid, unit_group_uuid FROM units WHERE name = ? LIMIT 1");
  $stmt->bind_param("s", $unit_name);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows === 0) {
    throw new Exception("Unidad '$unit_name' no encontrada");
  }
  $unit_data = $result->fetch_assoc();
  $unit_uuid = $unit_data['uuid'];
  $unit_group_uuid = $unit_data['unit_group_uuid'];

  // 3. Obtener flow_property
  $stmt = $conn->prepare("SELECT uuid FROM flow_properties WHERE unit_group_uuid = ? LIMIT 1");
  $stmt->bind_param("s", $unit_group_uuid);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $flow_property_uuid = $result->fetch_assoc()['uuid'];
  } else {
    $stmt = $conn->query("SELECT uuid FROM flow_properties LIMIT 1");
    $flow_property_uuid = $stmt->fetch_assoc()['uuid'];
  }

  // 4. Obtener internal_id
  $stmt = $conn->prepare("SELECT COALESCE(MAX(internal_id), 0) + 1 as next_id FROM process_inputs WHERE process_uuid = ?");
  $stmt->bind_param("s", $process_uuid);
  $stmt->execute();
  $internal_id = $stmt->get_result()->fetch_assoc()['next_id'];

  // 5. ✅ INSERTAR CON data_source
  $stmt = $conn->prepare("INSERT INTO process_inputs (uuid, process_uuid, internal_id, flow_uuid, category, amount, unit_uuid, flow_property_uuid, description, data_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssissdssss", $input_uuid, $process_uuid, $internal_id, $flow_uuid, $flow_category, $amount, $unit_uuid, $flow_property_uuid, $commentary, $data_source);
  return $stmt->execute() ? 1 : 0;
}
?>
