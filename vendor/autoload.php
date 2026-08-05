<?php
// vendor/autoload.php - Con ZipStream agregado

// PSR SimpleCache
if (!interface_exists('Psr\SimpleCache\CacheInterface', false)) {
    eval('
    namespace Psr\SimpleCache {
        interface CacheInterface {
            public function get($key, $default = null);
            public function set($key, $value, $ttl = null);
            public function delete($key);
            public function clear();
            public function getMultiple($keys, $default = null);
            public function setMultiple($values, $ttl = null);
            public function deleteMultiple($keys);
            public function has($key);
        }
        class CacheException extends \Exception {}
        class InvalidArgumentException extends \Exception {}
    }
    ');
}

// Autoloader
spl_autoload_register(function ($class) {
    // PhpOffice\PhpSpreadsheet
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet\\') === 0) {
        $path = str_replace('\\', '/', substr($class, 10));
        $file = __DIR__ . '/phpoffice/phpspreadsheet/' . $path . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    // ZipStream
    if (strpos($class, 'ZipStream\\') === 0) {
        $path = str_replace('\\', '/', substr($class, 10));
        $file = __DIR__ . '/maennchen/zipstream-php/src/' . $path . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    return false;
});

// Pre-cargar clases críticas
$baseDir = __DIR__ . '/phpoffice/phpspreadsheet/PhpSpreadsheet';

$criticalFiles = [
    $baseDir . '/Spreadsheet.php',
    $baseDir . '/Worksheet/Worksheet.php',
    $baseDir . '/Cell/Cell.php',
    $baseDir . '/Cell/Coordinate.php',
    $baseDir . '/Cell/DataType.php',
    $baseDir . '/Writer/Xlsx.php',
    $baseDir . '/Writer/BaseWriter.php',
    $baseDir . '/Writer/IWriter.php',
    $baseDir . '/IOFactory.php',
    $baseDir . '/Style/Style.php',
    $baseDir . '/Style/Font.php',
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}