<?php
// Este script se ejecuta en segundo plano SIN límite de tiempo
@ini_set('max_execution_time', '0'); // Sin límite
@set_time_limit(0);

// Obtener job_id del argumento
$job_id = $argv[1] ?? null;

if (!$job_id) {
    exit("Error: job_id no proporcionado\n");
}

$uploadDir = dirname(__DIR__) . '/temp_uploads/';
$pdfPath = $uploadDir . $job_id . '.pdf';
$metaPath = $uploadDir . $job_id . '_meta.json';
$resultPath = $uploadDir . $job_id . '_result.json';

// Verificar que existen los archivos
if (!file_exists($pdfPath) || !file_exists($metaPath)) {
    file_put_contents($resultPath, json_encode([
        'status' => 'error',
        'error' => 'Archivos no encontrados',
        'timestamp' => time()
    ]));
    exit("Error: archivos no encontrados\n");
}

// Leer metadata
$metadata = json_decode(file_get_contents($metaPath), true);

// Actualizar estado a "processing"
$metadata['status'] = 'processing';
$metadata['started_at'] = time();
file_put_contents($metaPath, json_encode($metadata));

try {
    // Preparar datos para n8n
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

    $n8n_webhook_url = 'http://3.17.144.50:5678/webhook/formulario-pdf';

    curl_setopt_array($ch, [
        CURLOPT_URL => $n8n_webhook_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 900, // 15 minutos máximo
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    // Ejecutar solicitud a n8n
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    curl_close($ch);

    // Guardar resultado
    if ($curlErrno) {
        // Error de cURL
        $result = [
            'status' => 'error',
            'error' => 'Error de conexión con n8n: ' . $curlError,
            'error_code' => $curlErrno,
            'timestamp' => time()
        ];
    } elseif ($httpCode >= 400) {
        // Error HTTP
        $result = [
            'status' => 'error',
            'error' => 'Error HTTP ' . $httpCode,
            'response' => substr($response, 0, 500),
            'timestamp' => time()
        ];
    } else {
        // Éxito
        $result = [
            'status' => 'completed',
            'response' => $response,
            'http_code' => $httpCode,
            'timestamp' => time()
        ];
    }

    // Guardar resultado
    file_put_contents($resultPath, json_encode($result));

    // Limpiar archivos temporales (después de 1 hora)
    // Descomenta estas líneas si quieres limpiar inmediatamente
    // @unlink($pdfPath);
    // @unlink($metaPath);

} catch (Exception $e) {
    // Error no controlado
    $result = [
        'status' => 'error',
        'error' => 'Excepción: ' . $e->getMessage(),
        'timestamp' => time()
    ];
    file_put_contents($resultPath, json_encode($result));
}
?>
