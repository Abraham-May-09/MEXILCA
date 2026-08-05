<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

session_start();

// ✅ Cargar verificar_permisos de forma segura
if (file_exists('php_actions/verificar_permisos.php')) {
    require_once 'php_actions/verificar_permisos.php';
}

// ✅ Función segura para verificar permisos
if (!function_exists('puede_añadir_datasets')) {
    function puede_añadir_datasets() {
        return isset($_SESSION['role']) && ($_SESSION['role'] === 'ADMIN' || $_SESSION['role'] === 'USER');
    }
}

// RESTRICCIONES PARA USUARIOS NORMALES SIN PERMISOS PARA DESCARGAR
$canDownload = false;

if (isset($_SESSION['user_uuid'])) {
    require_once 'conexion.php';
    
    $user_uuid = $_SESSION['user_uuid'];
    $stmt = $conn->prepare("SELECT role, can_download FROM users WHERE uuid = ?");
    $stmt->bind_param("s", $user_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['role'] === 'ADMIN' || $row['can_download'] == 1) {
            $canDownload = true;
        }
    }
    $stmt->close();
}

// Definir variable de borradores
$borradores = [];
if (isset($_SESSION['user_uuid'])) {
    require_once 'conexion.php';
    $user_uuid = $_SESSION['user_uuid'];
    $stmt = $conn->prepare("SELECT uuid FROM processes WHERE created_by_uuid = ? AND approval_status = 'draft'");
    $stmt->bind_param("s", $user_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $borradores[] = $row;
    }
    $stmt->close();
}

// Datasets pendientes + solicitudes de admin
$pending = 0;
if (isset($_SESSION["role"]) && $_SESSION["role"] === 'ADMIN') {
    require_once 'conexion.php';
    
    $sql_datasets = "SELECT COUNT(*) as total FROM processes WHERE approval_status = 'pending'";
    $result_datasets = $conn->query($sql_datasets);
    $datasets_pendientes = $result_datasets ? $result_datasets->fetch_assoc()['total'] : 0;
    
    $sql_admin_requests = "SELECT COUNT(*) as total FROM users WHERE admin_request = 1";
    $result_admin = $conn->query($sql_admin_requests);
    $admin_requests = $result_admin ? $result_admin->fetch_assoc()['total'] : 0;
    
    $pending = (int)$datasets_pendientes + (int)$admin_requests;
}

$uid   = $_SESSION['user_uuid'] ?? $_SESSION['user_id'] ?? null;
$name  = $_SESSION['name'] ?? $_SESSION['nombre'] ?? null;
$email = $_SESSION['email'] ?? null;
$photo = $_SESSION['photo_url'] ?? $_SESSION['foto'] ?? 'default-profile.png';

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

// Conexión
$mysqli = @new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($mysqli->connect_errno) {
  error_log("MySQL connect error ({$mysqli->connect_errno}): {$mysqli->connect_error}");
  http_response_code(500);
  exit("Error interno: conexión a base de datos falló.");
}
$mysqli->set_charset('utf8mb4');

// ============ OBTENER FILTROS DE URL ============
$filter_sector = isset($_GET['sector']) ? trim($_GET['sector']) : '';
$filter_region = isset($_GET['region']) ? trim($_GET['region']) : '';
$filter_risic = isset($_GET['risic']) ? trim($_GET['risic']) : '';
$filter_anio = isset($_GET['anio']) ? trim($_GET['anio']) : '';
$filter_tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$orden = isset($_GET['orden']) ? trim($_GET['orden']) : 'alfabetico';
$filter_creados_por_usuarios = isset($_GET['usuarios_solo']) && $_GET['usuarios_solo'] == '1' ? true : false;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';

$geoMexicoCondition = "(LOWER(p.geo_desc) LIKE '%mexico%' OR LOWER(l.name) LIKE '%mexico%' OR UPPER(l.code) = 'MX')";

// Determinar ORDER BY según selección
switch($orden) {
    case 'reciente':
        $orderBy = 'COALESCE(p.last_change, p.created_at) DESC';
        break;
    case 'sector':
        $orderBy = 'p.sector_principal ASC, p.name ASC';
        break;
    case 'alfabetico':
    default:
        $orderBy = 'p.name ASC';
        break;
}

// ============ CONSTRUIR WHERE DINÁMICO ============
$whereConditions = [];
$whereSQL = '';
if (!$isAdmin) {
    $whereConditions[] = $geoMexicoCondition;
}
if (!empty($filter_sector)) {
  $whereConditions[] = "p.sector_principal = '" . $mysqli->real_escape_string($filter_sector) . "'";
}
if (!empty($filter_risic)) {
  $whereConditions[] = "p.norma_isic = '" . $mysqli->real_escape_string($filter_risic) . "'";
}
if (!empty($filter_region)) {
  $whereConditions[] = "l.name = '" . $mysqli->real_escape_string($filter_region) . "'";
}
if (!empty($filter_anio)) {
  $whereConditions[] = "YEAR(pd.creation_date) = " . intval($filter_anio);
}
if (!empty($filter_tipo)) {
  $whereConditions[] = "p.process_type = '" . $mysqli->real_escape_string($filter_tipo) . "'";
}
if (!empty($filter_search)) {
  $search_escaped = $mysqli->real_escape_string($filter_search);
  $whereConditions[] = "(p.name LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%')";
}
if ($filter_creados_por_usuarios) {
  $whereConditions[] = "p.created_by_uuid IS NOT NULL";
}

