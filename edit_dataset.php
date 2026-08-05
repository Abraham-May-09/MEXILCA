<?php
session_start();
// Habilitar logs de errores detallados
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/edit_dataset_error.log');

// Log de debugging
file_put_contents(__DIR__ . '/debug.log', 
    date('Y-m-d H:i:s') . " - POST recibido: " . print_r($_POST, true) . "\n", 
    FILE_APPEND
);
// Seguridad y logging
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// Cargar config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
  error_log("FATAL: config.php no encontrado: $configPath");
  http_response_code(500);
  exit("Error de configuración.");
}
$config = require $configPath;
if (!is_array($config)) {
  error_log("FATAL: config.php inválido");
  http_response_code(500);
  exit("Error de configuración.");
}
foreach (['db_host','db_user','db_pass','db_name'] as $k) {
  if (!isset($config[$k]) || $config[$k] === '') {
    error_log("FATAL: Config faltante/vacía: $k");
    http_response_code(500);
    exit("Error de configuración.");
  }
}

// Conexión
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

// Manejo de guardado AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  if ($_POST['action'] === 'save_process') {
    try {
      $uuid = $_POST['uuid'] ?? '';
        $es_borrador = isset($_POST['isdraft']) && $_POST['isdraft'] == '1';
        $is_draft_value = $es_borrador ? 1 : 0;
        $approval_status = $es_borrador ? 'draft' : 'pending';

      // Actualizar proceso principal
      $stmt = $mysqli->prepare("
        UPDATE processes SET
          name = ?,
          category = ?,
          description = ?,
          tech_desc = ?,
          geo_desc = ?,
          process_type = ?,
          time_desc = ?,
          version = ?,
          valid_from = ?,
          valid_until = ?,
          is_draft = ?,
          approval_status = ?, 
          
          last_change = NOW()
        WHERE uuid = ?
      ");

      $stmt->bind_param('ssssssssssiss',
        $_POST['process_name'],
        $_POST['category'],
        $_POST['general_comment'],
        $_POST['technology_description'],
        $_POST['geo_description'],
        $_POST['process_type'],
        $_POST['time_desc'],
        $_POST['version'],
        $_POST['valid_from'],
        $_POST['valid_until'],
        $is_draft_value,
        $approval_status,
        $uuid
      );
      $stmt->execute();
      $stmt->close();

      // Actualizar documentación
      $copyrightFlag = isset($_POST['copyright']) && $_POST['copyright'] === '1' ? 1 : 0;

      $stmt = $mysqli->prepare("
        UPDATE process_documentation SET
          project = ?,
          lci_method = ?,
          ds_data_selection = ?,
          ds_data_treatment = ?,
          ds_collection_period = ?,
          ds_data_completeness = ?,
          completeness_text = ?,
          sources_text = ?,
          modeling_constants = ?,
          access_use_restrictions = ?,
          copyright_flag = ?
        WHERE process_uuid = ?
      ");

      $stmt->bind_param('ssssssssssss',
        $_POST['project'],
        $_POST['lci_method'],
        $_POST['ds_data_selection'],
        $_POST['ds_data_treatment'],
        $_POST['data_collection_period'],
        $_POST['ds_data_completeness'],
        $_POST['completeness_text'],
        $_POST['sources_text'],
        $_POST['modeling_constants'],
        $_POST['access_restrictions'],
        $copyrightFlag,
        $uuid
      );
      $stmt->execute();
      $stmt->close();

      // Actualizar modeller si se proporcionó
      if (isset($_POST['modeller_uuid']) && !empty($_POST['modeller_uuid']) && $_POST['modeller_uuid'] !== '') {
        $modellerUuid = $_POST['modeller_uuid'];
        
        // Verificar si el UUID existe en actors, si no, crearlo desde users
        $stmt = $mysqli->prepare("SELECT uuid FROM actors WHERE uuid = ?");
        $stmt->bind_param('s', $modellerUuid);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$exists) {
          // El UUID no existe en actors, buscar en users y crear el actor
          $stmt = $mysqli->prepare("SELECT uuid, name, email, institution FROM users WHERE uuid = ?");
          $stmt->bind_param('s', $modellerUuid);
          $stmt->execute();
          $user = $stmt->get_result()->fetch_assoc();
          $stmt->close();
          
          if ($user) {
            // Crear actor desde usuario
            $stmt = $mysqli->prepare("
              INSERT INTO actors (uuid, name, email, category) 
              VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email)
            ");
            $category = $user['institution'] ?? 'Usuario registrado';
            $stmt->bind_param('ssss', $user['uuid'], $user['name'], $user['email'], $category);

            $stmt->execute();
            $stmt->close();
          }
        }
        
        // Ahora sí eliminar e insertar en process_actors
        $stmt = $mysqli->prepare("DELETE FROM process_actors WHERE process_uuid = ? AND role = 'modeller'");
        $stmt->bind_param('s', $uuid);
        $stmt->execute();
        $stmt->close();
        
        $paUuid1 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $stmt = $mysqli->prepare("INSERT INTO process_actors (uuid, process_uuid, actor_uuid, role) VALUES (?, ?, ?, 'modeller')");
        $stmt->bind_param('sss', $paUuid1, $uuid, $modellerUuid);
        $stmt->execute();
        $stmt->close();
      }
      
      // Actualizar owner si se proporcionó
      if (isset($_POST['owner_uuid']) && !empty($_POST['owner_uuid']) && $_POST['owner_uuid'] !== '') {
        $ownerUuid = $_POST['owner_uuid'];
        
        // Verificar si el UUID existe en actors, si no, crearlo desde users
        $stmt = $mysqli->prepare("SELECT uuid FROM actors WHERE uuid = ?");
        $stmt->bind_param('s', $ownerUuid);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$exists) {
          // El UUID no existe en actors, buscar en users y crear el actor
          $stmt = $mysqli->prepare("SELECT uuid, name, email, institution FROM users WHERE uuid = ?");
          $stmt->bind_param('s', $ownerUuid);
          $stmt->execute();
          $user = $stmt->get_result()->fetch_assoc();
          $stmt->close();
          
          if ($user) {
            // Crear actor desde usuario (SIN created_at)
            $stmt = $mysqli->prepare("
              INSERT INTO actors (uuid, name, email, category) 
              VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), category = VALUES(category)
            ");
            $category = $user['institution'] ?? 'Usuario registrado';
            $stmt->bind_param('ssss', $user['uuid'], $user['name'], $user['email'], $category);
            $stmt->execute();
            $stmt->close();
          }
        }
        
        // Ahora sí eliminar e insertar en process_actors
        $stmt = $mysqli->prepare("DELETE FROM process_actors WHERE process_uuid = ? AND role = 'owner'");
        $stmt->bind_param('s', $uuid);
        $stmt->execute();
        $stmt->close();
        
        // Generar UUID para la relación
        $paUuid2 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $stmt = $mysqli->prepare("INSERT INTO process_actors (uuid, process_uuid, actor_uuid, role) VALUES (?, ?, ?, 'owner')");
        $stmt->bind_param('sss', $paUuid2, $uuid, $ownerUuid);
        $stmt->execute();
        $stmt->close();
      }

      echo json_encode(['success' => true, 'message' => 'Dataset actualizado correctamente']);
    } catch (Exception $e) {
      error_log("Error al guardar: " . $e->getMessage());
      echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($_POST['action'] === 'update_flow') {
    try {
      $flowType = $_POST['flow_type'];
      $flowUuid = $_POST['flow_uuid'];
      $newProcessUuid = $_POST['new_process_uuid'];
      $amount = $_POST['amount'];

      // Obtener información del nuevo proceso CON validación de flow_property Y category
      $stmt = $mysqli->prepare("
        SELECT 
          p.name,
          p.category,
          u.name as unit_name,
          u.uuid as unit_uuid,
          u.unit_group_uuid,
          o.flow_uuid,
          o.flow_property_uuid,
          o.category as output_category,
          fp.name as flow_property_name,
          fp.unit_group_uuid as fp_unit_group_uuid
        FROM processes p
        LEFT JOIN process_outputs o ON o.process_uuid = p.uuid AND o.is_reference = 1
        LEFT JOIN units u ON u.uuid = o.unit_uuid
        LEFT JOIN flow_properties fp ON fp.uuid = o.flow_property_uuid
        WHERE p.uuid = ?
        LIMIT 1
      ");
      $stmt->bind_param('s', $newProcessUuid);
      $stmt->execute();
      $newProcess = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$newProcess) {
        echo json_encode(['success' => false, 'message' => 'Proceso no encontrado']);
        exit;
      }

      // VALIDACIÓN CRÍTICA: verificar que unit pertenece al unit_group de la flow_property
      if ($newProcess['unit_group_uuid'] && $newProcess['fp_unit_group_uuid'] && 
          $newProcess['unit_group_uuid'] !== $newProcess['fp_unit_group_uuid']) {
        echo json_encode([
          'success' => false, 
          'message' => 'Error: La unidad (' . $newProcess['unit_name'] . ') no corresponde al grupo de unidades de la propiedad de flujo (' . $newProcess['flow_property_name'] . '). Por favor selecciona un proceso con unidades compatibles.'
        ]);
        exit;
      }

      // Determinar categoría a usar
      $categoryToUse = !empty($newProcess['output_category']) ? $newProcess['output_category'] : $newProcess['category'];

      // Actualizar el flujo con flow_property validada
      $table = ($flowType === 'input') ? 'process_inputs' : 'process_outputs';
      $stmt = $mysqli->prepare("
        UPDATE $table SET
          provider_process_uuid = ?,
          provider_name = ?,
          flow_uuid = ?,
          unit_uuid = ?,
          flow_property_uuid = ?,
          category = ?,
          amount = ?
        WHERE uuid = ?
      ");

      $stmt->bind_param('ssssssds',
        $newProcessUuid,
        $newProcess['name'],
        $newProcess['flow_uuid'],
        $newProcess['unit_uuid'],
        $newProcess['flow_property_uuid'],
        $categoryToUse,
        $amount,
        $flowUuid
      );
      $stmt->execute();
      $stmt->close();

      echo json_encode([
        'success' => true,
        'message' => 'Flujo actualizado correctamente',
        'unit' => $newProcess['unit_name'],
        'flow_name' => $newProcess['name'],
        'category' => $categoryToUse,
        'flow_property' => $newProcess['flow_property_name']
      ]);
    } catch (Exception $e) {
      error_log("Error al actualizar flujo: " . $e->getMessage());
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($_POST['action'] === 'update_amount') {
    try {
      $flowType = $_POST['flow_type'];
      $flowUuid = $_POST['flow_uuid'];
      $amount = $_POST['amount'];

      $table = ($flowType === 'input') ? 'process_inputs' : 'process_outputs';
      $stmt = $mysqli->prepare("UPDATE $table SET amount = ? WHERE uuid = ?");
      $stmt->bind_param('ds', $amount, $flowUuid);
      $stmt->execute();
      $stmt->close();

      echo json_encode(['success' => true, 'message' => 'Cantidad actualizada']);
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($_POST['action'] === 'update_description') {
    try {
      $flowType = $_POST['flow_type'];
      $flowUuid = $_POST['flow_uuid'];
      $description = $_POST['description'];

      $table = ($flowType === 'input') ? 'process_inputs' : 'process_outputs';
      $stmt = $mysqli->prepare("UPDATE $table SET description = ? WHERE uuid = ?");
      $stmt->bind_param('ss', $description, $flowUuid);
      $stmt->execute();
      $stmt->close();

      echo json_encode(['success' => true, 'message' => 'Descripción actualizada']);
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($_POST['action'] === 'create_new_flow') {
    try {
      $flowType = $_POST['flow_type'];
      $processUuid = $_POST['process_uuid'];
      $newProcessUuid = $_POST['new_process_uuid'];
      $amount = $_POST['amount'];

      // Obtener información del nuevo proceso
      $stmt = $mysqli->prepare("
        SELECT 
          p.name,
          p.category,
          u.name as unit_name,
          u.uuid as unit_uuid,
          u.unit_group_uuid,
          o.flow_uuid,
          o.flow_property_uuid,
          o.category as output_category,
          f.name as flow_name,
          f.flow_type as flow_type,
          fp.name as flow_property_name,
          fp.unit_group_uuid as fp_unit_group_uuid
        FROM processes p
        LEFT JOIN process_outputs o ON o.process_uuid = p.uuid AND o.is_reference = 1
        LEFT JOIN flows f ON f.uuid = o.flow_uuid
        LEFT JOIN units u ON u.uuid = o.unit_uuid
        LEFT JOIN flow_properties fp ON fp.uuid = o.flow_property_uuid
        WHERE p.uuid = ?
        LIMIT 1
      ");
      $stmt->bind_param('s', $newProcessUuid);
      $stmt->execute();
      $newProcess = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$newProcess) {
        echo json_encode(['success' => false, 'message' => 'Proceso no encontrado']);
        exit;
      }

      // VALIDAR que tenga flow_uuid
      if (!$newProcess['flow_uuid'] || empty($newProcess['flow_uuid'])) {
        echo json_encode(['success' => false, 'message' => 'El proceso seleccionado no tiene un flow de referencia definido']);
        exit;
      }

      // CREAR EL FLOW SI NO EXISTE en la tabla flows
      $stmt = $mysqli->prepare("SELECT uuid FROM flows WHERE uuid = ?");
      $stmt->bind_param('s', $newProcess['flow_uuid']);
      $stmt->execute();
      $flowExists = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$flowExists) {
        // El flow NO existe, crearlo automáticamente
        $flowNameToCreate = $newProcess['flow_name'] ?? $newProcess['name'];
        $flowCategoryToCreate = $newProcess['output_category'] ?? $newProcess['category'];
        $flowTypeToCreate = $newProcess['flow_type'] ?? 'PRODUCT_FLOW';

        $stmt = $mysqli->prepare("
          INSERT INTO flows (uuid, name, category, flow_type) 
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmt->bind_param('ssss', 
          $newProcess['flow_uuid'], 
          $flowNameToCreate, 
          $flowCategoryToCreate, 
          $flowTypeToCreate
        );
        $stmt->execute();
        $stmt->close();
      }

      // Validación de unit_group
      if ($newProcess['unit_group_uuid'] && $newProcess['fp_unit_group_uuid'] && 
          $newProcess['unit_group_uuid'] !== $newProcess['fp_unit_group_uuid']) {
        echo json_encode([
          'success' => false, 
          'message' => 'Error: La unidad no corresponde al grupo de unidades de la propiedad de flujo.'
        ]);
        exit;
      }

      // Generar nuevo UUID para el registro
      $newRecordUuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
      );

      // Determinar categoría
      $categoryToUse = !empty($newProcess['output_category']) ? $newProcess['output_category'] : $newProcess['category'];

      // Obtener el internal_id máximo
      $table = ($flowType === 'input') ? 'process_inputs' : 'process_outputs';
      $stmt = $mysqli->prepare("SELECT MAX(internal_id) as max_id FROM $table WHERE process_uuid = ?");
      $stmt->bind_param('s', $processUuid);
      $stmt->execute();
      $result = $stmt->get_result()->fetch_assoc();
      $maxId = $result['max_id'] ?? 0;
      $newInternalId = $maxId + 1;
      $stmt->close();

     // Insertar nuevo flujo
          if ($flowType === 'input') {
            $stmt = $mysqli->prepare("
              INSERT INTO process_inputs (
                uuid, process_uuid, internal_id, flow_uuid, provider_process_uuid, provider_name,
                unit_uuid, flow_property_uuid, category, amount, description
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $descEmpty = '';
            $stmt->bind_param('ssissssssds',
              $newRecordUuid, 
              $processUuid, 
              $newInternalId,
              $newProcess['flow_uuid'], 
              $newProcessUuid, 
              $newProcess['name'],
              $newProcess['unit_uuid'], 
              $newProcess['flow_property_uuid'],
              $categoryToUse, 
              $amount, 
              $descEmpty
            );
          } else {
            $stmt = $mysqli->prepare("
              INSERT INTO process_outputs (
                uuid, process_uuid, internal_id, flow_uuid, provider_process_uuid, provider_name,
                unit_uuid, flow_property_uuid, category, amount, is_reference, description
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $isRef = 0;
            $descEmpty = '';
            $stmt->bind_param('ssissssssdis',
              $newRecordUuid, 
              $processUuid, 
              $newInternalId,
              $newProcess['flow_uuid'], 
              $newProcessUuid, 
              $newProcess['name'],
              $newProcess['unit_uuid'], 
              $newProcess['flow_property_uuid'],
              $categoryToUse, 
              $amount, 
              $isRef,
              $descEmpty
            );
          }

      $stmt->execute();
      $stmt->close();

      echo json_encode([
        'success' => true,
        'message' => 'Flujo creado correctamente',
        'flow_uuid' => $newRecordUuid,
        'flow_name' => $newProcess['name'],
        'category' => $categoryToUse,
        'unit' => $newProcess['unit_name'],
        'amount' => $amount
      ]);
    } catch (Exception $e) {
      error_log("Error al crear flujo: " . $e->getMessage());
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }
  
    if ($_POST['action'] === 'delete_flow') {
    try {
      $flowType = $_POST['flow_type'] ?? '';
      $flowUuid = $_POST['flow_uuid'] ?? '';

      if (!$flowUuid || ($flowType !== 'input' && $flowType !== 'output')) {
        echo json_encode([
          'success' => false,
          'message' => 'Parámetros inválidos'
        ]);
        exit;
      }

      $table = ($flowType === 'input') ? 'process_inputs' : 'process_outputs';

      $stmt = $mysqli->prepare("DELETE FROM $table WHERE uuid = ?");
      $stmt->bind_param('s', $flowUuid);
      $stmt->execute();
      $stmt->close();

      echo json_encode([
        'success' => true,
        'message' => 'Flujo eliminado correctamente'
      ]);
    } catch (Exception $e) {
      error_log('Error al eliminar flujo: ' . $e->getMessage());
      echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar el flujo.'
      ]);
    }
    exit;
  }


  if ($_POST['action'] === 'search_datasets') {
    try {
      $search = '%' . ($_POST['search'] ?? '') . '%';
      $stmt = $mysqli->prepare("
        SELECT 
          p.uuid,
          p.name,
          p.category,
          l.name as location,
          u.name as unit,
          fp.name as flow_property
        FROM processes p
        LEFT JOIN locations l ON l.uuid = p.location_uuid
        LEFT JOIN process_outputs o ON o.process_uuid = p.uuid AND o.is_reference = 1
        LEFT JOIN units u ON u.uuid = o.unit_uuid
        LEFT JOIN flow_properties fp ON fp.uuid = o.flow_property_uuid
        WHERE p.name LIKE ? OR p.category LIKE ?
        ORDER BY p.name
        LIMIT 50
      ");
      $stmt->bind_param('ss', $search, $search);
      $stmt->execute();
      $result = $stmt->get_result();
      $datasets = [];
      while ($row = $result->fetch_assoc()) {
        $datasets[] = $row;
      }
      $stmt->close();

      echo json_encode(['success' => true, 'datasets' => $datasets]);
    } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

if ($_POST['action'] === 'search_actors_and_users') {
    try {
      $search = '%' . ($_POST['search'] ?? '') . '%';
      
      // Buscar en actors
      $actors = [];
      $stmt = $mysqli->prepare("
        SELECT uuid, name, email, 'actor' as source_type, category
        FROM actors
        WHERE name LIKE ? OR email LIKE ?
        ORDER BY name
      ");
      $stmt->bind_param('ss', $search, $search);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $actors[] = $row;
      }
      $stmt->close();
      
      // Buscar en usuarios registrados (tabla 'users')
      $users = [];
      $stmt = $mysqli->prepare("
        SELECT 
          uuid, 
          name, 
          email, 
          'user' as source_type, 
          CONCAT(institution, ' - ', country) as category
        FROM users
        WHERE name LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?
        ORDER BY name
      ");
      $stmt->bind_param('ssss', $search, $search, $search, $search);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $users[] = $row;
      }
      $stmt->close();
      
      // Combinar resultados (actors primero, luego usuarios)
      $combined = array_merge($actors, $users);
      
      echo json_encode(['success' => true, 'actors' => $combined]);
    } catch (Exception $e) {
      error_log("Error en search_actors_and_users: " . $e->getMessage());
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }
}

// Ruta propia para enlaces
$self = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'];

// Validar UUID
if (!isset($_GET['uuid'])) {
  http_response_code(400);
  exit('Parámetro ?uuid= faltante en la URL.');
}
$uuid = trim($_GET['uuid']);
if (!preg_match('/^[a-f0-9-]{32,36}$/i', $uuid)) {
  http_response_code(400);
  exit('UUID con formato inválido.');
}
if (strlen($uuid) === 32) {
  $uuid = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20);
}

// Proceso + documentación + ubicación + referencia cuantitativa
$sqlProc = "
SELECT
  p.uuid,
  p.name                         AS process_name,
  p.category                     AS category,
  p.description                  AS general_comment,
  p.tech_desc                    AS technology_description,
  p.geo_desc                     AS geo_description,
  p.process_type                 AS process_type,
  p.time_desc                    AS time_desc,
  p.version                      AS version,
  p.last_change                  AS last_change,
  p.created_at                   AS created_at,
  p.dq_flow_schema               AS flow_diagram,
  l.name                         AS location,
  pd.project                     AS project,
  pd.lci_method                  AS lci_method,
  pd.ds_data_selection           AS ds_data_selection,
  pd.ds_data_treatment           AS ds_data_treatment,
  pd.ds_collection_period        AS data_collection_period,
  pd.ds_data_completeness        AS ds_data_completeness,
  pd.completeness_text           AS completeness_text,
  pd.sources_text                AS sources_text,
  pd.modeling_constants          AS modeling_constants,
  pd.access_use_restrictions     AS access_restrictions,
  pd.creation_date               AS creation_date,
  pd.copyright_flag              AS copyright,
  p.valid_from                   AS valid_from,
  p.valid_until                  AS valid_until,
  CONCAT(ref.amount_str, ' ', ref.unit_name) AS functional_unit
FROM processes p
LEFT JOIN locations l            ON l.uuid = p.location_uuid
LEFT JOIN process_documentation pd ON pd.process_uuid = p.uuid
LEFT JOIN (
  SELECT o.process_uuid AS ref_puuid,
         o.amount       AS amount_str,
         u.name         AS unit_name
  FROM process_outputs o
  LEFT JOIN units u ON u.uuid = o.unit_uuid
  WHERE o.is_reference = 1
  ORDER BY o.internal_id
) ref ON ref.ref_puuid = p.uuid
WHERE p.uuid = ?
LIMIT 1";
$stmt = $mysqli->prepare($sqlProc);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$proceso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proceso) {
  http_response_code(404);
  exit('No se encontró el dataset con ese UUID.');
}

// Obtener modeller
$stmt = $mysqli->prepare("
  SELECT a.uuid, a.name 
  FROM process_actors pa 
  JOIN actors a ON a.uuid = pa.actor_uuid 
  WHERE pa.process_uuid = ? AND pa.role = 'modeller' 
  LIMIT 1
");
$stmt->bind_param('s', $uuid);
$stmt->execute();
$modellerData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener owner
$stmt = $mysqli->prepare("
  SELECT a.uuid, a.name 
  FROM process_actors pa 
  JOIN actors a ON a.uuid = pa.actor_uuid 
  WHERE pa.process_uuid = ? AND pa.role = 'owner' 
  LIMIT 1
");
$stmt->bind_param('s', $uuid);
$stmt->execute();
$ownerData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Annual supply or production volume
$annualVolume = null;
$stmt = $mysqli->prepare("
  SELECT value
  FROM parameters_process
  WHERE process_uuid = ? AND LOWER(name) IN ('annual_volume','annual volume','annualvolume')
  ORDER BY name
  LIMIT 1
");
$stmt->bind_param('s', $uuid);
$stmt->execute();
$annualVolume = $stmt->get_result()->fetch_column();
$stmt->close();

if ($annualVolume === null) {
  $stmt = $mysqli->prepare("
    SELECT value
    FROM parameters_global
    WHERE LOWER(name) IN ('annual_volume','annual volume','annualvolume')
    ORDER BY name
    LIMIT 1
  ");
  $stmt->execute();
  $annualVolume = $stmt->get_result()->fetch_column();
  $stmt->close();
}

// Inputs detallados
$sqlIn = "
SELECT
  pi.uuid,
  pi.internal_id,
  pi.flow_uuid,
  f.name         AS resource_type,
  pi.category    AS category,
  pi.amount      AS quantity,
  u.name         AS unit,
  fp.name        AS flow_property_name,
  pi.provider_process_uuid,
  pi.provider_name,
  pi.description AS commentary,
  loc.name       AS location_name
FROM process_inputs pi
LEFT JOIN flows            f   ON f.uuid  = pi.flow_uuid
LEFT JOIN units            u   ON u.uuid  = pi.unit_uuid
LEFT JOIN flow_properties  fp  ON fp.uuid = pi.flow_property_uuid
LEFT JOIN locations        loc ON loc.uuid = pi.location_uuid
WHERE pi.process_uuid = ?
ORDER BY pi.internal_id ASC";
$stmt = $mysqli->prepare($sqlIn);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$inputs = $stmt->get_result();
$stmt->close();

// Outputs detallados
$sqlOut = "
SELECT
  po.uuid,
  po.internal_id,
  po.flow_uuid,
  f.name         AS type_of_emission,
  po.category    AS category,
  po.amount      AS quantity,
  u.name         AS unit,
  fp.name        AS flow_property_name,
  po.is_reference,
  po.provider_process_uuid,
  po.provider_name,
  po.description AS commentary,
  loc.name       AS location_name
FROM process_outputs po
LEFT JOIN flows            f   ON f.uuid  = po.flow_uuid
LEFT JOIN units            u   ON u.uuid  = po.unit_uuid
LEFT JOIN flow_properties  fp  ON fp.uuid = po.flow_property_uuid
LEFT JOIN locations        loc ON loc.uuid = po.location_uuid
WHERE po.process_uuid = ?
ORDER BY po.internal_id ASC";
$stmt = $mysqli->prepare($sqlOut);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$outputs = $stmt->get_result();
$stmt->close();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Dataset</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="icon" type="image/png" href="icons/file-pen-line.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .accordion-chevron { transition: transform .3s cubic-bezier(.4,0,.2,1);}
    .accordion-open .accordion-chevron { transform: rotate(90deg);}
    .editable-field {
      border: 1px solid transparent;
      padding: 0.5rem;
      border-radius: 0.375rem;
      transition: all 0.2s;
    }
    .editable-field:hover {
      background-color: #f9fafb;
      border-color: #d1d5db;
    }
    .editable-field:focus {
      outline: none;
      border-color: #10b981;
      background-color: #fff;
    }
    .flow-row {
      cursor: pointer;
      transition: background-color 0.2s;
    }
    .flow-row:hover {
      background-color: #f0fdf4;
    }
    .amount-editable, .description-editable {
      border: 1px solid transparent;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      min-width: 80px;
      text-align: center;
    }
    .description-editable {
      min-width: 200px;
      min-height: 40px;
      resize: vertical;
    }
    .amount-editable:hover, .description-editable:hover {
      border-color: #d1d5db;
      background-color: #f9fafb;
    }
    .amount-editable:focus, .description-editable:focus {
      outline: none;
      border-color: #10b981;
      background-color: #fff;
    }
    /* Alineación perfecta de amount y unidad */
    .amount-container {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
    }
    .unit-display {
      font-size: 0.875rem;
      color: #6b7280;
      white-space: nowrap;
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background-color: white;
      padding: 2rem;
      border-radius: 1rem;
      max-width: 900px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
    }
    .dataset-item, .actor-item {
      border: 2px solid transparent;
      transition: all 0.2s;
    }
    .dataset-item.selected, .actor-item.selected {
      border-color: #10b981;
      background-color: #d1fae5;
    }
    .actor-badge {
      display: inline-block;
      padding: 0.125rem 0.5rem;
      font-size: 0.75rem;
      border-radius: 9999px;
      font-weight: 500;
    }
    .actor-badge.actor {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .actor-badge.user {
      background-color: #fef3c7;
      color: #92400e;
    }
    .empty-row {
      background-color: #f0fdf4 !important;
      font-style: italic;
    }
    .empty-row:hover {
      background-color: #dcfce7 !important;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 overflow-y-scroll">

  <!-- Botones de acción -->
    <div class="fixed top-4 right-4 z-50 flex gap-2">
      <button>
          <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg shadow-lg font-semibold transition-all inline-block">
           <i data-lucide="house" class="inline"></i>
          </a>
      </button>
      <button onclick="saveChanges()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-lg font-semibold transition-all">
        <i data-lucide="save" class="inline w-4 h-4 mr-2"></i> 
        Guardar Borrador
      </button>
      <button onclick="guardarYPublicar()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg shadow-lg font-semibold transition-all">
        <i data-lucide="check-circle" class="inline w-4 h-4 mr-2"></i> 
        Guardar y Publicar
      </button>
      <button>
        <a href="search_dataset.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg shadow-lg font-semibold transition-all inline-block">
          <i data-lucide="x" class="inline w-4 h-4 mr-2"></i> 
          Cancelar
        </a>
      </button>
    </div>

  <!-- Título -->
  <div class="rounded-xl bg-white border border-gray-200 shadow-md mt-8 p-8 w-fit max-w-6xl mx-auto flex flex-col items-center">
    <h1 class="font-extrabold text-3xl md:text-4xl text-center leading-relaxed text-green-700">
      Editar DataSet:
    </h1>
    <input type="text" id="process_name" value="<?= htmlspecialchars($proceso['process_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
           class="editable-field text-2xl font-semibold text-center mt-2 w-full max-w-2xl">
  </div>

  <!-- Process Information -->
  <div class="mt-8 w-full max-w-7xl h-full mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablaPrincipal', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group accordion-open">
      <span>Process Information</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400"></i>
    </button>
    <div id="tablaPrincipal" class="border-t border-gray-100 bg-white">
      <table class="min-w-full text-sm text-gray-700">
        <tbody>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Key Data Set Information</strong></td></tr>
          <tr class="border-b border-gray-100">
             <td class="bg-gray-50 p-4 font-medium w-1/3 border-r border-gray-100">Location</td>
              <td class="p-4">
                 <select id="locationuuid" class="editable-field w-full p-2 border border-gray-200 rounded-lg">
                    <option value="">Sin ubicación</option>
                    <?php
                    // Obtener todas las ubicaciones disponibles
                    $stmt_locations = $mysqli->prepare("SELECT uuid, name FROM locations ORDER BY name");
                    $stmt_locations->execute();
                    $locations = $stmt_locations->get_result();
                        
                    while ($loc = $locations->fetch_assoc()) {
                        $selected = ($proceso['locationuuid'] ?? '') === $loc['uuid'] ? 'selected' : '';
                        echo "<option value='{$loc['uuid']}' {$selected}>" . htmlspecialchars($loc['name']) . "</option>";
                    }
                    $stmt_locations->close();
                    ?>
                  </select>
              </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Geographical representativeness description</td>
            <td class="p-4">
              <textarea id="geo_description" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['geo_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Reference year</td>
            <td class="p-4">
              <input type="text" id="valid_from" value="<?= htmlspecialchars($proceso['valid_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Classification</td>
            <td class="p-4">
              <input type="text" id="category" value="<?= htmlspecialchars($proceso['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">General comment on data set</td>
            <td class="p-4">
              <textarea id="general_comment" class="editable-field w-full" rows="4"><?= htmlspecialchars($proceso['general_comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Quantitative Reference</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Reference flow(s)</td>
            <td class="p-4"><?= htmlspecialchars($proceso['functional_unit'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Time representativeness</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data Set Valid Until</td>
            <td class="p-4">
              <input type="text" id="valid_until" value="<?= htmlspecialchars($proceso['valid_until'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Time representativeness description</td>
            <td class="p-4">
              <textarea id="time_desc" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['time_desc'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Technological representativeness</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Technology description including background system</td>
            <td class="p-4">
              <textarea id="technology_description" class="editable-field w-full" rows="4"><?= htmlspecialchars($proceso['technology_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Included Data Sets</td>
            <td class="p-4">
              <input type="text" id="project" value="<?= htmlspecialchars($proceso['project'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Flow diagram(s) or picture(s)</td>
            <td class="p-4"><?= htmlspecialchars($proceso['flow_diagram'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modelling and Validation -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Modelling and Validation</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400"></i>
    </button>
    <div id="tablasecundaria" class="hidden border-t border-gray-100 bg-white">
      <table class="min-w-full text-sm text-gray-700">
        <tbody>
          <tr><td class="bg-white p-4 font-semibold text-green-600 w-1/3"><strong>LCI method and allocation</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Type Of Data Set</td>
            <td class="p-4">
              <input type="text" id="process_type" value="<?= htmlspecialchars($proceso['process_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">LCI Method Principle</td>
            <td class="p-4">
              <input type="text" id="lci_method" value="<?= htmlspecialchars($proceso['lci_method'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data sources, treatment and representativeness</td>
            <td class="p-4">
              <textarea id="sources_text" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['sources_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data cut-off and completeness principles</td>
            <td class="p-4">
              <textarea id="ds_data_completeness" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['ds_data_completeness'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data selection and combination principles</td>
            <td class="p-4">
              <textarea id="ds_data_selection" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['ds_data_selection'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data treatment and extrapolations principles</td>
            <td class="p-4">
              <textarea id="ds_data_treatment" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['ds_data_treatment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Percentage supply or production covered</td>
            <td class="p-4">
              <input type="text" id="modeling_constants" value="<?= htmlspecialchars($proceso['modeling_constants'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Annual supply or production volume</td>
            <td class="p-4"><?= htmlspecialchars(($annualVolume !== null && $annualVolume !== '') ? $annualVolume : '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data collection period</td>
            <td class="p-4">
              <input type="text" id="data_collection_period" value="<?= htmlspecialchars($proceso['data_collection_period'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Completeness</strong></td></tr>
          <tr>
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Completeness of product model</td>
            <td class="p-4">
              <textarea id="completeness_text" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['completeness_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Administrative Information -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria2', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Administrative Information</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400"></i>
    </button>
    <div id="tablasecundaria2" class="hidden border-t border-gray-100 bg-white">
      <table class="min-w-full text-sm text-gray-700">
        <tbody>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Data generator</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data set generator / modeller</td>
            <td class="p-4">
              <button onclick="openActorModal('modeller')" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded-lg transition-colors">
                <i data-lucide="user" class="inline w-4 h-4 mr-1"></i>
                <span id="modeller_display"><?= htmlspecialchars($modellerData['name'] ?? 'Seleccionar modeller', ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <input type="hidden" id="modeller_uuid" value="<?= htmlspecialchars($modellerData['uuid'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Data Entry By</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium">Time Stamp (Last saved)</td>
            <td class="p-4"><?= htmlspecialchars($proceso['last_change'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium">Creation Date</td>
            <td class="p-4 text-gray-600"><?= htmlspecialchars($proceso['created_at'] ?? '...', ENT_QUOTES, 'UTF-8') ?> (No editable)</td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Publication and ownership</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r w-1/3 border-gray-100">UUID</td>
            <td class="p-4"><?= htmlspecialchars($proceso['uuid'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data set version</td>
            <td class="p-4">
              <input type="text" id="version" value="<?= htmlspecialchars($proceso['version'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="editable-field w-full">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Owner of data set</td>
            <td class="p-4">
              <button onclick="openActorModal('owner')" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded-lg transition-colors">
                <i data-lucide="briefcase" class="inline w-4 h-4 mr-1"></i>
                <span id="owner_display"><?= htmlspecialchars($ownerData['name'] ?? 'Seleccionar owner', ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <input type="hidden" id="owner_uuid" value="<?= htmlspecialchars($ownerData['uuid'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Copyright</td>
            <td class="p-4">
              <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" id="copyright" value="1" 
                       <?= (($proceso['copyright'] ?? '0') === '1') ? 'checked' : '' ?>
                       class="w-5 h-5 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500">
                <span class="ml-2 text-sm font-medium text-gray-700">Sí</span>
              </label>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Access and use restrictions</td>
            <td class="p-4">
              <textarea id="access_restrictions" class="editable-field w-full" rows="3"><?= htmlspecialchars($proceso['access_restrictions'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

<!-- Inputs Table -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria3', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Inputs</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400"></i>
    </button>
    <div id="tablasecundaria3" class="hidden border-t border-gray-100 bg-white">
        <div class="p-4 bg-gray-50 border-b border-gray-200">
         <button onclick="openAddExchangeModal('input')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow font-semibold transition-all">
            <i data-lucide="plus-circle" class="inline w-4 h-4 mr-2"></i> 
            Add Exchange
         </button>
        </div>
      <table class="table-auto w-full mb-8">
        <thead>
          <tr>
            <th colspan="5" class="bg-white p-4 font-semibold text-green-600 text-left border-b border-gray-100">
              <strong>Inputs (Doble clic en Flow para cambiar dataset)</strong>
            </th>
          </tr>
          <tr class="bg-gray-100">
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Flow</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Category</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Amount</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Description</th>
            <th class="p-2 text-center border-b border-gray-100 font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($inputs) while($input = $inputs->fetch_assoc()): ?>
          <tr class="flow-row" 
              data-flow-uuid="<?= htmlspecialchars($input['uuid'], ENT_QUOTES, 'UTF-8') ?>"
              data-flow-type="input">
            <td class="p-2 text-center border-b border-r font-semibold border-gray-100"
                ondblclick="openDatasetModal('input', '<?= htmlspecialchars($input['uuid'], ENT_QUOTES, 'UTF-8') ?>')">
              <span class="flow-name cursor-pointer hover:text-green-600"><?= htmlspecialchars($input['resource_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td class="p-2 text-center border-b border-r border-gray-100 category-cell"><?= htmlspecialchars($input['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="p-2 border-b border-r border-gray-100">
              <div class="amount-container">
                <input type="number" step="any" 
                       value="<?= htmlspecialchars($input['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="amount-editable"
                       data-flow-uuid="<?= htmlspecialchars($input['uuid'], ENT_QUOTES, 'UTF-8') ?>"
                       data-flow-type="input"
                       onchange="updateAmount(this)"
                       onclick="event.stopPropagation()">
                <span class="unit-display"><?= htmlspecialchars($input['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </td>
            <td class="p-2 text-center border-b border-r border-gray-100">
              <textarea
                     class="description-editable w-full"
                     rows="2"
                     data-flow-uuid="<?= htmlspecialchars($input['uuid'], ENT_QUOTES, 'UTF-8') ?>"
                     data-flow-type="input"
                     onchange="updateDescription(this)"
                     onclick="event.stopPropagation();"><?= htmlspecialchars($input['commentary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
            <td class="p-2 text-center border-b border-gray-100">
              <button
                type="button"
                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm"
                onclick="deleteFlow('input', '<?= htmlspecialchars($input['uuid'], ENT_QUOTES, 'UTF-8') ?>', event)">
                Eliminar
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>


 <!-- Outputs Table -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria4', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Outputs</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400"></i>
    </button>
    <div id="tablasecundaria4" class="hidden border-t border-gray-100 bg-white">
        <div class="p-4 bg-gray-50 border-b border-gray-200">
         <button onclick="openAddExchangeModal('output')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow font-semibold transition-all">
          <i data-lucide="plus-circle" class="inline w-4 h-4 mr-2"></i> 
          Add Exchange
         </button>
        </div>
      <table class="table-auto w-full">
        <thead>
          <tr>
            <th colspan="5" class="bg-white p-4 font-semibold text-green-600 text-left border-b border-gray-100">
              <strong>Outputs (Doble clic en Flow para cambiar dataset)</strong>
            </th>
          </tr>
          <tr class="bg-gray-100">
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Flow</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Category</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Amount</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Description</th>
            <th class="p-2 text-center border-b border-gray-100 font-bold">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($outputs) while($output = $outputs->fetch_assoc()): ?>
          <tr class="flow-row"
              data-flow-uuid="<?= htmlspecialchars($output['uuid'], ENT_QUOTES, 'UTF-8') ?>"
              data-flow-type="output">
            <td class="p-2 text-center border-b border-r font-semibold border-gray-100"
                ondblclick="openDatasetModal('output', '<?= htmlspecialchars($output['uuid'], ENT_QUOTES, 'UTF-8') ?>')">
              <span class="flow-name cursor-pointer hover:text-green-600"><?= htmlspecialchars($output['type_of_emission'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td class="p-2 text-center border-b border-r border-gray-100 category-cell"><?= htmlspecialchars($output['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="p-2 border-b border-r border-gray-100">
              <div class="amount-container">
                <input type="number" step="any"
                       value="<?= htmlspecialchars($output['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="amount-editable"
                       data-flow-uuid="<?= htmlspecialchars($output['uuid'], ENT_QUOTES, 'UTF-8') ?>"
                       data-flow-type="output"
                       onchange="updateAmount(this)"
                       onclick="event.stopPropagation()">
                <span class="unit-display"><?= htmlspecialchars($output['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </td>
            <td class="p-2 text-center border-b border-r border-gray-100">
              <textarea
                     class="description-editable w-full"
                     rows="2"
                     data-flow-uuid="<?= htmlspecialchars($output['uuid'], ENT_QUOTES, 'UTF-8') ?>"
                     data-flow-type="output"
                     onchange="updateDescription(this)"
                     onclick="event.stopPropagation();"><?= htmlspecialchars($output['commentary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </td>
            <td class="p-2 text-center border-b border-gray-100">
              <button
                type="button"
                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm"
                onclick="deleteFlow('output', '<?= htmlspecialchars($output['uuid'], ENT_QUOTES, 'UTF-8') ?>', event)">
                Eliminar
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>


  <!-- Modal para seleccionar dataset -->
  <div id="datasetModal" class="modal">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-green-700">Seleccionar Dataset</h2>
        <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>

      <div class="mb-4">
        <input type="text" id="searchDataset" placeholder="Buscar por nombre o categoría..." 
               class="w-full p-3 border border-gray-300 rounded-lg"
               oninput="searchDatasets()">
      </div>

      <div id="datasetList" class="space-y-2 max-h-96 overflow-y-auto">
        <!-- Lista de datasets se cargará aquí -->
      </div>

      <div class="mt-4 flex gap-2">
        <input type="number" id="modalAmount" placeholder="Cantidad" step="any" 
               class="flex-1 p-2 border border-gray-300 rounded-lg">
        <button onclick="confirmSelection()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold">
          Confirmar
        </button>
        <button onclick="closeModal()" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold">
          Cancelar
        </button>
      </div>
    </div>
  </div>

  <!-- Modal para seleccionar actor/usuario (modeller/owner) -->
  <div id="actorModal" class="modal">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-green-700">Seleccionar Actor o Usuario</h2>
        <button onclick="closeActorModal()" class="text-gray-500 hover:text-gray-700">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>

      <div class="mb-4">
        <input type="text" id="searchActor" placeholder="Buscar por nombre o email..." 
               class="w-full p-3 border border-gray-300 rounded-lg"
               oninput="searchActors()">
      </div>

      <div id="actorList" class="space-y-2 max-h-96 overflow-y-auto">
        <!-- Lista de actores y usuarios se cargará aquí -->
      </div>

      <div class="mt-4 flex gap-2 justify-end">
        <button onclick="confirmActorSelection()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold">
          Confirmar
        </button>
        <button onclick="closeActorModal()" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold">
          Cancelar
        </button>
      </div>
    </div>
  </div>
    <!-- Modal para añadir nuevo intercambio -->
    <div id="addExchangeModal" class="modal">
      <div class="modal-content">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Añadir Nuevo Intercambio</h2>
          <button onclick="closeAddExchangeModal()" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>
    
        <!-- Buscador de procesos -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Buscar proceso para enlazar:</label>
          <div class="flex gap-2">
            <input type="text" id="addExchangeSearch" 
                   placeholder="Escribe el nombre del proceso o categoría..."
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <button onclick="searchProcessesForExchange()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
              <i data-lucide="search" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
    
        <!-- Campo de cantidad -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad:</label>
          <input type="number" step="any" id="newExchangeAmount" value="1" 
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>
    
        <!-- Resultados de búsqueda -->
        <div id="addExchangeResults" class="space-y-2 max-h-96 overflow-y-auto mb-6">
          <!-- Los resultados se cargarán aquí dinámicamente -->
        </div>
    
        <!-- Botones de acción -->
        <div class="flex justify-end gap-2">
          <button onclick="closeAddExchangeModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg">
            Cancelar
          </button>
          <button onclick="confirmAddExchange()" id="confirmAddExchangeBtn" disabled 
                  class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
            Añadir Intercambio
          </button>
        </div>
      </div>
    </div>

<script>
let currentFlowUuid = null;
let currentFlowType = null;
let selectedDatasetUuid = null;
let selectedActorUuid = null;
let selectedActorName = null;
let currentActorRole = null;
let isCreatingNew = false;
let currentFlowAmount = null; // NUEVO

function toggleAccordion(id, btn) {
  const el = document.getElementById(id);
  el.classList.toggle('hidden');
  btn.classList.toggle('accordion-open');
}

function saveChanges() {
  const formData = new FormData();
  formData.append('action', 'save_process');
  formData.append('uuid', '<?= $uuid ?>');
  formData.append('isdraft', '1');

  const fields = [
    'process_name', 'category', 'general_comment', 'technology_description',
    'geo_description', 'process_type', 'time_desc', 'version', 
    'valid_from', 'valid_until', 'project', 'lci_method',
    'ds_data_selection', 'ds_data_treatment', 'data_collection_period',
    'ds_data_completeness', 'completeness_text', 'sources_text',
    'modeling_constants', 'access_restrictions'
  ];

  fields.forEach(field => {
    const element = document.getElementById(field);
    if (element) {
      formData.append(field, element.value);
    }
  });

  const copyrightCheckbox = document.getElementById('copyright');
  formData.append('copyright', copyrightCheckbox.checked ? '1' : '0');

  formData.append('modeller_uuid', document.getElementById('modeller_uuid').value);
  formData.append('owner_uuid', document.getElementById('owner_uuid').value);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Cambios guardados correctamente');
      window.location.reload();
    } else {
      alert('Error al guardar: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error al guardar los cambios');
  });
}

function updateAmount(input) {
  const formData = new FormData();
  formData.append('action', 'update_amount');
  formData.append('flow_uuid', input.dataset.flowUuid);
  formData.append('flow_type', input.dataset.flowType);
  formData.append('amount', input.value);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (!data.success) {
      alert('Error al actualizar cantidad: ' + data.message);
    }
  })
  .catch(error => console.error('Error:', error));
}

function updateDescription(textarea) {
  const formData = new FormData();
  formData.append('action', 'update_description');
  formData.append('flow_uuid', textarea.dataset.flowUuid);
  formData.append('flow_type', textarea.dataset.flowType);
  formData.append('description', textarea.value);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (!data.success) {
      alert('Error al actualizar descripción: ' + data.message);
    }
  })
  .catch(error => console.error('Error:', error));
}

// NUEVO: leer cantidad actual al abrir modal
function openDatasetModal(flowType, flowUuid) {
  currentFlowType = flowType;
  currentFlowUuid = flowUuid;
  selectedDatasetUuid = null;
  isCreatingNew = false;

  // NUEVO: leer la cantidad actual de la fila
  const row = document.querySelector(`tr[data-flow-uuid="${flowUuid}"]`);
  if (row) {
    const amountInput = row.querySelector('.amount-editable');
    if (amountInput) {
      currentFlowAmount = amountInput.value || '';
    } else {
      currentFlowAmount = '';
    }
  } else {
    currentFlowAmount = '';
  }

  const modal = document.getElementById('datasetModal');
  modal.classList.add('active');

  document.getElementById('searchDataset').value = '';
  searchDatasets();

  // NUEVO: pre-llenar cantidad en el modal
  const amountField = document.getElementById('modalAmount');
  if (amountField) {
    amountField.value = currentFlowAmount || '';
  }
}

function closeModal() {
  document.getElementById('datasetModal').classList.remove('active');
  currentFlowUuid = null;
  currentFlowType = null;
  selectedDatasetUuid = null;
  currentFlowAmount = null;
}

function searchDatasets() {
  const search = document.getElementById('searchDataset').value;
  const formData = new FormData();
  formData.append('action', 'search_datasets');
  formData.append('search', search);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      displayDatasets(data.datasets);
    }
  })
  .catch(error => console.error('Error:', error));
}

function displayDatasets(datasets) {
  const listDiv = document.getElementById('datasetList');
  listDiv.innerHTML = '';

  if (datasets.length === 0) {
    listDiv.innerHTML = '<p class="text-gray-500 text-center py-4">No se encontraron datasets</p>';
    return;
  }

  datasets.forEach(dataset => {
    const div = document.createElement('div');
    div.className = 'dataset-item p-3 border border-gray-200 rounded-lg hover:bg-green-50 cursor-pointer transition-colors';
    div.onclick = () => selectDataset(dataset.uuid, div);
    div.innerHTML = `
      <div class="font-semibold text-gray-800">${dataset.name || 'Sin nombre'}</div>
      <div class="text-sm text-gray-600">${dataset.category || 'Sin categoría'}</div>
      <div class="text-xs text-gray-500 mt-1">
        <span class="inline-block mr-3"><i data-lucide="map-pin" class="inline w-3 h-3"></i> ${dataset.location || 'Sin ubicación'}</span>
        <span class="inline-block"><i data-lucide="ruler" class="inline w-3 h-3"></i> ${dataset.unit || 'Sin unidad'}</span>
        ${dataset.flow_property ? '<span class="inline-block ml-3 text-blue-600">(' + dataset.flow_property + ')</span>' : ''}
      </div>
    `;
    listDiv.appendChild(div);
  });

  lucide.createIcons();
}

function selectDataset(uuid, element) {
  document.querySelectorAll('.dataset-item').forEach(div => {
    div.classList.remove('selected');
  });
  element.classList.add('selected');
  selectedDatasetUuid = uuid;
}

function openDatasetModalForNew(flowType) {
  currentFlowType = flowType;
  currentFlowUuid = 'new';
  selectedDatasetUuid = null;
  isCreatingNew = true;
  currentFlowAmount = '1'; // valor por defecto para nuevo

  document.getElementById('datasetModal').classList.add('active');
  document.getElementById('searchDataset').value = '';
  searchDatasets();

  const amountField = document.getElementById('modalAmount');
  if (amountField) {
    amountField.value = '1';
  }
}

function createNewFlow(flowType, newProcessUuid, amount) {
  const formData = new FormData();
  formData.append('action', 'create_new_flow');
  formData.append('flow_type', flowType);
  formData.append('process_uuid', '<?= $uuid ?>');
  formData.append('new_process_uuid', newProcessUuid);
  formData.append('amount', amount);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      closeModal();
      alert('Flujo creado correctamente');
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error al crear el flujo');
  });
}

// NUEVO: si el usuario deja vacío, usar cantidad previa
function confirmSelection() {
  if (!selectedDatasetUuid) {
    alert('Por favor selecciona un dataset');
    return;
  }

  const amountField = document.getElementById('modalAmount');
  let amount = amountField ? amountField.value : '';

  // Si está vacío y no es nuevo, usar la cantidad previa
  if ((!amount || parseFloat(amount) === 0) && !isCreatingNew) {
    amount = currentFlowAmount || 0;
  }

  if (!amount || parseFloat(amount) === 0) {
    alert('Por favor ingresa una cantidad válida');
    return;
  }

  // Si es un nuevo flujo
  if (isCreatingNew || currentFlowUuid === 'new') {
    createNewFlow(currentFlowType, selectedDatasetUuid, amount);
    isCreatingNew = false;
    return;
  }

  // Si es actualización de flujo existente
  const formData = new FormData();
  formData.append('action', 'update_flow');
  formData.append('flow_type', currentFlowType);
  formData.append('flow_uuid', currentFlowUuid);
  formData.append('new_process_uuid', selectedDatasetUuid);
  formData.append('amount', amount);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const row = document.querySelector(`[data-flow-uuid="${currentFlowUuid}"]`);
      if (row) {
        row.querySelector('.flow-name').textContent = data.flow_name;
        row.querySelector('.amount-editable').value = amount;
        row.querySelector('.unit-display').textContent = data.unit;
        row.querySelector('.category-cell').textContent = data.category || '';
      }
      closeModal();
      alert('Flujo actualizado correctamente');
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error al actualizar el flujo');
  });
}

// NUEVO: función para eliminar flujo
function deleteFlow(flowType, flowUuid, event) {
  if (event) {
    event.stopPropagation();
  }

  if (!confirm('¿Seguro que deseas eliminar este flujo?')) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'delete_flow');
  formData.append('flow_type', flowType);
  formData.append('flow_uuid', flowUuid);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const row = document.querySelector(`tr[data-flow-uuid="${flowUuid}"]`);
      if (row) {
        row.remove();
      }
      alert('Flujo eliminado correctamente');
    } else {
      alert('Error al eliminar el flujo: ' + data.message);
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('Error al eliminar el flujo');
  });
}

function openActorModal(role) {
  currentActorRole = role;
  selectedActorUuid = null;
  selectedActorName = null;
  
  document.getElementById('actorModal').classList.add('active');
  document.getElementById('searchActor').value = '';
  
  searchActors();
}

function closeActorModal() {
  document.getElementById('actorModal').classList.remove('active');
  currentActorRole = null;
  selectedActorUuid = null;
  selectedActorName = null;
}

function searchActors() {
  const search = document.getElementById('searchActor').value;
  const formData = new FormData();
  formData.append('action', 'search_actors_and_users');
  formData.append('search', search);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      displayActors(data.actors);
    }
  })
  .catch(error => console.error('Error:', error));
}

function displayActors(actors) {
  const listDiv = document.getElementById('actorList');
  listDiv.innerHTML = '';

  if (actors.length === 0) {
    listDiv.innerHTML = '<p class="text-gray-500 text-center py-4">No se encontraron actores ni usuarios</p>';
    return;
  }

  actors.forEach(actor => {
    const div = document.createElement('div');
    div.className = 'actor-item p-3 border border-gray-200 rounded-lg hover:bg-blue-50 cursor-pointer transition-colors';
    div.onclick = () => selectActor(actor.uuid, actor.name, div);

    const badgeClass = actor.source_type === 'actor' ? 'actor' : 'user';
    const badgeText = actor.source_type === 'actor' ? 'Actor' : 'Usuario';

    div.innerHTML = `
      <div class="flex items-center justify-between">
        <div class="font-semibold text-gray-800">${actor.name || 'Sin nombre'}</div>
        <span class="actor-badge ${badgeClass}">${badgeText}</span>
      </div>
      <div class="text-sm text-gray-600">${actor.email || 'Sin email'}</div>
      ${actor.category ? '<div class="text-xs text-gray-500 mt-1">' + actor.category + '</div>' : ''}
    `;
    listDiv.appendChild(div);
  });
}

function selectActor(uuid, name, element) {
  document.querySelectorAll('.actor-item').forEach(div => {
    div.classList.remove('selected');
  });
  element.classList.add('selected');
  selectedActorUuid = uuid;
  selectedActorName = name;
}

function confirmActorSelection() {
  if (!selectedActorUuid) {
    alert('Por favor selecciona un actor o usuario');
    return;
  }

  document.getElementById(currentActorRole + '_uuid').value = selectedActorUuid;
  document.getElementById(currentActorRole + '_display').textContent = selectedActorName;

  closeActorModal();
}

window.onload = () => { 
  lucide.createIcons();
}

function guardarYPublicar() {
  if (!confirm('¿Estás seguro de guardar y publicar este dataset? Una vez publicado, será visible para todos los usuarios.')) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'save_process');
  formData.append('uuid', '<?= $uuid ?>');

  const fields = [
    'process_name', 'category', 'general_comment', 'technology_description',
    'geo_description', 'process_type', 'time_desc', 'version', 'valid_from', 'valid_until',
    'project', 'lci_method', 'ds_data_selection', 'ds_data_treatment', 'data_collection_period',
    'ds_data_completeness', 'completeness_text', 'sources_text', 'modeling_constants', 'access_restrictions'
  ];

  fields.forEach(field => {
    const element = document.getElementById(field);
    if (element) {
      formData.append(field, element.value);
    }
  });

  const copyrightCheckbox = document.getElementById('copyright');
  formData.append('copyright', copyrightCheckbox ? (copyrightCheckbox.checked ? 1 : 0) : 0);
  
  const modellerUuidEl = document.getElementById('modeller_uuid');
  const ownerUuidEl = document.getElementById('owner_uuid');
  
  formData.append('modeller_uuid', modellerUuidEl ? modellerUuidEl.value : '');
  formData.append('owner_uuid', ownerUuidEl ? ownerUuidEl.value : '');

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(text => {
    console.log('RESPUESTA DEL SERVIDOR:', text);
    
    try {
      const data = JSON.parse(text);
      
      if (data.success) {
        return fetch('php_actions/publicar_borrador.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ uuid: '<?= $uuid ?>' })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('✅ Dataset guardado exitosamente');
            window.location.href = 'conjuntos.php';
          } else {
            throw new Error(data.message);
          }
        });
      } else {
        throw new Error(data.message);
      }
    } catch (e) {
      console.error('ERROR AL PARSEAR:', e);
      console.error('CONTENIDO HTML RECIBIDO:', text.substring(0, 1000));
      alert('Error: El servidor devolvió HTML en lugar de JSON. Revisa la consola para más detalles.');
    }
  })
  .catch(error => {
    console.error('ERROR:', error);
    alert('Error: ' + error.message);
  });
}

// VARIABLES PARA EL INTERCAMBIO DE INPUTS Y OUTPUTS
let currentExchangeType = null;
let selectedProcessForExchange = null;

function openAddExchangeModal(flowType) {
  currentExchangeType = flowType;
  selectedProcessForExchange = null;
  document.getElementById('addExchangeModal').classList.add('active');
  document.getElementById('addExchangeSearch').value = '';
  document.getElementById('addExchangeResults').innerHTML = '';
  document.getElementById('newExchangeAmount').value = '1';
  document.getElementById('confirmAddExchangeBtn').disabled = true;
  lucide.createIcons();
}

function closeAddExchangeModal() {
  document.getElementById('addExchangeModal').classList.remove('active');
  currentExchangeType = null;
  selectedProcessForExchange = null;
}

function searchProcessesForExchange() {
  const search = document.getElementById('addExchangeSearch').value;
  
  if (search.trim().length < 2) {
    alert('Por favor escribe al menos 2 caracteres para buscar');
    return;
  }

  fetch('<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?uuid=<?= htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=search_datasets&search=' + encodeURIComponent(search)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      displayExchangeResults(data.datasets);
    } else {
      alert('Error al buscar: ' + data.message);
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('Error al buscar datasets');
  });
}

function displayExchangeResults(datasets) {
  const container = document.getElementById('addExchangeResults');
  
  if (datasets.length === 0) {
    container.innerHTML = '<p class="text-gray-500 text-center py-4">No se encontraron procesos</p>';
    return;
  }

  container.innerHTML = datasets.map(ds => `
    <div class="dataset-item p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50" 
         onclick="selectProcessForExchange('${ds.uuid}', this)">
      <div class="font-semibold text-gray-800">${ds.name}</div>
      <div class="text-sm text-gray-600">
        <span class="font-medium">Categoría:</span> ${ds.category || 'Sin categoría'}
      </div>
      ${ds.location ? `<div class="text-sm text-gray-600"><span class="font-medium">Ubicación:</span> ${ds.location}</div>` : ''}
      ${ds.unit ? `<div class="text-sm text-gray-600"><span class="font-medium">Unidad:</span> ${ds.unit}</div>` : ''}
      ${ds.flow_property ? `<div class="text-sm text-gray-600"><span class="font-medium">Propiedad de flujo:</span> ${ds.flow_property}</div>` : ''}
    </div>
  `).join('');

  lucide.createIcons();
}

function selectProcessForExchange(processUuid, element) {
  document.querySelectorAll('#addExchangeResults .dataset-item').forEach(item => {
    item.classList.remove('selected');
  });
  
  element.classList.add('selected');
  selectedProcessForExchange = processUuid;
  document.getElementById('confirmAddExchangeBtn').disabled = false;
}

function confirmAddExchange() {
  if (!selectedProcessForExchange) {
    alert('Por favor selecciona un proceso');
    return;
  }

  const amount = document.getElementById('newExchangeAmount').value;
  
  if (!amount || parseFloat(amount) <= 0) {
    alert('Por favor ingresa una cantidad válida');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'create_new_flow');
  formData.append('flow_type', currentExchangeType);
  formData.append('process_uuid', '<?= htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8') ?>');
  formData.append('new_process_uuid', selectedProcessForExchange);
  formData.append('amount', amount);

  fetch('<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?uuid=<?= htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8') ?>', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('Intercambio añadido correctamente');
      closeAddExchangeModal();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('Error al crear el intercambio');
  });
}

document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('addExchangeSearch');
  if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        searchProcessesForExchange();
      }
    });
  }
});
</script>
</body>
</html>
