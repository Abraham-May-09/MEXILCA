<?php
// Seguridad y logging
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// Cargar config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
  error_log("FATAL: config.php no encontrado: $configPath");
  http_response_code(500);
  exit("Error de configuración.");
}
$config = require $configPath;
if (!is_array($config)) {
  error_log("FATAL: config.php inválido");
  http_response_code(500);
  exit("Error de configuración.");
}
foreach (['db_host','db_user','db_pass','db_name'] as $k) {
  if (!isset($config[$k]) || $config[$k] === '') {
    error_log("FATAL: Config faltante/vacía: $k");
    http_response_code(500);
    exit("Error de configuración.");
  }
}

// Conexión
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

// Ruta propia para enlaces
$self = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'];

// Redirección por id -> uuid (opcional)
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  $id = (int)$_GET['id'];
  $q = $mysqli->prepare("SELECT uuid FROM processes WHERE id = ? LIMIT 1");
  $q->bind_param("i", $id);
  $q->execute();
  $found = $q->get_result()->fetch_assoc();
  $q->close();
  if ($found && !empty($found['uuid'])) {
    header("Location: " . $self . "?uuid=" . urlencode($found['uuid']), true, 302);
    exit;
  }
  http_response_code(404);
  exit("No se encontró UUID para id={$id}");
}

// Validar UUID
if (!isset($_GET['uuid'])) {
  http_response_code(400);
  exit('Parámetro ?uuid= faltante en la URL.');
}
$uuid = trim($_GET['uuid']);
if (!preg_match('/^[a-f0-9-]{32,36}$/i', $uuid)) {
  http_response_code(400);
  exit('UUID con formato inválido.');
}
if (strlen($uuid) === 32) {
  $uuid = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20);
}

// Función helper para resolver proceso por flow_uuid
function getProcessByFlow($mysqli, $flow_uuid) {
  if (empty($flow_uuid)) return '';
  $stmt = $mysqli->prepare("
    SELECT p.uuid
    FROM process_outputs o
    JOIN processes p ON p.uuid = o.process_uuid
    WHERE o.flow_uuid = ? AND o.is_reference = 1
    ORDER BY p.version DESC, o.internal_id ASC
    LIMIT 1
  ");
  $stmt->bind_param('s', $flow_uuid);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_row();
  $stmt->close();
  return $row ? $row[0] : '';
}

// Proceso + documentación + ubicación + referencia cuantitativa
$sqlProc = "
SELECT
  p.uuid,
  p.name                         AS process_name,
  p.category                     AS category,
  p.description                  AS general_comment,
  p.tech_desc                    AS technology_description,
  p.geo_desc                     AS geo_description,
  p.process_type                 AS process_type,
  p.time_desc                    AS time_desc,
  p.version                      AS version,
  p.last_change                  AS last_change,
  p.created_at                   AS created_at,
  p.dq_flow_schema               AS flow_diagram,
  l.name                         AS location,
  pd.project                     AS project,
  pd.lci_method                  AS lci_method,
  pd.ds_data_selection           AS ds_data_selection,
  pd.ds_data_treatment           AS ds_data_treatment,
  pd.ds_collection_period        AS data_collection_period,
  pd.ds_data_completeness        AS ds_data_completeness,
  pd.completeness_text           AS completeness_text,
  pd.sources_text                AS sources_text,
  pd.modeling_constants          AS modeling_constants,
  pd.access_use_restrictions     AS access_restrictions,
  pd.creation_date               AS creation_date,
  pd.copyright_flag              AS copyright,
  p.valid_from                   AS valid_from,
  p.valid_until                  AS valid_until,
  CONCAT(ref.amount_str, ' ', ref.unit_name) AS functional_unit
FROM processes p
LEFT JOIN locations l            ON l.uuid = p.location_uuid
LEFT JOIN process_documentation pd ON pd.process_uuid = p.uuid
LEFT JOIN (
  SELECT o.process_uuid AS ref_puuid,
         o.amount       AS amount_str,
         u.name         AS unit_name
  FROM process_outputs o
  LEFT JOIN units u ON u.uuid = o.unit_uuid
  WHERE o.is_reference = 1
  ORDER BY o.internal_id
) ref ON ref.ref_puuid = p.uuid
WHERE p.uuid = ?
LIMIT 1";
$stmt = $mysqli->prepare($sqlProc);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$proceso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proceso) {
  http_response_code(404);
  exit('No se encontró el dataset con ese UUID.');
}

