<?php
session_start();

// Limpiar buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurar errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/check_status_errors.log');

$job_id = $_GET['job_id'] ?? null;

if (empty($job_id)) {
    echo json_encode(['status' => 'error', 'error' => 'job_id requerido']);
    exit;
}

$uploadDir = dirname(__DIR__) . '/temp_uploads/';
$resultPath = $uploadDir . $job_id . '_result.json';
$metaPath = $uploadDir . $job_id . '_meta.json';
$pdfPath = $uploadDir . $job_id . '.pdf';

error_log("Check status para job_id: $job_id");

// Verificar si ya hay resultado
if (file_exists($resultPath)) {
    $result = json_decode(file_get_contents($resultPath), true);
    
    error_log("Resultado encontrado - Status: " . ($result['status'] ?? 'unknown'));
    
    // Limpiar archivos temporales si completado o error
    if (in_array($result['status'], ['completed', 'error', 'timeout'])) {
        @unlink($pdfPath);
        @unlink($metaPath);
        
        // Limpiar resultado después de 5 minutos
        if (isset($result['timestamp']) && (time() - $result['timestamp']) > 300) {
            @unlink($resultPath);
        }
    }
    
    echo json_encode($result);
    exit;
}

// Verificar metadata
if (file_exists($metaPath)) {
    $metadata = json_decode(file_get_contents($metaPath), true);
    $elapsedTime = time() - ($metadata['created_at'] ?? 0);
    
    // Timeout después de 10 minutos
    if ($elapsedTime > 600) {
        $errorResult = [
            'status' => 'timeout',
            'error' => 'Timeout: El procesamiento excedió el tiempo máximo (10 min)',
            'elapsed_seconds' => $elapsedTime,
            'timestamp' => time()
        ];
        
        file_put_contents($resultPath, json_encode($errorResult));
        @unlink($pdfPath);
        @unlink($metaPath);
        
        error_log("Timeout para job: $job_id");
        echo json_encode($errorResult);
        exit;
    }
    
    // Advertencia después de 4 minutos
    if ($elapsedTime > 240) {
        echo json_encode([
            'status' => 'processing',
            'message' => 'El procesamiento está tardando más de lo esperado...',
            'elapsed_seconds' => $elapsedTime
        ]);
        exit;
    }
    
    // Procesando normalmente
    echo json_encode([
        'status' => $metadata['status'] ?? 'processing',
        'message' => 'Procesando archivo PDF...',
        'elapsed_seconds' => $elapsedTime
    ]);
    exit;
}

// No encontrado
error_log("Job no encontrado: $job_id");
echo json_encode([
    'status' => 'error',
    'error' => 'Job no encontrado',
    'job_id' => $job_id
]);
?>
