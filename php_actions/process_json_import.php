<?php

// Limpiar output buffer
ob_start();

session_start();
require_once 'conexion.php';

// Limpiar buffer
ob_clean();

// Headers
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/import_errors.log');

// Verificar autenticación
if (!isset($_SESSION['user_uuid'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

$user_uuid = $_SESSION['user_uuid'];

// ========== VALIDACIÓN CRÍTICA: VERIFICAR QUE EL USUARIO EXISTA ==========
$stmt_check = $conn->prepare("SELECT uuid FROM users WHERE uuid = ? LIMIT 1");
$stmt_check->bind_param("s", $user_uuid);
$stmt_check->execute();
$user_exists = $stmt_check->get_result()->num_rows > 0;
$stmt_check->close();

if (!$user_exists) {
    error_log("ADVERTENCIA: Usuario en sesión no existe en BD. UUID: $user_uuid");
    
    // Buscar un usuario ADMIN como fallback
    $stmt_admin = $conn->prepare("SELECT uuid FROM users WHERE role = 'ADMIN' LIMIT 1");
    $stmt_admin->execute();
    $admin_result = $stmt_admin->get_result();
    
    if ($admin_row = $admin_result->fetch_assoc()) {
        $user_uuid = $admin_row['uuid'];
        error_log("Usando usuario ADMIN como fallback: $user_uuid");
        $stmt_admin->close();
    } else {
        $stmt_admin->close();
        
        // Si no hay ADMIN, buscar CUALQUIER usuario válido
        $stmt_any = $conn->prepare("SELECT uuid FROM users LIMIT 1");
        $stmt_any->execute();
        $any_result = $stmt_any->get_result();
        
        if ($any_row = $any_result->fetch_assoc()) {
            $user_uuid = $any_row['uuid'];
            error_log("Usando primer usuario disponible: $user_uuid");
            $stmt_any->close();
        } else {
            $stmt_any->close();
            echo json_encode([
                'success' => false, 
                'error' => 'No existe ningún usuario válido en la base de datos'
            ]);
            exit;
        }
    }
}

// Leer JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

$conn->begin_transaction();

try {
    $generalInfo = $data['General information'] ?? [];
    $locationData = $data['location'] ?? [];
    $processDoc = $data['processDocumentation'] ?? [];
    $actorsData = $data['actors'] ?? [];
    
    $process_uuid = $generalInfo['UUID'] ?? generateUUID();
    $process_name = $generalInfo['Name'] ?? 'Proceso sin nombre';
    $process_category = $generalInfo['Category'] ?? null;
    $process_description = $generalInfo['Description'] ?? null;
    $tags = $generalInfo['Tags'] ?? null;
    $tech_description = $generalInfo['Technology | Description'] ?? null;
    
    $last_change = null;
    if (isset($generalInfo['Last_change'])) {
        $last_change = convertToMySQLDateTime($generalInfo['Last_change']);
    }
    
    // ==================== INSERTAR LOCATION ====================
    $location_uuid = null;
    if (!empty($locationData['@id'])) {
        $location_uuid = $locationData['@id'];
        $location_name = $locationData['name'] ?? null;
        $location_code = $locationData['code'] ?? null;
        $location_desc = $locationData['description'] ?? null;
        $location_lat = $locationData['latitude'] ?? null;
        $location_lon = $locationData['longitude'] ?? null;
        $location_version = $locationData['version'] ?? '00.00.000';
        
        $stmt = $conn->prepare("
            INSERT INTO locations (uuid, code, name, description, latitude, longitude, version, last_change)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                code = VALUES(code),
                last_change = NOW()
        ");
        $stmt->bind_param("ssssdds", 
            $location_uuid, $location_code, $location_name, $location_desc,
            $location_lat, $location_lon, $location_version
        );
        $stmt->execute();
        $stmt->close();
    }
    
    // ==================== INSERTAR ACTOR ====================
    $actor_uuid = null;
    if (!empty($actorsData['@id'])) {
        $actor_uuid = $actorsData['@id'];
        $actor_name = $actorsData['name'] ?? 'Sin nombre';
        $actor_desc = $actorsData['description'] ?? null;
        $actor_category = $actorsData['category'] ?? null;
        $actor_version = $actorsData['version'] ?? '01.00.000';
        $actor_address = $actorsData['address'] ?? null;
        $actor_city = $actorsData['city'] ?? null;
        $actor_zipcode = $actorsData['zipCode'] ?? null;
        $actor_country = $actorsData['country'] ?? null;
        $actor_email = $actorsData['email'] ?? null;
        $actor_phone = $actorsData['telephone'] ?? null;
        $actor_fax = $actorsData['telefax'] ?? null;
        $actor_website = $actorsData['website'] ?? null;
        
        $stmt = $conn->prepare("
            INSERT INTO actors (uuid, name, description, category, version, address, city, zip_code, country, email, telephone, telefax, website, last_change)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                last_change = NOW()
        ");
        $stmt->bind_param("sssssssssssss",
            $actor_uuid, $actor_name, $actor_desc, $actor_category, $actor_version,
            $actor_address, $actor_city, $actor_zipcode, $actor_country,
            $actor_email, $actor_phone, $actor_fax, $actor_website
        );
        $stmt->execute();
        $stmt->close();
    }
    
    // ==================== INSERTAR PROCESO ====================
    $process_type = $processDoc['Process type'] ?? 'UNIT_PROCESS';
    $version = '01.00.000';
    $approval_status = 'draft';
    $is_imported = 1;
    $is_draft = 1;

    $stmt = $conn->prepare("
        INSERT INTO processes (
            uuid, name, process_type, category, description, version, 
            last_change, tags_text, location_uuid, tech_desc, created_at, 
            approval_status, is_draft, created_by_uuid, is_imported
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            description = VALUES(description),
            last_change = NOW()
    ");

    $stmt->bind_param("ssssssssssisi", 
        $process_uuid,        // 1 - s
        $process_name,        // 2 - s
        $process_type,        // 3 - s
        $process_category,    // 4 - s
        $process_description, // 5 - s
        $version,             // 6 - s
        $tags,                // 7 - s
        $location_uuid,       // 8 - s
        $tech_description,    // 9 - s
        $approval_status,     // 10 - s
        $is_draft,            // 11 - i
        $user_uuid,           // 12 - s
        $is_imported          // 13 - i
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al insertar proceso: " . $stmt->error);
    }
    $stmt->close();
    
    // ==================== INSERTAR DOCUMENTATION ====================
    // DETERMINAR TIPO DE DOCUMENTO
    $respuesta_usuario = $_SESSION['respuesta_pdf'] ?? 'No';
    $document_type = ($respuesta_usuario === 'Sí') ? 'Tesis' : 'Reportes/Artículos';
    
    $lci_method = $processDoc['LCI method'] ?? null;
    $modeling_constants = $processDoc['Modeling constants'] ?? null;
    $data_completeness = $processDoc['Data completeness'] ?? null;
    $data_selection = $processDoc['Data selection'] ?? null;
    $data_treatment = $processDoc['Data treatment'] ?? null;
    $sampling_proc = $processDoc['Sampling procedure'] ?? null;
    $collection_period = $processDoc['Data collection period'] ?? null;
    $use_advice = $processDoc['Use advice'] ?? null;
    $completeness_text = $processDoc['Completeness'] ?? null;
    $sources_text = $processDoc['Sources'] ?? null;
    $project = $processDoc['Project'] ?? null;
    $intended_app = $processDoc['Intended application'] ?? null;
    $creation_date = convertToMySQLDateTime($processDoc['Creation date'] ?? null);
    $copyright_flag = ($processDoc['Copyright'] === 'Proprietary') ? 1 : 0;
    $access_restrictions = $processDoc['Access and use restrictions'] ?? null;
    
    $stmt = $conn->prepare("
        INSERT INTO process_documentation (
            process_uuid, document_type, lci_method, modeling_constants, ds_data_completeness,
            ds_data_selection, ds_data_treatment, ds_sampling_procedure,
            ds_collection_period, ds_use_advice, completeness_text, sources_text,
            project, intended_application, creation_date, copyright_flag, access_use_restrictions
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            document_type = VALUES(document_type),
            lci_method = VALUES(lci_method)
    ");
    
    $stmt->bind_param("ssssssssssssssssi",
        $process_uuid,
        $document_type,
        $lci_method,
        $modeling_constants,
        $data_completeness,
        $data_selection,
        $data_treatment,
        $sampling_proc,
        $collection_period,
        $use_advice,
        $completeness_text,
        $sources_text,
        $project,
        $intended_app,
        $creation_date,
        $copyright_flag,
        $access_restrictions
    );

    $stmt->execute();
    $stmt->close();
    
    // ==================== VINCULAR ACTOR ====================
    if ($actor_uuid) {
        $role = 'data owner';
        $stmt = $conn->prepare("
            INSERT INTO process_actors (uuid, process_uuid, actor_uuid, role)
            VALUES (UUID(), ?, ?, ?)
            ON DUPLICATE KEY UPDATE role = VALUES(role)
        ");
        $stmt->bind_param("sss", $process_uuid, $actor_uuid, $role);
        $stmt->execute();
        $stmt->close();
    }
    
    // ==================== PROCESAR INPUTS CON AUTO-VINCULACIÓN ====================
    $inputs = $data['Inputs'] ?? [];
    $input_count = 0;
    $input_warnings = [];
    
    foreach ($inputs as $input) {
        $flow_name = $input['flow'] ?? 'Unknown flow';
        $category = $input['category'] ?? '';
        $amount = $input['amount'] ?? 0;
        $unit_name = $input['unit'] ?? '';
        $provider_name = $input['provider'] ?? '';
        $description = $input['description'] ?? '';
        $is_reference = ($input['is_reference'] === true || $input['is_reference'] === 'true') ? 1 : 0;
        
        try {
            $unit_parts = parseComplexUnit($conn, $unit_name);
            $primary_unit = $unit_parts['primary_unit'];
            $secondary_units = $unit_parts['secondary_units'];
            $was_found = $unit_parts['unit_found_in_bd'];
            
            if ($secondary_units) {
                $description .= ($description ? " | " : "") . "Unidades adicionales: $secondary_units";
                $input_warnings[] = "Input '$flow_name': Unidad '$unit_name' NO existe en BD → Separada en Primary: '$primary_unit', Secondary: '$secondary_units'";
            } elseif ($was_found) {
                $input_warnings[] = "Input '$flow_name': Unidad '$unit_name' encontrada de lleno en BD ✓";
            }
            
            $flow_uuid = findOrCreateFlowWithProperty($conn, $flow_name, $category, $primary_unit);
            
            $validated_unit = validateAndGetCompatibleUnit($conn, $primary_unit, $flow_uuid);
            $unit_uuid = $validated_unit['unit_uuid'];
            $flow_property_uuid = $validated_unit['flow_property_uuid'];
            
            // ========== VINCULACIÓN AUTOMÁTICA DE PROVEEDOR ==========
            $provider_uuid = null;
            
            // 1. Prioridad: Si viene provider_name en JSON
            if (!empty($provider_name)) {
                $provider_uuid = findProcessByName($conn, $provider_name);
                if ($provider_uuid) {
                    $input_warnings[] = "✓ Input '$flow_name': Vinculado a proveedor '$provider_name'";
                }
            }
            
            // 2. Si no hay provider_name, buscar automáticamente por flow
            if (!$provider_uuid) {
                $provider_uuid = findProviderProcessByFlow($conn, $flow_name, $category);
                if ($provider_uuid) {
                    $stmt_prov = $conn->prepare("SELECT name FROM processes WHERE uuid = ?");
                    $stmt_prov->bind_param("s", $provider_uuid);
                    $stmt_prov->execute();
                    $prov_data = $stmt_prov->get_result()->fetch_assoc();
                    $stmt_prov->close();
                    
                    $provider_name = $prov_data['name'] ?? '';
                    $input_warnings[] = "✓ Input '$flow_name': AUTO-VINCULADO a proceso '$provider_name'";
                } else {
                    $input_warnings[] = "⚠ Input '$flow_name': No se encontró proveedor automático";
                }
            }
            
            $input_uuid = generateUUID();
            $stmt = $conn->prepare("
                INSERT INTO process_inputs (
                    uuid, process_uuid, flow_uuid, category, amount, unit_uuid,
                    flow_property_uuid, provider_process_uuid, provider_name,
                    description, is_reference, uncertainty_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'NONE')
            ");
            
            $stmt->bind_param("ssssdsssssi",
                $input_uuid, $process_uuid, $flow_uuid, $category, $amount,
                $unit_uuid, $flow_property_uuid, $provider_uuid, $provider_name,
                $description, $is_reference
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar input: " . $stmt->error);
            }
            $stmt->close();
            $input_count++;
            
        } catch (Exception $inputError) {
            error_log("Error en input: " . $inputError->getMessage());
            $input_warnings[] = "⚠️ Input '$flow_name': " . $inputError->getMessage();
        }
    }
    
    // ==================== PROCESAR OUTPUTS ====================
    $outputs = $data['Outputs'] ?? [];
    $output_count = 0;
    $output_warnings = [];
    
    foreach ($outputs as $output) {
        $flow_name = $output['flow'] ?? 'Unknown flow';
        $category = $output['category'] ?? '';
        $amount = $output['amount'] ?? 0;
        $unit_name = $output['unit'] ?? '';
        $description = $output['description'] ?? '';
        $is_reference = ($output['is_reference'] === true || $output['is_reference'] === 'true') ? 1 : 0;
        
        try {
            $unit_parts = parseComplexUnit($conn, $unit_name);
            $primary_unit = $unit_parts['primary_unit'];
            $secondary_units = $unit_parts['secondary_units'];
            $was_found = $unit_parts['unit_found_in_bd'];
            
            if ($secondary_units) {
                $description .= ($description ? " | " : "") . "Unidades adicionales: $secondary_units";
                $output_warnings[] = "Output '$flow_name': Unidad '$unit_name' NO existe en BD → Separada en Primary: '$primary_unit', Secondary: '$secondary_units'";
            } elseif ($was_found) {
                $output_warnings[] = "Output '$flow_name': Unidad '$unit_name' encontrada de lleno en BD ✓";
            }
            
            $flow_uuid = findOrCreateFlowWithProperty($conn, $flow_name, $category, $primary_unit);
            
            $validated_unit = validateAndGetCompatibleUnit($conn, $primary_unit, $flow_uuid);
            $unit_uuid = $validated_unit['unit_uuid'];
            $flow_property_uuid = $validated_unit['flow_property_uuid'];
            
            $output_uuid = generateUUID();
            $stmt = $conn->prepare("
                INSERT INTO process_outputs (
                    uuid, process_uuid, flow_uuid, category, amount, unit_uuid,
                    flow_property_uuid, description, is_reference, uncertainty_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'NONE')
            ");
            
            $stmt->bind_param("ssssdsssi",
                $output_uuid, $process_uuid, $flow_uuid, $category, $amount,
                $unit_uuid, $flow_property_uuid, $description, $is_reference
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar output: " . $stmt->error);
            }
            $stmt->close();
            $output_count++;
            
        } catch (Exception $outputError) {
            error_log("Error en output: " . $outputError->getMessage());
            $output_warnings[] = "⚠️ Output '$flow_name': " . $outputError->getMessage();
        }
    }
    
    $conn->commit();
    
    // ==================== RESPUESTA EXITOSA ====================
    $response = [
        'success' => true,
        'message' => 'Dataset importado correctamente',
        'process_uuid' => $process_uuid,
        'process_name' => $process_name,
        'inputs_count' => $input_count,
        'outputs_count' => $output_count
    ];
    
    if (!empty($input_warnings) || !empty($output_warnings)) {
        $response['warnings'] = [
            'inputs' => $input_warnings,
            'outputs' => $output_warnings
        ];
        $response['has_warnings'] = true;
    }
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    
    error_log("Error en process_json_import.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al insertar datos: ' . $e->getMessage()
    ]);
}

$conn->close();
ob_end_flush(); 
exit;

// ╔════════════════════════════════════════════════════════════╗
// ║                  FUNCIONES AUXILIARES                      ║
// ╚════════════════════════════════════════════════════════════╝

/**
 * Busca automáticamente un proceso proveedor para un flujo
 * Estrategia:
 * 1. Verificar mapeo manual (flow_provider_mapping)
 * 2. Buscar proceso con output de referencia coincidente (exacto)
 * 3. Buscar proceso con output de referencia similar (LIKE)
 */
function findProviderProcessByFlow($conn, $flow_name, $category = null) {
    // 1. Verificar mapeo manual primero
    $stmt = $conn->prepare("
        SELECT process_uuid 
        FROM flow_provider_mapping 
        WHERE flow_name = ?
        ORDER BY priority DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $flow_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['process_uuid'];
    }
    $stmt->close();
    
    // 2. Búsqueda exacta por nombre de flow
    $stmt = $conn->prepare("
        SELECT p.uuid, p.name
        FROM processes p
        INNER JOIN process_outputs po ON po.process_uuid = p.uuid
        INNER JOIN flows f ON f.uuid = po.flow_uuid
        WHERE f.name = ?
        AND po.is_reference = 1
        AND p.approval_status = 'approved'
        ORDER BY p.last_change DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $flow_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['uuid'];
    }
    $stmt->close();
    
    // 3. Búsqueda parcial (contiene) - útil para variaciones de nombre
    $search_pattern = "%$flow_name%";
    $stmt = $conn->prepare("
        SELECT p.uuid, p.name, f.name as flow_name
        FROM processes p
        INNER JOIN process_outputs po ON po.process_uuid = p.uuid
        INNER JOIN flows f ON f.uuid = po.flow_uuid
        WHERE f.name LIKE ?
        AND po.is_reference = 1
        AND p.approval_status = 'approved'
        ORDER BY 
            CASE WHEN f.name = ? THEN 0 ELSE 1 END,
            p.last_change DESC
        LIMIT 1
    ");
    $stmt->bind_param("ss", $search_pattern, $flow_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['uuid'];
    }
    $stmt->close();
    
    return null;
}

function parseComplexUnit($conn, $unit_name) {
    $unit_name = trim($unit_name);
    
    if (empty($unit_name)) {
        return [
            'primary_unit' => 'kg',
            'secondary_units' => '',
            'unit_found_in_bd' => false
        ];
    }
    
    $stmt = $conn->prepare("SELECT uuid FROM units WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $unit_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return [
            'primary_unit' => $unit_name,
            'secondary_units' => '',
            'unit_found_in_bd' => true
        ];
    }
    $stmt->close();
    
    $valid_units = [
        'kg', 'g', 't', 'ton', 'lb', 'oz',
        'l', 'ml', 'm3', 'gal',
        'm', 'cm', 'km', 'ft', 'in',
        'm2', 'm3', 'ha',
        'h', 'min', 'sec', 's', 'day', 'year',
        'mj', 'gj', 'kwh', 'j', 'cal',
        'unit', 'item', 'pcs', 'piece',
        'ppm', '%', 'percent'
    ];
    
    $unit_lower = strtolower($unit_name);
    $unit_parts = preg_split('/[\s\/\(\)]+/', $unit_lower, -1, PREG_SPLIT_NO_EMPTY);
    
    if (count($unit_parts) === 0) {
        return [
            'primary_unit' => 'kg',
            'secondary_units' => '',
            'unit_found_in_bd' => false
        ];
    }
    
    $primary = trim($unit_parts[0]);
    
    if (in_array($primary, $valid_units)) {
        if (count($unit_parts) > 1) {
            return [
                'primary_unit' => $primary,
                'secondary_units' => $unit_name,
                'unit_found_in_bd' => false
            ];
        }
        
        return [
            'primary_unit' => $primary,
            'secondary_units' => '',
            'unit_found_in_bd' => false
        ];
    }
    
    foreach ($valid_units as $valid) {
        if (strpos($unit_lower, $valid) === 0) {
            $primary = $valid;
            $secondary = trim(substr($unit_name, strlen($valid)));
            
            if ($secondary) {
                return [
                    'primary_unit' => $primary,
                    'secondary_units' => $secondary,
                    'unit_found_in_bd' => false
                ];
            } else {
                return [
                    'primary_unit' => $primary,
                    'secondary_units' => '',
                    'unit_found_in_bd' => false
                ];
            }
        }
    }
    
    return [
        'primary_unit' => 'kg',
        'secondary_units' => $unit_name,
        'unit_found_in_bd' => false
    ];
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function convertToMySQLDateTime($dateStr) {
    if (empty($dateStr)) return null;
    
    $formats = [
        'd/m/Y H:i',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i:s.u\Z',
        'Y-m-d'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    
    return null;
}

function validateAndGetCompatibleUnit($conn, $unit_name, $flow_uuid) {
    $stmt = $conn->prepare("SELECT uuid, unit_group_uuid FROM units WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $unit_name);
    $stmt->execute();
    $unit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$unit_data) {
        throw new Exception("Unidad '$unit_name' no encontrada en la base de datos");
    }
    
    $unit_uuid = $unit_data['uuid'];
    $unit_group_uuid = $unit_data['unit_group_uuid'];
    
    $stmt = $conn->prepare("SELECT reference_flow_property_uuid FROM flows WHERE uuid = ? LIMIT 1");
    $stmt->bind_param("s", $flow_uuid);
    $stmt->execute();
    $flow_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$flow_data || !$flow_data['reference_flow_property_uuid']) {
        throw new Exception("El flow no tiene flow_property de referencia asignada");
    }
    
    $flow_property_uuid = $flow_data['reference_flow_property_uuid'];
    
    $stmt = $conn->prepare("SELECT unit_group_uuid FROM flow_properties WHERE uuid = ? LIMIT 1");
    $stmt->bind_param("s", $flow_property_uuid);
    $stmt->execute();
    $fp_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$fp_data) {
        throw new Exception("Flow property no encontrada");
    }
    
    $fp_unit_group_uuid = $fp_data['unit_group_uuid'];
    
    if ($unit_group_uuid !== $fp_unit_group_uuid) {
        error_log("WARNING: Unidad '$unit_name' (group: $unit_group_uuid) no coincide con flow_property");
        
        $stmt = $conn->prepare("
            SELECT uuid FROM units 
            WHERE unit_group_uuid = ? AND is_ref_unit = 1 
            LIMIT 1
        ");
        $stmt->bind_param("s", $fp_unit_group_uuid);
        $stmt->execute();
        $ref_unit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$ref_unit) {
            throw new Exception("No se encontró unidad de referencia para el unit_group requerido");
        }
        
        $unit_uuid = $ref_unit['uuid'];
    }
    
    return [
        'unit_uuid' => $unit_uuid,
        'flow_property_uuid' => $flow_property_uuid
    ];
}

function findOrCreateFlowWithProperty($conn, $flow_name, $category, $unit_name) {
    $stmt = $conn->prepare("SELECT uuid FROM flows WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $flow_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['uuid'];
    }
    $stmt->close();
    
    $flow_uuid = generateUUID();
    $flow_type = determineFlowType($flow_name, $category);
    $fp_uuid = getFlowPropertyForUnit($conn, $unit_name);
    
    $stmt = $conn->prepare("
        INSERT INTO flows (uuid, name, category, flow_type, reference_flow_property_uuid, last_change)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sssss", $flow_uuid, $flow_name, $category, $flow_type, $fp_uuid);
    $stmt->execute();
    $stmt->close();
    
    return $flow_uuid;
}

function getFlowPropertyForUnit($conn, $unit_name) {
    $unit_to_property = [
        'unit' => 'Number of items',
        'item(s)' => 'Number of items',
        'gj' => 'Energy',
        'mj' => 'Energy',
        'kwh' => 'Energy',
        'kg' => 'Mass',
        'g' => 'Mass',
        't' => 'Mass',
        'ton' => 'Mass',
        'km' => 'Length',
        'm' => 'Length',
        'l' => 'Volume',
        'm3' => 'Volume',
        'm2' => 'Area'
    ];
    
    $property_name = $unit_to_property[strtolower($unit_name)] ?? 'Mass';
    
    $stmt = $conn->prepare("SELECT uuid FROM flow_properties WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $property_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['uuid'];
    }
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT uuid FROM flow_properties LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['uuid'];
    }
    $stmt->close();
    
    return null;
}

function determineFlowType($flow_name, $category) {
    $flow_name_lower = strtolower($flow_name);
    
    $elementary_keywords = ['carbon dioxide', 'co2', 'carbon monoxide', 'nitrogen', 'particulates', 'emissions', 'energy', 'occupation', 'transformation', 'water'];
    foreach ($elementary_keywords as $keyword) {
        if (strpos($flow_name_lower, $keyword) !== false) {
            return 'ELEMENTARY_FLOW';
        }
    }
    
    if (strpos($flow_name_lower, 'waste') !== false) {
        return 'WASTE_FLOW';
    }
    
    return 'PRODUCT_FLOW';
}

function findProcessByName($conn, $process_name) {
    if (empty($process_name)) return null;
    
    $stmt = $conn->prepare("SELECT uuid FROM processes WHERE name LIKE ? LIMIT 1");
    $like_name = "%$process_name%";
    $stmt->bind_param("s", $like_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['uuid'];
    }
    $stmt->close();
    
    return null;
}
?>