// Añadir siempre condiciones de aprobación
$whereConditions[] = "p.approval_status = 'approved'";

$whereConditions[] = "p.is_draft = 0";

if (count($whereConditions) > 0) {
  $whereSQL = ' WHERE ' . implode(' AND ', $whereConditions);
}

// ============ PAGINACIÓN ============
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(DISTINCT p.uuid) as total 
              FROM processes p
              LEFT JOIN process_documentation pd ON pd.process_uuid = p.uuid
              LEFT JOIN locations l ON l.uuid = p.location_uuid
              $whereSQL";
              
$count_result = $mysqli->query($count_sql);
if (!$count_result) {
    error_log("ERROR COUNT SQL: " . $mysqli->error);
    die("Error en query de conteo. Revisa php_error.log");
}
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// ============ FILTROS DEPENDIENTES ============
$sectores = [];
$regiones = [];
$anios    = [];
$tipos    = [];
$Risic    = [];

// SECTORES
$whereSectores = "WHERE p.sector_principal IS NOT NULL 
                  AND p.approval_status = 'approved' 
                  AND p.is_draft = 0";

if (!$isAdmin) {
    $whereSectores .= " AND $geoMexicoCondition";
}

$sqlSectores = "SELECT DISTINCT 
                  p.sector_principal, 
                  sm.sector_isic_es,
                  sm.sector_isic_en
                FROM processes p 
                LEFT JOIN locations l ON l.uuid = p.location_uuid
                LEFT JOIN sector_mapping sm ON sm.sector_principal = p.sector_principal
                $whereSectores
                ORDER BY p.sector_principal ASC";

if ($res = $mysqli->query($sqlSectores)) {
  while ($r = $res->fetch_assoc()) {
    $sectores[] = [
      'sector' => $r['sector_principal'],
      'sector_isic_es' => $r['sector_isic_es'],
      'sector_isic_en' => $r['sector_isic_en']
    ];
  }
  $res->free();
}

// RISIC/NORMA_ISIC
$whereRisic = "WHERE p.norma_isic IS NOT NULL 
               AND p.approval_status = 'approved' 
               AND p.is_draft = 0";

if (!$isAdmin) {
    $whereRisic .= " AND $geoMexicoCondition";
}

$sqlRisic = "SELECT DISTINCT p.norma_isic 
             FROM processes p
             LEFT JOIN locations l ON l.uuid = p.location_uuid
             $whereRisic
             ORDER BY p.norma_isic ASC";

if ($res = $mysqli->query($sqlRisic)) {
  while ($r = $res->fetch_assoc()) {
    if (!empty($r['norma_isic'])) {
      $Risic[] = ['risic' => $r['norma_isic']];
    }
  }
  $res->free();
}

// REGIONES
$whereRegiones = "WHERE p.approval_status = 'approved' AND p.is_draft = 0";

if (!empty($filter_sector)) {
  $whereRegiones .= " AND p.sector_principal = '" . $mysqli->real_escape_string($filter_sector) . "'";
}

if (!$isAdmin) {
    $whereRegiones .= " AND $geoMexicoCondition";
}
$sqlRegiones = "SELECT DISTINCT l.name AS region
                FROM processes p
                LEFT JOIN locations l ON l.uuid = p.location_uuid
                $whereRegiones
                ORDER BY l.name ASC";
if ($res = $mysqli->query($sqlRegiones)) {
  while ($r = $res->fetch_assoc()) {
    if (!empty($r['region'])) $regiones[] = $r['region'];
  }
  $res->free();
}

// TIPOS
$whereTipos = 'WHERE p.approval_status = \'approved\' AND p.is_draft = 0';
if (!empty($filter_sector)) {
  $whereTipos .= " AND p.sector_principal = '" . $mysqli->real_escape_string($filter_sector) . "'";
}
$sqlTipos = "SELECT DISTINCT p.process_type AS tipo
             FROM processes p
             $whereTipos
             ORDER BY p.process_type ASC";
if ($res = $mysqli->query($sqlTipos)) {
  while ($r = $res->fetch_assoc()) {
    if (!empty($r['tipo'])) $tipos[] = $r['tipo'];
  }
  $res->free();
}

// AÑOS
$whereAnios = 'WHERE pd.creation_date IS NOT NULL 
               AND p.approval_status = \'approved\' 
               AND p.is_draft = 0';
if (!empty($filter_sector)) {
  $whereAnios .= " AND p.sector_principal = '" . $mysqli->real_escape_string($filter_sector) . "'";
}
$sqlAnios = "SELECT DISTINCT YEAR(pd.creation_date) AS y 
             FROM process_documentation pd
             LEFT JOIN processes p ON p.uuid = pd.process_uuid
             $whereAnios
             ORDER BY y DESC";
