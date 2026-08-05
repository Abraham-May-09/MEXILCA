<?php
/**
 * Recibe el resultado de n8n cuando termina el procesamiento
 * Este archivo es llamado por n8n vía HTTP Request
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$logFile = _DIR_ . '/../temp_uploads/n8n_callbacks.log';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Solo POST']);
    exit();
}

try {
    // Leer body
    $rawInput = file_get_contents('php://input');
    
    // Log detallado
    $logEntry = sprintf(
        "\n=== [%s] ===\n%s\n%s\n",
        date('Y-m-d H:i:s'),
        "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        "Body: " . $rawInput
    );
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Decodificar JSON
    $data = json_decode($rawInput, true);
    
    if ($data === null) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    // Extraer job_id
    $job_id = $data['job_id'] ?? null;
    
    if (!$job_id) {
        throw new Exception('job_id no encontrado en payload');
    }
    
    file_put_contents($logFile, "Job ID: $job_id\n", FILE_APPEND);
    
    // Validar formato
    if (!preg_match('/^pdf_[a-f0-9.]+$/', $job_id)) {
        throw new Exception('Formato de job_id inválido');
    }
    
    $uploadDir = _DIR_ . '/../temp_uploads/';
    
    // Verificar que existe
    if (!is_dir($uploadDir)) {
        throw new Exception('Directorio temp_uploads no existe');
    }
    
    // Preparar resultado
    $resultData = [
        'status' => 'completed',
        'response_data' => $data['result'] ?? $data,
        'response' => json_encode($data['result'] ?? $data),
        'http_code' => 200,
        'timestamp' => time(),
        'received_from_n8n' => true
    ];
    
    // Guardar
    $resultPath = $uploadDir . $job_id . '_result.json';
    $bytesWritten = file_put_contents(
        $resultPath,
        json_encode($resultData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    
    if ($bytesWritten === false) {
        throw new Exception('No se pudo escribir archivo de resultado');
    }
    
    file_put_contents($logFile, "✓ Resultado guardado: $resultPath ($bytesWritten bytes)\n\n", FILE_APPEND);
    
    // Responder a n8n
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Resultado guardado correctamente',
        'job_id' => $job_id,
        'bytes' => $bytesWritten
    ]);
    
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    @file_put_contents($logFile, "ERROR: $errorMsg\n\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errorMsg
    ]);
}
?>