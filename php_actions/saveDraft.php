<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_uuid'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$user_uuid = $_SESSION['user_uuid'];
$uuid = $_POST['uuid'] ?? null;

if (!$uuid) {
    echo json_encode(['success' => false, 'message' => 'UUID faltante']);
    exit;
}

// Recopilar datos
$process_name = $_POST['processname'] ?? '';
$process_description = $_POST['processdescription'] ?? '';
$category = $_POST['category'] ?? '';
$sector = $_POST['sector'] ?? '';
$tags = $_POST['tags'] ?? '';
$type_of_process = $_POST['typeofprocess'] ?? 'UNIT_PROCESS';
$lifecycle_stage = $_POST['lifecyclestage'] ?? '';
$functional_unit = $_POST['functionalunit'] ?? '';
$location = $_POST['location'] ?? '';
$location_description = $_POST['locationdescription'] ?? '';
$technology_description = $_POST['technologydescription'] ?? '';
$general_comment = $_POST['generalcomment'] ?? '';

try {
    // Verificar si existe
    $stmt = $conn->prepare("SELECT uuid FROM processes WHERE uuid = ? AND created_by_uuid = ?");
    if (!$stmt) throw new Exception("Error prepare: " . $conn->error);
    
    $stmt->bind_param("ss", $uuid, $user_uuid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE processes SET 
            name = ?, description = ?, category = ?, sector = ?, tags_text = ?,
            process_type = ?, geo_desc = ?, tech_desc = ?,
            approval_status = 'draft', is_draft = 1, last_change = NOW()
            WHERE uuid = ? AND created_by_uuid = ?");
            
        if (!$stmt) throw new Exception("Error UPDATE: " . $conn->error);
        
        $stmt->bind_param("ssssssssss", 
            $process_name, $process_description, $category, $sector, $tags,
            $type_of_process, $location_description, $technology_description,
            $uuid, $user_uuid
        );
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO processes 
            (uuid, name, description, category, sector, tags_text, process_type, 
            geo_desc, tech_desc, approval_status, is_draft, created_by_uuid, created_at, last_change) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 1, ?, NOW(), NOW())");
            
        if (!$stmt) throw new Exception("Error INSERT: " . $conn->error);
        
        $stmt->bind_param("ssssssssss", 
            $uuid, $process_name, $process_description, $category, $sector, $tags,
            $type_of_process, $location_description, $technology_description, $user_uuid
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Error ejecutar: " . $stmt->error);
    }

    // Aquí puedes agregar después el guardado de inputs/outputs/documentación

    echo json_encode([
        'success' => true, 
        'uuid' => $uuid,
        'message' => 'Borrador guardado'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