if ($res = $mysqli->query($sqlAnios)) {
  while ($r = $res->fetch_assoc()) {
    if (!empty($r['y'])) $anios[] = (int)$r['y'];
  }
  $res->free();
}

// Query principal
$processes = [];
$q = "SELECT
        p.uuid,
        p.name AS process_name,
        p.description AS process_description,
        p.sector_principal,
        sm.sector_isic_es,
        sm.sector_isic_en,
        p.process_type AS type_of_process,
        l.name AS location,
        p.version,
        p.last_change,
        p.created_at,
        p.approval_status,
        p.created_by_uuid,
        pd.project AS project,
        pd.lci_method AS lci_method,
        pd.ds_data_completeness AS ds_data_completeness,
        pd.completeness_text AS completeness_text,
        pd.ds_data_selection AS ds_data_selection,
        pd.ds_data_treatment AS ds_data_treatment,
        pd.ds_collection_period AS ds_collection_period,
        pd.sources_text AS sources_text,
        pd.modeling_constants AS modeling_constants,
        pd.access_use_restrictions AS access_use_restrictions,
        pd.copyright_flag AS copyright_flag,
        pd.creation_date AS creation_date,
        pd.document_type AS document_type
      FROM processes p
      LEFT JOIN process_documentation pd ON pd.process_uuid = p.uuid
      LEFT JOIN locations l ON l.uuid = p.location_uuid
      LEFT JOIN sector_mapping sm ON sm.sector_principal = p.sector_principal
      $whereSQL
      ORDER BY $orderBy
      LIMIT $per_page OFFSET $offset";

if ($res = $mysqli->query($q)) {
  while ($row = $res->fetch_assoc()) $processes[] = $row;
  $res->free();
} else {
    error_log("ERROR QUERY PRINCIPAL: " . $mysqli->error);
    die("Error en query principal. Revisa php_error.log");
}

// Auxiliares IO
function getInputsFor($mysqli, $process_uuid) {
  $sql = "SELECT pi.*,
                 u.name AS unit_name,
                 fp.name AS flow_property_name,
                 f.name AS flow_name,
                 c.code AS currency_code,
                 loc.name AS location_name
          FROM process_inputs pi
          LEFT JOIN units u ON u.uuid = pi.unit_uuid
          LEFT JOIN flow_properties fp ON fp.uuid = pi.flow_property_uuid
          LEFT JOIN flows f ON f.uuid = pi.flow_uuid
          LEFT JOIN currencies c ON c.uuid = pi.currency_uuid
          LEFT JOIN locations loc ON loc.uuid = pi.location_uuid
          WHERE pi.process_uuid = ?
          ORDER BY pi.internal_id ASC";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) return [];
  $stmt->bind_param('s', $process_uuid);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return $rows;
}

function getOutputsFor($mysqli, $process_uuid) {
  $sql = "SELECT po.*,
                 u.name AS unit_name,
                 fp.name AS flow_property_name,
                 f.name AS flow_name,
                 c.code AS currency_code,
                 loc.name AS location_name
          FROM process_outputs po
          LEFT JOIN units u ON u.uuid = po.unit_uuid
          LEFT JOIN flow_properties fp ON fp.uuid = po.flow_property_uuid
          LEFT JOIN flows f ON f.uuid = po.flow_uuid
          LEFT JOIN currencies c ON c.uuid = po.currency_uuid
          LEFT JOIN locations loc ON loc.uuid = po.location_uuid
          WHERE po.process_uuid = ?
          ORDER BY po.internal_id ASC";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) return [];
  $stmt->bind_param('s', $process_uuid);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return $rows;
}

// Enriquecer y derivar unidad funcional
foreach ($processes as &$proc) {
  $uuid = $proc['uuid'] ?? null;
  $proc['functional_unit'] = null;
  if ($uuid) {
    $outs = getOutputsFor($mysqli, $uuid);
    $ins  = getInputsFor($mysqli, $uuid);
    foreach ($outs as $o) {
      if (!empty($o['is_reference'])) { $proc['functional_unit'] = $o['unit_name'] ?? null; break; }
    }
    if (!$proc['functional_unit']) {
      foreach ($ins as $i) {
        if (!empty($i['is_reference'])) { $proc['functional_unit'] = $i['unit_name'] ?? null; break; }
      }
    }
    $proc['inputs']  = $ins;
    $proc['outputs'] = $outs;
  } else {
    $proc['inputs'] = [];
    $proc['outputs'] = [];
  }
}
unset($proc);

