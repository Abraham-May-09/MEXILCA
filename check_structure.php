<?php
// check_structure.php
echo "<h1>Estructura de vendor/phpoffice/phpspreadsheet/</h1>";

function listDirectory($path, $prefix = '') {
    if (!is_dir($path)) {
        echo "<p style='color:red;'>❌ La carpeta no existe: $path</p>";
        return;
    }
    
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $fullPath = $path . '/' . $item;
        if (is_dir($fullPath)) {
            echo $prefix . "📁 <strong>$item/</strong><br>";
            if (in_array($item, ['src', 'PhpSpreadsheet', 'Spreadsheet'])) {
                listDirectory($fullPath, $prefix . '&nbsp;&nbsp;&nbsp;&nbsp;');
            }
        } else {
            echo $prefix . "📄 $item<br>";
        }
    }
}

echo "<h2>Estructura de vendor/phpoffice/:</h2>";
listDirectory(__DIR__ . '/vendor/phpoffice');

echo "<hr>";
echo "<h2>¿Dónde está Spreadsheet.php?</h2>";

$possiblePaths = [
    __DIR__ . '/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
    __DIR__ . '/vendor/phpoffice/phpspreadsheet/PhpSpreadsheet/Spreadsheet.php',
    __DIR__ . '/vendor/phpoffice/phpspreadsheet/Spreadsheet.php',
    __DIR__ . '/vendor/phpoffice/phpspreadsheet/src/Spreadsheet.php',
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        echo "<p style='color:green;'>✅ ENCONTRADO: $path</p>";
    } else {
        echo "<p style='color:red;'>❌ No existe: $path</p>";
    }
}
?>
