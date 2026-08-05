<?php
// Script de procesamiento en segundo plano
@ini_set('max_execution_time', '0');
@set_time_limit(0);
ignore_user_abort(true);

// Obtener job_id
$job_id = $_GET['job_id'] ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['error' => 'job_id requerido']);
    exit();
}

$uploadDir = dirname(__DIR__) . '/temp_uploads/';
$pdfPath = $uploadDir . $job_id . '.pdf';
$metaPath = $uploadDir . $job_id . '_meta.json';
$resultPath = $uploadDir . $job_id . '_result.json';

// Verificar archivos
if (!file_exists($pdfPath) || !file_exists($metaPath)) {
    file_put_contents($resultPath, json_encode([
        'status' => 'error',
        'error' => 'Archivos no encontrados',
        'timestamp' => time()
    ]));
    exit();
}

// Leer metadata
$metadata = json_decode(file_get_contents($metaPath), true);

// Actualizar estado
$metadata['status'] = 'processing';
$metadata['started_at'] = time();
file_put_contents($metaPath, json_encode($metadata));

try {
    // Preparar cURL con configuración mejorada
    $ch = curl_init();
    
    $cfile = new CURLFile(
        $pdfPath,
        'application/pdf',
        $metadata['filename']
    );
    
    $postData = [
        'pdf' => $cfile,
        'respuesta' => $metadata['respuesta']
    ];

    // URL de n8n (actualiza con la URL real)
    $n8n_webhook_url = 'http://3.17.144.50:5678/webhook/formulario-pdf';

    curl_setopt_array($ch, [
        CURLOPT_URL => $n8n_webhook_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 0, // Sin límite de tiempo
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Cache-Control: no-cache'
        ]
    ]);

    // Ejecutar
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    curl_close($ch);

    // Guardar resultado
    if ($curlErrno) {
        $result = [
            'status' => 'error',
            'error' => 'Error de conexión con n8n: ' . $curlError,
            'error_code' => $curlErrno,
            'timestamp' => time()
        ];
    } elseif ($httpCode >= 400) {
        $result = [
            'status' => 'error',
            'error' => 'Error HTTP ' . $httpCode,
            'response' => $response ? substr($response, 0, 500) : 'Sin respuesta',
            'timestamp' => time()
        ];
    } else {
        // ÉXITO - Verificar si hay respuesta
        $responseData = null;
        
        // Intentar decodificar como JSON
        if (!empty($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responseData = $decoded;
            } else {
                $responseData = $response;
            }
        }
        
        $result = [
            'status' => 'completed',
            'response' => $response,
            'response_data' => $responseData,
            'response_length' => strlen($response),
            'http_code' => $httpCode,
            'timestamp' => time()
        ];
        
        if (empty($response)) {
            $result['note'] = 'n8n devolvió HTTP 200 pero sin contenido. Verifica configuración del webhook.';
        }
    }

    // Guardar resultado
    file_put_contents($resultPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

} catch (Exception $e) {
    $result = [
        'status' => 'error',
        'error' => 'Excepción: ' . $e->getMessage(),
        'timestamp' => time()
    ];
    file_put_contents($resultPath, json_encode($result));
}

echo json_encode(['success' => true, 'message' => 'Processing completed']);
?>
