<?php
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

session_start();

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP [$errno]: $errstr en $errfile en línea $errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['original_uuid']) || empty($data['original_uuid'])) {
        throw new Exception('UUID no proporcionado');
    }
    $original_uuid = $data['original_uuid'];

    // ✅ Verificar que haya sesión activa
    if (!isset($_SESSION['user_uuid'])) {
        throw new Exception('No has iniciado sesión');
    }

    $config = require __DIR__ . '/config.php';

    $mysqli = new mysqli(
        $config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    $mysqli->set_charset('utf8mb4');

    $new_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

    error_log("Nuevo UUID generado: $new_uuid");
    error_log("UUID original a copiar: $original_uuid");

    if (empty($new_uuid)) {
        throw new Exception('UUID inválido generado');
    }

    // ✅ Usuario actual y estado de aprobación
    $user_uuid = $_SESSION['user_uuid'];
    $es_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
    $approval_status = $es_admin ? 'approved' : 'pending';

    $mysqli->begin_transaction();

    // ✅ Insertar en processes CON is_draft = 1 y approval_status
    $stmt = $mysqli->prepare("
        INSERT INTO processes (
            uuid, name, sector_principal, process_type, category, description, version,
            last_change, tags_text, valid_from, valid_until, time_desc, location_uuid,
            geo_desc, tech_desc, dq_process_schema, dq_data_quality, dq_flow_schema,
            dq_social_schema, created_at, is_draft, approval_status, created_by_uuid
        )
        SELECT ?, 
            CONCAT(name, ' (Copia en edición)'), 
            sector_principal, process_type, category, description, version,
            NOW(), tags_text, valid_from, valid_until, time_desc, location_uuid,
            geo_desc, tech_desc, dq_process_schema, dq_data_quality, dq_flow_schema,
            dq_social_schema, 
            NOW(),
            1,              -- ✅ is_draft = 1 (BORRADOR)
            ?,              -- ✅ approval_status ('approved' para admin, 'pending' para contributor)
            ?               -- ✅ created_by_uuid
        FROM processes WHERE uuid = ?
    ");
    $stmt->bind_param('ssss', $new_uuid, $approval_status, $user_uuid, $original_uuid);
    
    if (!$stmt->execute()) {
        error_log("Error en tabla processes: " . $stmt->error);
        throw new Exception("Error en tabla processes: " . $stmt->error);
    }
    $stmt->close();

    // Insertar en process_documentation
    $stmt = $mysqli->prepare("
        INSERT INTO process_documentation (
            process_uuid, project, lci_method, ds_data_selection, ds_data_treatment,
            ds_collection_period, ds_data_completeness, completeness_text, sources_text,
            modeling_constants, access_use_restrictions, creation_date, copyright_flag
        )
        SELECT ?, project, lci_method, ds_data_selection, ds_data_treatment,
            ds_collection_period, ds_data_completeness, completeness_text, sources_text,
            modeling_constants, access_use_restrictions, creation_date, copyright_flag
        FROM process_documentation WHERE process_uuid = ?
    ");
    $stmt->bind_param('ss', $new_uuid, $original_uuid);
    if (!$stmt->execute()) {
        error_log("Error en tabla process_documentation: " . $stmt->error);
        throw new Exception("Error en tabla process_documentation: " . $stmt->error);
    }
    $stmt->close();

    // Insertar en process_inputs
    $stmt = $mysqli->prepare("
        INSERT INTO process_inputs (
            process_uuid, flow_uuid, category, amount, unit_uuid, flow_property_uuid,
            uncertainty_type, stat_mean_mode, stat_sd_gsd, stat_min, stat_max,
            price_type, price_value, currency_uuid, provider_process_uuid, provider_name,
            dq_entry_text, description, location_uuid
        )
        SELECT ?, flow_uuid, category, amount, unit_uuid, flow_property_uuid,
            uncertainty_type, stat_mean_mode, stat_sd_gsd, stat_min, stat_max,
            price_type, price_value, currency_uuid, provider_process_uuid, provider_name,
            dq_entry_text, description, location_uuid
        FROM process_inputs WHERE process_uuid = ?
    ");
    $stmt->bind_param('ss', $new_uuid, $original_uuid);
    if (!$stmt->execute()) {
        error_log("Error en tabla process_inputs: " . $stmt->error);
        throw new Exception("Error en tabla process_inputs: " . $stmt->error);
    }
    $stmt->close();

    // Insertar en process_outputs
    $stmt = $mysqli->prepare("
        INSERT INTO process_outputs (
            process_uuid, flow_uuid, category, amount, unit_uuid, flow_property_uuid,
            uncertainty_type, stat_mean_mode, stat_sd_gsd, stat_min, stat_max,
            price_type, price_value, currency_uuid, is_reference, provider_process_uuid,
            provider_name, dq_entry_text, description, location_uuid
        )
        SELECT ?, flow_uuid, category, amount, unit_uuid, flow_property_uuid,
            uncertainty_type, stat_mean_mode, stat_sd_gsd, stat_min, stat_max,
            price_type, price_value, currency_uuid, is_reference, provider_process_uuid,
            provider_name, dq_entry_text, description, location_uuid
        FROM process_outputs WHERE process_uuid = ?
    ");
    $stmt->bind_param('ss', $new_uuid, $original_uuid);
    if (!$stmt->execute()) {
        error_log("Error en tabla process_outputs: " . $stmt->error);
        throw new Exception("Error en tabla process_outputs: " . $stmt->error);
    }
    $stmt->close();

    $mysqli->commit();

    ob_clean();
    echo json_encode([
        'success' => true, 
        'new_uuid' => $new_uuid,
        'message' => 'Copia creada como borrador. Solo tú puedes verla hasta que la publiques.'
    ]);

} catch (Throwable $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    ob_clean();
    error_log("Error al crear copia: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$mysqli->close();
?>
