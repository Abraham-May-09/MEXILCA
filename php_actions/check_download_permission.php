<?php
function can_user_download() {
    if (!isset($_SESSION['user_uuid'])) {
        return false;
    }
    
    require_once __DIR__ . '/../conexion.php';
    
    $user_uuid = $_SESSION['user_uuid'];
    $stmt = $conn->prepare("SELECT role, can_download FROM users WHERE uuid = ?");
    $stmt->bind_param("s", $user_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // ADMIN siempre puede descargar, o usuario con permiso especial
        if ($row['role'] === 'ADMIN' || $row['can_download'] == 1) {
            $stmt->close();
            return true;
        }
    }
    
    $stmt->close();
    return false;
}
?>
