<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$job_id = $_GET['job_id'] ?? '';

if (empty($job_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'job_id requerido']);
    exit();
}

$uploadDir = dirname(__DIR__) . '/temp_uploads/';
$resultPath = $uploadDir . $job_id . '_result.json';
$metaPath = $uploadDir . $job_id . '_meta.json';

// Verificar si ya hay resultado
if (file_exists($resultPath)) {
    $result = json_decode(file_get_contents($resultPath), true);
    
    // Si está completado o con error, limpiar archivos
    if (in_array($result['status'], ['completed', 'error'])) {
        $pdfPath = $uploadDir . $job_id . '.pdf';
        @unlink($pdfPath);
        @unlink($metaPath);
        @unlink($resultPath);
    }
    
    echo json_encode($result);
    exit();
}

// Si no hay resultado, verificar metadata
if (file_exists($metaPath)) {
    $metadata = json_decode(file_get_contents($metaPath), true);
    
    // Verificar timeout (15 minutos)
    $elapsedTime = time() - ($metadata['created_at'] ?? 0);
    if ($elapsedTime > 900) {
        echo json_encode([
            'status' => 'timeout',
            'message' => 'El procesamiento excedió el tiempo máximo (15 min)',
            'elapsed_seconds' => $elapsedTime
        ]);
        exit();
    }
    
    echo json_encode([
        'status' => $metadata['status'] ?? 'processing',
        'message' => 'Procesando archivo...',
        'elapsed_seconds' => $elapsedTime
    ]);
    exit();
}

// Si no existe nada
http_response_code(404);
echo json_encode([
    'status' => 'not_found',
    'error' => 'Job no encontrado'
]);
?>
