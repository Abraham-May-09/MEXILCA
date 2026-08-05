<?php
$job_id = $_GET['job_id'] ?? 'pdf_68fba97d3af4e3.98514961';
$uploadDir = __DIR__ . '/temp_uploads/';
$resultFile = $uploadDir . $job_id . '_result.json';

echo "<h2>Resultado del Job: $job_id</h2>";

if (file_exists($resultFile)) {
    $result = json_decode(file_get_contents($resultFile), true);
    
    echo "<h3>Estado:</h3>";
    echo "<pre style='background:#e8f5e9; padding:15px; border-left:4px solid #4caf50;'>";
    echo "Status: " . ($result['status'] ?? 'desconocido') . "\n";
    echo "Timestamp: " . date('Y-m-d H:i:s', $result['timestamp'] ?? 0) . "\n";
    
    if (isset($result['http_code'])) {
        echo "HTTP Code: " . $result['http_code'] . "\n";
    }
    
    if (isset($result['error'])) {
        echo "\nError: " . $result['error'] . "\n";
    }
    echo "</pre>";
    
    echo "<h3>Respuesta de n8n:</h3>";
    if (isset($result['response'])) {
        echo "<pre style='background:#f5f5f5; padding:15px; max-height:400px; overflow:auto;'>";
        echo htmlspecialchars($result['response']);
        echo "</pre>";
    } else {
        echo "<em>No hay respuesta</em>";
    }
    
    echo "<h3>Resultado completo (JSON):</h3>";
    echo "<pre style='background:#fff3e0; padding:15px; max-height:300px; overflow:auto;'>";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    
} else {
    echo "<p style='color:red;'>❌ No existe archivo de resultado para este job</p>";
    echo "<p>Ruta buscada: $resultFile</p>";
}

echo "<hr>";
echo "<h3>Probar con otro job_id:</h3>";
echo "<form method='get'>";
echo "<input type='text' name='job_id' value='$job_id' size='40' />";
echo "<button type='submit'>Ver resultado</button>";
echo "</form>";

echo "<p><a href='view_log.php'>← Volver al diagnóstico</a></p>";
?>
