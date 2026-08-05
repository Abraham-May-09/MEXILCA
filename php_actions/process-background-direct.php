<?php
// php_actions/process-background-direct.php

// Configurar logging personalizado
$uploadDir = dirname(__DIR__) . '/temp_uploads/';
$logFile = $uploadDir . 'process_errors.log';

function log_error($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Capturar errores fatales
register_shutdown_function(function() use ($logFile) {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        log_error("ERROR FATAL: " . json_encode($error), $logFile);
    }
});

// Activar error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logFile);
error_reporting(E_ALL);

// --- OBTENER JOB_ID ---
$job_id = $_GET['job_id'] ?? null;

if (empty($job_id)) {
    log_error("ERROR: job_id no recibido", $logFile);
    echo json_encode(['success' => false, 'error' => 'job_id requerido']);
    exit();
}

log_error("=== Iniciando procesamiento para job_id: $job_id ===", $logFile);

$pdfPath = $uploadDir . $job_id . '.pdf';
$metaPath = $uploadDir . $job_id . '_meta.json';
$resultPath = $uploadDir . $job_id . '_result.json';

// --- VERIFICAR ARCHIVOS ---
if (!file_exists($pdfPath) || !file_exists($metaPath)) {
    log_error("ERROR: Archivos no encontrados para job $job_id", $logFile);
    file_put_contents($resultPath, json_encode([
        'status' => 'error',
        'error' => 'Archivos no encontrados',
        'timestamp' => time()
    ]));
    exit();
}

// ── IDEMPOTENCIA: abortar si ya fue enviado ──────────────────
$metadata = json_decode(file_get_contents($metaPath), true);
if (in_array($metadata['status'] ?? '', ['processing', 'sent_to_n8n', 'completed'])) {
    log_error("⚠️ Job $job_id ya fue enviado (status: {$metadata['status']}). Ignorando duplicado.", $logFile);
    exit();
}
// ────────────────────────────────────────────────────────────

// Marcar como processing ANTES de llamar a n8n (lock optimista)
$metadata['status']     = 'processing';
$metadata['started_at'] = time();
file_put_contents($metaPath, json_encode($metadata));
log_error("Metadata actualizada a 'processing'", $logFile);

try {
    // --- PREPARAR DATOS ---
    $ch = curl_init();
    $cfile = new CURLFile(
        $pdfPath,
        'application/pdf',
        $metadata['filename']
    );

    $postData = [
        'respuesta' => $metadata['respuesta'],
        'pdf' => $cfile
    ];

    // --- URL de n8n (SIN CORCHETES) ---
    $n8n_webhook_url = 'http://44.220.84.111:5678/webhook/82921b6e-dc98-46b7-a6d6-d241d0512deb?job_id=' . urlencode($job_id);
    
    log_error("Enviando a n8n: $n8n_webhook_url", $logFile);

    curl_setopt_array($ch, [
    CURLOPT_URL            => $n8n_webhook_url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => false,   // ← no esperar respuesta
    CURLOPT_TIMEOUT        => 30,       // ← solo confirmar entrega
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_HTTPHEADER     => ['Cache-Control: no-cache']
    ]);
    
    curl_exec($ch);
    // El timeout aquí es ESPERADO y correcto — n8n sigue trabajando en background
    curl_close($ch);
    
    $metadata['status'] = 'sent_to_n8n';
    file_put_contents($metaPath, json_encode($metadata));
    log_error("Enviado. n8n llamará al callback al terminar.", $logFile);

    // --- EJECUTAR Y CAPTURAR RESPUESTA ---
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);

    // Log detallado de cURL
    $curlLogPath = $uploadDir . $job_id . '_curl.log';
    file_put_contents($curlLogPath, json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => $n8n_webhook_url,
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => $response,
        'curl_info' => $curlInfo
    ], JSON_PRETTY_PRINT));

    log_error("Respuesta de n8n - HTTP: $httpCode, Error: " . ($curlError ?: 'ninguno'), $logFile);

    curl_close($ch);

    // --- VERIFICAR ERRORES DE CONEXIÓN ---
    if ($curlError || $httpCode >= 400) {
        $errorMsg = $curlError ?: "n8n respondió con código $httpCode";
        log_error("ERROR en conexión a n8n: $errorMsg", $logFile);
        
        file_put_contents($resultPath, json_encode([
            'status' => 'error',
            'error' => 'Error al conectar con n8n: ' . $errorMsg,
            'n8n_response' => $response,
            'http_code' => $httpCode,
            'timestamp' => time()
        ]));
        
        $metadata['status'] = 'error';
        file_put_contents($metaPath, json_encode($metadata));
        exit();
    }

    // --- ÉXITO: n8n procesará y llamará al callback ---
    log_error("Enviado exitosamente a n8n. Esperando callback...", $logFile);
    $metadata['status'] = 'sent_to_n8n';
    $metadata['http_code'] = $httpCode;
    file_put_contents($metaPath, json_encode($metadata));

} catch (Exception $e) {
    log_error("EXCEPCIÓN: " . $e->getMessage(), $logFile);
    file_put_contents($resultPath, json_encode([
        'status' => 'error',
        'error' => 'Excepción: ' . $e->getMessage(),
        'timestamp' => time()
    ]));
}
?>


