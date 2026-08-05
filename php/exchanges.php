<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('display_errors', '0'); // evita que warnings rompan el JSON
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

ob_start();

require_once __DIR__ . '/conexion.php';

session_start();

// Validar autenticación (opcional, ajusta según tu sistema)
if (!isset($_SESSION['user_uuid']) && !isset($_SESSION['user_id'])) {
  http_response_code(401);
  ob_clean();
  echo json_encode(['ok'=>false, 'error'=>'No autenticado']);
  exit;
}

try {
  $q   = isset($_POST['q']) ? trim($_POST['q']) : '';
  $dir = isset($_POST['direction']) ? trim($_POST['direction']) : 'input';

  if (!$conn) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['ok'=>false,'error'=>'Sin conexión a la BD']);
    exit;
  }

  // Búsqueda en la tabla processes
  $like = '%' . $conn->real_escape_string($q) . '%';
  
  $sql = "SELECT 
            p.uuid, 
            p.name as process_name, 
            p.category, 
            l.name as location, 
            p.tech_desc as technology_description
          FROM processes p
          LEFT JOIN locations l ON p.location_uuid = l.uuid
          WHERE p.name LIKE ? OR p.category LIKE ? OR p.tags_text LIKE ?
          ORDER BY p.name ASC
          LIMIT 25";
  
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception("Error en prepare: " . $conn->error);
  }
  
  $like = '%'.$q.'%';
  $stmt->bind_param('sss', $like, $like, $like);
  $stmt->execute();
  $res = $stmt->get_result();

  $items = [];
  while ($row = $res->fetch_assoc()) {
    $items[] = [
      'uuid'       => $row['uuid'],
      'name'       => $row['process_name'] ?? '',
      'category'   => $row['category'] ?? '',
      'location'   => $row['location'] ?? '',
      'technology' => $row['technology_description'] ?? '',
      'direction'  => $dir,
    ];
  }

  $stmt->close();

  // Limpia cualquier salida accidental (espacios, BOM, etc.)
  ob_clean();
  echo json_encode([
    'ok'    => true, 
    'count' => count($items), 
    'items' => $items
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  ob_clean();
  echo json_encode([
    'ok'    => false, 
    'error' => $e->getMessage()
  ]);
}
?>
