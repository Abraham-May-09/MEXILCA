<?php
echo "<h2>Prueba directa con n8n</h2>";

// URL del webhook de n8n (la que te dio tu colega)
$n8n_webhook_url = 'http://3.17.144.50:5678/webhook/formulario-pdf';

// Crear un PDF de prueba pequeño
$testPdfContent = "%PDF-1.4\nTest PDF";
$tempPdf = tempnam(sys_get_temp_dir(), 'test_') . '.pdf';
file_put_contents($tempPdf, $testPdfContent);

echo "<p><strong>URL n8n:</strong> $n8n_webhook_url</p>";
echo "<p><strong>Enviando petición...</strong></p>";

$ch = curl_init();

$cfile = new CURLFile($tempPdf, 'application/pdf', 'test.pdf');
$postData = [
    'pdf' => $cfile,
    'respuesta' => 'Sí'
];

curl_setopt_array($ch, [
    CURLOPT_URL => $n8n_webhook_url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_VERBOSE => true,
    CURLOPT_HEADER => true  // Incluir headers en la respuesta
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$info = curl_getinfo($ch);

curl_close($ch);
unlink($tempPdf);

echo "<h3>Resultado:</h3>";
echo "<pre style='background:#f5f5f5; padding:15px;'>";
echo "HTTP Code: $httpCode\n";
echo "Curl Error: " . ($curlError ?: 'Ninguno') . "\n";
echo "Content Type: " . $info['content_type'] . "\n";
echo "Total Time: " . $info['total_time'] . " segundos\n";
echo "\n--- RESPUESTA COMPLETA ---\n";
echo htmlspecialchars($response);
echo "</pre>";

echo "<h3>Análisis:</h3>";
if ($httpCode == 200) {
    echo "<p style='color:green;'>✅ n8n respondió correctamente</p>";
    if (empty($response)) {
        echo "<p style='color:orange;'>⚠️ Pero la respuesta está vacía</p>";
        echo "<p><strong>Solución:</strong> Tu colega debe configurar el webhook de n8n para que devuelva datos usando el nodo 'Respond to Webhook'</p>";
    } else {
        echo "<p style='color:green;'>✅ n8n está devolviendo datos</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Error al conectar con n8n</p>";
}
?>
