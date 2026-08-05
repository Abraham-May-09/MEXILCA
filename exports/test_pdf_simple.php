<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. Verificando ruta TCPDF...<br>";
$tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
echo "Ruta: $tcpdf_path<br>";

if (!file_exists($tcpdf_path)) {
    die("❌ ERROR: No se encuentra tcpdf.php en: $tcpdf_path");
}
echo "✅ tcpdf.php encontrado<br><br>";

echo "2. Cargando TCPDF...<br>";
require_once($tcpdf_path);
echo "✅ TCPDF cargado correctamente<br><br>";

echo "3. Creando PDF de prueba...<br>";
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, '¡TCPDF funciona correctamente!', 0, 1);
echo "✅ PDF creado<br><br>";

echo "4. Enviando PDF al navegador...<br>";
$pdf->Output('test.pdf', 'I');
?>
