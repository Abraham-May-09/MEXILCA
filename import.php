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
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Importar Datasets</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image" href="icons/file-plus-2.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    .custom-file-input {
    width: 100%;
    padding: 8px;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    background: white;
    }
    
    .custom-file-output:hover {
        border-color: #059669;
        background: #f9fafb;
    }
    
    .custom-file-input::file-selector-button {
        padding: 4px 6px;
        margin-right: 10px;
        border: none;
        border-radius: 4px;
        background: #059669;
        color: white;
        font-weight: 600;
        cursor: pointer;
    }
    
    .custom-file-input::file-selector-button:hover {
        background: #047857;
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
    
    .custom-file-input:hover {
        border-color: #105218;
        background: #f9fafb;
    }
    
    .custom-file-output::file-selector-button {
        padding: 4px 6px;
        margin-right: 10px;
        border: none;
        border-radius: 4px;
        background: #2A6330;
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
            <!-- Botón principal que despliega el menú -->
            <button onclick="toggleDropdown('datasetMenu')" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700 w-full text-left">
                <i data-lucide="file" class="w-5 h-5"></i>
                Añadir Dataset
                <i data-lucide="chevron-down" class="w-4 h-4 ml-auto"></i>
            </button>
            <!-- Menú desplegable -->
            <div id="datasetMenu" class="hidden mt-4 ml-8 space-y-4">
                <a href="Añadir Conjunto de Datos.php" class="flex items-center gap-3 hover:text-green-700 text-sm">
                    <i data-lucide="file-plus-2" class="w-4 h-4"></i>
                    Manual
                </a>
                <a href="import.php" class="flex items-center gap-3 text-gray-800 font-bold hover:text-green-700 text-sm">
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
  <!-- Boton de Login -->
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

<!-- Contenido principal de la pagina -->
<main class="ml-64 p-10">
    <h1 class="text-4xl font-bold mb-8 text-gray-900">Importar Datasets</h1>                  
    <div class="max-w-6xl mx-auto bg-white border border-gray-200 shadow-sm rounded-xl">
        <div class="p-6">
            <div class="mb-6">
                <h3 class="text-xl font-extrabold text-black mb-4">Importa Datasets a partir de PDF</h3>
                <form id="pdfFormulario" class="space-y-6">
                    <div class="space-y-2">
                      <label for="archivoPDF" class="block text-sm font-semibold text-gray-700">
                          Sube tu archivo PDF:
                      </label>
                      <input type="file" id="archivoPDF" name="archivo" accept="application/pdf" required class="custom-file-input">
                    </div>
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-700">
                            ¿Tu documento tiene estructura de tesis?
                        </h4>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" id="opSi" name="respuesta" value="Sí" required class="w-4 h-4 text-green-600 focus:ring-green-500">
                                <span class="text-sm text-gray-700">Sí</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" id="opNo" name="respuesta" value="No" class="w-4 h-4 text-green-600 focus:ring-green-500">
                                <span class="text-sm text-gray-700">No</span>
                            </label>
                        </div>
                    </div>
                    <!-- Botones -->
                    <div class="mt-6">
                        <button id="btnEnviarPDF" type="submit" class="w-full bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition font-semibold">
                            Enviar
                        </button>
                        <button id="btnEsperaPDF" type="button" disabled class="w-full bg-green-700 text-white py-2 rounded-lg cursor-not-allowed font-semibold hidden">
                            <span class="flex items-center justify-center gap-2">
                                <i data-lucide="loader-circle" class="w-5 h-5 animate-spin"></i>
                                Procesando tu información, por favor espera...
                            </span>
                        </button>
                    </div>
                    <!-- Área de resultado -->
                    <div id="resultadoPDF" class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg hidden">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Resultado:</h4>
                        <div id="resultadoTexto" class="text-sm text-gray-800 whitespace-pre-wrap max-h-64 overflow-y-auto"></div>
                    </div>
                </form>
            </div>
        </div> 
    </div>
</main>

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
  
<!-- Modales de Configuración -->
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
  // Funciones para modales
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

  // Lógica del menú de usuario
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');
  if (userMenuBtn && userDropdown) { 
    userMenuBtn.addEventListener('click', (event) => {
      event.stopPropagation(); 
      userDropdown.classList.toggle('hidden');
    });
  }

  // Cierra el menú desplegable del usuario si se hace clic fuera
  document.addEventListener('click', (e) => {
    if (userMenuBtn && userDropdown && !userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.classList.add('hidden');
    }
  });

  // Configuración - abrir modales internos
  const btnChangePhoto = document.getElementById('btnChangePhoto');
  if (btnChangePhoto) {
      btnChangePhoto.addEventListener('click', () => {
          closeModal('configModal');
          openModal('changePhotoModal');
      });
  }
  
  const btnChangePassword = document.getElementById('btnChangePassword');
  if (btnChangePassword) {
      btnChangePassword.addEventListener('click', () => {
          closeModal('configModal');
          openModal('changePasswordModal');
      });
  }

  const btnRequestAdmin = document.getElementById('btnRequestAdmin');
  if (btnRequestAdmin) {
      btnRequestAdmin.addEventListener('click', function() {
          fetch('request_admin.php', { method: 'POST' })
              .then(res => res.json())
              .then(data => {
                  alert(data.message);
                  if(data.success) {
                      this.disabled = true; 
                  }
              })
              .catch(error => {
                  console.error("Error al solicitar permisos de admin:", error);
                  alert("Error al solicitar permisos de administrador.");
              });
      });
  }

  //MENU DESPLEGABLE DE AÑADIR DATASETS  
  function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    menu.classList.toggle('hidden');
  }

  // Cerrar el menú al hacer clic fuera de él
  document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('datasetMenu');
    const button = event.target.closest('button');
    
    if (!button && dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
    }
  });

