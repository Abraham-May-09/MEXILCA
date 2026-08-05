<?php
$autoloadContent = <<<'AUTOLOAD'
<?php
// vendor/autoload.php - Autoloader corregido

spl_autoload_register(function ($class) {
    // PhpOffice\PhpSpreadsheet
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet\\') === 0) {
        $file = __DIR__ . '/phpoffice/phpspreadsheet/src/' . str_replace('\\', '/', substr($class, 24)) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
    
    // Psr\SimpleCache
    if (strpos($class, 'Psr\\SimpleCache\\') === 0) {
        $file = __DIR__ . '/psr/simple-cache/' . str_replace('\\', '/', substr($class, 17)) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
    
    return false;
});

// Cargar dependencias críticas manualmente
$criticalFiles = [
    __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
    __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Worksheet/Worksheet.php',
    __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/Cell.php',
    __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php',
    __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/BaseWriter.php',
    __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php',
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}
AUTOLOAD;

file_put_contents(__DIR__ . '/vendor/autoload.php', $autoloadContent);

echo "<h1>✅ autoload.php actualizado correctamente</h1>";
echo "<p>Ahora prueba: <a href='test_vendor.php'>test_vendor.php</a></p>";
?>
