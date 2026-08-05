<?php
// debug_jobs.php - Para monitorear todos los jobs
$uploadDir = __DIR__ . '/temp_uploads/';

// Verificar si existe la carpeta
if (!is_dir($uploadDir)) {
    die("La carpeta temp_uploads no existe");
}

$files = scandir($uploadDir);

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<meta charset='utf-8'>";
echo "<title>Monitor de Jobs</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
    h2 { color: #4ec9b0; }
    .job { background: #252526; padding: 15px; margin: 10px 0; border-left: 4px solid #007acc; }
    .error { border-left-color: #f48771; }
    .completed { border-left-color: #4ec9b0; }
    .processing { border-left-color: #dcdcaa; }
    pre { background: #1e1e1e; padding: 10px; overflow-x: auto; border: 1px solid #3e3e42; }
    button { background: #007acc; color: white; border: none; padding: 10px 20px; cursor: pointer; margin: 10px 5px 10px 0; }
    button:hover { background: #005a9e; }
</style>";
echo "<script>
    function refreshPage() {
        location.reload();
    }
    setInterval(refreshPage, 5000); // Auto-refresh cada 5 segundos
</script>";
echo "</head><body>";

echo "<h1>🔍 Monitor de Jobs en Tiempo Real</h1>";
echo "<button onclick='refreshPage()'>Actualizar Ahora</button>";
echo "<p>Auto-actualización cada 5 segundos</p>";
echo "<hr>";

$jobsData = [];

// Agrupar archivos por job_id
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    $job_id = preg_replace('/(\.pdf|_meta\.json|_result\.json|_curl\.log|_debug\.log)$/', '', $file);
    
    if (!isset($jobsData[$job_id])) {
        $jobsData[$job_id] = [];
    }
    
    if (strpos($file, '_result.json') !== false) {
        $jobsData[$job_id]['result'] = json_decode(file_get_contents($uploadDir . $file), true);
    } elseif (strpos($file, '_meta.json') !== false) {
        $jobsData[$job_id]['meta'] = json_decode(file_get_contents($uploadDir . $file), true);
    } elseif (strpos($file, '_curl.log') !== false) {
        $jobsData[$job_id]['curl'] = json_decode(file_get_contents($uploadDir . $file), true);
    } elseif (strpos($file, '.pdf') !== false) {
        $jobsData[$job_id]['pdf'] = $file;
    }
}

// Mostrar jobs
foreach ($jobsData as $job_id => $data) {
    $status = $data['result']['status'] ?? $data['meta']['status'] ?? 'unknown';
    $cssClass = "job $status";
    
    echo "<div class='$cssClass'>";
    echo "<h2>Job ID: $job_id</h2>";
    
    if (isset($data['meta'])) {
        $elapsed = time() - ($data['meta']['created_at'] ?? 0);
        echo "<p><strong>Estado:</strong> " . ($data['meta']['status'] ?? 'desconocido') . "</p>";
        echo "<p><strong>Tiempo transcurrido:</strong> $elapsed segundos</p>";
        echo "<p><strong>Archivo:</strong> " . ($data['meta']['filename'] ?? 'N/A') . "</p>";
    }
    
    if (isset($data['result'])) {
        echo "<h3>📋 Resultado:</h3>";
        echo "<pre>" . json_encode($data['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    if (isset($data['curl'])) {
        echo "<h3>🌐 Log de cURL:</h3>";
        echo "<pre>" . json_encode($data['curl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    echo "</div>";
}

// Mostrar logs de errores
if (file_exists($uploadDir . 'process_errors.log')) {
    echo "<div class='job'>";
    echo "<h2>📝 Log de Procesamiento</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($uploadDir . 'process_errors.log')) . "</pre>";
    echo "</div>";
}

if (file_exists($uploadDir . 'callback_errors.log')) {
    echo "<div class='job'>";
    echo "<h2>📞 Log de Callbacks</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($uploadDir . 'callback_errors.log')) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>