// Función para construir URL con filtros
function buildUrl($page) {
  global $filter_sector, $filter_region, $filter_risic, $filter_anio, $filter_tipo, $filter_search, $orden;
  
  $params = ['page' => $page];
  
  if (!empty($filter_sector)) $params['sector'] = $filter_sector;
  if (!empty($filter_region)) $params['region'] = $filter_region;
  if (!empty($filter_risic)) $params['risic'] = $filter_risic;
  if (!empty($filter_anio)) $params['anio'] = $filter_anio;
  if (!empty($filter_tipo)) $params['tipo'] = $filter_tipo;
  if (!empty($filter_search)) $params['search'] = $filter_search;
  if (!empty($orden)) $params['orden'] = $orden;
  if ($filter_creados_por_usuarios) $params['usuarios_solo'] = '1';
  
  return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Base de Datos</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image/png" href="icons/database.png" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    body { font-family: 'Inter', sans-serif; }
    #sugerencias { max-height: 220px; overflow-y: auto; }
    pre.json-preview { max-height: 260px; overflow:auto; background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e6edf3; font-size:12px; }
    
    .custom-file-output {
    width: 100%;
    padding: 4px;
    border: 2px solid #5F7562;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    background: white;
    }
    
    .custom-file-output:hover {
        border-color: #105218;
        background: #f9fafb;
    }
    
    .custom-file-output::file-selector-button {
        padding: 4px 6px;
        margin-right: 10px;
        border: none;
        border-radius: 4px;
        background: #196334;
        color: white;
        font-weight: 600;
        cursor: pointer;
    }
    
    .custom-file-output::file-selector-button:hover {
        background: #264D2B;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex">
<!-- Barra Lateral -->
  <aside class="fixed top-0 left-0 h-screen z-40 w-64 bg-white p-6 flex flex-col justify-between border-r border-gray-200 shadow-sm">
    <div>
      <img src="images/LOGO-MEXI-.png" class="w-50 mx-auto mb-8">
      <nav class="space-y-4 text-left text-sm">
        <a href="index.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="home" class="w-5 h-5"></i> Inicio</a>
        <a href="conjuntos.php" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700"><i data-lucide="database" class="w-5 h-5"></i> Base de Datos</a>
        <a href hidden="informes.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="file-text" class="w-5 h-5"></i> Informes</a>
        <a href hidden="resultados.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="leaf" class="w-5 h-5"></i> Resultados del ACV</a>
        <a href="contactos.php" hidden class="flex items-center gap-3 hover:text-green-700"><i data-lucide="mail" class="w-5 h-5"></i> Contactos</a>
        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'ADMIN'): ?>
            <a href="Admin.php" class="flex items-center gap-3 hover:text-green-700">
                <i data-lucide="shield-user" class="w-5 h-5"></i>
                Administración
                <?php if ($pending > 0): ?>
                    <span class="bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full ml-auto">
                        <?= $pending ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if (puede_añadir_datasets()): ?>
        <div class="relative">
            <button onclick="toggleDropdown('datasetMenu')" class="flex items-center gap-3 hover:text-green-700 w-full text-left">
                <i data-lucide="file" class="w-5 h-5"></i>
                Añadir Dataset
                <i data-lucide="chevron-down" class="w-4 h-4 ml-auto"></i>
            </button>
            <div id="datasetMenu" class="hidden mt-4 ml-8 space-y-4">
                <a href="Añadir Conjunto de Datos.php" class="flex items-center gap-3 hover:text-green-700 text-sm">
                    <i data-lucide="file-plus-2" class="w-4 h-4"></i>
                    Manual
                </a>
                <a href="import.php" class="flex items-center gap-3 hover:text-green-700 text-sm">
                    <i data-lucide="file-code-2" class="w-4 h-4"></i>
                    Automatico
                </a>
                <a href="search_dataset.php" class="flex items-center gap-3 hover:text-green-700 text-sm">
                    <i data-lucide="file-pen-line" class="w-4 h-4"></i>
                    Editar
                </a>
                <a href="mis_borradores.php" class="flex items-center gap-3 hover:text-green-700 text-sm">
                    <i data-lucide="file-edit" class="w-4 h-4"></i>
                    Mis Borradores
                    <?php if (count($borradores) > 0): ?>
                        <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full ml-auto">
                            <?= count($borradores) ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="mis_datasets.php" class="flex items-center gap-3 hover:text-green-700 text-sm">
                    <i data-lucide="receipt-text" class="w-4 h-4"></i>
                    Mis Contribuciones
                </a>
            </div>
        </div>        
        <?php endif; ?>
        <a href="ayuda.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="info" class="w-5 h-5"></i>Manual y Ayuda</a>
      </nav>
    </div>
<?php if (isset($_SESSION["user_uuid"])): ?>
  <nav class="relative border-t pt-4 mt-4 text-sm text-gray-700">
    <button id="userMenuBtn" class="w-full text-center hover:text-green-700 transition">
      <div class="flex flex-col items-center gap-1">
        <img id="profileImg" src="<?= htmlspecialchars($_SESSION['photo_url'] ?? 'images/default-profile.png') ?>?v=<?= time() ?>" alt="Foto de Perfil" class="w-20 h-20 rounded-full object-cover border border-gray-300" />        
        <p class="font-semibold"><?= htmlspecialchars($_SESSION["name"]) ?></p>
        <p class="text-xs text-gray-500"><?= isset($_SESSION["email"]) ? htmlspecialchars($_SESSION["email"]) : 'Correo no disponible' ?></p>
      </div>
    </button>
    <div id="userDropdown" class="absolute bottom-16 left-0 bg-white border border-gray-200 shadow-lg rounded-md hidden w-44 z-50 text-sm">
      <button onclick="openModal('configModal')" class="block w-full px-4 py-2 text-left hover:bg-gray-100">Configuración</button>
      <a href="logout.php" class="block w-full px-4 py-2 text-left hover:bg-gray-100">Cerrar sesión</a>
    </div>
  </nav>
  <input id="profileUpload" type="file" accept="image/*" class="hidden" />
<?php else: ?>
  <div class="border-t pt-4 mt-4 w-full max-w-xs mx-auto">
    <div class="flex flex-col gap-3">
      <a href="login.php"
         class="flex items-center justify-center gap-2 bg-green-700 text-white text-sm py-2 rounded-lg hover:bg-green-600 transition shadow">
        <i data-lucide="log-in" class="w-4 h-4"></i>
        Iniciar sesión
      </a>
    </div>
  </div>
<?php endif; ?>
  </aside>

  <!-- Contenido Principal -->
  <main class="ml-64 flex-1 p-10">
    <h1 class="text-4xl font-bold mb-6 text-gray-900">
      <?= $isAdmin ? 'Base de Datos Global MexILCA' : 'Base de Datos MexILCA' ?>
    </h1>    
    <div class="max-w-5xl bg-white border border-gray-200 shadow-sm p-6 rounded-xl mb-8">
      <!-- BUSCADOR -->
      <form method="GET" action="conjuntos.php" id="searchForm" class="relative w-full mb-6">
        <?php if (!empty($filter_sector)): ?>
          <input type="hidden" name="sector" value="<?= htmlspecialchars($filter_sector) ?>">
        <?php endif; ?>
        <?php if (!empty($filter_risic)): ?>
          <input type="hidden" name="risic" value="<?= htmlspecialchars($filter_risic) ?>">
        <?php endif; ?>
        <?php if (!empty($filter_region)): ?>
          <input type="hidden" name="region" value="<?= htmlspecialchars($filter_region) ?>">
        <?php endif; ?>
        <?php if (!empty($filter_anio)): ?>
          <input type="hidden" name="anio" value="<?= htmlspecialchars($filter_anio) ?>">
        <?php endif; ?>
        <?php if (!empty($filter_tipo)): ?>
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($filter_tipo) ?>">
        <?php endif; ?>
        
        <input type="text" name="search" id="buscarInput" placeholder="Buscar un producto o servicio" 
               value="<?= htmlspecialchars($filter_search) ?>"
               class="w-full border border-gray-300 px-4 py-2 rounded-md text-sm shadow-sm pr-10" autocomplete="off" />
        <button type="submit" class="absolute right-3 top-2.5 text-gray-500 hover:text-green-700 flex items-center gap-1 text-sm font-medium">
          <i data-lucide="search" class="w-4 h-4"></i>
        </button>
        <div id="sugerencias" class="absolute left-0 right-0 top-12 bg-white border border-gray-200 rounded-md z-30 shadow text-sm hidden"></div>
      </form>

      <!-- FILTROS -->
      <form method="GET" action="conjuntos.php" id="filterForm" class="flex flex-wrap gap-4 mb-6 text-sm items-center">
        <?php if (!empty($filter_search)): ?>
          <input type="hidden" name="search" value="<?= htmlspecialchars($filter_search) ?>">
        <?php endif; ?>
        
        <label class="mr-2">Filtrar por:</label>
        
        <select name="sector" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white shadow-sm">
          <option value="">Sector</option>
          <?php foreach($sectores as $s): ?>
            <option value="<?= htmlspecialchars($s['sector']) ?>" 
                    <?= ($filter_sector == $s['sector']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['sector']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <select name="risic" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white shadow-sm">
            <option value="">Sección ISIC</option>
            <?php foreach ($Risic as $s): ?>
            <option value="<?= htmlspecialchars($s['risic']) ?>"
                    <?= ($filter_risic == $s['risic']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['risic']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="region" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white shadow-sm">
          <option value="">Región</option>
          <?php foreach($regiones as $reg): ?>
            <option value="<?= htmlspecialchars($reg) ?>" <?= $filter_region === $reg ? 'selected' : '' ?>>
              <?= htmlspecialchars($reg) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="anio" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white shadow-sm">
          <option value="">Año</option>
          <?php foreach($anios as $y): ?>
            <option value="<?= htmlspecialchars($y) ?>" <?= $filter_anio == $y ? 'selected' : '' ?>>
              <?= htmlspecialchars($y) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="tipo" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white shadow-sm">
          <option value="">Tipo de proceso</option>
          <?php foreach($tipos as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $filter_tipo === $t ? 'selected' : '' ?>>
              <?= htmlspecialchars($t) ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <select name="orden" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white shadow-sm">
          <option value="alfabetico" <?= ($_GET['orden'] ?? 'alfabetico') === 'alfabetico' ? 'selected' : '' ?>>A-Z (Alfabético)</option>
          <option value="reciente" <?= ($_GET['orden'] ?? '') === 'reciente' ? 'selected' : '' ?>>Más recientes</option>
        </select>
        
        <label class="flex items-center gap-2 border border-gray-300 rounded px-3 py-1 text-sm bg-white shadow-sm cursor-pointer hover:bg-gray-50">
          <input type="checkbox" name="usuarios_solo" value="1" 
            <?= $filter_creados_por_usuarios ? 'checked' : '' ?> onchange="this.form.submit()" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"/>
          <span>Solo datasets de usuarios</span>
        </label>

        <?php if (!empty($filter_sector) || !empty($filter_region) || !empty($filter_risic) || !empty($filter_anio) || !empty($filter_tipo) || !empty($filter_search)): ?>
        <a href="conjuntos.php" class="text-sm text-gray-600 hover:text-green-700 underline">
          Limpiar filtros
        </a>
        <?php endif; ?>
      </form>

      <!-- LISTADO -->
      <div id="listaDatasets">
      <?php if (!empty($processes)): ?>
        <?php foreach($processes as $row): 
            $year = '';
            if (!empty($row['creation_date'])) {
              $ts = strtotime($row['creation_date']);
              if ($ts) $year = date('Y', $ts);
            }
            $fu = $row['functional_unit'] ?? 'N/D';
        ?>
        <div class="dataset bg-white border border-gray-200 rounded-xl p-4 space-y-4 shadow-sm mb-8">
          <h2 class="text-lg font-bold"><?= htmlspecialchars($row['process_name'] ?? 'N/D') ?></h2>
          <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1 text-sm">
              <p><?= htmlspecialchars($row['process_description'] ?? '') ?></p>
            </div>
            <div class="md:w-64 text-sm border-l md:pl-4">
              <h3 class="font-bold mb-2">Datos clave</h3>
                <ul class="space-y-1">
                  <li><span class="inline-flex items-center gap-2 font-bold"><i data-lucide="globe" class="w-4 h-4"></i> Geografía:</span> <?= htmlspecialchars($row['location'] ?? 'N/D') ?></li>
                  <li><span class="inline-flex items-center gap-2 font-bold"><i data-lucide="zap" class="w-4 h-4"></i> Sector:</span> <?= htmlspecialchars($row['sector_principal'] ?? 'N/D') ?></li>
                  <li><span class="inline-flex items-center gap-2 font-bold"><i data-lucide="scale" class="w-4 h-4"></i> Unidad:</span> <?= htmlspecialchars($fu) ?></li>
                  <li><span class="inline-flex items-center gap-2 font-bold"><i data-lucide="calendar" class="w-4 h-4"></i> Año de referencia:</span> <?= htmlspecialchars($row['creation_date'] ?? 'N/A') ?></li>
                  <?php if (!empty($row['document_type'])): ?>
                  <li>
                    <span class="inline-flex items-center gap-2 font-bold">
                      <i data-lucide="file-text" class="w-4 h-4"></i> Documento:
                    </span> 
                    <span class="<?= ($row['document_type'] === 'Tesis') ? 'text-green-800 font-semibold' : 'text-green-700 font-semibold' ?>">
                      <?= htmlspecialchars($row['document_type']) ?>
                    </span>
                  </li>
                  <?php endif; ?>
                </ul>

            <div class="mt-4 flex flex-col gap-2">
              <button onclick="handleMoreInfoClick('<?= htmlspecialchars($row['uuid'] ?? '') ?>')" class="border border-gray-300 rounded-md px-3 py-1 text-sm hover:bg-gray-100 transition text-gray-700 text-center cursor-pointer">
                  Más información
              </button>
              
              <?php if ($canDownload): ?>
                <button onclick="openDownloadModal('<?= htmlspecialchars($row['uuid'] ?? '') ?>')" class="border border-green-600 rounded-md px-3 py-1 text-sm hover:bg-green-100 transition text-green-700">
                    Exportar Dataset
                </button>
              <?php else: ?>
                <button onclick="alert('⚠️ No tienes permisos para descargar datasets. Contacta a un administrador.')" class="border border-gray-400 rounded-md px-3 py-1 text-sm bg-gray-100 text-gray-500 cursor-not-allowed">
                    🔒 Exportar Dataset
                </button>
              <?php endif; ?>
            </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-gray-500">No hay datasets disponibles con los filtros seleccionados.</p>
      <?php endif; ?>
      </div>

    <!-- PAGINACIÓN CON SELECTOR -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center items-center gap-4 mt-8">
      <?php if ($page > 1): ?>
        <a href="<?= buildUrl($page - 1) ?>" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-sm">
          ← Anterior
        </a>
      <?php endif; ?>
    
      <div class="flex items-center gap-2">
        <label for="pageSelector" class="text-sm text-gray-600">Ir a página:</label>
        <select id="pageSelector" onchange="goToPage(this.value)" 
                class="border border-gray-300 rounded-md px-3 py-2 text-sm bg-white shadow-sm">
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <option value="<?= $i ?>" <?= ($i == $page) ? 'selected' : '' ?>>
              <?= $i ?>
            </option>
          <?php endfor; ?>
        </select>
        <span class="text-sm text-gray-600">de <?= $total_pages ?></span>
      </div>
    
      <span class="text-sm text-gray-500">(<?= $total_records ?> resultados)</span>
    
      <?php if ($page < $total_pages): ?>
        <a href="<?= buildUrl($page + 1) ?>" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-sm">
          Siguiente →
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Modal de Exportar Datos -->
    <div id="downloadModal" class="text-center flex fixed inset-0 bg-black/30 backdrop-blur-sm bg-opacity-40 hidden items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
        <button onclick="closeModal('downloadModal')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
        <h2 class="text-2xl font-bold mb-4">Exportar Datos</h2>
        <div class="grid grid-cols-1 md:grid-cols-1 gap-4 items-center text-center">
          <div>
            <table class="w-full text-sm border items-center border-black text-center">
              <tbody>
                <tr><td class="border px-4 py-2">PDF</td><td class="border px-4 py-2"><a href="#" onclick="downloadPDF()" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100"><i data-lucide="download" class="w-4 h-4"></i> Descargar</a></td></tr>
                <tr><td class="border px-4 py-2">JSON-LD</td><td class="border px-4 py-2"><a href="#" onclick="downloadJSONLD()" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100 cursor-pointer"><i data-lucide="download" class="w-4 h-4"></i> Descargar</a></td></tr>
                <tr><td class="border px-4 py-2">Excel</td><td class="border px-4 py-2"><a href="#" onclick="downloadExcel()" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100 cursor-pointer"><i data-lucide="download" class="w-4 h-4"></i> Descargar</a></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modales -->
  <div id="configModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
      <h2 class="text-xl font-semibold text-gray-800 mb-6">Configuración</h2>
      <div class="space-y-3 text-base">
        <button id="btnChangePhoto" class="w-full flex items-center gap-3 border border-gray-200 rounded-lg px-4 py-3 hover:bg-gray-100 transition">
          <i data-lucide="camera" class="w-5 h-5 text-green-700"></i>
          Cambiar foto de perfil
        </button>
        <button id="btnChangePassword" class="w-full flex items-center gap-3 border border-gray-200 rounded-lg px-4 py-3 hover:bg-gray-100 transition">
          <i data-lucide="lock" class="w-5 h-5 text-green-700"></i>
          Cambiar contraseña
        </button>
        <button id="btnRequestAdmin" class="w-full flex items-center gap-3 border border-gray-200 rounded-lg px-4 py-3 hover:bg-gray-100 transition">
          <i data-lucide="shield" class="w-5 h-5 text-green-700"></i>
          Solicitar permisos de administrador
        </button>
      </div>
      <div class="text-right mt-6">
        <button onclick="closeModal('configModal')" class="text-green-700 hover:underline text-sm">Cerrar</button>
      </div>
    </div>
  </div>
  
<div id="changePhotoModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Cambiar foto de perfil</h2>
  <form action="upload_photo.php" method="POST" enctype="multipart/form-data">
    <input name="foto" type="file" accept="image/*" class="custom-file-output" required />
    <button type="submit" class="w-full bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition">Guardar</button>
  </form>
    <div class="text-right">
      <button onclick="closeModal('changePhotoModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
    </div>
  </div>
</div>

<div id="changePasswordModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Cambiar contraseña</h2>
    <form action="change_password.php" method="POST" id="changePasswordForm">
      <input name="new_password" type="password" placeholder="Nueva contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" required />
      <input name="confirm_password" type="password" placeholder="Confirmar contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" required />
      <button type="submit" class="w-full bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition">Guardar</button>
    </form>
    <div class="text-right mt-4">
      <button onclick="closeModal('changePasswordModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
    </div>
  </div>
</div>

<script>
  lucide.createIcons();

  document.addEventListener('DOMContentLoaded', function () {
    const buscarInput   = document.getElementById('buscarInput');
    const sugerencias   = document.getElementById('sugerencias');
    let searchTimeout = null;

    buscarInput.addEventListener('input', function () {
      const texto = buscarInput.value.trim();
      
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }
      
      sugerencias.innerHTML = "";
      
      if (texto.length >= 2) {
        searchTimeout = setTimeout(() => {
          fetch('search_autocomplete.php?q=' + encodeURIComponent(texto))
            .then(response => response.json())
            .then(data => {
              if (data.length > 0) {
                data.forEach(item => {
                  const div = document.createElement('div');
                  div.textContent = item.name;
                  div.className = "px-3 py-2 hover:bg-green-50 cursor-pointer";
                  div.onclick = function() {
                    buscarInput.value = item.name;
                    sugerencias.classList.add('hidden');
                    document.getElementById('searchForm').submit();
                  }
                  sugerencias.appendChild(div);
                });
                sugerencias.classList.remove('hidden');
              } else {
                sugerencias.classList.add('hidden');
              }
            })
            .catch(err => {
              console.error('Error en búsqueda:', err);
              sugerencias.classList.add('hidden');
            });
        }, 300);
      } else {
        sugerencias.classList.add('hidden');
      }
    });
    
    buscarInput.addEventListener('blur', function() {
      setTimeout(() => sugerencias.classList.add('hidden'), 200);
    });
  });

  function openModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('flex');
    modal.classList.add('hidden');
  }

  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');
  if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', () => {
      userDropdown.classList.toggle('hidden');
    });
    
    document.addEventListener('click', (e) => {
      if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.classList.add('hidden');
      }
    });
  }

  if (document.getElementById('btnChangePhoto')) {
    document.getElementById('btnChangePhoto').addEventListener('click', () => {
      closeModal('configModal');
      openModal('changePhotoModal');
    });
  }
  
  if (document.getElementById('btnChangePassword')) {
    document.getElementById('btnChangePassword').addEventListener('click', () => {
      closeModal('configModal');
      openModal('changePasswordModal');
    });
  }
  
  if (document.getElementById('btnRequestAdmin')) {
    document.getElementById('btnRequestAdmin').addEventListener('click', function() {
      fetch('request_admin.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if(data.success) {
            this.disabled = true;
          }
        });
    });
  }

  function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    if (menu) menu.classList.toggle('hidden');
  }

  document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('datasetMenu');
    const button = event.target.closest('button');
    if (!button && dropdown && !dropdown.classList.contains('hidden')) {
      dropdown.classList.add('hidden');
    }
  });
  
