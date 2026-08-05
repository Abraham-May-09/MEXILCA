<?php
session_start();
require_once '../conexion.php';

// Verificar que sea ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_uuid = $_POST['user_uuid'] ?? '';
    $action = $_POST['action'] ?? ''; // 'grant' o 'revoke'
    
    if (empty($user_uuid)) {
        echo json_encode(['success' => false, 'message' => 'UUID de usuario requerido']);
        exit;
    }
    
    $can_download = ($action === 'grant') ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE users SET can_download = ? WHERE uuid = ?");
    $stmt->bind_param("is", $can_download, $user_uuid);
    
    if ($stmt->execute()) {
        $message = ($action === 'grant') 
            ? 'Permiso de descarga otorgado exitosamente' 
            : 'Permiso de descarga revocado exitosamente';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar permisos']);
    }
    
    $stmt->close();
}
?>
