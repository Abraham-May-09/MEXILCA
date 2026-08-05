<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

ob_start();

require_once __DIR__ . '/conexion.php';

try {
    if (!isset($_POST['action']) || $_POST['action'] !== 'search_processes') {
        throw new Exception("Acción no válida");
    }
    
    $query = isset($_POST['query']) ? trim($_POST['query']) : '';
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
    
    if (strlen($query) < 2) {
        ob_clean();
        echo json_encode([]);
        exit;
    }
    
    if (!$conn) {
        throw new Exception('Sin conexión a la BD');
    }
    
    // Búsqueda con functional_unit incluido
    $like = '%' . $query . '%';
    
    $stmt = $conn->prepare("
        SELECT 
            p.uuid,
            p.name,
            p.category,
            CONCAT(po.amount, ' ', u.name) AS functional_unit
        FROM processes p
        LEFT JOIN process_outputs po ON p.uuid = po.process_uuid AND po.is_reference = 1
        LEFT JOIN units u ON po.unit_uuid = u.uuid
        WHERE p.name LIKE ? OR p.category LIKE ? OR p.tags_text LIKE ?
        ORDER BY p.name ASC
        LIMIT ?
    ");
    
    if (!$stmt) {
        throw new Exception("Error en prepare: " . $conn->error);
    }
    
    $stmt->bind_param("sssi", $like, $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $processes = [];
    while ($row = $result->fetch_assoc()) {
        $processes[] = $row;
    }
    
    $stmt->close();
    
    ob_clean();
    echo json_encode($processes, JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
?>