let currentDatasetUUID = '';

const canDownload = <?= $canDownload ? 'true' : 'false' ?>;
const isLoggedIn = <?= isset($_SESSION['user_uuid']) ? 'true' : 'false' ?>;

function openDownloadModal(uuid) {
  currentDatasetUUID = uuid;
  openModal('downloadModal');
}

function checkLoginAndDownload(downloadFunction, formatName) {
  if (!canDownload) {
    alert('⚠️ No tienes permisos para descargar datasets. Contacta a un administrador para solicitar acceso.');
    return false;
  }
  return downloadFunction();
}

function handleMoreInfoClick(uuid) {
  if (!isLoggedIn) {
    alert('⚠️ Para ver mas información del dataset, por favor regístrate o inicia sesión.');
    return;
  }
  window.location.href = 'Procesos.php?uuid=' + encodeURIComponent(uuid);
}

function downloadJSONLD() {
  return checkLoginAndDownload(() => {
    if (currentDatasetUUID) {
      window.open('export_jsonld.php?uuid=' + encodeURIComponent(currentDatasetUUID), '_blank');
      closeModal('downloadModal');
      return true;
    } else {
      alert('Error: No se ha seleccionado un dataset válido.');
      return false;
    }
  }, 'JSON-LD');
}

function downloadExcel() {
  return checkLoginAndDownload(() => {
    if (currentDatasetUUID) {
      window.open('export_excel.php?uuid=' + encodeURIComponent(currentDatasetUUID), '_blank');
      closeModal('downloadModal');
      return true;
    } else {
      alert('Error: No se ha seleccionado un dataset válido.');
      return false;
    }
  }, 'Excel');
}