//Data Quality Indicators 
$stmt = $mysqli->prepare("
  SELECT indicator_type, score_level, description, is_selected 
  FROM process_dq_indicators 
  WHERE process_uuid = ? 
  ORDER BY 
    FIELD(indicator_type, 
      'Flow Reliability', 
      'Temporal Correlation', 
      'Geographical Correlation', 
      'Technological Correlation', 
      'Data Collection Methods'
    ),
    score_level ASC
");
$stmt->bind_param('s', $uuid);
$stmt->execute();
$dq_all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// DEBUGGING
error_log("=== DEBUG DATA QUALITY ===");
error_log("UUID: " . $uuid);
error_log("Total registros: " . count($dq_all));
error_log("Datos: " . print_r($dq_all, true));

// Agrupar por indicador
$dq_grouped = [];
foreach ($dq_all as $row) {
  $indicator = $row['indicator_type'];
  if (!isset($dq_grouped[$indicator])) {
    $dq_grouped[$indicator] = [];
  }
  $dq_grouped[$indicator][] = $row;
}

// Mapeo de scores a etiquetas (Pedigree Matrix)
$score_labels = [
  1 => 'Very good',
  2 => 'Good',
  3 => 'Fair',
  4 => 'Poor',
  5 => 'Very poor'
];

//UUIDS RELACIONADAS AL PROCESO PRINCIPAL

// Annual supply or production volume
$annualVolume = null;

// 1) Parámetro por proceso
$stmt = $mysqli->prepare("
  SELECT value
  FROM parameters_process
  WHERE process_uuid = ? AND LOWER(name) IN ('annual_volume','annual volume','annualvolume')
  ORDER BY name
  LIMIT 1
");
$stmt->bind_param('s', $uuid);
$stmt->execute();
$row = $stmt->get_result()->fetch_row();
$annualVolume = $row ? $row[0] : null;
$stmt->close();

// 2) Parámetro global (respaldo)
if ($annualVolume === null) {
  $stmt = $mysqli->prepare("
    SELECT value
    FROM parameters_global
    WHERE LOWER(name) IN ('annual_volume','annual volume','annualvolume')
    ORDER BY name
    LIMIT 1
  ");
  $stmt->execute();
  $row = $stmt->get_result()->fetch_row();
  $annualVolume = $row ? $row[0] : null;
  $stmt->close();
}

// Validation / Independent external review
$reviews = [];
$stmt = $mysqli->prepare("
  SELECT
    s.name AS source_name,
    ps.note AS scope_method,
    s.year_published AS year
  FROM process_sources ps
  JOIN sources s ON s.uuid = ps.source_uuid
  WHERE ps.process_uuid = ?
  ORDER BY s.year_published DESC, s.name ASC
");
$stmt->bind_param('s', $uuid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { $reviews[] = $r; }
$stmt->close();

// Inputs detallados desde process_inputs
$sqlIn = "
SELECT
  pi.internal_id,
  pi.flow_uuid,
  f.name         AS resource_type,
  COALESCE(
    CASE 
      WHEN provider.norma_isic IS NOT NULL AND provider.norma_isic != '' 
      THEN CONCAT(COALESCE(pi.category, ''), ' / ', provider.norma_isic)
      ELSE NULL 
    END,
    pi.category,
    '...'
  ) AS category,
  pi.amount      AS quantity,
  u.name         AS unit,
  fp.name        AS flow_property_name,
  pi.uncertainty_type,
  pi.stat_mean_mode,
  pi.stat_sd_gsd,
  pi.stat_min,
  pi.stat_max,
  pi.price_type,
  pi.price_value,
  c.code         AS currency_code,
  pi.provider_process_uuid,
  pi.provider_name,
  pi.dq_entry_text,
  pi.description AS commentary,
  loc.name       AS location_name
FROM process_inputs pi
LEFT JOIN flows            f        ON f.uuid   = pi.flow_uuid
LEFT JOIN units            u        ON u.uuid   = pi.unit_uuid
LEFT JOIN flow_properties  fp       ON fp.uuid  = pi.flow_property_uuid
LEFT JOIN currencies       c        ON c.uuid   = pi.currency_uuid
LEFT JOIN locations        loc      ON loc.uuid = pi.location_uuid
LEFT JOIN processes        provider ON provider.uuid = pi.provider_process_uuid
WHERE pi.process_uuid = ?
ORDER BY pi.internal_id ASC";
$stmt = $mysqli->prepare($sqlIn);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$inputs = $stmt->get_result();
$stmt->close();

// Outputs detallados desde process_outputs
$sqlOut = "
SELECT
  po.internal_id,
  po.flow_uuid,
  f.name         AS type_of_emission,
  COALESCE(
    CASE 
      WHEN provider.norma_isic IS NOT NULL AND provider.norma_isic != '' 
      THEN CONCAT(COALESCE(po.category, ''), ' / ', provider.norma_isic)
      ELSE NULL 
    END,
    po.category,
    '...'
  ) AS category,
  po.amount      AS quantity,
  u.name         AS unit,
  fp.name        AS flow_property_name,
  po.uncertainty_type,
  po.stat_mean_mode,
  po.stat_sd_gsd,
  po.stat_min,
  po.stat_max,
  po.price_type,
  po.price_value,
  c.code         AS currency_code,
  po.is_reference,
  po.provider_process_uuid,
  po.provider_name,
  po.dq_entry_text,
  po.description AS commentary,
  loc.name       AS location_name
FROM process_outputs po
LEFT JOIN flows            f        ON f.uuid   = po.flow_uuid
LEFT JOIN units            u        ON u.uuid   = po.unit_uuid
LEFT JOIN flow_properties  fp       ON fp.uuid  = po.flow_property_uuid
LEFT JOIN currencies       c        ON c.uuid   = po.currency_uuid
LEFT JOIN locations        loc      ON loc.uuid = po.location_uuid
LEFT JOIN processes        provider ON provider.uuid = po.provider_process_uuid
WHERE po.process_uuid = ?
ORDER BY po.internal_id ASC";
$stmt = $mysqli->prepare($sqlOut);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$outputs = $stmt->get_result();
$stmt->close();

// Exchanges condicional (solo si existe la tabla)
$ex_inputs = $ex_outputs = false;
$hasExchanges = false;
$chk = $mysqli->prepare("
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = ? AND table_name = 'exchanges'
");
$chk->bind_param('s', $config['db_name']);
$chk->execute();
$row = $chk->get_result()->fetch_row();
$hasExchanges = $row ? (bool)$row[0] : false;
$chk->close();

if ($hasExchanges) {
  try {
    $sqlExIn = "
      SELECT
        e.*,
        p.name AS linked_name,
        p.functional_unit,
        CASE
          WHEN e.data_source IS NULL OR e.data_source = '' OR LOWER(e.data_source) = 'link'
            THEN d.sources_text
          ELSE e.data_source
        END AS data_source_resolved
      FROM exchanges e
      LEFT JOIN processes p             ON p.uuid = e.linked_process
      LEFT JOIN process_documentation d ON d.process_uuid = p.uuid
      WHERE e.process_uuid = ? AND e.type_of_exchange = 'input'
      ORDER BY e.id";
    $stmt = $mysqli->prepare($sqlExIn);
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $ex_inputs = $stmt->get_result();
    $stmt->close();

    $sqlExOut = "
      SELECT
        e.*,
        p.name AS linked_name,
        p.functional_unit,
        CASE
          WHEN e.data_source IS NULL OR e.data_source = '' OR LOWER(e.data_source) = 'link'
            THEN d.sources_text
          ELSE e.data_source
        END AS data_source_resolved
      FROM exchanges e
      LEFT JOIN processes p             ON p.uuid = e.linked_process
      LEFT JOIN process_documentation d ON d.process_uuid = p.uuid
      WHERE e.process_uuid = ? AND e.type_of_exchange = 'output'
      ORDER BY e.id";
    $stmt = $mysqli->prepare($sqlExOut);
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $ex_outputs = $stmt->get_result();
    $stmt->close();
  } catch (mysqli_sql_exception $e) {
    error_log("Exchanges deshabilitado temporalmente: ".$e->getMessage());
    $ex_inputs = $ex_outputs = false;
  }
} else {
  error_log("Aviso: 'exchanges' no existe en {$config['db_name']}; se omite sección opcional.");
}

// Cabecera
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Procesos</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="icon" type="image/png" href="icons/file-box.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .accordion-chevron { transition: transform .3s cubic-bezier(.4,0,.2,1);}
    .accordion-open .accordion-chevron { transform: rotate(90deg);}
  </style>
  <script>
    function toggleAccordion(id, btn) {
      const el = document.getElementById(id);
      el.classList.toggle('hidden');
      btn.classList.toggle('accordion-open');
    }
    window.onload = () => { lucide.createIcons(); }
  </script>
</head>
<body class="bg-gray-50 text-gray-800 overflow-y-scroll">
  <!-- Contenido Principal -->
<div class="mt-8 max-w-7xl mx-auto px-4 flex items-center gap-4">
<!-- Botón pequeño y redondeado A LA IZQUIERDA -->
<button onclick="window.history.back()" 
   class="flex-shrink-0 inline-flex items-center justify-center gap-2 w-12 h-12 bg-gradient-to-br from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110 cursor-pointer"
   title="Volver atrás">
  <i data-lucide="arrow-left" class="w-5 h-5"></i>
</button>
<button onclick="window.location.href='conjuntos.php'" class="flex-shrink-0 inline-flex items-center justify-center gap-2 w-12 h-12 bg-gradient-to-br from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110 cursor-pointer" title="Volver a la Base de Datos" >
  <i data-lucide="house" class="w-5 h-5"></i>
</button>

  <!-- Título del DataSet -->
  <div class="flex-1 rounded-xl bg-white border border-gray-200 shadow-md p-8">
    <h1 class="font-extrabold text-3xl md:text-4xl text-center leading-relaxed text-green-700">
      DataSet: <strong class="text-black font-semibold"><?= htmlspecialchars($proceso['process_name'] ?? 'No encontrado', ENT_QUOTES, 'UTF-8') ?></strong>
    </h1>
  </div>
</div>

  <!-- Process Information -->
  <div class="mt-8 w-full max-w-7xl h-full mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablaPrincipal', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Process Information</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400 group-[.accordion-open]:rotate-90"></i>
    </button>
    <div id="tablaPrincipal" class="hidden border-t border-gray-100 bg-white">
      <table class="min-w-full text-sm text-gray-700">
        <tbody>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Key Data Set Information</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium w-1/3 border-r border-gray-100">Location</td>
            <td class="p-4"><?= htmlspecialchars($proceso['location'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Geographical representativeness description</td>
            <td class="p-4"><?= htmlspecialchars($proceso['geo_description'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Reference year</td>
            <td class="p-4"><?php $refYear = $proceso['valid_from'] ?? ($proceso['valid_until'] ?? ($proceso['time_desc'] ?? '...')); echo htmlspecialchars($refYear, ENT_QUOTES, 'UTF-8');?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Name</td>
            <td class="p-4"><?= htmlspecialchars($proceso['process_name'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Classification</td>
            <td class="p-4"><?= htmlspecialchars($proceso['category'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">General comment on data set</td>
            <td class="p-4"><?= htmlspecialchars($proceso['general_comment'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Quantitative Reference</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Reference flow(s)</td>
            <td class="p-4"><?= htmlspecialchars($proceso['functional_unit'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Time representativeness</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data Set Valid Until</td>
            <td class="p-4"><?= htmlspecialchars($proceso['valid_until'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Time representativeness description</td>
            <td class="p-4"><?= htmlspecialchars($proceso['time_desc'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Technological representativeness</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Technology description including background system</td>
            <td class="p-4"><?= htmlspecialchars($proceso['technology_description'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Included Data Sets</td>
            <td class="p-4"><?= htmlspecialchars($proceso['project'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Flow diagram(s) or picture(s)</td>
            <td class="p-4"><?= htmlspecialchars($proceso['flow_diagram'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modelling and Validation -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Modelling and Validation</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400 group-[.accordion-open]:rotate-90"></i>
    </button>
    <div id="tablasecundaria" class="hidden border-t border-gray-100 bg-white">
      <table class="min-w-full text-sm text-gray-700">
        <tbody>
          <tr><td class="bg-white p-4 font-semibold text-green-600 w-1/3"><strong>LCI method and allocation</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Type Of Data Set</td>
            <td class="p-4"><?= htmlspecialchars(($proceso['process_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">LCI Method Principle</td>
            <td class="p-4"><?= htmlspecialchars($proceso['lci_method'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
            <tr class="border-b border-gray-100">
              <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">
                Data sources, treatment and representativeness
              </td>
              <td class="p-4">
                <?= htmlspecialchars(
                  trim(($proceso['sources_text'] ?? '') . (($proceso['ds_data_treatment'] ?? '') ? ' — ' . $proceso['ds_data_treatment'] : '')) ?: '...',
                  ENT_QUOTES, 'UTF-8'
                ) ?>
              </td>
            </tr>          
            <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data cut-off and completeness principles</td>
            <td class="p-4"><?= htmlspecialchars(($proceso['ds_data_completeness'] ?? '').($proceso['completeness_text'] ? ' — '.$proceso['completeness_text'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data selection and combination principles</td>
            <td class="p-4"><?= htmlspecialchars($proceso['ds_data_selection'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data treatment and extrapolations principles</td>
            <td class="p-4"><?= htmlspecialchars($proceso['ds_data_treatment'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Percentage supply or production covered</td>
            <td class="p-4"><?= htmlspecialchars($proceso['modeling_constants'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Annual supply or production volume</td>
            <td class="p-4">
                <?= htmlspecialchars(($annualVolume !== null && $annualVolume !== '') ? $annualVolume : '...', ENT_QUOTES, 'UTF-8') ?>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data collection period</td>
            <td class="p-4"><?= htmlspecialchars($proceso['data_collection_period'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>completeness</strong></td></tr>
          <tr>
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Completeness of product model</td>
            <td class="p-4"><?= htmlspecialchars($proceso['completeness_text'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Administrative Information -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria2', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Administrative Information</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400 group-[.accordion-open]:rotate-90"></i>
    </button>
    <div id="tablasecundaria2" class="hidden border-t border-gray-100 bg-white">
      <table class="min-w-full text-sm text-gray-700">
        <tbody>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Data generator</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data set generator / modeller</td>
            <td class="p-4">
              <?php
                $stmt = $mysqli->prepare("SELECT a.name FROM process_actors pa JOIN actors a ON a.uuid = pa.actor_uuid WHERE pa.process_uuid = ? AND pa.role = 'modeller' LIMIT 1");
                $stmt->bind_param('s', $uuid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_row();
                $modeller = $row ? $row[0] : null;
                $stmt->close();
                echo htmlspecialchars($modeller ?: '...', ENT_QUOTES, 'UTF-8');
              ?>
            </td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Data Entry By</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium">Time Stamp (Last saved)</td>
            <td class="p-4"><?= htmlspecialchars($proceso['last_change'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr><td class="bg-white p-4 font-semibold text-green-600"><strong>Publication and ownership</strong></td></tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r w-1/3 border-gray-100">UUID</td>
            <td class="p-4"><?= htmlspecialchars($proceso['uuid'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Date of last revision</td>
            <td class="p-4"><?= htmlspecialchars($proceso['last_change'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Data set version</td>
            <td class="p-4"><?= htmlspecialchars($proceso['version'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Owner of data set</td>
            <td class="p-4">
              <?php
                $stmt = $mysqli->prepare("SELECT a.name FROM process_actors pa JOIN actors a ON a.uuid = pa.actor_uuid WHERE pa.process_uuid = ? AND pa.role = 'owner' LIMIT 1");
                $stmt->bind_param('s', $uuid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_row();
                $owner = $row ? $row[0] : null;
                $stmt->close();
                echo htmlspecialchars($owner ?: '...', ENT_QUOTES, 'UTF-8');
              ?>
            </td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Copyright</td>
            <td class="p-4"><?= htmlspecialchars((($proceso['copyright'] ?? '') === '1') ? 'Sí' : 'No', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <tr class="border-b border-gray-100">
            <td class="bg-gray-50 p-4 font-medium border-r border-gray-100">Access and use restrictions</td>
            <td class="p-4"><?= htmlspecialchars($proceso['access_restrictions'] ?? '...', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
<!-- Data Quality Indicators Section -->
<div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
  <button onclick="toggleAccordion('dataQualityTable', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
    <span>Data Quality Indicators</span>
    <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400 group-[.accordion-open]:rotate-90"></i>
  </button>
  <div id="dataQualityTable" class="hidden border-t border-gray-100 bg-white">
    <?php 
    echo "<!-- DEBUG: UUID = " . htmlspecialchars($uuid) . " -->\n";
    echo "<!-- DEBUG: dq_all registros = " . count($dq_all ?? []) . " -->\n";
    echo "<!-- DEBUG: dq_grouped indicadores = " . count($dq_grouped ?? []) . " -->\n";
    
    if (!empty($dq_grouped)): 
    ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="p-3 text-left font-bold border-b border-r border-gray-200 sticky left-0 bg-gray-100 z-10 w-64">Indicator</th>
              <th class="p-3 text-center font-bold border-b border-r border-gray-200 text-green-700 w-48">1<br></th>
              <th class="p-3 text-center font-bold border-b border-r border-gray-200 text-green-600 w-48">2<br></th>
              <th class="p-3 text-center font-bold border-b border-r border-gray-200 text-yellow-600 w-48">3<br></th>
              <th class="p-3 text-center font-bold border-b border-r border-gray-200 text-orange-600 w-48">4<br></th>
              <th class="p-3 text-center font-bold border-b border-r border-gray-200 text-red-600 w-48">5<br></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dq_grouped as $indicator => $scores): ?>
              <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="p-3 font-semibold text-gray-800 border-r border-gray-200 sticky left-0 bg-white">
                  <?= htmlspecialchars($indicator, ENT_QUOTES, 'UTF-8') ?>
                </td>
                <?php 
                $score_data = [];
                foreach ($scores as $s) {
                  $score_data[$s['score_level']] = $s;
                }
                
                for ($level = 1; $level <= 5; $level++): 
                  $current = $score_data[$level] ?? null;
                  $is_selected = $current && $current['is_selected'] == 1;
                  $has_text = $current && !empty($current['description']);
                ?>
                  <td class="p-3 text-center border-r border-gray-200 relative <?= $is_selected ? 'bg-green-50 border-2 border-green-500' : '' ?>">
                    <?php if ($is_selected): ?>
                      <div class="absolute top-1 right-1">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-xs font-bold">
                          ✓
                        </span>
                      </div>
                    <?php endif; ?>
                    
                    <?php if ($has_text): ?>
                      <div class="text-xs text-gray-700 leading-relaxed <?= $is_selected ? 'font-medium' : '' ?>">
                        <?= nl2br(htmlspecialchars($current['description'], ENT_QUOTES, 'UTF-8')) ?>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-300 italic text-xs">-</span>
                    <?php endif; ?>
                  </td>
                <?php endfor; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="p-8 text-center">
        <div class="text-gray-400">
          <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-3"></i>
          <p class="font-medium">No data quality indicators defined for this process.</p>
          <p class="text-sm mt-1">Add indicators in the Documentation section.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
  <!-- Inputs Table -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria3', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Inputs</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400 group-[.accordion-open]:rotate-90"></i>
    </button>
    <div id="tablasecundaria3" class="hidden border-t border-gray-100 bg-white">
      <table class="table-auto w-full mb-8">
        <thead>
          <tr>
            <th colspan="7" class="bg-white p-4 font-semibold text-green-600 text-left border-b border-gray-100"><strong>Inputs</strong></th>
          </tr>
          <tr class="bg-gray-100">
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Flow</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Category</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Amount</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Description</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($inputs) while($input = $inputs->fetch_assoc()): ?>
          <?php
            $targetUuid = !empty($input['provider_process_uuid']) 
              ? $input['provider_process_uuid'] 
              : getProcessByFlow($mysqli, $input['flow_uuid']);
            
            $linkStart = $targetUuid ? '<a class="text-emerald-700 hover:underline font-medium" href="'.htmlspecialchars($self, ENT_QUOTES, 'UTF-8').'?uuid='.urlencode($targetUuid).'" title="Abrir proceso relacionado">' : '';
            $linkEnd   = $targetUuid ? ' <i data-lucide="external-link" class="inline w-4 h-4 align-[-2px]"></i></a>' : '';
          ?>
          <tr>
            <td class="p-2 text-center border-b border-r font-semibold border-gray-100">
              <?= $linkStart . htmlspecialchars($input['resource_type'] ?? '', ENT_QUOTES, 'UTF-8') . $linkEnd ?>
            </td>
            <td class="p-2 text-center border-b border-r border-gray-100"><?= htmlspecialchars($input['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="p-2 text-center border-b border-r border-gray-100"><?= htmlspecialchars($input['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($input['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="p-2 text-center border-b border-r border-gray-100"><?= htmlspecialchars($input['commentary'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    </div>
      
<!-- Outputs Table -->
  <div class="w-full max-w-7xl mx-auto bg-white shadow-lg rounded-2xl border border-gray-100 overflow-hidden mb-8">
    <button onclick="toggleAccordion('tablasecundaria4', this)" class="w-full px-8 py-5 text-xl font-semibold text-left text-gray-800 flex items-center justify-between hover:bg-green-50 transition-all duration-200 focus:outline-none group">
      <span>Outputs</span>
      <i data-lucide="chevron-right" class="accordion-chevron w-5 h-5 text-gray-400 group-[.accordion-open]:rotate-90"></i>
    </button>
    <div id="tablasecundaria4" class="hidden border-t border-gray-100 bg-white">
      <table class="table-auto w-full">
        <thead>
          <tr>
            <th colspan="11" class="bg-white p-4 font-semibold text-green-600 text-left border-b border-gray-100"><strong>Outputs</strong></th>
          </tr>
          <tr class="bg-gray-100">
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Flow</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Category</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Amount</th>
            <th class="p-2 text-center border-b border-r border-gray-100 font-bold">Description</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($outputs) while($output = $outputs->fetch_assoc()): ?>
          <?php
            $targetUuid = !empty($output['provider_process_uuid']) 
              ? $output['provider_process_uuid'] 
              : getProcessByFlow($mysqli, $output['flow_uuid']);
            
            $linkStart = $targetUuid ? '<a class="text-emerald-700 hover:underline font-medium" href="'.htmlspecialchars($self, ENT_QUOTES, 'UTF-8').'?uuid='.urlencode($targetUuid).'" title="Abrir proceso relacionado">' : '';
            $linkEnd   = $targetUuid ? ' <i data-lucide="external-link" class="inline w-4 h-4 align-[-2px]"></i></a>' : '';
          ?>
          <tr>
            <td class="p-2 text-center border-b border-r font-semibold border-gray-100">
              <?= $linkStart . htmlspecialchars($output['type_of_emission'] ?? '', ENT_QUOTES, 'UTF-8') . $linkEnd ?>
            </td>
            <td class="p-2 text-center border-b border-r border-gray-100"><?= htmlspecialchars($output['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="p-2 text-center border-b border-r border-gray-100"><?= htmlspecialchars($output['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($output['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="p-2 text-center border-b border-r border-gray-100"><?= htmlspecialchars($output['commentary'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
