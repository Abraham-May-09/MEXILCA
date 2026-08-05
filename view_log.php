<?php
echo "<h2>Diagnóstico completo</h2>";

$uploadDir = __DIR__ . '/temp_uploads/';

// 1. Verificar carpeta
echo "<h3>1. Carpeta temp_uploads:</h3>";
if (file_exists($uploadDir)) {
    echo "✅ Existe<br>";
    echo "✅ Ruta: $uploadDir<br>";
    if (is_writable($uploadDir)) {
        echo "✅ Tiene permisos de escritura<br>";
    } else {
        echo "❌ NO tiene permisos de escritura<br>";
    }
} else {
    echo "❌ NO existe<br>";
}

// 2. Archivos en temp_uploads
echo "<hr><h3>2. Archivos en temp_uploads:</h3>";
$files = glob($uploadDir . '*');
if (empty($files)) {
    echo "<em>No hay archivos (la carpeta está vacía)</em><br>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th></tr>";
    foreach ($files as $file) {
        $name = basename($file);
        $size = filesize($file);
        $date = date('Y-m-d H:i:s', filemtime($file));
        echo "<tr><td>$name</td><td>$size bytes</td><td>$date</td></tr>";
    }
    echo "</table>";
}

// 3. Verificar archivos PHP
echo "<hr><h3>3. Archivos PHP necesarios:</h3>";
$phpFiles = [
    'php_actions/proxy-async.php',
    'php_actions/process-background-direct.php',
    'php_actions/check_status.php'
];

echo "<ul>";
foreach ($phpFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "<li>✅ $file</li>";
    } else {
        echo "<li>❌ $file (NO EXISTE)</li>";
    }
}
echo "</ul>";

// 4. Buscar archivos de jobs recientes
echo "<hr><h3>4. Jobs recientes (últimos PDFs subidos):</h3>";
$pdfs = glob($uploadDir . 'pdf_*.pdf');
if (empty($pdfs)) {
    echo "<em>No se han subido PDFs todavía</em><br>";
} else {
    usort($pdfs, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    echo "<strong>Últimos 5 PDFs:</strong><br>";
    $count = 0;
    foreach ($pdfs as $pdf) {
        if ($count >= 5) break;
        $name = basename($pdf);
        $job_id = str_replace('.pdf', '', $name);
        $date = date('Y-m-d H:i:s', filemtime($pdf));
        
        // Verificar archivos relacionados
        $metaExists = file_exists($uploadDir . $job_id . '_meta.json') ? '✅' : '❌';
        $resultExists = file_exists($uploadDir . $job_id . '_result.json') ? '✅' : '❌';
        
        echo "<div style='background:#f5f5f5; padding:10px; margin:5px 0;'>";
        echo "<strong>Job ID:</strong> $job_id<br>";
        echo "<strong>Fecha:</strong> $date<br>";
        echo "<strong>Meta:</strong> $metaExists | <strong>Result:</strong> $resultExists<br>";
        
        // Si existe result, mostrarlo
        if ($resultExists) {
            $resultFile = $uploadDir . $job_id . '_result.json';
            $result = json_decode(file_get_contents($resultFile), true);
            echo "<strong>Estado:</strong> " . ($result['status'] ?? 'desconocido') . "<br>";
            if (isset($result['error'])) {
                echo "<strong>Error:</strong> " . htmlspecialchars($result['error']) . "<br>";
            }
        }
        echo "</div>";
        
        $count++;
    }
}

echo "<hr><p><a href='import.php'>← Volver a import.php</a></p>";
?>
