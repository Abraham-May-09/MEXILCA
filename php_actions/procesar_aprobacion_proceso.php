<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
ob_clean();
header('Content-Type: application/json');

// ========== INCLUIR PHPMAILER ==========
require_once __DIR__ . '/send_email.php';  // ← AGREGADO

try {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    if (!file_exists(__DIR__ . '/conexion.php')) {
        throw new Exception('Archivo conexion.php no encontrado');
    }
    
    require_once __DIR__ . '/conexion.php';

    if (!isset($conn)) {
        throw new Exception('Variable $conn no definida en conexion.php');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Datos JSON inválidos');
    }

    $proceso_uuid = $data['proceso_uuid'] ?? null;
    $action = $data['action'] ?? null;
    $reason = $data['reason'] ?? '';

    if (!$proceso_uuid || !$action) {
        throw new Exception('Faltan datos: proceso_uuid o action');
    }

    $admin_uuid = $_SESSION['user_uuid'] ?? null;

    if (!$admin_uuid) {
        throw new Exception('UUID de admin no encontrado en sesión');
    }

    // ========== APROBAR PROCESO ==========
    if ($action === 'approve') {
        
        $sql = "UPDATE processes 
                SET approval_status = 'approved',
                    reviewed_by_uuid = ?,
                    reviewed_at = NOW()
                WHERE uuid = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error preparar UPDATE: ' . $conn->error);
        }
        
        $stmt->bind_param("ss", $admin_uuid, $proceso_uuid);
        
        if (!$stmt->execute()) {
            throw new Exception('Error ejecutar UPDATE: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Proceso no encontrado con UUID: ' . $proceso_uuid);
        }
        
        notificarCreador($proceso_uuid, 'approved', null, $conn);
        echo json_encode(['success' => true, 'message' => 'Proceso aprobado exitosamente y notificación enviada']);
        
    // ========== RECHAZAR Y ELIMINAR PROCESO ==========
    } elseif ($action === 'reject') {
        
        if (empty(trim($reason))) {
            echo json_encode(['success' => false, 'message' => 'Debe proporcionar un motivo detallado del rechazo']);
            exit();
        }
        
        // ✅ PASO 1: Notificar ANTES de eliminar (para que el email tenga los datos)
        notificarCreador($proceso_uuid, 'rejected', $reason, $conn);
        
        // ✅ PASO 2: Eliminar TODAS las tablas relacionadas con el proceso
        $tablas_relacionadas = [
            'parameters_process',
            'process_actors',
            'process_dq_indicators',
            'process_sources',
            'process_inputs',
            'process_outputs',
            'process_documentation'
        ];
        
        $eliminados = 0;
        
        foreach ($tablas_relacionadas as $tabla) {
            $sql = "DELETE FROM $tabla WHERE process_uuid = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $proceso_uuid);
                if ($stmt->execute()) {
                    $filas = $stmt->affected_rows;
                    if ($filas > 0) {
                        $eliminados += $filas;
                        error_log("Eliminadas $filas filas de $tabla");
                    }
                }
            }
        }
        
        // ✅ PASO 3: Eliminar el proceso principal
        $sql = "DELETE FROM processes WHERE uuid = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error preparar DELETE proceso: ' . $conn->error);
        }
        
        $stmt->bind_param("s", $proceso_uuid);
        
        if (!$stmt->execute()) {
            throw new Exception('Error ejecutar DELETE proceso: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Proceso no encontrado con UUID: ' . $proceso_uuid);
        }
        
        error_log("Proceso eliminado exitosamente: $proceso_uuid (Total registros relacionados eliminados: $eliminados)");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Proceso rechazado y eliminado completamente. El contributor recibirá un email con el motivo del rechazo.'
        ]);
        
    } else {
        throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Error en procesar_aprobacion_proceso.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    error_log("Error fatal en procesar_aprobacion_proceso.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fatal: ' . $e->getMessage()]);
}

ob_end_flush();

// ========== FUNCIÓN PARA NOTIFICAR AL CREADOR CON PHPMAILER ==========
function notificarCreador($proceso_uuid, $status, $reason, $conn) {
    try {
        $sql = "SELECT p.name, u.uuid, u.email, u.name as creator_name
                FROM processes p
                LEFT JOIN users u ON p.created_by_uuid = u.uuid
                WHERE p.uuid = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log('Error preparar SELECT notificación: ' . $conn->error);
            return;
        }
        
        $stmt->bind_param("s", $proceso_uuid);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if (!$data || !$data['email']) {
            error_log('Email no encontrado para proceso: ' . $proceso_uuid);
            return;
        }
        
        // ========== ENVIAR CON PHPMAILER ==========
        if ($status === 'approved') {
            $email_enviado = enviarNotificacionDataset(
                $data['email'], 
                $data['creator_name'], 
                $data['name'], 
                'aprobado'
            );
        } else {
            $email_enviado = enviarNotificacionDataset(
                $data['email'], 
                $data['creator_name'], 
                $data['name'], 
                'rechazado', 
                $reason
            );
        }
        
        if ($email_enviado) {
            error_log("Email de $status enviado exitosamente a: " . $data['email']);
        } else {
            error_log("Error al enviar email de $status a: " . $data['email']);
        }
        
    } catch (Exception $e) {
        error_log('Error en notificarCreador: ' . $e->getMessage());
    }
}
?>
