<?php
session_start();
require_once 'php_actions/verificar_permisos.php';
require_once 'conexion.php';

// Definir variable de borradores
$borradores = [];
if (isset($_SESSION['user_uuid'])) {
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
    // Contar datasets pendientes
    $sql_datasets = "SELECT COUNT(*) as total FROM processes WHERE approval_status = 'pending'";
    $result_datasets = $conn->query($sql_datasets);
    $datasets_pendientes = $result_datasets ? $result_datasets->fetch_assoc()['total'] : 0;
    
    // Contar solicitudes de admin
    $sql_admin_requests = "SELECT COUNT(*) as total FROM users WHERE admin_request = 1";
    $result_admin = $conn->query($sql_admin_requests);
    $admin_requests = $result_admin ? $result_admin->fetch_assoc()['total'] : 0;
    
    // SUMA TOTAL
    $pending = (int)$datasets_pendientes + (int)$admin_requests;
    
    echo "<!-- Datasets: " . $datasets_pendientes . ", Admin requests: " . $admin_requests . ", Total: " . $pending . " -->";
}

solo_admin_o_contributor();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Dataset</title>
    <link rel="stylesheet" href="https://unpkg.com/country-select-js/build/css/countrySelect.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://unpkg.com/country-select-js/build/js/countrySelect.min.js"></script>
    <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
    <link rel="icon" type="image" href="icons/file-plus-2.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body {
    font-family: 'Inter', sans-serif;
  }
 .custom-scrollbar::-webkit-scrollbar {
  width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
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
<!-- BARRA LATERAL -->
  <aside class="fixed top-0 left-0 h-screen z-40 w-64 bg-white p-6 flex flex-col justify-between border-r border-gray-200 shadow-sm">
    <div>
      <img src="images/LOGO-MEXI-.png" class="w-50 mx-auto mb-8">
      <nav class="space-y-4 text-left text-sm">
        <a href="index.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="home" class="w-5 h-5"></i> Inicio</a>
        <a href="conjuntos.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="database" class="w-5 h-5"></i> Base de Datos</a>
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
            <button onclick="toggleDropdown('datasetMenu')" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700 w-full text-left">
                <i data-lucide="file" class="w-5 h-5"></i>
                Añadir Dataset
                <i data-lucide="chevron-down" class="w-4 h-4 ml-auto"></i>
            </button>
            <div id="datasetMenu" class="hidden mt-4 ml-8 space-y-4">
                <a href="Añadir Conjunto de Datos.php" class="flex items-center gap-3 text-gray-800 font-bold hover:text-green-700 text-sm">
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
    <!-- Perfil de usuario -->
<?php if (isset($_SESSION["user_uuid"])): ?>
  <nav class="relative border-t pt-4 mt-4 text-sm text-gray-700">
    <button id="userMenuBtn" class="w-full text-center hover:text-green-700 transition">
      <div class="flex flex-col items-center gap-1">
<img id="profileImg" src="<?= htmlspecialchars($_SESSION['photo_url'] ?? 'images/default-profile.png') ?>?v=<?= time() ?>" alt="Foto de Perfil" class="w-20 h-20 rounded-full object-cover border border-gray-300" />        <p class="font-semibold"><?= htmlspecialchars($_SESSION["name"]) ?></p>
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

<!-- FORMULARIOS DEL DATASET -->
<main class="ml-64 p-10">
  <h1 class="text-4xl font-bold mb-8 text-gray-900">Añadir Nuevo Dataset</h1>      
  <div class="max-w-6xl mx-auto bg-white border border-gray-200 shadow-sm rounded-xl">
  <div class="flex border-b text-sm font-medium text-gray-600 bg-gray-50 rounded-t-xl overflow-x-auto">
    <button onclick="showTab('activity', this)" class="tab-btn font-semibold px-5 py-3 active">Información General del Proceso</button>
    <button onclick="showTab('imputsandoutputs', this)" class="tab-btn font-semibold px-5 py-3">Entradas y Salidas</button>
    <button onclick="showTab('exchanges', this)" class="tab-btn font-semibold px-5 py-3">Intercambios</button>
    <button onclick="showTab('documentation', this)" class="tab-btn font-semibold px-5 py-3">Documentación</button>
    <button onclick="showTab('parameters', this)" class="hidden tab-btn font-semibold px-5 py-3">Parámetros</button>
    <button onclick="showTab('tasks', this)" class="hidden tab-btn font-semibold px-5 py-3">Tareas</button>
  </div>

<!-- Contenido -->
<div class="p-6">

<!-- GENERAL PROCESS INFORMATION -->
<div id="activity" class="tab-content">
  <div class="mx-auto max-w-7xl px-4 lg:pr-[380px]">
    <h3 class="text-2xl font-extrabold text-black mb-4">Descripción de la Actividad</h3>
    
    <!-- UUID oculto -->
    <input type="hidden" id="uuidGen" name="uuid" readonly>
    
    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Nombre del Proceso<span class="text-green-700">*</span></label>
      <input type="text" id="processName" name="process_name" class="w-full border border-gray-300 px-4 py-2 rounded-md shadow-sm bg-gray-50 text-gray-700 text-sm" required placeholder="Ejemplo: Producción de acero">
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Descripción del Proceso</label>
      <textarea id="processDescription" name="process_description" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2" placeholder="Ejemplo: Proceso de fabricación de acero en horno eléctrico"></textarea>
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Categoría / Clasificación<span class="text-green-700">*</span></label>
      <select id="category" name="category" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
        <option value="">Selecciona una Categoría</option>
        <optgroup label="Alimentos">
          <option>Procesamiento y conservación de carne</option>
          <option>Procesamiento y conservación de pescado, crustáceos y moluscos</option>
          <option>Procesamiento y conservación de frutas y hortalizas</option>
          <option>Fabricación de aceites y grasas vegetales y animales</option>
          <option>Fabricación de productos lácteos</option>
          <option>Fabricación de productos de molinería de cereales</option>
          <option>Fabricación de almidones y productos obtenidos de cereales</option>
          <option>Productos de panadería</option>
          <option>Fabricación de azúcar</option>
          <option>Fabricación de cacao, chocolate y confitería</option>
          <option>Fabricación de pasta alimenticia</option>
          <option>Fabricación de comidas y platos preparados</option>
          <option>Otros productos alimenticios n.c.o.p.</option>
          <option>Fabricación de piensos preparados</option>
        </optgroup>
        <optgroup label="Agua">
          <option>Captación, tratamiento y distribución de agua</option>
        </optgroup>
        <optgroup label="Construcción">
          <option>Construcción de edificios</option>
          <option class="italic" disabled>Ingeniería civil</option>
          <option>Construcción de carreteras y ferrocarriles</option>
          <option>Construcción de proyectos de servicios públicos</option>
          <option>Otras obras de ingeniería civil</option>
          <option class="italic" disabled>Actividades de construcción especializada</option>
          <option>Demolición y preparación del terreno</option>
          <option>Instalaciones eléctricas</option>
          <option>Fontanería, calefacción y aire acondicionado</option>
          <option>Otras instalaciones</option>
          <option>Acabado de edificios</option>
          <option>Otras actividades especializadas</option>
        </optgroup>
        <optgroup label="Energía">
          <option>Generación, transmisión y distribución de electricidad</option>
          <option>Fabricación de gas; distribución de combustibles gaseosos por tuberías</option>
          <option>Suministro de vapor y aire acondicionado</option>
        </optgroup>
        <optgroup label="Residuos">
          <option>Alcantarillado</option>
          <option class="italic" disabled>Recogida, tratamiento y eliminación de residuos; recuperación de materiales</option>
          <option>Recogida de residuos no peligrosos</option>
          <option>Recogida de residuos peligrosos</option>
          <option>Tratamiento y eliminación de residuos no peligrosos</option>
          <option>Tratamiento y eliminación de residuos peligrosos</option>
          <option>Recuperación de materiales</option>
          <option>Remediación y otros servicios de gestión de residuos</option>
        </optgroup>
      </select>
      <input type="hidden" id="sector" name="sector">
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Comentario General</label>
      <textarea id="generalComment" name="general_comment" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2" placeholder="Ejemplo: Datos basados en planta en México"></textarea>
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Etiquetas</label>
      <input type="text" id="tags" name="tags" class="w-full border border-gray-300 px-4 py-2 rounded-md shadow-sm bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: acero, industrial, México">
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Tipo de Proceso<span class="text-green-700">*</span></label>
      <select id="typeOfProcess" name="type_of_process" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
        <option value="">Selecciona un Proceso</option>
        <option value="UNIT_PROCESS">Unit Process</option>
        <option value="SYSTEM_PROCESS">System Process</option>
      </select>
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Etapa del Ciclo de Vida</label>
      <select id="lifeCycleStage" name="life_cycle_stage" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
        <option>Selecciona una Etapa</option>
        <option>Extraction</option>
        <option>Production</option>
        <option>Transport</option>
        <option>Use</option>
        <option>End Of Life</option>
      </select>
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Unidad Funcional<span class="text-green-700">*</span></label>
      <input type="text" id="functionalUnit" name="functional_unit" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" required placeholder="Ejemplo: 1 kg de acero">
    </div>

    <!-- Geografía -->
    <h4 class="text-xl font-extrabold text-black mb-4 mt-6">Geografía<span class="text-green-700">*</span></h4>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Ubicación</label>
      <input id="country" name="location" type="text" readonly class="w-full border border-gray-300 px-4 py-2 rounded-md shadow-sm bg-gray-50 text-gray-700 text-sm" required placeholder="Ejemplo: México">
      <input type="hidden" id="countryIso" name="country_iso">
    </div>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Descripción</label>
      <textarea id="locationDescription" name="location_description" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2" required placeholder="Ejemplo: Planta ubicada en Monterrey, México"></textarea>
    </div>

    <!-- Tecnología -->
    <h4 class="text-xl font-extrabold text-black mb-4 mt-6">Tecnología</h4>

    <div class="mb-4">
      <label class="block font-semibold text-gray-700 mb-1">Descripción</label>
      <textarea id="technologyDescription" name="technology_description" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2" placeholder="Ejemplo: Horno de arco eléctrico"></textarea>
    </div>
    
    <div class="flex flex-col items-center gap-2 mt-2">
        <button type="button" onclick="guardarBorrador()" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-md text-sm mt-4">Guardar Borrador</button>
        <button type="button" onclick="guardarProceso()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-md text-sm mt-4">Guardar en la Base de Datos</button>
    </div>
  </div>
</div>

<!-- INPUTS AND OUTPUTS -->
<div id="imputsandoutputs" class="tab-content hidden">
  <div>
    <!-- Input PRINCIPAL -->
    <div class="mx-auto max-w-7xl px-4 lg:pr-[380px]">
      <h3 class="text-2xl font-extrabold text-black mb-4">Entradas</h3>
      <form id="inputForm" class="grid gap-4" onsubmit="event.preventDefault(); guardarInputPrincipal();">
        <input type="hidden" id="uuidInput" name="uuidInput">
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Nombre del Flujo<span class="text-green-700">*</span></label>
          <input type="text" id="inputResourceName" name="input_resource_name" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Electricidad" required>
        </div>
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Categoría<span class="text-green-700">*</span></label>
          <input type="text" id="inputCategory" name="input_category" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Energía">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">Cantidad<span class="text-green-700">*</span></label>
          <input type="number" id="inputQuantity" name="input_quantity" step="0.01" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: 200" required>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">Unidad<span class="text-green-700">*</span></label>
          <select id="inputUnit" name="input_unit" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" required>
            <option value="">Selecciona una unidad</option>
            <option>kg</option>
            <option>MJ</option>
            <option>m3</option>
            <option>kWh</option>
            <option>L</option>
            <option>ton</option>
            <option>m2</option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">Fuente de Datos</label>
          <input type="text" id="inputDataSource" name="input_data_source" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Ecoinvent">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">Descripción</label>
          <textarea id="inputCommentary" name="input_commentary" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2" placeholder="Ejemplo: Electricidad de la red nacional"></textarea>
        </div>

        <!-- SOLO UN BOTÓN para el input principal -->
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-md text-sm">Guardar Entrada</button>
      </form>
              <!-- TABLA TIPO OPENLCA -->
        <div class="mt-8 border-t pt-6">
            <h5 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-green-600"></i>
                Entradas Agregadas (<span id="countInputs">0</span>)
            </h5>
            <div class="overflow-x-auto border rounded-lg">
                <table class="w-full text-xs">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="border px-2 py-2 text-left">Nombre del Flujo</th>
                            <th class="border px-2 py-2 text-left">Categoría</th>
                            <th class="border px-2 py-2 text-right">Cantidad</th>
                            <th class="border px-2 py-2 text-left">Unidad</th>
                            <th class="border px-2 py-2 text-left">Fuente de Datos</th>
                            <th class="border px-2 py-2 text-left">Descripción</th>
                            <th class="border px-2 py-2 text-center w-20">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaInputs">
                        <tr>
                            <td colspan="7" class="text-center py-6 text-gray-400 italic">
                                No hay entradas agregadas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
        <!-- BOTÓN GUARDAR INPUTS -->
    <div class="p-6 bg-green-50 border-2 border-green-300 rounded-xl">
        <div class="text-center">
            <button type="button" onclick="guardarSoloInputs()" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-bold text-base shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 flex items-center justify-center gap-2">
                <i data-lucide="database" class="w-5 h-5"></i>
                Guardar Entradas a Base de Datos
            </button>
        </div>
    </div>
    </div>
    <!-- Output principal -->
    <div class="mt-4 mx-auto max-w-7xl px-4 lg:pr-[380px]">
      <h3 class="text-2xl font-extrabold text-black mb-4">Salidas</h3>
      <form id="outputForm" class="grid gap-4" onsubmit="event.preventDefault(); guardarOutputPrincipal();">
        <input type="hidden" id="uuidOutput" name="uuidOutput">
        
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Nombre del Flujo<span class="text-green-700">*</span></label>
          <input type="text" id="outputEmissionName" name="output_emission_name" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: CO2" required>
        </div>
        
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Tipo de Emisión<span class="text-green-700">*</span></label>
          <select id="outputEmissionType" name="output_emission_type" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" required>
            <option value="">Selecciona un Tipo</option>
            <option>Air Emissions</option>
            <option>Emissions to Water</option>
            <option>Solid Waste</option>
            <option>Product</option> 
          </select>
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Categoría<span class="text-green-700">*</span></label>
          <input type="text" id="outputCategory" name="output_category" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Emisiones al aire">
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Compartimento (Medio)<span class="text-green-700">*</span></label>
          <input type="text" id="outputCompartment" name="output_compartment" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: air">
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Subcompartimento<span class="text-green-700">*</span></label>
          <input type="text" id="outputSubcompartment" name="output_subcompartment" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: low population density">
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Cantidad<span class="text-green-700">*</span></label>
          <input type="number" id="outputQuantity" name="output_quantity" step="0.01" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: 1.5" required>
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Unidad<span class="text-green-700">*</span></label>
          <select id="outputUnit" name="output_unit" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" required>
            <option value="">Selecciona una Unidad</option>
            <option>kg</option>
            <option>MJ</option>
            <option>m3</option>
            <option>kWh</option>
            <option>L</option>
            <option>ton</option>
            <option>m2</option>
          </select>
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Fuente de Datos</label>
          <input type="text" id="outputDataSource" name="output_data_source" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Ecoinvent">
        </div>
    
        <div class="flex items-center">
          <input type="checkbox" id="outputIsReference" name="output_reference_flow" class="mr-2">
          <label for="outputIsReference" class="font-semibold text-gray-700">¿Es flujo de Referencia?<span class="text-green-700">*</span></label>
        </div>
    
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Descripción</label>
          <textarea id="outputCommentary" name="output_commentary" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2" placeholder="Ejemplo: Emisión de CO2 por combustión"></textarea>
        </div>
    
        <!-- SOLO UN BOTÓN para el output principal -->
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-md text-sm">Guardar Salida</button>
      </form>
        <!-- TABLA OPENLCA -->
        <div class="mt-8 border-t pt-6">
            <h5 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i data-lucide="arrow-up-right" class="w-4 h-4 text-blue-600"></i>
                Salidas Agregadas (<span id="countOutputs">0</span>)
            </h5>
            <div class="overflow-x-auto border rounded-lg">
                <table class="w-full text-xs">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="border px-2 py-2 text-left">Nombre del Flujo</th>
                            <th class="border px-2 py-2 text-left">Tipo de Emisión</th>
                            <th class="border px-2 py-2 text-left">Categoría</th>
                            <th class="border px-2 py-2 text-left">Compartimento</th>
                            <th class="border px-2 py-2 text-left">Subcompartimento</th>
                            <th class="border px-2 py-2 text-right">Cantidad</th>
                            <th class="border px-2 py-2 text-left">Unidad</th>
                            <th class="border px-2 py-2 text-left">Fuente</th>
                            <th class="border px-2 py-2 text-center">Ref?</th>
                            <th class="border px-2 py-2 text-left">Descripción</th>
                            <th class="border px-2 py-2 text-center w-20">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaOutputs">
                        <tr>
                            <td colspan="11" class="text-center py-6 text-gray-400 italic">
                                No hay salidas agregadas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
            <!-- BOTÓN GUARDAR OUTPUTS -->
    <div class="p-6 bg-blue-50 border-2 border-blue-300 rounded-xl">
        <div class="text-center">
            <button type="button" onclick="guardarSoloOutputs()" 
                    class="w-full bg-green-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold text-base shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 flex items-center justify-center gap-2">
                <i data-lucide="database" class="w-5 h-5"></i>
                Guardar Salidas a Base de Datos
            </button>
        </div>
    </div>
    </div>
    </div>

<!-- EXCHANGES -->
<div id="exchanges" class="tab-content hidden">
  <div id="exchangesContainer" class="hidden"></div>
  
  <!-- Contenedor principal -->
  <div class="mx-auto max-w-7xl px-4 lg:pr-[380px]">
    <h3 class="text-2xl font-extrabold text-black mb-4">Intercambios</h3>
    
    <!-- Sección de vinculación -->
    <section class="rounded-2xl border border-green-100 bg-white shadow-md p-6 mb-4">
      <div class="mb-4">
        <label class="block font-semibold text-gray-800 mb-1">Entradas / Salidas</label>
        <p class="text-xs text-gray-500 mb-3">
          Vincula flujos existentes para las entradas y salidas.
        </p>
        <button type="button" onclick="openLinkModal()" class="inline-flex items-center gap-2 text-white text-sm font-semibold px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-300 transition-all">
          <i data-lucide="link" class="w-4 h-4"></i>
          Seleccionar Entrada / Salida
        </button>
      </div>
      
      <!-- Lista de exchanges vinculados -->
      <div id="ioSummary" class="space-y-2 mt-4 scroll">
        <!-- Los exchanges se agregarán aquí dinámicamente -->
      </div>
    </section>

    <!-- Botón de guardar -->
    <div class="mt-4">
      <button 
        type="button" 
        onclick="guardarExchanges()" 
        class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition-all"
      >
        Guardar Intercambios
      </button>
    </div>
  </div>
</div>

<!-- DOCUMENTATION -->
<div id="documentation" class="tab-content hidden">
  <div class="mx-auto max-w-7xl px-4 lg:pr-[380px]">
    <h2 class="text-2xl font-extrabold text-black mb-4">Documentación</h2>
      <form id="documentationForm" onsubmit="event.preventDefault(); guardarDocumentacion();">
        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Año de Referencia<span class="text-green-700">*</span></label>
          <input type="text" id="referenceYear" name="referenceYear" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: 2020-06-02">
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Valido hasta</label>
          <input type="text" id="validuntil" name="validuntil" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: 2023-06-01">
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Propietario de Datos<span class="text-green-700">*</span></label>
          <input type="text" id="dataOwner" name="dataOwner" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Empresa Acero S.A.">
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Información de Contacto<span class="text-green-700">*</span></label>
          <input type="text" id="contactInformation" name="contactInformation" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: contacto@acero.com">
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Fuente de Datos</label>
          <input type="text" id="dataSource" name="dataSource" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Ecoinvent, mediciones internas">
        </div>

        <div class="mb-4 relative">
          <label class="block font-semibold text-gray-700 mb-1">Calidad de Datos</label>
            <div class="flex items-center gap-2">
              <input type="text" id="dataQualityIndicators" name="dataQualityIndicators" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: 1,1,1,1,1" readonly>
              <button type="button" onclick="abrirModalDataQuality()" class="text-blue-600 hover:underline text-xs">Seleccionar</button>
            </div>
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Cumplimiento con Normas</label>
          <input type="text" id="complianceStandards" name="complianceStandards" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: ISO 14040, ILCD">
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Estado de revisión<span class="text-green-700">*</span></label>
          <select id="reviewStatus" name="reviewStatus" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" required>
            <option value="">Selecciona un estatus</option>
            <option selected>Sin revisar</option>
            <option>Revisado por consultora externa</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Condiciones de Acceso<span class="text-green-700">*</span></label>
          <select id="accessConditions" name="accessConditions" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" required>
            <option value="">Selecciona una condición</option>
            <option>Restringida</option>
            <option>Pública</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block font-semibold text-gray-700 mb-1">Licencia<span class="text-green-700">*</span></label>
          <input type="text" id="license" name="license" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" placeholder="Ejemplo: Propietaria">
        </div>

        <button type="button" onclick="guardarTodoDocumentacion()" class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition">
          Guardar Documentación
        </button>      
        </form>
  </div>
</div>

<!-- PARAMETERS -->
<div id="parameters" class="tab-content hidden">
  <div class="mb-4">
    <h3 class="text-2xl font-extrabold text-black mb-4">Parameters</h3>
    <form id="parametersForm" class="grid gap-4">
      <input type="text" id="uuidParameterRecord" name="parameter_uuid[]" class="hidden">

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Parameter Name</label>
        <input type="text" name="param-name[]" placeholder="Ejemplo: Eficiencia energética" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Type of parameter</label>
        <select name="param-type[]" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
          <option value="">Select a type</option>
          <option value="global">Global (Affects the entire process)</option>
          <option value="local">Local (Specific to a flow/process)</option>
        </select>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Default Value</label>
        <input type="text" name="param-value[]" placeholder="Ejemplo: 0.85" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Unit</label>
        <input type="text" name="param-unit[]" placeholder="Ejemplo: %" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Description</label>
        <textarea name="param-description[]" placeholder="Ejemplo: Eficiencia del horno eléctrico" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2"></textarea>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Uncertainty (Optional)</label>
        <input type="text" name="param-uncertainty[]" placeholder="Ejemplo: ±10%" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Range (Optional)</label>
        <input type="text" name="param-range[]" placeholder="Ejemplo: 0.8-0.9" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Formula (Optional)</label>
        <input type="text" name="param-formula[]" placeholder="Ejemplo: Consumo_energía * 0.85" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
      </div>

      <div id="parametersContainer"></div>

      <div class="flex gap-2">
        <button type="button" onclick="addParameter()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-md text-sm">Add Parameter</button>
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-md text-sm">Save Parameters</button>
      </div>
    </form>
  </div>
</div>

<!-- TASKS -->
<div id="tasks" class="tab-content hidden">
  <div class="mb-4">
    <h3 class="text-2xl font-extrabold text-black mb-4">Tasks</h3>
    <form id="taskForm" class="grid gap-4">
      <div>
        <label class="block font-semibold text-gray-700 mb-1">Task Description</label>
        <textarea name="task-description" placeholder="Ejemplo: Recolectar datos de consumo energético" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="1"></textarea>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Context of Use</label>
        <textarea name="task-purpose" placeholder="Ejemplo: Usado para calcular impacto ambiental" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="1"></textarea>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Limitations of Use (Optional)</label>
        <textarea name="task-limitations" placeholder="Ejemplo: Datos válidos solo para México" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2"></textarea>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">Relationship with Other Datasets (Optional)</label>
        <textarea name="task-related-datasets" placeholder="Ejemplo: Vinculado a dataset de electricidad" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2"></textarea>
      </div>

      <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-md text-sm">Save Task</button>
    </form>
  </div>
</div>

</div>
</div>
</main>

<!-- Modales -->
<!-- Modal de la tabla de Calidad de Datos -->
<div id="modalDataQuality" class="fixed inset-0 bg-black/30 backdrop-blur-sm bg-opacity-40 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl p-6 relative">
    <button onclick="cerrarModalDataQuality()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-2xl font-bold mb-6 text-center">Data quality Indicators</h2>
    <div class="overflow-x-auto">
      <form id="dataQualityForm">
        <table class="w-full text-sm border border-black text-center select-none">
          <thead>
            <tr class="bg-gray-50">
              <th class="border px-3 py-2 font-semibold text-left">Indicator</th>
              <th class="border px-3 py-2 font-semibold">1</th>
              <th class="border px-3 py-2 font-semibold">2</th>
              <th class="border px-3 py-2 font-semibold">3</th>
              <th class="border px-3 py-2 font-semibold">4</th>
              <th class="border px-3 py-2 font-semibold">5</th>
            </tr>
          </thead>
          <tbody>
            <!-- Flow Reliability -->
            <tr>
              <td class="border px-3 py-2 text-left font-semibold bg-gray-50">Flow Reliability</td>
              <td class="border px-3 py-2">
                <input type="radio" name="frflow" value="1" class="dq-radio mb-2" />
                <textarea name="frflow_1" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frflow" value="2" class="dq-radio mb-2" />
                <textarea name="frflow_2" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frflow" value="3" class="dq-radio mb-2" />
                <textarea name="frflow_3" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frflow" value="4" class="dq-radio mb-2" />
                <textarea name="frflow_4" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frflow" value="5" class="dq-radio mb-2" />
                <textarea name="frflow_5" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
            </tr>
            
            <!-- Temporal Correlation -->
            <tr>
              <td class="border px-3 py-2 text-left font-semibold bg-gray-50">Temporal Correlation</td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtemp" value="1" class="dq-radio mb-2" />
                <textarea name="frtemp_1" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtemp" value="2" class="dq-radio mb-2" />
                <textarea name="frtemp_2" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtemp" value="3" class="dq-radio mb-2" />
                <textarea name="frtemp_3" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtemp" value="4" class="dq-radio mb-2" />
                <textarea name="frtemp_4" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtemp" value="5" class="dq-radio mb-2" />
                <textarea name="frtemp_5" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
            </tr>
            
            <!-- Geographical Correlation -->
            <tr>
              <td class="border px-3 py-2 text-left font-semibold bg-gray-50">Geographical Correlation</td>
              <td class="border px-3 py-2">
                <input type="radio" name="frgeo" value="1" class="dq-radio mb-2" />
                <textarea name="frgeo_1" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frgeo" value="2" class="dq-radio mb-2" />
                <textarea name="frgeo_2" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frgeo" value="3" class="dq-radio mb-2" />
                <textarea name="frgeo_3" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frgeo" value="4" class="dq-radio mb-2" />
                <textarea name="frgeo_4" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frgeo" value="5" class="dq-radio mb-2" />
                <textarea name="frgeo_5" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
            </tr>
            
            <!-- Technological Correlation -->
            <tr>
              <td class="border px-3 py-2 text-left font-semibold bg-gray-50">Technological Correlation</td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtec" value="1" class="dq-radio mb-2" />
                <textarea name="frtec_1" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtec" value="2" class="dq-radio mb-2" />
                <textarea name="frtec_2" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtec" value="3" class="dq-radio mb-2" />
                <textarea name="frtec_3" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtec" value="4" class="dq-radio mb-2" />
                <textarea name="frtec_4" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frtec" value="5" class="dq-radio mb-2" />
                <textarea name="frtec_5" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
            </tr>
            
            <!-- Data Collection Methods -->
            <tr>
              <td class="border px-3 py-2 text-left font-semibold bg-gray-50">Data Collection Methods</td>
              <td class="border px-3 py-2">
                <input type="radio" name="frdata" value="1" class="dq-radio mb-2" />
                <textarea name="frdata_1" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frdata" value="2" class="dq-radio mb-2" />
                <textarea name="frdata_2" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frdata" value="3" class="dq-radio mb-2" />
                <textarea name="frdata_3" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frdata" value="4" class="dq-radio mb-2" />
                <textarea name="frdata_4" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
              <td class="border px-3 py-2">
                <input type="radio" name="frdata" value="5" class="dq-radio mb-2" />
                <textarea name="frdata_5" placeholder="Escribe aquí..." class="w-full border border-gray-300 rounded p-1 text-sm resize-none" rows="2"></textarea>
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Botón de guardar -->
        <div class="mt-6 flex justify-end gap-3">
          <button type="button" onclick="cerrarModalDataQuality()" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-lg transition">
            Cancelar
          </button>
          <button type="button" onclick="guardarDataQuality()" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition">
            Guardar
          </button>

        </div>
      </form>
    </div>
  </div>
</div>

<!-- Vincular dataset existente -->
<div id="linkModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-green-900/20 backdrop-blur-sm" onclick="closeLinkModal()"></div>
  <div class="relative h-screen flex items-center justify-center p-6 pointer-events-none">
    <div role="dialog" aria-modal="true" aria-labelledby="linkModalTitle" class="pointer-events-auto w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-green-100 overflow-hidden max-h-[calc(100svh-4cm)]">
      <div class="h-1 bg-gradient-to-r from-green-500 to-green-400"></div>
      <div class="p-6 ">
        <h3 id="linkModalTitle" class="text-lg font-semibold text-gray-900">
          Vincular dataset existente
        </h3>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
          <div class="sm:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
            <div class="relative">
              <input 
                id="searchQ" 
                type="text" 
                placeholder="Ej. Tap water" 
                class="w-full rounded-xl border border-green-100 bg-gray-50/60 pl-3 pr-3 py-2 text-sm text-gray-800 focus:border-green-300 focus:ring-2 focus:ring-green-200 transition"
                onkeyup="searchDatasets()"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <select 
              id="linkDirection" 
              class="w-full rounded-xl border border-green-100 bg-gray-50/60 px-3 py-2 text-sm text-gray-800 focus:border-green-300 focus:ring-2 focus:ring-green-200 transition"
            >
              <option value="input">Input</option>
              <option value="output">Output</option>
            </select>
          </div>
        </div>

        <!-- RESULTADOS CON SCROLL FIJO -->
        <div class="mt-4 rounded-xl border border-green-100 bg-white overflow-hidden">
          <div id="searchResults" class="divide-y divide-green-50" style="max-height: 300px; overflow-y: auto;">
            <div class="p-8 text-center text-sm text-gray-400 italic">
              Escribe para buscar datasets
            </div>
          </div>
        </div>

        <!-- Loading indicator -->
        <div id="loadingIndicator" class="hidden mt-4 text-center py-4">
          <div class="inline-block w-6 h-6 border-2 border-green-600 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-sm text-gray-500 mt-2">Buscando...</p>
        </div>

        <!-- Botón Ver más -->
        <button 
          id="loadMoreBtn" 
          onclick="loadMoreResults()" 
          class="hidden w-full mt-3 px-4 py-2 rounded-xl border border-green-200 bg-white text-sm font-medium text-gray-700 hover:bg-green-50 transition"
        >
          Ver más resultados
        </button>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
          <input 
            id="linkComment" 
            type="text" 
            placeholder="Añade un comentario" 
            class="w-full rounded-xl border border-green-100 bg-gray-50/60 px-3 py-2 text-sm text-gray-800 focus:border-green-300 focus:ring-2 focus:ring-green-200"
          />
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button 
            onclick="closeLinkModal()" 
            class="px-4 py-2 rounded-xl border border-green-200 bg-white text-sm font-medium text-green-700 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
          >
            Cancelar
          </button>
          <button 
            id="confirmLinkBtn"
            onclick="confirmAddLink()" 
            disabled
            class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold shadow hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-300 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Agregar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Configuración -->
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
<!-- Cambiar foto -->
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
<!-- Cambiar contraseña -->
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
<!-- Cambiar contraseña -->
<div id="changePasswordModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Cambiar contraseña</h2>
    <input type="password" placeholder="Nueva contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" />
    <input type="password" placeholder="Confirmar contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" />
    <button onclick="alert('Contraseña cambiada exitosamente')" class="w-full bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition">Guardar</button>
    <div class="text-right mt-4">
      <button onclick="closeModal('changePasswordModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
    </div>
  </div>
</div>

<script>
// Al cargar la página, verificar si hay un borrador
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const draftUuid = urlParams.get('draft_uuid');
    
    if (draftUuid) {
        cargarBorrador(draftUuid);
    } else {
        generateUUIDs(); // Generar nuevos UUIDs para dataset nuevo
    }
});

