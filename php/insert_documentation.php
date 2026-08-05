<?php
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // ✅ LEER process_uuid (soporta ambos nombres)
    $process_uuid = $_POST['processuuid'] ?? $_POST['process_uuid'] ?? null;
    if (!$process_uuid) {
      throw new Exception("Falta process_uuid");
    }

    // ✅ CAMPOS (no validar como obligatorios para borradores)
    $reference_year = $_POST['referenceYear'] ?? '';
    $data_owner = $_POST['dataOwner'] ?? '';
    $contact_information = $_POST['contactInformation'] ?? '';
    $review_status = $_POST['reviewStatus'] ?? '';
    $access_conditions = $_POST['accessConditions'] ?? '';
    $license = $_POST['license'] ?? '';
    $valid_until = $_POST['validuntil'] ?? '';
    $data_source = $_POST['dataSource'] ?? '';
    $compliance = $_POST['complianceStandards'] ?? '';
    $data_quality = $_POST['dataQualityIndicators'] ?? '';

    $conn->begin_transaction();

    // 1. ✅ ACTUALIZAR processes (valid_until y dq_data_quality)
    $stmt = $conn->prepare("
      UPDATE processes 
      SET valid_from = ?, 
          valid_until = ?,
          dq_data_quality = ?
      WHERE uuid = ?
    ");
    $stmt->bind_param("ssss", 
      $reference_year, 
      $valid_until,
      $data_quality,
      $process_uuid
    );
    if (!$stmt->execute()) {
      throw new Exception("Error al actualizar proceso: " . $stmt->error);
    }

    // 2. Crear o buscar el actor (data owner) - OPCIONAL
    $owner_uuid = null;
    if (!empty($data_owner)) {
      $stmt = $conn->prepare("SELECT uuid FROM actors WHERE name = ? LIMIT 1");
      $stmt->bind_param("s", $data_owner);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows > 0) {
        $owner_uuid = $result->fetch_assoc()['uuid'];
      } else {
        $owner_uuid = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO actors (uuid, name, email, telephone) VALUES (?, ?, ?, '')");
        $stmt->bind_param("sss", $owner_uuid, $data_owner, $contact_information);
        if (!$stmt->execute()) {
          throw new Exception("Error al crear actor: " . $stmt->error);
        }
      }

      // Vincular actor como owner
      $pa_uuid = bin2hex(random_bytes(16));
      $stmt = $conn->prepare("
        INSERT INTO process_actors (uuid, process_uuid, actor_uuid, role) 
        VALUES (?, ?, ?, 'owner') 
        ON DUPLICATE KEY UPDATE role='owner'
      ");
      $stmt->bind_param("sss", $pa_uuid, $process_uuid, $owner_uuid);
      $stmt->execute();
    }

    // 3. ✅ INSERTAR/ACTUALIZAR process_documentation CON COLUMNAS SEPARADAS
    // Crear access_use_restrictions concatenado (para compatibilidad con código viejo)
    $access_text = "Review: $review_status | Access: $access_conditions | License: $license";
    if (!empty($compliance)) {
      $access_text .= " | Compliance: $compliance";
    }
    
    $stmt = $conn->prepare("
      INSERT INTO process_documentation (
        process_uuid,
        creation_date,
        sources_text,
        data_owner,
        contact_information,
        review_status,
        access_conditions,
        license,
        access_use_restrictions
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        creation_date = VALUES(creation_date),
        sources_text = VALUES(sources_text),
        data_owner = VALUES(data_owner),
        contact_information = VALUES(contact_information),
        review_status = VALUES(review_status),
        access_conditions = VALUES(access_conditions),
        license = VALUES(license),
        access_use_restrictions = VALUES(access_use_restrictions)
    ");
    
    $stmt->bind_param("sssssssss",
      $process_uuid,
      $reference_year,
      $data_source,
      $data_owner,
      $contact_information,
      $review_status,
      $access_conditions,
      $license,
      $access_text
    );
    
    if (!$stmt->execute()) {
      throw new Exception("Error al guardar documentación: " . $stmt->error);
    }

    $conn->commit();
    echo "✓ Documentación guardada exitosamente";
    
  } catch (Exception $e) {
    if (isset($conn)) {
      $conn->rollback();
    }
    error_log("Error en insert_documentation.php: " . $e->getMessage());
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
  }
} else {
  http_response_code(405);
  echo "Método no permitido";
}
?>
