<?php
// php_actions/n8n-callback.php
header('Content-Type: application/json');

$uploadDir = dirname(__DIR__) . '/temp_uploads/';
$logFile   = $uploadDir . 'callback_errors.log';

function log_cb($msg, $f) {
    file_put_contents($f, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ── Autenticación ────────────────────────────────────────────
define('N8N_SECRET_KEY', 'K9mX2pL7qR4wE8nT5yH6uJ3vB1zA0cF');
if (($_SERVER['HTTP_X_N8N_AUTH'] ?? '') !== N8N_SECRET_KEY) {
    log_cb("AUTH FALLIDA", $logFile);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Acceso denegado']);
    exit();
}

// ── job_id ───────────────────────────────────────────────────
$job_id = $_GET['job_id'] ?? null;
if (empty($job_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'job_id requerido']);
    exit();
}

log_cb("Callback recibido para job: $job_id", $logFile);

// ── Leer body ────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

log_cb("Body length: " . strlen($raw), $logFile);
log_cb("Body preview: " . substr($raw, 0, 300), $logFile);

// ── Detectar error en el payload de n8n ─────────────────────
$status    = 'completed';
$error_msg = null;

if (isset($data['error']) || isset($data['errorMessage'])) {
    $status    = 'error';
    $error_msg = $data['error'] ?? $data['errorMessage'] ?? 'Error desconocido';
    log_cb("Error detectado en payload: $error_msg", $logFile);
}

// ── Escribir resultado (sin depender de _meta.json) ──────────
$resultPath = $uploadDir . $job_id . '_result.json';

$final = [
    'status'        => $status,
    'error'         => $error_msg,
    'response_data' => $data,
    'timestamp'     => time()
];

if (file_put_contents($resultPath, json_encode($final, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    // Actualizar meta si existe (opcional, no bloqueante)
    $metaPath = $uploadDir . $job_id . '_meta.json';
    if (file_exists($metaPath)) {
        $meta             = json_decode(file_get_contents($metaPath), true) ?? [];
        $meta['status']   = $status;
        $meta['done_at']  = time();
        file_put_contents($metaPath, json_encode($meta));
    }

    log_cb("Resultado guardado: $resultPath", $logFile);
    http_response_code(200);
    echo json_encode(['status' => 'success', 'job_id' => $job_id]);
} else {
    log_cb("No se pudo escribir: $resultPath", $logFile);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'No se pudo guardar el resultado']);
}
?>