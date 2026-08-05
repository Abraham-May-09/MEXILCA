<?php
// php_actions/proxy-async.php
session_start();

// Limpiar buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Configurar logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/proxy_errors.log');

error_log("=== PROXY-ASYNC INICIADO ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Método no permitido - " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar que se recibió el archivo PDF
if (!isset($_FILES['pdf'])) {
    error_log("ERROR: No se recibió archivo PDF en \$_FILES");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibió el archivo PDF']);
    exit;
}

if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    error_log("ERROR: Upload error code - " . $_FILES['pdf']['error']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Error al subir archivo: ' . $_FILES['pdf']['error']]);
    exit;
}

error_log("Archivo recibido: " . $_FILES['pdf']['name']);
error_log("Tamaño: " . $_FILES['pdf']['size'] . " bytes");

// Verificar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
finfo_close($finfo);

error_log("MIME type detectado: $mimeType");

if ($mimeType !== 'application/pdf') {
    error_log("ERROR: Tipo MIME no válido - $mimeType");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solo se permiten archivos PDF']);
    exit;
}

// Capturar respuesta
$respuesta = $_POST['respuesta'] ?? 'No';
$_SESSION['respuesta_pdf'] = $respuesta;

error_log("Respuesta de tesis: $respuesta");

// Generar job_id único
$job_id = uniqid('pdf_', true);
error_log("Job ID generado: $job_id");

// Crear carpeta temp_uploads si no existe
$uploadDir = dirname(__DIR__) . '/temp_uploads/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        error_log("ERROR: No se pudo crear directorio temp_uploads");
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al crear directorio temporal']);
        exit;
    }
    error_log("Directorio temp_uploads creado");
}

// Guardar archivo temporalmente
$pdfPath = $uploadDir . $job_id . '.pdf';
error_log("Guardando PDF en: $pdfPath");

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfPath)) {
    error_log("ERROR: No se pudo mover el archivo a $pdfPath");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo']);
    exit;
}

error_log("PDF guardado exitosamente");

// Guardar metadata
$metadata = [
    'job_id' => $job_id,
    'filename' => $_FILES['pdf']['name'],
    'respuesta' => $respuesta,
    'status' => 'pending',
    'created_at' => time()
];

$metaPath = $uploadDir . $job_id . '_meta.json';
file_put_contents($metaPath, json_encode($metadata));
error_log("Metadata guardada en: $metaPath");

// Construir URL del proceso en segundo plano
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$processUrl = $protocol . '://' . $host . '/php_actions/process-background-direct.php?job_id=' . urlencode($job_id);

error_log("Iniciando proceso en segundo plano...");
error_log("URL: $processUrl");

// Iniciar proceso con CURL
$ch = curl_init($processUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, // ✅ Cambiar a true para capturar errores
    CURLOPT_TIMEOUT => 2,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_NOSIGNAL => 1,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true
]);

$curlResponse = curl_exec($ch);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Log resultado del CURL
if ($curlErrno) {
    error_log("⚠️ CURL ERROR ($curlErrno): $curlError");
    error_log("Pero el proceso continuará en segundo plano...");
} else {
    error_log("✅ CURL exitoso - HTTP Code: $httpCode");
    if ($curlResponse) {
        error_log("Respuesta del background: " . substr($curlResponse, 0, 200));
    }
}

// Responder inmediatamente al cliente (siempre exitoso si llegó aquí)
error_log("=== RESPONDIENDO AL CLIENTE ===");
error_log("Job ID: $job_id");

echo json_encode([
    'success' => true,
    'job_id' => $job_id,
    'message' => 'Procesamiento iniciado',
    'debug' => [
        'curl_error' => $curlErrno ? $curlError : null,
        'http_code' => $httpCode
    ]
]);

error_log("=== PROXY-ASYNC FINALIZADO ===");
?>
