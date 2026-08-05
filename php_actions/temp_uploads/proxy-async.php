<?php
// Configuración inicial
@ini_set('max_execution_time', '10');
@set_time_limit(10);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejo de preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Verificar que se recibió el archivo PDF
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibió el archivo PDF']);
    exit();
}

// Verificar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solo se permiten archivos PDF']);
    exit();
}

// Generar job_id único
$job_id = uniqid('pdf_', true);

// Crear carpeta temp_uploads si no existe
$uploadDir = dirname(__DIR__) . '/temp_uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Guardar archivo temporalmente
$pdfPath = $uploadDir . $job_id . '.pdf';
if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo']);
    exit();
}

// Guardar metadata del job
$metadata = [
    'job_id' => $job_id,
    'filename' => $_FILES['pdf']['name'],
    'respuesta' => $_POST['respuesta'] ?? '',
    'status' => 'queued',
    'created_at' => time()
];
file_put_contents($uploadDir . $job_id . '_meta.json', json_encode($metadata));

// RESPONDER INMEDIATAMENTE AL USUARIO
echo json_encode([
    'success' => true,
    'job_id' => $job_id,
    'status' => 'queued',
    'message' => 'Archivo recibido. Procesando en segundo plano...'
]);

// Cerrar la conexión con el navegador
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// AHORA procesar en segundo plano sin exec()
$host = $_SERVER['HTTP_HOST'];
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$processUrl = $scheme . '://' . $host . '/php_actions/process-background-direct.php?job_id=' . urlencode($job_id);

// Hacer request asíncrono usando fsockopen
$urlParts = parse_url($processUrl);
$port = isset($urlParts['port']) ? $urlParts['port'] : ($scheme === 'https' ? 443 : 80);
$path = $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');

$fp = @fsockopen(($scheme === 'https' ? 'ssl://' : '') . $urlParts['host'], $port, $errno, $errstr, 1);
if ($fp) {
    $request = "GET $path HTTP/1.1\r\n";
    $request .= "Host: " . $urlParts['host'] . "\r\n";
    $request .= "Connection: Close\r\n\r\n";
    
    stream_set_blocking($fp, false);
    fwrite($fp, $request);
    fclose($fp);
}
?>