// ========== SISTEMA ASÍNCRONO CON POLLING ==========
const form = document.getElementById("pdfFormulario");
const btnEnviar = document.getElementById("btnEnviarPDF");
const btnEspera = document.getElementById("btnEsperaPDF");
const resultado = document.getElementById("resultadoPDF");
const resultadoTexto = document.getElementById("resultadoTexto");

if (form) {
  form.addEventListener("submit", async function(e) {
    e.preventDefault();

    const archivo = document.getElementById("archivoPDF").files[0];
    const respuesta = document.querySelector('input[name="respuesta"]:checked')?.value;

    if (!archivo) {
      alert("Por favor sube un archivo PDF.");
      return;
    }

    if (!respuesta) {
      alert("Por favor selecciona si tu documento tiene estructura de tesis.");
      return;
    }

    if (!archivo.name.toLowerCase().endsWith('.pdf')) {
      alert("Solo se permiten archivos PDF.");
      return;
    }

    // Cambiar botones
    btnEnviar.classList.add("hidden");
    btnEspera.classList.remove("hidden");
    resultado.classList.add("hidden");
    resultadoTexto.textContent = "";

    const formData = new FormData();
    formData.append("pdf", archivo);
    formData.append("respuesta", respuesta);

    try {
      // PASO 1: Enviar a proxy-async.php (respuesta inmediata)
      const resp = await fetch("php_actions/proxy-async.php", {
        method: "POST",
        body: formData
      });

      const responseText = await resp.text();
      let data;
      
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error(`Error al parsear respuesta: ${responseText.substring(0, 200)}`);
      }

      if (!resp.ok || data.success === false) {
        throw new Error(data.error || `Error HTTP ${resp.status}`);
      }

      const job_id = data.job_id;
      
      // Mostrar mensaje inicial
      resultadoTexto.textContent = 
        `✓ Archivo recibido correctamente\n` +
        `✓ ID de trabajo: ${job_id}\n` +
        `✓ Estado: Procesando en segundo plano...\n\n` +
        `Esto puede tardar varios minutos.\n` +
        `Verificando estado cada 3 segundos...`;
      resultado.classList.remove("hidden");

      // PASO 2: Verificar estado cada 3 segundos (polling)
      let attempts = 0;
      const maxAttempts = 300; // 15 minutos (300 × 3 seg)

      const checkStatus = setInterval(async () => {
        attempts++;
        
        try {
          const statusResp = await fetch(`php_actions/check_status.php?job_id=${job_id}`);
          const statusData = await statusResp.json();

          const minutes = Math.floor(attempts * 3 / 60);
          const seconds = (attempts * 3) % 60;
          const timeStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;

          if (statusData.status === 'completed') {
            
            // Intentar obtener los datos de n8n
            let n8nData = null;
            
            // Priorizar response_data si existe
            // ── DESPUÉS ─────────────────────────────────────────
            if (statusData.response_data) {
              // Extraer result_data si existe, si no usar response_data completo
              n8nData = statusData.response_data.result_data ?? statusData.response_data;
            } else if (statusData.response && statusData.response.trim() !== '') {
              try {
                const parsed = JSON.parse(statusData.response);
                n8nData = parsed.result_data ?? parsed;
              } catch {
                n8nData = statusData.response;
              }
            }
            
            // Debug para confirmar estructura
            console.log("n8nData keys:", Object.keys(n8nData));
            console.log("General information:", n8nData["General information"]);
            
            if (n8nData) {
              // ========== ENVIAR JSON A LA BASE DE DATOS ==========
              try {
                resultadoTexto.textContent = 
                  `✅ PROCESO COMPLETADO\n\n` +
                  `Tiempo total: ${timeStr}\n\n` +
                  `💾 Guardando en base de datos...\n`;
                
                // Validar que sea serializable
                let jsonString;
                try {
                  jsonString = JSON.stringify(n8nData);
                  console.log("✅ JSON válido, longitud:", jsonString.length);
                } catch (serializeError) {
                  console.error("❌ ERROR: n8nData no es serializable:", serializeError);
                  throw new Error("Los datos de n8n contienen referencias circulares");
                }

                // Enviar a PHP
                const insertResp = await fetch('php_actions/process_json_import.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                  },
                  body: jsonString
                });
                
                console.log("✅ Fetch completado - Status:", insertResp.status);

                // Obtener respuesta
                const responseText = await insertResp.text();
                
                if (!responseText || responseText.trim() === '') {
                  throw new Error("El servidor devolvió una respuesta vacía");
                }

                // Parsear JSON
                let insertResult;
                try {
                  insertResult = JSON.parse(responseText);
                  console.log("✅ JSON parseado correctamente");
                } catch (parseError) {
                  console.error("❌ ERROR AL PARSEAR JSON:", parseError);
                  throw new Error(`El servidor devolvió una respuesta inválida: ${parseError.message}`);
                }
                
                if (insertResult.success) {
                  console.log("✅ Dataset guardado correctamente");
                  
                    // ========== DEBUGGING: VER QUÉ TRAE n8nData ==========
  console.log("=== CONTENIDO COMPLETO DE n8nData ===");
  console.log(n8nData);
  console.log("=== validation_result ===");
  console.log(n8nData.validation_result);
  console.log("=== Keys de n8nData ===");
  console.log(Object.keys(n8nData));
                  
                  // ========== DETENER POLLING INMEDIATAMENTE ==========
                  clearInterval(checkStatus);
                  console.log("✅ Polling detenido");
                  
                  // Generar bloque de validación si existe
                  let validationHtml = '';
                  if (n8nData.validation_result) {
                    const val = n8nData.validation_result;
                    const scoreColor = val.dqr_score <= 2 ? 'text-green-600' : (val.dqr_score <= 3 ? 'text-yellow-600' : 'text-red-600');
                    const bgClass = val.dqr_score <= 2 ? 'bg-green-50 border-green-200' : (val.dqr_score <= 3 ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200');
                    
                    let warnList = '';
                    if (val.warnings && val.warnings.length > 0) {
                      warnList = `<div class="mt-3 pt-3 border-t border-gray-200/50 text-xs text-gray-700">
                        <strong class="flex items-center gap-1 mb-1"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Observaciones:</strong>
                        <ul class="list-disc pl-4 space-y-1">
                          ${val.warnings.slice(0, 3).map(w => `<li>${w}</li>`).join('')}
                          ${val.warnings.length > 3 ? `<li class="italic text-gray-500">...y ${val.warnings.length - 3} más.</li>` : ''}
                        </ul>
                      </div>`;
                    }

                    validationHtml = `
                    <div class="mt-4 mb-4 p-4 rounded-lg border ${bgClass}">
                      <div class="flex justify-between items-start">
                        <div>
                          <h5 class="font-bold text-gray-800 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="w-5 h-5"></i> Auditoría de Calidad (ILCD)
                          </h5>
                          <p class="text-sm text-gray-600 mt-1">Nivel de confianza: <strong>${val.dqr_interpretation}</strong></p>
                        </div>
                        <div class="text-right">
                          <span class="block text-xs text-gray-500 uppercase font-bold">DQR Score</span>
                          <span class="text-3xl font-bold ${scoreColor}">${val.dqr_score}<span class="text-sm text-gray-400 font-normal">/5</span></span>
                        </div>
                      </div>
                      ${warnList}
                    </div>`;
                  }
                  
                  // Mostrar resultado con botón mejorado
                  resultadoTexto.innerHTML = 
                    `✅ DATASET IMPORTADO CORRECTAMENTE\n\n` +
                    `⏱️ Tiempo de procesamiento: ${timeStr}\n` +
                    `📦 Proceso: ${insertResult.process_name}\n` +
                    `📥 Inputs insertados: ${insertResult.inputs_count}\n` +
                    `📤 Outputs insertados: ${insertResult.outputs_count}\n\n` +
                    validationHtml +
                    `<div style="margin-top: 20px;">\n` +
                    `  <button id="btnVerDataset" data-uuid="${insertResult.process_uuid}" ` +
                    `style="display: inline-block; background-color: #059669; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: background-color 0.3s;" ` +
                    `onmouseover="this.style.backgroundColor='#047857'" ` +
                    `onmouseout="this.style.backgroundColor='#059669'">\n` +
                    `    Visualizar Dataset\n` +
                    `  </button>\n` +
                    `</div>`;
                  
                  resultado.classList.remove("hidden");
                  
                  // Re-inicializar iconos de Lucide
                  if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                  }
                  
                  // Re-habilitar botón de envío
                  btnEnviar.classList.remove("hidden");
                  btnEspera.classList.add("hidden");
                  
                  // ========== AGREGAR EVENT LISTENER AL BOTÓN ==========
                  setTimeout(() => {
                    const btnVerDataset = document.getElementById('btnVerDataset');
                    if (btnVerDataset) {
                      btnVerDataset.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log("✅ Botón clickeado, redirigiendo...");
                        const uuid = this.getAttribute('data-uuid');
                        console.log("UUID:", uuid);
                        
                        // Pequeña pausa antes de redirigir
                        setTimeout(() => {
                          window.location.href = `edit_dataset.php?uuid=${uuid}`;
                        }, 100);
                      });
                      console.log("✅ Event listener agregado al botón");
                    }
                  }, 100);
                  
                  alert("¡Dataset importado exitosamente!");
                  console.log("=== PROCESO COMPLETADO SIN ERRORES ===");
                  
                } else {
                  // Error en la inserción
                  clearInterval(checkStatus);
                  throw new Error(insertResult.error || 'Error al guardar en base de datos');
                }
                
              } catch (dbError) {
                console.error("❌ ERROR EN BASE DE DATOS:", dbError);
                clearInterval(checkStatus);
                
                resultadoTexto.textContent = 
                  `⚠️ PROCESADO PERO ERROR AL GUARDAR\n\n` +
                  `Error: ${dbError.message}`;
                
                resultado.classList.remove("hidden");
                btnEnviar.classList.remove("hidden");
                btnEspera.classList.add("hidden");
              }
              
            } else {
              // No hay datos - mostrar confirmación
              clearInterval(checkStatus);
              
              resultadoTexto.textContent = 
                `✅ PDF PROCESADO CORRECTAMENTE\n\n` +
                `Tiempo total: ${timeStr}\n` +
                `HTTP Code: ${statusData.http_code || 200}\n\n` +
                `ℹ️ El archivo fue enviado y procesado por n8n.\n\n` +
                `⚠️ Nota: n8n no devolvió datos en la respuesta.`;
              
              btnEnviar.classList.remove("hidden");
              btnEspera.classList.add("hidden");
            }
            
          } else if (statusData.status === 'error') {
            clearInterval(checkStatus);
            
            resultadoTexto.textContent = 
              `❌ ERROR AL PROCESAR\n\n` +
              `Error: ${statusData.error || 'Error desconocido'}`;
            
            alert("Error al procesar el archivo");
            
            btnEnviar.classList.remove("hidden");
            btnEspera.classList.add("hidden");
            
          } else if (statusData.status === 'timeout') {
            clearInterval(checkStatus);
            
            resultadoTexto.textContent += 
              `\n\n⚠️ El procesamiento excedió el tiempo máximo (15 min).`;
            
            btnEnviar.classList.remove("hidden");
            btnEspera.classList.add("hidden");
            
          } else if (attempts >= maxAttempts) {
            clearInterval(checkStatus);
            
            resultadoTexto.textContent += 
              `\n\n⚠️ Tiempo de espera agotado.`;
            
            btnEnviar.classList.remove("hidden");
            btnEspera.classList.add("hidden");
            
          } else {
            // Actualizar progreso
            resultadoTexto.textContent = 
              `✓ Procesando en n8n...\n` +
              `✓ Tiempo transcurrido: ${timeStr}\n` +
              `✓ Verificación ${attempts}/${maxAttempts}\n\n` +
              `Estado: ${statusData.message || 'Procesando...'}`;
          }
          
        } catch (error) {
          console.error("Error verificando estado:", error);
        }
        
      }, 3000); // Cada 3 segundos

    } catch (err) {
      console.error("Error completo:", err);
      resultadoTexto.textContent = "❌ Error: " + err.message;
      resultado.classList.remove("hidden");
      alert("Error: " + err.message);
      
      btnEnviar.classList.remove("hidden");
      btnEspera.classList.add("hidden");
    }
  });
}

// Iconos Lucide (fuera del event listener)
lucide.createIcons();
</script>
</body>
</html>
