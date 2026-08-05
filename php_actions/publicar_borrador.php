<?php
session_start();
require_once 'verificar_permisos.php';
solo_admin_o_contributor();

header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$uuid = $data['uuid'] ?? null;

if (!$uuid) {
    echo json_encode(['success' => false, 'message' => 'UUID no proporcionado']);
    exit;
}

try {
    // Obtener el estado actual del proceso
    $stmt = $mysqli->prepare("SELECT approval_status, created_by_uuid FROM processes WHERE uuid = ?");
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $proceso = $result->fetch_assoc();
    $stmt->close();
    
    if (!$proceso) {
        echo json_encode(['success' => false, 'message' => 'Proceso no encontrado']);
        exit;
    }
    
    $estadoActual = $proceso['approval_status'];
    $creador_uuid = $proceso['created_by_uuid'];
    
    // Verificar permisos
    $user_uuid = $_SESSION['user_uuid'];
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
    
    // Verificar que el usuario sea el creador o admin
    if (!$is_admin && $creador_uuid !== $user_uuid) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar este dataset']);
        exit;
    }
    
    if ($is_admin) {
        // ✅ ADMINISTRADOR: Publicar directamente (aprobación automática)
        $sql = "UPDATE processes 
                SET is_draft = 0,
                    approval_status = 'approved',
                    last_change = NOW()
                WHERE uuid = ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $uuid);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Dataset publicado exitosamente'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo publicar el dataset']);
        }
        
    } else {
        // ⏳ USUARIO NORMAL
        
        // Si el dataset YA estaba aprobado → Enviar a revisión nuevamente
        if ($estadoActual === 'approved') {
            $sql = "UPDATE processes 
                    SET is_draft = 0,
                        approval_status = 'pending',
                        last_change = NOW()
                    WHERE uuid = ? 
                    AND created_by_uuid = ?";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ss", $uuid, $user_uuid);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Tus cambios han sido enviados para revisión. El dataset volverá a estar visible públicamente una vez que un administrador los apruebe.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se pudieron enviar los cambios']);
            }
        } 
        // Si es un borrador nuevo → Enviar a aprobación por primera vez
        else {
            $sql = "UPDATE processes 
                    SET is_draft = 0,
                        approval_status = 'pending',
                        last_change = NOW()
                    WHERE uuid = ? 
                    AND created_by_uuid = ?";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ss", $uuid, $user_uuid);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Tu dataset ha sido enviado para revisión. Aparecerá públicamente una vez que un administrador lo apruebe.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se pudo enviar para revisión']);
            }
        }
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error en publicar_borrador.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$mysqli->close();
?>