function downloadEcoSpold() {
  return checkLoginAndDownload(() => {
    if (currentDatasetUUID) {
      window.open('export_ecospold.php?uuid=' + encodeURIComponent(currentDatasetUUID), '_blank');
      closeModal('downloadModal');
      return true;
    } else {
      alert('Error: No se ha seleccionado un dataset válido.');
      return false;
    }
  }, 'EcoSpold');
}

function downloadILCD() {
  return checkLoginAndDownload(() => {
    if (currentDatasetUUID) {
      window.open('export_ilcd.php?uuid=' + encodeURIComponent(currentDatasetUUID), '_blank');
      closeModal('downloadModal');
      return true;
    } else {
      alert('Error: No se ha seleccionado un dataset válido.');
      return false;
    }
  }, 'ILCD XML');
}

function downloadPDF() {
  return checkLoginAndDownload(() => {
    if (currentDatasetUUID) {
      window.open('/exports/export_pdf.php?uuid=' + encodeURIComponent(currentDatasetUUID), '_blank');
      closeModal('downloadModal');
      return true;
    } else {
      alert('Error: No se ha seleccionado un dataset válido.');
      return false;
    }
  }, 'PDF');
}

//Selector de paginas en la base de Datos
function goToPage(pageNumber) {
  if (pageNumber) {
    window.location.href = '<?= "?" . http_build_query(array_filter([
      "sector" => $filter_sector,
      "region" => $filter_region,
      "risic" => $filter_risic,
      "anio" => $filter_anio,
      "tipo" => $filter_tipo,
      "search" => $filter_search,
      "orden" => $orden
    ])) ?>' + '&page=' + pageNumber;
  }
}
</script>
</body>
</html>
