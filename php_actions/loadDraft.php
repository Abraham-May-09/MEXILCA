<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json');

$uuid = $_GET['uuid'] ?? null;
$user_uuid = $_SESSION['user_uuid'] ?? null;

if (!$uuid || !$user_uuid) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit;
}

try {
    // 1. CARGAR PROCESO CON LOCATION
    $stmt = $conn->prepare("
        SELECT p.*, l.name as location
        FROM processes p
        LEFT JOIN locations l ON l.uuid = p.location_uuid
        WHERE p.uuid = ? AND p.created_by_uuid = ? AND p.is_draft = 1
    ");
    
    if (!$stmt) {
        throw new Exception("Error prepare proceso: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $uuid, $user_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $process = $result->fetch_assoc();

    if (!$process) {
        echo json_encode([
            'success' => false, 
            'message' => 'Borrador no encontrado',
            'debug' => [
                'uuid' => $uuid,
                'user_uuid' => $user_uuid
            ]
        ]);
        exit;
    }
    
    // 2. CARGAR INPUTS CON FLOW NAME Y UNIT NAME
    $inputs = [];
    $stmt = $conn->prepare("
        SELECT 
            pi.*,
            f.name as flow_name,
            u.name as unit_name
        FROM process_inputs pi
        LEFT JOIN flows f ON f.uuid = pi.flow_uuid
        LEFT JOIN units u ON u.uuid = pi.unit_uuid
        WHERE pi.process_uuid = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $inputs[] = $row;
        }
    }
    
    // 3. CARGAR OUTPUTS CON FLOW NAME Y UNIT NAME
    $outputs = [];
    $stmt = $conn->prepare("
        SELECT 
            po.*,
            f.name as flow_name,
            u.name as unit_name
        FROM process_outputs po
        LEFT JOIN flows f ON f.uuid = po.flow_uuid
        LEFT JOIN units u ON u.uuid = po.unit_uuid
        WHERE po.process_uuid = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $outputs[] = $row;
        }
    }
    
    // 4. CARGAR DOCUMENTACIÓN
    $documentation = null;
    $stmt = $conn->prepare("SELECT * FROM process_documentation WHERE process_uuid = ?");
    if ($stmt) {
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $result = $stmt->get_result();
        $documentation = $result->fetch_assoc();
    }
    
    // 5. CARGAR DATA QUALITY INDICATORS
    $dq_indicators = [];
    $stmt = $conn->prepare("
        SELECT indicator_type, score_level, selected_score, description, is_selected
        FROM process_dq_indicators
        WHERE process_uuid = ?
        ORDER BY indicator_type, score_level
    ");

    if ($stmt) {
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $dq_indicators[] = $row;
        }
    }

    // 6. RESPUESTA COMPLETA
    echo json_encode([
        'success' => true,
        'process' => $process,
        'inputs' => $inputs,
        'outputs' => $outputs,
        'documentation' => $documentation,
        'dq_indicators' => $dq_indicators,
        'debug' => [
            'total_inputs' => count($inputs),
            'total_outputs' => count($outputs),
            'total_dq_indicators' => count($dq_indicators),
            'has_documentation' => !empty($documentation)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en loadDraft.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
