<?php
declare(strict_types=1);
session_start();

/**
 * run_import.php — UI mínima y protegida para lanzar el import por web.
 */

// CONFIG 
$BASE_DATASETS_DIR = '/home/u303404040/domains/ciclodevida.mx/Data_datasetsJSON';  
$ENGINE = __DIR__ . '/import_engine_V1.php';


// CSRF token
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function listDatasets(string $base): array {
  $out = [];
  if (!is_dir($base)) return $out;
  $dh = opendir($base);
  if ($dh === false) return $out;
  while (($f = readdir($dh)) !== false) {
    if ($f === '.' || $f === '..') continue;
    $path = $base . DIRECTORY_SEPARATOR . $f;
    if (is_dir($path) && preg_match('/^[A-Za-z0-9_\-]+$/', $f)) {
      $out[] = $f;
    }
  }
  closedir($dh);
  sort($out, SORT_NATURAL | SORT_FLAG_CASE);
  return $out;
}

function fail(int $code, string $msg) {
  http_response_code($code);
  echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    fail(403, 'CSRF token inválido.');
  }
  $dataset = $_POST['dataset'] ?? '';
  if (!preg_match('/^[A-Za-z0-9_\-]+$/', $dataset)) {
    fail(400, 'Nombre de dataset inválido.');
  }

  $baseReal = realpath($BASE_DATASETS_DIR);
  if ($baseReal === false) {
    fail(500, 'Directorio base de datasets no existe.');
  }

  $requested = $BASE_DATASETS_DIR . DIRECTORY_SEPARATOR . $dataset;
  $datasetReal = realpath($requested);
  if ($datasetReal === false || strpos($datasetReal, $baseReal) !== 0) {
    fail(404, 'Dataset no encontrado.');
  }


  $_GET['dataset_dir'] = $datasetReal;
  // Opcional: mostrar salida detallada del engine
  // $_GET['debug'] = '1';

  // Ejecutar el import dentro del mismo proceso para ver resultados en la página
  require $ENGINE;
  exit;
}

// GET — pintar formulario
$datasets = listDatasets($BASE_DATASETS_DIR);
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <meta name="referrer" content="no-referrer">
  <title>Importar Dataset (JSON)</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 20px; }
    form { display: inline-block; padding: 16px; border: 1px solid #ddd; border-radius: 10px; }
    label { display:block; margin-bottom: 8px; font-weight: 600; }
    select, button { padding: 8px 10px; font-size: 14px; }
    .note { color:#444; font-size: 13px; margin-top: 10px; }
  </style>
</head>
<body>
  <h1>Importar Dataset</h1>
  <form method="post" autocomplete="off">
    <label for="dataset">Selecciona el dataset (carpeta en Data_datasetsJSON):</label>
    <select id="dataset" name="dataset" required>
      <?php foreach ($datasets as $d): ?>
        <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="note">Leyendo desde: <?= htmlspecialchars($BASE_DATASETS_DIR, ENT_QUOTES, 'UTF-8') ?></div>
    <p style="margin-top:12px;">
      <button type="submit">Importar</button>
    </p>
  </form>
  <p class="note">Protege este directorio con <code>.htaccess</code> (BasicAuth) o valida sesión admin.</p>
</body>
</html>
