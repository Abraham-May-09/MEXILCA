<?php
// ✅ MOSTRAR ERRORES (TEMPORAL PARA DEBUG)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); 
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // ✅ VERIFICAR QUE HAY SESIÓN ACTIVA
    if (!isset($_SESSION['user_uuid'])) {
      throw new Exception("Error: No has iniciado sesión");
    }

    // ✅ DEBUG: Ver qué llega en POST
    error_log("=== POST DATA RECIBIDO ===");
    error_log(print_r($_POST, true));

    $conn->begin_transaction();

    // ✅ CAMPOS OBLIGATORIOS - USAR NOMBRES EXACTOS DEL FORMULARIO
    $process_name = $_POST['processname'] ?? null;
    $category = $_POST['category'] ?? null;
    $type_of_process = $_POST['typeofprocess'] ?? null;
    $functional_unit = $_POST['functionalunit'] ?? null;
    $location = $_POST['location'] ?? null;

    error_log("Valores recibidos:");
    error_log("processname: " . ($process_name ?? 'NULL'));
    error_log("category: " . ($category ?? 'NULL'));
    error_log("typeofprocess: " . ($type_of_process ?? 'NULL'));
    error_log("functionalunit: " . ($functional_unit ?? 'NULL'));
    error_log("location: " . ($location ?? 'NULL'));

    if (!$process_name || !$category || !$type_of_process || !$functional_unit || !$location) {
      throw new Exception("Campos obligatorios faltantes: Nombre, Categoría, Tipo de Proceso, Unidad Funcional y Ubicación");
    }

    // CAMPOS OPCIONALES - USAR NOMBRES EXACTOS DEL FORMULARIO
    $uuid = $_POST['uuid'] ?? bin2hex(random_bytes(16));
    $sector = $_POST['sector'] ?? '';
    $process_description = $_POST['processdescription'] ?? '';
    $general_comment = $_POST['generalcomment'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $life_cycle_stage = $_POST['lifecyclestage'] ?? '';
    $location_description = $_POST['locationdescription'] ?? '';
    $technology_description = $_POST['technologydescription'] ?? '';
    $valid_until = !empty($_POST['validuntil']) ? $_POST['validuntil'] : NULL;
    $dq_data_quality = $_POST['dataQualityIndicators'] ?? '';

    // ✅ DETERMINAR ESTADO SEGÚN ROL DEL USUARIO
    $es_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
    $created_by = $_SESSION['user_uuid'];
    
    // ⚠️ DETECTAR SI ES BORRADOR
    $es_borrador = isset($_POST['is_draft']) && $_POST['is_draft'] == '1';
    $is_draft = $es_borrador ? 1 : 0;
    
    if ($es_borrador) {
        $approval_status = 'draft';
    } else {
        $approval_status = $es_admin ? 'approved' : 'pending';
    }

    error_log("Estado: $approval_status, is_draft: $is_draft");

    // 1. CREAR O BUSCAR LA UBICACIÓN
    $location_uuid = null;
    if (!empty($location)) {
      $stmt = $conn->prepare("SELECT uuid FROM locations WHERE name = ? LIMIT 1");
      if (!$stmt) {
        throw new Exception("Error preparando SELECT location: " . $conn->error);
      }
      $stmt->bind_param("s", $location);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows > 0) {
        $location_uuid = $result->fetch_assoc()['uuid'];
        error_log("✅ Location encontrada: $location_uuid");
      } else {
        $location_uuid = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO locations (uuid, name, description) VALUES (?, ?, ?)");
        if (!$stmt) {
          throw new Exception("Error preparando INSERT location: " . $conn->error);
        }
        $stmt->bind_param("sss", $location_uuid, $location, $location_description);
        if (!$stmt->execute()) {
          throw new Exception("Error al crear ubicación: " . $stmt->error);
        }
        error_log("✅ Location creada: $location_uuid");
      }
    }

    // 2. ✅ INSERTAR EL PROCESO CON TODAS LAS COLUMNAS
    $sql = "
      INSERT INTO processes (
        uuid, name, sector_principal, process_type, life_cycle_stage, category, description,
        tags_text, location_uuid, geo_desc, tech_desc,
        approval_status, is_draft, created_by_uuid, valid_until, dq_data_quality
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    error_log("SQL preparado: $sql");
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparando INSERT proceso: " . $conn->error);
    }
    
    // ✅ 16 parámetros: 14 strings + 1 integer + 1 string
    $stmt->bind_param("ssssssssssssisss",
      $uuid,                    // 1 - s
      $process_name,            // 2 - s
      $sector,                  // 3 - s
      $type_of_process,         // 4 - s
      $life_cycle_stage,        // 5 - s
      $category,                // 6 - s
      $process_description,     // 7 - s
      $tags,                    // 8 - s
      $location_uuid,           // 9 - s
      $location_description,    // 10 - s
      $technology_description,  // 11 - s
      $approval_status,         // 12 - s
      $is_draft,                // 13 - i (integer)
      $created_by,              // 14 - s
      $valid_until,             // 15 - s
      $dq_data_quality          // 16 - s
    );
    
    error_log("Binding params completado");
    
    if (!$stmt->execute()) {
      throw new Exception("Error al insertar proceso: " . $stmt->error);
    }
    
    error_log("✅ Proceso insertado exitosamente: $uuid");

    // 3. INSERTAR DOCUMENTACIÓN
    $stmt = $conn->prepare("
      INSERT INTO process_documentation (
        process_uuid, completeness_text, project, intended_application
      ) VALUES (?, ?, '', ?)
    ");
    if (!$stmt) {
      throw new Exception("Error preparando INSERT doc: " . $conn->error);
    }
    $stmt->bind_param("sss", $uuid, $general_comment, $functional_unit);
    if (!$stmt->execute()) {
      throw new Exception("Error al crear documentación: " . $stmt->error);
    }

    $conn->commit();
    
    error_log("✅ Transacción completada exitosamente");
    
    // ✅ RESPUESTA SEGÚN ROL
    if ($es_admin) {
      echo "✓ Proceso guardado y aprobado exitosamente con UUID: " . $uuid;
    } else {
      notificarAdmins($uuid, $process_name, $created_by, $conn);
      echo "✓ Proceso enviado para revisión. Recibirás un email cuando sea aprobado o rechazado.";
    }
    
  } catch (Exception $e) {
    if (isset($conn)) {
      $conn->rollback();
    }
    error_log("❌ ERROR EN INSERT_PROCESS: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
  }
} else {
  http_response_code(405);
  echo "Método no permitido";
}

// ✅ FUNCIÓN PARA NOTIFICAR A LOS ADMINS
function notificarAdmins($proceso_uuid, $proceso_name, $creator_uuid, $conn) {
  try {
    $sql = "SELECT uuid, email, name FROM users WHERE role = 'ADMIN'";
    $result = $conn->query($sql);
    
    if ($result) {
      while ($admin = $result->fetch_assoc()) {
        $asunto = "📋 Nuevo proceso pendiente de revisión - CREAA";
        $mensaje = "Hola {$admin['name']},\n\n";
        $mensaje .= "Hay un nuevo proceso esperando tu revisión:\n\n";
        $mensaje .= "Nombre: $proceso_name\n";
        $mensaje .= "UUID: $proceso_uuid\n";
        $mensaje .= "Revisar en: https://ciclodevida.mx/Admin.php\n\n";
        $mensaje .= "Saludos,\nEquipo CREAA";
        
        $headers = "From: noreply@ciclodevida.mx\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        mail($admin['email'], $asunto, $mensaje, $headers);
      }
    }
  } catch (Exception $e) {
    error_log("Error enviando notificaciones a admins: " . $e->getMessage());
  }
}
?>