function cargarBorrador(uuid) {
  fetch(`https://ciclodevida.mx/php_actions/loadDraft.php?uuid=${uuid}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        alert(`Error: ${data.message}`);
        return;
      }
      
      console.log('📄 Datos recibidos del servidor:', data);
      
      // ✅ FUNCIÓN HELPER PARA SETEAR VALORES DE FORMA SEGURA
      function setValueSafe(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
          element.value = value || '';
        } else {
          console.warn(`⚠️ Elemento no encontrado: ${elementId}`);
        }
      }
      
      // ✅ CARGAR PROCESO
      if (data.process) {
        setValueSafe('uuidGen', data.process.uuid);
        setValueSafe('processName', data.process.name);
        setValueSafe('processDescription', data.process.description);
        setValueSafe('category', data.process.category);
        setValueSafe('generalComment', data.process.description);
        setValueSafe('tags', data.process.tags_text);
        setValueSafe('typeOfProcess', data.process.process_type);
        setValueSafe('lifeCycleStage', data.process.life_cycle_stage);
        setValueSafe('country', data.process.location);
        setValueSafe('locationDescription', data.process.geo_desc);
        setValueSafe('technologyDescription', data.process.tech_desc);
        
        // Disparar evento para cargar sector
        const categorySelect = document.getElementById('category');
        if (categorySelect && data.process.sector_principal) {
          categorySelect.dispatchEvent(new Event('change'));
          setTimeout(() => {
            setValueSafe('sector', data.process.sector_principal);
          }, 100);
        }
      }
      
      // ✅ CARGAR DOCUMENTACIÓN
      if (data.documentation) {
        console.log('📋 Cargando documentación...');
        
        // Parsear access_use_restrictions si viene concatenado (compatibilidad con datos viejos)
        const restrictions = data.documentation.access_use_restrictions || '';
        const reviewMatch = restrictions.match(/Review:\s*([^|]+)/);
        const accessMatch = restrictions.match(/Access:\s*([^|]+)/);
        const licenseMatch = restrictions.match(/License:\s*([^|]+)/);
        const complianceMatch = restrictions.match(/Compliance:\s*(.+)/);
        
        // Campos básicos
        setValueSafe('functionalUnit', data.documentation.intended_application);
        setValueSafe('referenceYear', data.documentation.creation_date?.split(' ')[0] || '');
        setValueSafe('validuntil', data.process?.valid_until || '');
        setValueSafe('dataSource', data.documentation.sources_text);
        setValueSafe('dataQualityIndicators', data.process?.dq_data_quality || '');
        
        // ✅ Nuevos campos (si existen en BD, sino usa parseado)
        setValueSafe('dataOwner', data.documentation.data_owner || '');
        setValueSafe('contactInformation', data.documentation.contact_information || '');
        
        // ✅ Campos separados (priorizar columnas separadas, fallback a parseado)
        setValueSafe('reviewStatus', data.documentation.review_status || (reviewMatch ? reviewMatch[1].trim() : ''));
        setValueSafe('accessConditions', data.documentation.access_conditions || (accessMatch ? accessMatch[1].trim() : ''));
        setValueSafe('license', data.documentation.license || (licenseMatch ? licenseMatch[1].trim() : ''));
        setValueSafe('complianceStandards', complianceMatch ? complianceMatch[1].trim() : '');
        
        console.log('✅ Documentación cargada');
      }
      
      // ✅ CARGAR INPUTS
      if (data.inputs && data.inputs.length > 0) {
        console.log('📥 Cargando ' + data.inputs.length + ' inputs...');
        todosLosInputs = data.inputs.map(input => ({
          uuid: input.uuid,
          resourceName: input.flow_name || '',
          category: input.category || '',
          quantity: parseFloat(input.amount) || 0,
          unit: input.unit_name || '',
          datasource: input.data_source || '',
          commentary: input.description || ''
        }));
        
        console.log('✅ Inputs mapeados:', todosLosInputs);
        
        // Renderizar la tabla de inputs
        if (typeof renderTablaInputs === 'function') {
          renderTablaInputs();
        } else if (typeof actualizarListaInputs === 'function') {
          actualizarListaInputs();
        } else {
          console.warn('⚠️ No se encontró función de renderizado de inputs');
        }
      }
      
      // ✅ CARGAR OUTPUTS
      if (data.outputs && data.outputs.length > 0) {
        console.log('📤 Cargando ' + data.outputs.length + ' outputs...');
        todosLosOutputs = data.outputs.map(output => ({
          uuid: output.uuid,
          emissionName: output.flow_name || '',
          emissionType: 'ELEMENTARY_FLOW',
          category: output.category || '',
          compartment: output.compartment || '',
          subcompartment: output.subcompartment || '',
          quantity: parseFloat(output.amount) || 0,
          unit: output.unit_name || '',
          datasource: output.data_source || '',
          commentary: output.description || '',
          isReference: output.is_reference == 1
        }));
        
        console.log('✅ Outputs mapeados:', todosLosOutputs);
        
        // Renderizar la tabla de outputs
        if (typeof renderTablaOutputs === 'function') {
          renderTablaOutputs();
        } else if (typeof actualizarListaOutputs === 'function') {
          actualizarListaOutputs();
        } else {
          console.warn('⚠️ No se encontró función de renderizado de outputs');
        }
      }
      
       // CARGAR DATA QUALITY INDICATORS
if (data.dq_indicators && data.dq_indicators.length > 0) {
  console.log('📊 Cargando ' + data.dq_indicators.length + ' DQ indicators...');
  
  // Agrupar por indicator_type
  const dqByType = {};
  data.dq_indicators.forEach(indicator => {
    const type = indicator.indicator_type;
    if (!dqByType[type]) {
      dqByType[type] = [];
    }
    dqByType[type].push(indicator);
  });
  
  console.log('📊 DQ agrupados:', dqByType);
  
  // Para cada tipo de indicador
  Object.keys(dqByType).forEach(type => {
    const indicators = dqByType[type];
    const typeNormalized = type.replace(/ /g, '_').toUpperCase();
    
    // Mapear indicator_type a radioName
    let radioName = '';
    if (typeNormalized === 'FLOW_RELIABILITY') radioName = 'frflow';
    else if (typeNormalized === 'TEMPORAL_CORRELATION') radioName = 'frtemp';
    else if (typeNormalized === 'GEOGRAPHICAL_CORRELATION') radioName = 'frgeo';
    else if (typeNormalized === 'TECHNOLOGICAL_CORRELATION') radioName = 'frtec';
    else if (typeNormalized === 'DATA_COLLECTION_METHODS') radioName = 'frdata';
    
    if (radioName) {
      // ✅ Procesar TODOS los indicadores de este tipo
      indicators.forEach(indicator => {
        const level = indicator.score_level;
        const description = indicator.description || '';
        
        // Si está seleccionado, marcar el radio
        if (indicator.is_selected == 1) {
          const radioElement = document.querySelector(`input[name="${radioName}"][value="${level}"]`);
          if (radioElement) {
            radioElement.checked = true;
            console.log(`✅ Radio marcado: ${radioName} = ${level}`);
          }
        }
        
        // Llenar el textarea correspondiente (SIEMPRE, si hay descripción)
        if (description) {
          const textareaName = `${radioName}_${level}`;
          const textareaElement = document.querySelector(`textarea[name="${textareaName}"]`);
          if (textareaElement) {
            textareaElement.value = description;
            console.log(`✅ Descripción cargada en: ${textareaName}`);
          }
        }
      });
    }
  });
  
  console.log('✅ DQ Indicators procesados');
} else {
  console.log('⚠️ No hay DQ indicators para cargar');
}
      
      console.log('✅ Borrador cargado completamente');
      alert('✅ Borrador cargado. Puedes continuar editando.');
      
    })
    .catch(err => {
      console.error('❌ Error al cargar borrador:', err);
      alert('Error al cargar el borrador: ' + err.message);
    });
}
// Arrays para inputs y outputs
let todosLosInputs = [];
let todosLosOutputs = [];
let editingInputIndex = null;
let editingOutputIndex = null;

//Logica del UUID
function generateUUIDs() {
  document.getElementById("uuidGen").value = self.crypto.randomUUID();
  document.getElementById("uuidInput").value = self.crypto.randomUUID();
  document.getElementById("uuidOutput").value = self.crypto.randomUUID();
}
window.onload = generateUUIDs;

//Logica de las pestañas
function showTab(tabId, clickedBtn) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active', 'border-b-2', 'border-blue-500', 'text-blue-600'));
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
    document.getElementById(tabId).classList.remove('hidden');

    const btns = document.querySelectorAll('.tab-btn');
    btns.forEach(btn => {
        if (btn.textContent.trim().replace(/\s+/g, '').toLowerCase().includes(tabId)) {
          btn.classList.add('active', 'border-b-2', 'border-blue-500', 'text-blue-600');
        }
    });
    clickedBtn.classList.add('active', 'border-b-2', 'border-blue-500', 'text-blue-600')
}

lucide.createIcons();

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('flex');
    modal.classList.add('hidden');
  }
}

function toggleDropdown(menuId) {
  const menu = document.getElementById(menuId);
  if (menu) {
    menu.classList.toggle('hidden');
  }
}

// Esperar a que el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
  
  // Menú de usuario (dropdown del perfil)
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');
  
  if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      userDropdown.classList.toggle('hidden');
    });
    
    // Cerrar al hacer clic fuera
    document.addEventListener('click', (e) => {
      if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.classList.add('hidden');
      }
    });
  }
  
  // Botón: Cambiar foto de perfil
  const btnChangePhoto = document.getElementById('btnChangePhoto');
  if (btnChangePhoto) {
    btnChangePhoto.addEventListener('click', () => {
      closeModal('configModal');
      openModal('changePhotoModal');
    });
  }
  
  // Botón: Cambiar contraseña
  const btnChangePassword = document.getElementById('btnChangePassword');
  if (btnChangePassword) {
    btnChangePassword.addEventListener('click', () => {
      closeModal('configModal');
      openModal('changePasswordModal');
    });
  }
  
  // Botón: Solicitar permisos de administrador
  const btnRequestAdmin = document.getElementById('btnRequestAdmin');
  if (btnRequestAdmin) {
    btnRequestAdmin.addEventListener('click', function() {
      fetch('request_admin.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            this.disabled = true;
            this.textContent = 'Solicitud enviada';
            this.classList.add('opacity-50', 'cursor-not-allowed');
          }
        })
        .catch(err => {
          console.error('Error:', err);
          alert('Error al enviar la solicitud');
        });
    });
  }
  
  // Cerrar menú de dataset al hacer clic fuera
  document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('datasetMenu');
    const button = event.target.closest('button');
    
    if (!button && dropdown && !dropdown.classList.contains('hidden')) {
      dropdown.classList.add('hidden');
    }
  });
  
  // Preview de imagen de perfil (opcional)
  const profileUpload = document.getElementById('profileUpload');
  const profileImg = document.getElementById('profileImg');
  
  if (profileUpload && profileImg) {
    profileUpload.addEventListener('change', (e) => {
      if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = (event) => {
          profileImg.src = event.target.result;
        };
        reader.readAsDataURL(e.target.files[0]);
      }
    });
  }
});


$('#country').countrySelect();
$('#country').on('countryselect', function (e, country) {
  const code = country.iso2.toUpperCase();
  $('#codeDisplay').text(code);
});

function generateUUID() {
  return self.crypto.randomUUID();
}

window.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById("uuidParameterRecord"))
    document.getElementById("uuidParameterRecord").value = generateUUID();
});

let parameterCount = 0;
function addParameter() {
  parameterCount++;
  const container = document.getElementById("parametersContainer");
  const uuid = generateUUID();

  const div = document.createElement("div");
  div.className = "border border-gray-300 rounded-xl p-4 mb-4 grid grid-cols-1 gap-4 relative";
  div.id = `parameter-${parameterCount}`;
  div.innerHTML = `
    <input type="text" name="parameter_uuid[]" value="${uuid}" class="hidden">
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Parameter Name</label>
      <input type="text" name="param-name[]" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Type of parameter</label>
      <select name="param-type[]" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
        <option value="">Select a type</option>
        <option value="global">Global (Affects the entire process)</option>
        <option value="local">Local (Specific to a flow/process)</option>
      </select>
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Default Value</label>
      <input type="text" name="param-value[]" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Unit</label>
      <input type="text" name="param-unit[]" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Description</label>
      <textarea name="param-description[]" required class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm" rows="2"></textarea>
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Uncertainty (Optional)</label>
      <input type="text" name="param-uncertainty[]" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Range (Optional)</label>
      <input type="text" name="param-range[]" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
    </div>
    <div>
      <label class="block font-semibold text-gray-700 mb-1">Formula (Optional)</label>
      <input type="text" name="param-formula[]" class="w-full px-4 py-2 rounded-md shadow-sm border border-gray-300 bg-gray-50 text-gray-700 text-sm">
    </div>
    <button type="button" onclick="deleteParameter('parameter-${parameterCount}')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">
      Eliminar
    </button>
  `;
  container.appendChild(div);
}

function deleteParameter(id) {
  const element = document.getElementById(id);
  if (element) element.remove();
}

//-------- CONEXION DE TODOS LOS FORMULARIOS AL BACKEND --------//
// ==========================================
// SINCRONIZAR SECTOR CON CATEGORÍA
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
  const categorySelect = document.getElementById('category');
  const sectorInput = document.getElementById('sector');
  
  if (categorySelect && sectorInput) {
    categorySelect.addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const optgroup = selectedOption.closest('optgroup');
      const sector = optgroup ? optgroup.label : '';
      
      sectorInput.value = sector;
      console.log('Categoría seleccionada:', selectedOption.text);
      console.log('Sector asignado:', sector);
    });
  }
});

//-------- FORMULARIO DEL PROCESO PADRE --------//
function guardarProceso(isDraft = false) {
    // Validar campos obligatorios
    const camposObligatorios = ['processName', 'category', 'typeOfProcess', 'functionalUnit', 'country'];
    let camposVacios = [];
    
    camposObligatorios.forEach(id => {
        const el = document.getElementById(id);
        if (!el || !el.value || el.value.trim() === '' || 
            el.value === 'Selecciona un Proceso' || 
            el.value === 'Selecciona una Categoría') {
            camposVacios.push(id);
        }
    });

    if (camposVacios.length > 0) {
        alert('Por favor llena todos los campos obligatorios: Nombre del Proceso, Categoría, Tipo de Proceso, Unidad Funcional y Ubicación');
        document.getElementById(camposVacios[0]).focus();
        return;
    }

    // Recopilar datos
    const formData = new FormData();
    formData.append('uuid', document.getElementById('uuidGen').value);
    formData.append('processname', document.getElementById('processName').value);
    formData.append('processdescription', document.getElementById('processDescription').value);
    formData.append('category', document.getElementById('category').value);
    formData.append('generalcomment', document.getElementById('generalComment').value);
    formData.append('tags', document.getElementById('tags').value);
    formData.append('typeofprocess', document.getElementById('typeOfProcess').value);
    formData.append('lifecyclestage', document.getElementById('lifeCycleStage').value);
    formData.append('functionalunit', document.getElementById('functionalUnit').value);
    formData.append('location', document.getElementById('country').value);
    formData.append('locationdescription', document.getElementById('locationDescription').value);
    formData.append('technologydescription', document.getElementById('technologyDescription').value);
    formData.append('sector', document.getElementById('sector').value);
    
        // Valid until
const validUntilValue = document.getElementById('validuntil')?.value;
console.log('🔍 Valor de validuntil:', validUntilValue);  // ← DEBUG
if (validUntilValue && validUntilValue.trim() !== '') {
  formData.append('validuntil', validUntilValue.trim());
  console.log('✅ validuntil agregado al FormData');  // ← DEBUG
} else {
  console.log('⚠️ validuntil está vacío, no se envía');  // ← DEBUG
}
    
    // DRAFT PARA BORRADOR
    if (isDraft) {
        formData.append('approval_status', 'draft');
        formData.append('is_draft', '1');
    } else {
        formData.append('approval_status', 'pending');
        formData.append('is_draft', '0');
    }

    // Enviar al backend
    fetch('php/insert_process.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(resp => {
        if (isDraft) {
            alert('✅ Borrador guardado exitosamente con todos los datos');
            window.location.href = 'mis_borradores.php';
        } else {
            alert(resp);
        }
    })
    .catch(err => {
        alert('Error al guardar proceso');
        console.error(err);
    });
}

// --------- INPUT --------- //
async function guardarInputPrincipal() {
    const category = document.getElementById('inputCategory').value;
    const resourceName = document.getElementById('inputResourceName').value;
    const quantity = document.getElementById('inputQuantity').value;
    const unit = document.getElementById('inputUnit').value;
    const datasource = document.getElementById('inputDataSource').value;
    const commentary = document.getElementById('inputCommentary').value;

    if (!category || !resourceName || !quantity || !unit) {
        alert('Por favor completa los campos obligatorios');
        return;
    }

    const inputData = {
        uuid: editingInputIndex !== null ? todosLosInputs[editingInputIndex].uuid : self.crypto.randomUUID(),
        category,
        resourceName,
        quantity: parseFloat(quantity),
        unit,
        datasource,
        commentary
    };

    if (editingInputIndex !== null) {
        todosLosInputs[editingInputIndex] = inputData;
        editingInputIndex = null;
        alert('Input actualizado');
    } else {
        todosLosInputs.push(inputData);
        alert('Input agregado a la tabla');
    }

    document.getElementById('inputForm').reset();
    document.getElementById('uuidInput').value = self.crypto.randomUUID();
    renderTablaInputs();
}

function renderTablaInputs() {
    const tbody = document.getElementById('tablaInputs');
    const count = document.getElementById('countInputs');
    
    count.textContent = todosLosInputs.length;
    
    if (todosLosInputs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-6 text-gray-400 italic">No hay entradas agregadas</td></tr>';
        return;
    }
    
    tbody.innerHTML = todosLosInputs.map((input, index) => `
        <tr class="hover:bg-gray-50">
            <td class="border px-2 py-2 font-medium">${input.resourceName}</td>
            <td class="border px-2 py-2">${input.category}</td>
            <td class="border px-2 py-2 text-right font-mono">${input.quantity}</td>
            <td class="border px-2 py-2">${input.unit}</td>
            <td class="border px-2 py-2 text-gray-600 text-xs">${input.datasource || '-'}</td>
            <td class="border px-2 py-2 text-gray-600 text-xs truncate max-w-xs" title="${input.commentary}">${input.commentary || '-'}</td>
            <td class="border px-2 py-2 text-center">
                <div class="flex gap-1 justify-center">
                    <button onclick="editarInputFromTable(${index})" class="p-1 text-blue-600 hover:bg-blue-100 rounded">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                    </button>
                    <button onclick="eliminarInputFromTable(${index})" class="p-1 text-red-600 hover:bg-red-100 rounded">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    lucide.createIcons();
}

function editarInputFromTable(index) {
    const input = todosLosInputs[index];
    
    document.getElementById('inputCategory').value = input.category;
    document.getElementById('inputResourceName').value = input.resourceName;
    document.getElementById('inputQuantity').value = input.quantity;
    document.getElementById('inputUnit').value = input.unit;
    document.getElementById('inputDataSource').value = input.datasource || '';
    document.getElementById('inputCommentary').value = input.commentary || '';
    
    editingInputIndex = index;
    document.getElementById('inputForm').scrollIntoView({ behavior: 'smooth' });
}

function eliminarInputFromTable(index) {
    if (!confirm('¿Eliminar esta entrada?')) return;
    todosLosInputs.splice(index, 1);
    renderTablaInputs();
}

// --------- OUTPUT --------- //
async function guardarOutputPrincipal() {
    const emissionType = document.getElementById('outputEmissionType').value;
    const category = document.getElementById('outputCategory').value;
    const emissionName = document.getElementById('outputEmissionName').value;
    const compartment = document.getElementById('outputCompartment').value;
    const subcompartment = document.getElementById('outputSubcompartment').value;
    const quantity = document.getElementById('outputQuantity').value;
    const unit = document.getElementById('outputUnit').value;
    const datasource = document.getElementById('outputDataSource').value;
    const commentary = document.getElementById('outputCommentary').value;
    const isReference = document.getElementById('outputIsReference').checked;

    if (!emissionType || !category || !emissionName || !compartment || !subcompartment || !quantity || !unit) {
        alert('Por favor completa los campos obligatorios');
        return;
    }

    const outputData = {
        uuid: editingOutputIndex !== null ? todosLosOutputs[editingOutputIndex].uuid : self.crypto.randomUUID(),
        emissionType,
        category,
        emissionName,
        compartment,
        subcompartment,
        quantity: parseFloat(quantity),
        unit,
        datasource,
        commentary,
        isReference
    };

    if (editingOutputIndex !== null) {
        todosLosOutputs[editingOutputIndex] = outputData;
        editingOutputIndex = null;
        alert('Output actualizado');
    } else {
        todosLosOutputs.push(outputData);
        alert('Output agregado a la tabla');
    }

    document.getElementById('outputForm').reset();
    document.getElementById('uuidOutput').value = self.crypto.randomUUID();
    renderTablaOutputs();
}

function renderTablaOutputs() {
    const tbody = document.getElementById('tablaOutputs');
    const count = document.getElementById('countOutputs');
    
    count.textContent = todosLosOutputs.length;
    
    if (todosLosOutputs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center py-6 text-gray-400 italic">No hay salidas agregadas</td></tr>';
        return;
    }
    
    tbody.innerHTML = todosLosOutputs.map((output, index) => `
        <tr class="hover:bg-gray-50">
            <td class="border px-2 py-2 font-medium">${output.emissionName}</td>
            <td class="border px-2 py-2">${output.emissionType}</td>
            <td class="border px-2 py-2">${output.category}</td>
            <td class="border px-2 py-2">${output.compartment}</td>
            <td class="border px-2 py-2">${output.subcompartment}</td>
            <td class="border px-2 py-2 text-right font-mono">${output.quantity}</td>
            <td class="border px-2 py-2">${output.unit}</td>
            <td class="border px-2 py-2 text-gray-600 text-xs">${output.datasource || '-'}</td>
            <td class="border px-2 py-2 text-center">
                ${output.isReference ? '<span class="text-green-600 font-bold">✓</span>' : '-'}
            </td>
            <td class="border px-2 py-2 text-gray-600 text-xs truncate max-w-xs" title="${output.commentary}">${output.commentary || '-'}</td>
            <td class="border px-2 py-2 text-center">
                <div class="flex gap-1 justify-center">
                    <button onclick="editarOutputFromTable(${index})" class="p-1 text-blue-600 hover:bg-blue-100 rounded">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                    </button>
                    <button onclick="eliminarOutputFromTable(${index})" class="p-1 text-red-600 hover:bg-red-100 rounded">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    lucide.createIcons();
}

function editarOutputFromTable(index) {
    const output = todosLosOutputs[index];
    
    document.getElementById('outputEmissionType').value = output.emissionType;
    document.getElementById('outputCategory').value = output.category;
    document.getElementById('outputEmissionName').value = output.emissionName;
    document.getElementById('outputCompartment').value = output.compartment;
    document.getElementById('outputSubcompartment').value = output.subcompartment;
    document.getElementById('outputQuantity').value = output.quantity;
    document.getElementById('outputUnit').value = output.unit;
    document.getElementById('outputDataSource').value = output.datasource || '';
    document.getElementById('outputCommentary').value = output.commentary || '';
    document.getElementById('outputIsReference').checked = output.isReference;
    
    editingOutputIndex = index;
    document.getElementById('outputForm').scrollIntoView({ behavior: 'smooth' });
}

function eliminarOutputFromTable(index) {
    if (!confirm('¿Eliminar esta salida?')) return;
    todosLosOutputs.splice(index, 1);
    renderTablaOutputs();
}


// --------- DOCUMENTATION --------- //
function guardarDocumentacion() {
  const referenceYear = document.getElementById('referenceYear').value.trim();
  const dataOwner = document.getElementById('dataOwner').value.trim();
  const contactInfo = document.getElementById('contactInformation').value.trim();
  const reviewStatus = document.getElementById('reviewStatus').value;
  const accessConditions = document.getElementById('accessConditions').value;
  const license = document.getElementById('license').value.trim();

  if (!referenceYear || !dataOwner || !contactInfo || !reviewStatus || !accessConditions || !license) {
    alert('Por favor completa los campos obligatorios: Año de Referencia, Propietario de Datos, Información de Contacto, Estado de revisión, Condiciones de Acceso y Licencia');
    return;
  }

  const formData = new FormData();
  formData.append('process_uuid', document.getElementById('uuidGen').value);
  formData.append('referenceYear', referenceYear);
  formData.append('valid_until', document.getElementById('valid_until').value || ''); // OPCIONAL
  formData.append('dataOwner', dataOwner);
  formData.append('contactInformation', contactInfo);
  formData.append('dataSource', document.getElementById('dataSource').value || ''); // OPCIONAL
  formData.append('dataQualityIndicators', document.getElementById('dataQualityIndicators').value || ''); // OPCIONAL
  formData.append('complianceStandards', document.getElementById('complianceStandards').value || ''); // OPCIONAL
  formData.append('reviewStatus', reviewStatus);
  formData.append('accessConditions', accessConditions);
  formData.append('license', license);

  fetch('php/insert_documentation.php', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(resp => {
      alert(resp);
    })
    .catch(err => {
      alert('Hubo un error al guardar la documentación.');
      console.error(err);
    });
}

//MODAL TABLAS DE DATA QUALITY
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modalDataQuality');

  window.abrirModalDataQuality = function () {
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  };

  window.cerrarModalDataQuality = function () {
    if (!modal) return;
    modal.classList.remove('flex');
    modal.classList.add('hidden');
  };

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) window.cerrarModalDataQuality();
    });
  }

  // Función del botón "Guardar" del modal (SOLO MUESTRA NÚMEROS)
window.guardarDataQuality = function () {
  const grupos = ['frflow', 'frtemp', 'frgeo', 'frtec', 'frdata'];
  const scores = [];
  const details = {};
  
  const criterios = [
    'Flow Reliability',
    'Temporal Correlation',
    'Geographical Correlation',
    'Technological Correlation',
    'Data Collection Methods'
  ];
  
  // Recopilar scores Y TODOS los textareas
  for (let i = 0; i < grupos.length; i++) {
    const name = grupos[i];
    const criterio = criterios[i];
    
    const sel = document.querySelector(`input[name="${name}"]:checked`);
    if (!sel) {
      alert('Por favor, selecciona una opción para cada criterio.');
      return;
    }
    
    const score = sel.value;
    scores.push(score);
    
    // Capturar TODOS los textareas (del 1 al 5)
    details[criterio] = {};
    for (let scoreNum = 1; scoreNum <= 5; scoreNum++) {
      const textarea = document.querySelector(`textarea[name="${name}_${scoreNum}"]`); // ← FIX: Agregar _
      if (textarea && textarea.value.trim()) {
        details[criterio][scoreNum] = textarea.value.trim();
      }
    }
  }
  
  // Guardar scores en campo visible
  const joined = scores.join(',');
  const visible = document.getElementById('dataQualityIndicators');
  if (visible) {
    visible.value = joined;
  }
  
  // Guardar details en un campo hidden
  let detailsField = document.getElementById('dataQualityDetailsHidden');
  if (!detailsField) {
    detailsField = document.createElement('input');
    detailsField.type = 'hidden';
    detailsField.id = 'dataQualityDetailsHidden';
    detailsField.name = 'dataQualityDetailsHidden';
    document.getElementById('documentationForm').appendChild(detailsField);
  }
  detailsField.value = JSON.stringify(details);
  
  window.cerrarModalDataQuality();
  alert('✓ Data Quality: ' + joined);
};

  // Función del botón principal de documentación
window.guardarTodoDocumentacion = function () {
  const process_uuid = document.getElementById('uuidGen')?.value;
  
  if (!process_uuid) {
    alert('❌ Error: Guarda primero el proceso.');
    return;
  }

  // Recopilar datos de documentación
  const formData = new FormData(document.getElementById('documentationForm'));
  formData.append('process_uuid', process_uuid);

  // DETECTAR si hay Data Quality
  const dqField = document.getElementById('dataQualityIndicators');
  const dqScores = dqField?.value || '';
  
  // Declarar detailsJSON FUERA del if para que esté disponible en todo el scope
  let detailsJSON = '{}';
  
  if (dqScores) {
    // Obtener details del campo hidden en lugar de buscar textareas
    const detailsField = document.getElementById('dataQualityDetailsHidden');
    detailsJSON = detailsField?.value || '{}';
    
    console.log('✅ Data Quality detectado:', dqScores);
    console.log('📝 Details (desde hidden):', detailsJSON);
    
    formData.append('dataQualityIndicators', dqScores);
    formData.append('dataQualityDetails', detailsJSON);
  } else {
    console.log('⚠️ No hay Data Quality configurado');
  }

  // Guardar documentación
  fetch('php/insert_documentation.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    console.log('Respuesta documentación:', data);
    
    if (data.includes('✓') && dqScores) {
      // Guardar Data Quality en tabla separada
      const dqFormData = new FormData();
      dqFormData.append('process_uuid', process_uuid);
      dqFormData.append('dataQualityIndicators', dqScores);
      dqFormData.append('dataQualityDetails', detailsJSON);
      
      console.log('Enviando a insert_dq_indicators.php:');
      console.log('  process_uuid:', process_uuid);
      console.log('  dataQualityIndicators:', dqScores);
      console.log('  dataQualityDetails:', detailsJSON);
      
      return fetch('php/insert_dq_indicators.php', {
        method: 'POST',
        body: dqFormData
      });
    } else if (data.includes('✓')) {
      alert('✓ Documentación guardada (sin Data Quality)');
      return 'sin-dq';
    } else {
      throw new Error(data);
    }
  })
  .then(response => {
    if (response === 'sin-dq') return;
    return response.text();
  })
  .then(data => {
    if (data) {
      console.log('Respuesta DQ:', data);
      if (data.includes('✓')) {
        alert('✅ Documentación y Data Quality guardados exitosamente!');
      } else {
        alert('⚠️ Documentación guardada, pero error en Data Quality:\n' + data);
      }
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('❌ Error: ' + error.message);
  });
};
});


// --------- PROCESS LINKS - EXCHANGES --------- //
let selectedProcess = null;
let searchTimeout = null;
let allResults = []; // Todos los resultados de búsqueda
let displayedCount = 0; // Cuántos se han mostrado
const RESULTS_PER_PAGE = 5; // Mostrar 5 a la vez

// Abrir modal
function openLinkModal() {
  document.getElementById('linkModal').classList.remove('hidden');
  document.getElementById('searchQ').value = '';
  document.getElementById('linkComment').value = '';
  document.getElementById('searchResults').innerHTML = `
    <div class="p-12 text-center text-sm text-gray-400">
      <i data-lucide="search" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
      <p>Escribe para buscar</p>
    </div>
  `;
  selectedProcess = null;
  allResults = [];
  displayedCount = 0;
  document.getElementById('loadMoreBtn').classList.add('hidden');
  const confirmBtn = document.getElementById('confirmLinkBtn');
  if (confirmBtn) confirmBtn.disabled = true;
  lucide.createIcons();
}

// Cerrar modal
function closeLinkModal() {
  document.getElementById('linkModal').classList.add('hidden');
}

// Búsqueda con debounce
function searchDatasets() {
  clearTimeout(searchTimeout);
  
  const query = document.getElementById('searchQ').value.trim();
  
  if (query.length < 2) {
    document.getElementById('searchResults').innerHTML = `
      <div class="p-12 text-center text-sm text-gray-400">
        <i data-lucide="search" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
        <p>Escribe al menos 2 caracteres</p>
      </div>
    `;
    document.getElementById('loadMoreBtn').classList.add('hidden');
    lucide.createIcons();
    return;
  }
  
  const loadingIndicator = document.getElementById('loadingIndicator');
  if (loadingIndicator) loadingIndicator.classList.remove('hidden');
  
  searchTimeout = setTimeout(() => {
    performSearch(query);
  }, 300);
}

// Realizar búsqueda
function performSearch(query) {
  const formData = new FormData();
  formData.append('action', 'search_processes');
  formData.append('query', query);
  formData.append('limit', '50'); // Buscar hasta 50, pero mostrar 5 a la vez

  fetch('php/search_processes.php', {
    method: 'POST',
    body: formData
  })
  .then(resp => resp.json())
  .then(data => {
    const loadingIndicator = document.getElementById('loadingIndicator');
    if (loadingIndicator) loadingIndicator.classList.add('hidden');
    
    allResults = data || [];
    displayedCount = 0;
    displayResults();
  })
  .catch(err => {
    console.error('Error en búsqueda:', err);
    const loadingIndicator = document.getElementById('loadingIndicator');
    if (loadingIndicator) loadingIndicator.classList.add('hidden');
    document.getElementById('searchResults').innerHTML = `
      <div class="p-8 text-center text-sm text-red-600">
        Error al buscar
      </div>
    `;
  });
}

// Mostrar resultados paginados
function displayResults() {
  const container = document.getElementById('searchResults');
  
  if (allResults.length === 0) {
    container.innerHTML = `
      <div class="p-12 text-center text-sm text-gray-400">
        <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
        <p>No se encontraron procesos</p>
      </div>
    `;
    document.getElementById('loadMoreBtn').classList.add('hidden');
    lucide.createIcons();
    return;
  }
  
  // Mostrar los primeros 5 resultados
  const resultsToShow = allResults.slice(0, RESULTS_PER_PAGE);
  displayedCount = RESULTS_PER_PAGE;
  
  container.innerHTML = resultsToShow.map(proc => createResultHTML(proc)).join('');
  
  // Mostrar/ocultar botón "Ver más"
  if (allResults.length > RESULTS_PER_PAGE) {
    document.getElementById('loadMoreBtn').classList.remove('hidden');
  } else {
    document.getElementById('loadMoreBtn').classList.add('hidden');
  }
  
  lucide.createIcons();
}

// Cargar más resultados
function loadMoreResults() {
  const container = document.getElementById('searchResults');
  const nextResults = allResults.slice(displayedCount, displayedCount + RESULTS_PER_PAGE);
  
  nextResults.forEach(proc => {
    const div = document.createElement('div');
    div.innerHTML = createResultHTML(proc);
    container.appendChild(div.firstElementChild);
  });
  
  displayedCount += RESULTS_PER_PAGE;
  
  // Ocultar botón si ya no hay más resultados
  if (displayedCount >= allResults.length) {
    document.getElementById('loadMoreBtn').classList.add('hidden');
  }
  
  lucide.createIcons();
}

// Crear HTML de un resultado
function createResultHTML(proc) {
  return `
    <div 
      onclick="selectProcess('${escapeHtml(proc.uuid)}', '${escapeHtml(proc.name)}', '${escapeHtml(proc.functional_unit || '')}', '${escapeHtml(proc.category || '')}')"
      class="group p-3 rounded-lg border border-gray-200 bg-white hover:border-green-300 hover:bg-green-50 cursor-pointer transition-all"
      data-uuid="${escapeHtml(proc.uuid)}"
    >
      <div class="flex items-start justify-between gap-2">
        <div class="flex-1 min-w-0">
          <h4 class="font-medium text-gray-900 text-sm truncate group-hover:text-green-700">
            ${escapeHtml(proc.name)}
          </h4>
          ${proc.functional_unit ? `
            <p class="text-xs text-gray-500 mt-0.5">
              ${escapeHtml(proc.functional_unit)}
            </p>
          ` : ''}
        </div>
        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-green-600 flex-shrink-0"></i>
      </div>
    </div>
  `;
}

// Seleccionar proceso
function selectProcess(uuid, name, functionalUnit, category) {
  selectedProcess = { uuid, name, functionalUnit, category };
  
  document.querySelectorAll('#searchResults > div[data-uuid]').forEach(div => {
    div.classList.remove('!border-green-500', '!bg-green-100');
  });
  
  const clickedElement = event.target.closest('[data-uuid]');
  if (clickedElement) {
    clickedElement.classList.add('!border-green-500', '!bg-green-100');
  }
  
  const confirmBtn = document.getElementById('confirmLinkBtn');
  if (confirmBtn) confirmBtn.disabled = false;
}

// Confirmar y agregar
function confirmAddLink() {
  if (!selectedProcess) {
    alert('Selecciona un proceso primero');
    return;
  }
  
  const direction = document.getElementById('linkDirection').value;
  const comment = (document.getElementById('linkComment').value || '').trim();
  
  addExchangeToList(selectedProcess, direction, comment);
  
  const bag = document.getElementById('exchangesContainer');
  const ts = Date.now();
  appendHidden(bag, `exchanges[${ts}][linked_process]`, selectedProcess.uuid);
  appendHidden(bag, `exchanges[${ts}][type_of_exchange]`, direction);
  appendHidden(bag, `exchanges[${ts}][flow_name]`, selectedProcess.name);
  appendHidden(bag, `exchanges[${ts}][flow_type]`, 'product');
  appendHidden(bag, `exchanges[${ts}][category]`, selectedProcess.category || '');
  appendHidden(bag, `exchanges[${ts}][data_source]`, 'link');
  appendHidden(bag, `exchanges[${ts}][commentary]`, comment);
  
  closeLinkModal();
}
// Agregar exchange a la lista visual
function addExchangeToList(process, direction, comment) {
  const container = document.getElementById('ioSummary');
  
  const exchangeDiv = document.createElement('div');
  exchangeDiv.className = 'p-3 rounded-lg border border-green-100 bg-green-50/50 flex items-start justify-between gap-3';
  exchangeDiv.innerHTML = `
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
          direction === 'input' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'
        }">
          ${direction === 'input' ? 'Input' : 'Output'}
        </span>
        <span class="text-sm font-medium text-gray-900 truncate">${escapeHtml(process.name)}</span>
      </div>
      ${process.functionalUnit ? `
        <p class="text-xs text-gray-500 mt-1">${escapeHtml(process.functionalUnit)}</p>
      ` : ''}
      ${comment ? `
        <p class="text-xs text-gray-600 mt-1 italic">${escapeHtml(comment)}</p>
      ` : ''}
    </div>
    <button 
      onclick="removeExchange(this)" 
      class="text-red-600 hover:text-red-700 p-1"
      title="Eliminar"
    >
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  `;
  
  container.appendChild(exchangeDiv);
  lucide.createIcons();
  
  console.log('Exchange agregado:', { process, direction, comment });
}

// Eliminar exchange de la lista
function removeExchange(button) {
  button.parentElement.remove();
}

// Funciones auxiliares
function appendHidden(parent, name, value) {
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = name;
  input.value = value;
  parent.appendChild(input);
}

function escapeHtml(str) {
  return (str ?? '').toString()
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

// =================
// GUARDAR EXCHANGES 
// =================
async function guardarExchanges() {
  const bag = document.getElementById('exchangesContainer');
  const processUuidInput = document.getElementById('uuidGen');

  if (!processUuidInput || !processUuidInput.value) {
    alert('Falta el UUID del proceso (uuidGen). Guarda/crea primero el proceso.');
    return;
  }
  
  const hiddenFields = bag ? [...bag.querySelectorAll('input[type="hidden"]')] : [];
  if (!hiddenFields.length) {
    alert('No hay intercambios seleccionados.');
    return;
  }

  const fd = new FormData();
  fd.append('process_uuid', processUuidInput.value);
  hiddenFields.forEach(h => fd.append(h.name, h.value));

  try {
    const resp = await fetch('php/insert_exchanges.php', {
      method: 'POST',
      body: fd,
      headers: { Accept: 'application/json' }
    });
    const data = await resp.json();

    if (!resp.ok || !data.ok) throw new Error(data.error || 'No se pudieron guardar los intercambios');

    alert(`Intercambios guardados: ${data.saved}`);
    
    // Limpiar contenedor
    bag.innerHTML = '';
    document.getElementById('ioSummary')?.replaceChildren();
  } catch (err) {
    console.error(err);
    alert('Error al guardar intercambios: ' + (err.message || err));
  }
}

//MODAL ESTILIZADO DE Intercambios
function createResultHTML(proc) {
  return `
    <div 
      onclick="selectProcess('${escapeHtml(proc.uuid)}', '${escapeHtml(proc.name)}', '${escapeHtml(proc.functional_unit || '')}', '${escapeHtml(proc.category || '')}')"
      class="group p-4 cursor-pointer transition-all hover:bg-green-50 border-b border-green-50 last:border-b-0"
      data-uuid="${escapeHtml(proc.uuid)}"
    >
      <div class="flex items-start gap-3">
        <div class="flex-1 min-w-0">
          <h4 class="font-semibold text-gray-900 text-sm group-hover:text-green-700 transition">
            ${escapeHtml(proc.name)}
          </h4>
          
          ${proc.category ? `
            <p class="text-xs text-gray-500 mt-1">
              <span class="font-medium">Clasificación:</span> ${escapeHtml(proc.category)}
            </p>
          ` : ''}
          
          ${proc.functional_unit ? `
            <p class="text-xs text-gray-600 mt-1">
              ${escapeHtml(proc.functional_unit)}
            </p>
          ` : ''}
          
          <p class="text-xs text-gray-400 mt-2 font-mono">
            UUID: ${escapeHtml(proc.uuid)}
          </p>
        </div>
        
        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-green-600 transition flex-shrink-0"></i>
      </div>
    </div>
  `;
}

// Seleccionar proceso con marcado visual
function selectProcess(uuid, name, functionalUnit, category) {
  selectedProcess = { uuid, name, functionalUnit, category };
  
  // Quitar selección anterior
  document.querySelectorAll('#searchResults > div[data-uuid]').forEach(div => {
    div.classList.remove('bg-green-100', 'border-l-4', 'border-l-green-600');
  });
  
  // Marcar el seleccionado
  const clickedElement = event.target.closest('[data-uuid]');
  if (clickedElement) {
    clickedElement.classList.add('bg-green-100', 'border-l-4', 'border-l-green-600');
  }
  
  // Habilitar botón
  const confirmBtn = document.getElementById('confirmLinkBtn');
  if (confirmBtn) confirmBtn.disabled = false;
  
  console.log('Proceso seleccionado:', selectedProcess);
}

// Agregar exchange a la lista con TODO el detalle
function addExchangeToList(process, direction, comment) {
  const container = document.getElementById('ioSummary');
  
  const exchangeDiv = document.createElement('div');
  exchangeDiv.className = 'p-4 rounded-lg border-2 border-green-200 bg-green-50 relative';
  exchangeDiv.innerHTML = `
    <div class="pr-8">
      <!-- Badge de dirección -->
      <div class="flex items-center gap-2 mb-2">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${
          direction === 'input' 
            ? 'bg-blue-100 text-blue-700' 
            : 'bg-orange-100 text-orange-700'
        }">
          ${direction === 'input' ? '⬇ Input' : '⬆ Output'}
        </span>
      </div>
      
      <!-- Nombre del proceso -->
      <h4 class="font-bold text-gray-900 text-sm mb-2">
        ${escapeHtml(process.name)}
      </h4>
      
      <!-- Clasificación -->
      ${process.category ? `
        <p class="text-xs text-gray-600 mb-1">
          <span class="font-semibold">Clasificación:</span> ${escapeHtml(process.category)}
        </p>
      ` : ''}
      
      <!-- Unidad funcional -->
      ${process.functionalUnit ? `
        <p class="text-xs text-gray-600 mb-1">
          <span class="font-semibold">Cantidad:</span> ${escapeHtml(process.functionalUnit)}
        </p>
      ` : ''}
      
      <!-- UUID -->
      <p class="text-xs text-gray-400 font-mono mt-2 bg-white/50 p-1.5 rounded">
        UUID: ${escapeHtml(process.uuid)}
      </p>
      
      <!-- Comentario -->
      ${comment ? `
        <p class="text-xs text-gray-700 mt-2 italic border-l-2 border-green-400 pl-2">
          "${escapeHtml(comment)}"
        </p>
      ` : ''}
    </div>
    
    <!-- Botón eliminar -->
    <button 
      onclick="removeExchange(this)" 
      class="absolute top-3 right-3 text-red-600 hover:text-red-700 hover:bg-red-100 rounded-full p-1.5 transition"
      title="Eliminar"
    >
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  `;
  
  container.appendChild(exchangeDiv);
  lucide.createIcons();
  
  console.log('Exchange agregado:', { process, direction, comment });
}

// Abrir modal (resetear estado)
function openLinkModal() {
  document.getElementById('linkModal').classList.remove('hidden');
  document.getElementById('searchQ').value = '';
  document.getElementById('linkComment').value = '';
  document.getElementById('searchResults').innerHTML = `
    <div class="p-8 text-center text-sm text-gray-400 italic">
      Escribe para buscar datasets
    </div>
  `;
  selectedProcess = null;
  allResults = [];
  displayedCount = 0;
  document.getElementById('loadMoreBtn').classList.add('hidden');
  const confirmBtn = document.getElementById('confirmLinkBtn');
  if (confirmBtn) confirmBtn.disabled = true;
  lucide.createIcons();
}
// Guardar SOLO INPUTS desde la tabla temporal
async function guardarSoloInputs() {
    const processUuid = document.getElementById('uuidGen').value;
    
    if (!processUuid) {
        alert('ERROR: Primero guarda el proceso padre');
        return;
    }
    
    if (todosLosInputs.length === 0) {
        alert('No hay entradas para guardar');
        return;
    }
    
    if (!confirm(`¿Guardar ${todosLosInputs.length} entradas a la base de datos?`)) return;
    
    const formData = new FormData();
    formData.append('process_uuid', processUuid);
    
    todosLosInputs.forEach((input) => {
        formData.append('resource_name[]', input.resourceName);
        formData.append('category[]', input.category);
        formData.append('quantity[]', input.quantity);
        formData.append('unit[]', input.unit);
        formData.append('data_source[]', input.datasource || '');
        formData.append('commentary[]', input.commentary || '');
        formData.append('uuid[]', input.uuid);
    });
    
    try {
        const resp = await fetch('php/insert_input.php', {
            method: 'POST',
            body: formData
        });
        const msg = await resp.text();
        alert(msg);
    } catch (err) {
        alert('Error: ' + err);
    }
}
// Guardar SOLO OUTPUTS desde la tabla temporal
async function guardarSoloOutputs() {
    const processUuid = document.getElementById('uuidGen').value;
    
    if (!processUuid) {
        alert('ERROR: Primero guarda el proceso padre');
        return;
    }
    
    if (todosLosOutputs.length === 0) {
        alert('No hay salidas para guardar');
        return;
    }
    
    if (!confirm(`¿Guardar ${todosLosOutputs.length} salidas a la base de datos?`)) return;
    
    const formData = new FormData();
    formData.append('process_uuid', processUuid);
    
    todosLosOutputs.forEach((output) => {
        formData.append('name_of_the_emission[]', output.emissionName);
        formData.append('type_of_emission[]', output.emissionType);
        formData.append('category[]', output.category);
        formData.append('compartment[]', output.compartment);
        formData.append('subcompartment[]', output.subcompartment);
        formData.append('quantity[]', output.quantity);
        formData.append('unit[]', output.unit);
        formData.append('data_source[]', output.datasource || '');
        formData.append('commentary[]', output.commentary || '');
        formData.append('uuid[]', output.uuid);
        if (output.isReference) {
            formData.append('is_reference_flow[]', '1');
        }
    });
    
    try {
        const resp = await fetch('php/insert_output.php', {
            method: 'POST',
            body: formData
        });
        const msg = await resp.text();
        alert(msg);
    } catch (err) {
        alert('Error: ' + err);
    }
}

// GUARDAR BORRADOR DE PROCESO CREADO
async function guardarBorrador() {
  const processName = document.getElementById('processName')?.value?.trim();
  if (!processName) {
    alert('Necesitas al menos un nombre para guardar el borrador');
    return;
  }
  
  const btnBorrador = event.target;
  const textoOriginal = btnBorrador.innerHTML;
  btnBorrador.innerHTML = '⏳ Guardando...';
  btnBorrador.disabled = true;
  
  try {
    // 1. GUARDAR PROCESO PADRE
    const processData = new FormData();     
    // UUID
    processData.append('uuid', document.getElementById('uuidGen').value);
    
    // ✅ CAMPOS OBLIGATORIOS (nombres correctos que coinciden con tu PHP)
    processData.append('processname', document.getElementById('processName').value);
    processData.append('category', document.getElementById('category')?.value || '');
    processData.append('typeofprocess', document.getElementById('typeOfProcess')?.value || '');
    processData.append('functionalunit', document.getElementById('functionalUnit')?.value || '');
    processData.append('location', document.getElementById('country')?.value || '');
    
    // ✅ CAMPOS OPCIONALES
    processData.append('processdescription', document.getElementById('processDescription')?.value || '');
    processData.append('sector', document.getElementById('sector')?.value || '');  // ← CORRECTO: 'sector', no 'sectorprincipal'
    processData.append('generalcomment', document.getElementById('generalComment')?.value || '');
    processData.append('tags', document.getElementById('tags')?.value || '');
    processData.append('lifecyclestage', document.getElementById('lifeCycleStage')?.value || '');
    processData.append('locationdescription', document.getElementById('locationDescription')?.value || '');
    processData.append('technologydescription', document.getElementById('technologyDescription')?.value || '');  // ← AGREGADO
    
    // valid_until
    const validUntilValue = document.getElementById('valid_until')?.value;
    if (validUntilValue && validUntilValue !== '') {
      processData.append('validuntil', validUntilValue);
    }
    
    // Marcar como borrador
    processData.append('is_draft', '1');
    processData.append('approval_status', 'draft');
    
    // ✅ DEBUG: Ver qué se envía
    console.log('=== DATOS DEL BORRADOR ===');
    for (let [key, value] of processData.entries()) {
      console.log(`${key}: ${value}`);
    }
    
    // Guardar proceso
    const resp = await fetch('php/insert_process.php', {
      method: 'POST',
      body: processData
    });
    
    const respText = await resp.text();
    console.log('Respuesta servidor:', respText);
    
    if (!resp.ok || respText.toLowerCase().includes('error')) {
      throw new Error(respText);
    }
    
    const processUuid = document.getElementById('uuidGen').value;
    
    // 2. GUARDAR INPUTS (si hay)
    if (todosLosInputs && todosLosInputs.length > 0) {
      const inputData = new FormData();
      inputData.append('processuuid', processUuid);
      todosLosInputs.forEach(input => {
        inputData.append('resourcename', input.resourceName);
        inputData.append('category', input.category);
        inputData.append('quantity', input.quantity);
        inputData.append('unit', input.unit);
        inputData.append('datasource', input.datasource);
        inputData.append('commentary', input.commentary);
        inputData.append('uuid', input.uuid);
      });
      await fetch('php/insert_input.php', { method: 'POST', body: inputData });
    }
    
    // 3. GUARDAR OUTPUTS (si hay)
    if (todosLosOutputs && todosLosOutputs.length > 0) {
      const outputData = new FormData();
      outputData.append('processuuid', processUuid);
      todosLosOutputs.forEach(output => {
        outputData.append('nameoftheemission', output.emissionName);
        outputData.append('typeofemission', output.emissionType);
        outputData.append('category', output.category);
        outputData.append('compartment', output.compartment);
        outputData.append('subcompartment', output.subcompartment);
        outputData.append('quantity', output.quantity);
        outputData.append('unit', output.unit);
        outputData.append('datasource', output.datasource);
        outputData.append('commentary', output.commentary);
        outputData.append('uuid', output.uuid);
        if (output.isReference) {
          outputData.append('isreferenceflow', '1');
        }
      });
      await fetch('php/insert_output.php', { method: 'POST', body: outputData });
    }
    
    // 4. GUARDAR DOCUMENTACIÓN (si hay)
    const referenceYear = document.getElementById('referenceYear')?.value;
    if (referenceYear) {
      const docData = new FormData();
      docData.append('processuuid', processUuid);
      docData.append('referenceYear', referenceYear);
      docData.append('validuntil', document.getElementById('valid_until')?.value || '');
      docData.append('dataOwner', document.getElementById('dataOwner')?.value || '');
      docData.append('contactInformation', document.getElementById('contactInformation')?.value || '');
      docData.append('dataSource', document.getElementById('dataSource')?.value || '');
      docData.append('dataQualityIndicators', document.getElementById('dataQualityIndicators')?.value || '');
      docData.append('complianceStandards', document.getElementById('complianceStandards')?.value || '');
      docData.append('reviewStatus', document.getElementById('reviewStatus')?.value || '');
      docData.append('accessConditions', document.getElementById('accessConditions')?.value || '');
      docData.append('license', document.getElementById('license')?.value || '');
      await fetch('php/insert_documentation.php', { method: 'POST', body: docData });
    }
    
    alert('✓ Borrador guardado exitosamente');
    
    // Agregar UUID a la URL para poder seguir editando
    const currentUrl = new URL(window.location.href);
    if (!currentUrl.searchParams.has('draftuuid')) {
      currentUrl.searchParams.set('draftuuid', processUuid);
      window.history.replaceState({}, '', currentUrl);
    }
    
  } catch (error) {
    console.error('❌ Error:', error);
    alert('❌ Error: ' + error.message);
  } finally {
    btnBorrador.innerHTML = textoOriginal;
    btnBorrador.disabled = false;
  }
}
</script>
</body>
</html>