<?php
session_start();
require_once 'php_actions/verificar_permisos.php';

// Conexión y obtención de datos
require_once 'conexion.php';
$user_uuid = $_SESSION['user_uuid'] ?? null;
if (!$user_uuid) {
    header('Location: login.php');
    exit();
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

// Obtener todos los datasets del usuario
$datasets = [];
$stmt_contribuciones = $conn->prepare("
    SELECT p.uuid, p.name, p.approval_status, p.created_at, p.rejection_reason, l.name as location_name
    FROM processes p
    LEFT JOIN locations l ON p.location_uuid = l.uuid
    WHERE p.created_by_uuid = ?
    ORDER BY p.created_at DESC
");
$stmt_contribuciones->bind_param("s", $user_uuid);
$stmt_contribuciones->execute();
$result_contribuciones = $stmt_contribuciones->get_result();
while ($row = $result_contribuciones->fetch_assoc()) {
    $datasets[] = $row;
}
$stmt_contribuciones->close();

// Separar datasets en grupos
$en_revision = array_filter($datasets, fn($ds) => in_array($ds['approval_status'], ['pending', 'rejected']));
$aprobados = array_filter($datasets, fn($ds) => $ds['approval_status'] === 'approved');

// Función para badges de estado
function getStatusBadge($status) {
    $styles = ['pending' => 'bg-yellow-200 text-yellow-800', 'approved' => 'bg-green-200 text-green-800', 'rejected' => 'bg-red-200 text-red-800'];
    $text = ['pending' => 'En Revisión', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];
    $style = $styles[$status] ?? 'bg-gray-200 text-gray-800';
    $status_text = $text[$status] ?? 'Desconocido';
    return "<span class='text-xs font-semibold me-2 px-2.5 py-1 rounded-full {$style}'>{$status_text}</span>";
}

// Cargar datos para el menú lateral
$borradores = [];
$stmt_borradores = $conn->prepare("SELECT uuid FROM processes WHERE created_by_uuid = ? AND approval_status = 'draft'");
$stmt_borradores->bind_param("s", $user_uuid);
$stmt_borradores->execute();
$result_borradores = $stmt_borradores->get_result();
while ($row = $result_borradores->fetch_assoc()) {
    $borradores[] = $row;
}
$stmt_borradores->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Contribuciones</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image" href="icons/receipt-text.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .tab-active { border-bottom: 2px solid #15803d; color: #15803d; font-weight: 600; }
    
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
                <a href="mis_datasets.php" class="flex items-center gap-3 text-gray-800 font-bold hover:text-green-700 text-sm">
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

  <!-- Contenido Principal -->
  <main class="ml-64 p-10">
    <header class="mb-6">
        <h1 class="text-3xl font-bold text-green-700">Mis Contribuciones</h1>
        <p class="text-gray-600 mt-1">Gestiona los datasets que has enviado y consulta el historial de tus aportaciones.</p>
    </header>
    <!-- PESTAÑAS DE NAVEGACIÓN -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex gap-8 -mb-px">
        <button onclick="switchTab('aprobados')" id="tab-aprobados" class="tab-active pb-3 text-sm">
          Contribuciones
        </button>
        <button onclick="switchTab('revision')" id="tab-revision" class="pb-3 text-sm text-gray-600 hover:text-green-700">
          En Revisión
        </button>
      </nav>
    </div>
<!-- CONTENIDO PESTAÑA 1: APROBADOS -->
    <div id="content-aprobados" class="tab-content">
      <?php if (empty($aprobados)) : ?>
        <div class="p-8 rounded-lg"><p class="text-gray-500">Aún no tienes contribuciones aprobadas.</p></div>
      <?php else : ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden border">
          <table class="w-full text-sm text-left text-gray-600">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3">Nombre del Dataset</th>
                <th scope="col" class="px-6 py-3">Geografía</th>
                <th scope="col" class="px-6 py-3">Fecha de Envío</th>
                <th scope="col" class="px-6 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aprobados as $ds) : ?>
                <tr class="bg-white border-b last:border-b-0 hover:bg-gray-50">
                  <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap"><?= htmlspecialchars($ds['name']) ?></th>
                  <td class="px-6 py-4"><?= htmlspecialchars($ds['location_name'] ?? 'N/A') ?></td>
                  <td class="px-6 py-4"><?= date('d/m/Y', strtotime($ds['created_at'])) ?></td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <a href="Procesos.php?uuid=<?= htmlspecialchars($ds['uuid']) ?>" class="font-medium text-green-600 hover:underline">Ver detalles</a>
                      <button onclick="openExportModal('<?= htmlspecialchars($ds['uuid']) ?>')" class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                        <i data-lucide="download" class="w-3 h-3"></i>
                        Exportar
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <!-- CONTENIDO PESTAÑA 2: EN REVISIÓN -->
    <div id="content-revision" class="tab-content hidden">
      <?php if (empty($en_revision)) : ?>
        <div class="p-8 rounded-lg"><p class="text-gray-500">No tienes datasets pendientes de revisión o que requieran correcciones.</p></div>
      <?php else : ?>
        <div class="space-y-4">
          <?php foreach ($en_revision as $ds) : ?>
            <div class="border <?= $ds['approval_status'] === 'rejected' ? 'border-red-300 bg-red-50/50' : 'border-gray-200 bg-white' ?> rounded-xl shadow-sm" data-uuid="<?= $ds['uuid'] ?>">
              <button onclick="toggleSection('ds-<?= $ds['uuid'] ?>')" class="w-full text-left p-4 flex justify-between items-center hover:bg-gray-50">
                <span class="font-semibold text-gray-800"><?= htmlspecialchars($ds['name']) ?></span>
                <div class="flex items-center gap-4">
                  <?= getStatusBadge($ds['approval_status']) ?>
                  <i data-lucide="chevron-down" class="w-5 h-5 text-gray-500"></i>
                </div>
              </button>
              <div id="ds-<?= $ds['uuid'] ?>" class="hidden border-t border-gray-200 p-6 space-y-4">
                <p class="text-sm text-gray-600"><strong>Enviado:</strong> <?= date('d/m/Y', strtotime($ds['created_at'])) ?></p>
                <?php if ($ds['approval_status'] === 'rejected' && !empty($ds['rejection_reason'])) : ?>
                  <div class="bg-red-100 border-l-4 border-red-500 text-red-800 p-4 rounded-r-lg">
                    <p class="font-bold">Motivo del Rechazo:</p>
                    <p class="text-sm"><?= htmlspecialchars($ds['rejection_reason']) ?></p>
                  </div>
                <?php endif; ?>
                <div class="pt-4">
                  <a href="edit_dataset.php?uuid=<?= htmlspecialchars($ds['uuid']) ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors text-sm shadow-sm">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                    Corregir y Reenviar
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
<!-- Modal de Exportar Dataset -->
<!-- Modal de Exportar Dataset -->
<div id="exportModal" class="text-center flex fixed inset-0 bg-black/30 backdrop-blur-sm bg-opacity-40 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
    <button onclick="closeModal('exportModal')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-2xl font-bold mb-4">Exportar Datos</h2>
    <div class="grid grid-cols-1 md:grid-cols-1 gap-4 items-center text-center">
      <div>
        <table class="w-full text-sm border items-center border-black text-center">
          <tbody>
            <tr class="hidden">
              <td class="border px-4 py-2">XML</td>
              <td class="border px-4 py-2">
                <a href="#" onclick="downloadFormat('ilcd')" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100">
                  <i data-lucide="download" class="w-4 h-4"></i> Descargar
                </a>
              </td>
            </tr>
            <tr>
              <td class="border px-4 py-2">PDF</td>
              <td class="border px-4 py-2">
                <a href="#" onclick="downloadFormat('pdf')" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100">
                  <i data-lucide="download" class="w-4 h-4"></i> Descargar
                </a>
              </td>
            </tr>
            <tr>
              <td class="border px-4 py-2">JSON-LD</td>
              <td class="border px-4 py-2">
                <a href="#" onclick="downloadFormat('jsonld')" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100 cursor-pointer">
                  <i data-lucide="download" class="w-4 h-4"></i> Descargar
                </a>
              </td>
            </tr>
            <tr>
              <td class="border px-4 py-2">Excel</td>
              <td class="border px-4 py-2">
                <a href="#" onclick="downloadFormat('excel')" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100 cursor-pointer">
                  <i data-lucide="download" class="w-4 h-4"></i> Descargar
                </a>
              </td>
            </tr>
            <tr class="hidden">
              <td class="hidden border px-4 py-2">EcoSpold</td>
              <td class="border px-4 py-2">
                <a href="#" onclick="downloadFormat('ecospold')" class="inline-flex items-center gap-2 border border-gray-300 px-3 py-1 rounded hover:bg-green-100 cursor-pointer">
                  <i data-lucide="download" class="w-4 h-4"></i> Descargar
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
  </main>
<!-- Modales -->
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
  <script>
    // Iconos Lucide
     lucide.createIcons();
  
    //ABRIR PESTAÑAS ESTILIZADAS
    function switchTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
      document.querySelectorAll('nav button[id^="tab-"]').forEach(tab => {
        tab.classList.remove('tab-active', 'text-green-700');
        tab.classList.add('text-gray-600');
      });
      document.getElementById('content-' + tabName).classList.remove('hidden');
      const activeTab = document.getElementById('tab-' + tabName);
      activeTab.classList.add('tab-active', 'text-green-700');
      activeTab.classList.remove('text-gray-600');
    }

    function toggleSection(sectionId) {
      const section = document.getElementById(sectionId);
      const icon = section.previousElementSibling.querySelector('[data-lucide="chevron-down"]');
      if (section) {
          section.classList.toggle('hidden');
          icon.classList.toggle('rotate-180');
      }
    }

  // Funciones para abrir y cerrar modales
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

  // MENU DESPLEGABLE DE AÑADIR DATASETS  
  function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    if (menu) {
      menu.classList.toggle('hidden');
    }
  }

  // Inicialización cuando el DOM está listo
  document.addEventListener('DOMContentLoaded', () => {
    // Toggle menú perfil
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuBtn && userDropdown) {
      userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('hidden');
      });

      // Cerrar menú si se hace click fuera
      document.addEventListener('click', (e) => {
        if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
          userDropdown.classList.add('hidden');
        }
      });
    }

    // Configuración - abrir modales internos
    const btnChangePhoto = document.getElementById('btnChangePhoto');
    const btnChangePassword = document.getElementById('btnChangePassword');
    const btnRequestAdmin = document.getElementById('btnRequestAdmin');

    if (btnChangePhoto) {
      btnChangePhoto.addEventListener('click', () => {
        closeModal('configModal');
        openModal('changePhotoModal');
      });
    }

    if (btnChangePassword) {
      btnChangePassword.addEventListener('click', () => {
        closeModal('configModal');
        openModal('changePasswordModal');
      });
    }

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
          .catch(err => console.error('Error:', err));
      });
    }

    // Cerrar menú dataset al hacer clic fuera
    document.addEventListener('click', function(event) {
      const dropdown = document.getElementById('datasetMenu');
      const button = event.target.closest('button');
      
      if (!button && dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
      }
    });
  });
  <?php $conn->close(); ?>
  
  //DESCARGAS DE MIS DATASETS
  // Variable para guardar el UUID del dataset actual
let currentExportUUID = '';

// Abrir modal de exportación
function openExportModal(uuid) {
  currentExportUUID = uuid;
  openModal('exportModal');
  lucide.createIcons(); // Reiniciar iconos después de abrir modal
}

// Descargar en el formato seleccionado
function downloadFormat(format) {
  if (!currentExportUUID) {
    alert('Error: No se ha seleccionado un dataset válido.');
    return;
  }

  let url = '';
  switch(format) {
    case 'ilcd':
      url = 'export_ilcd.php?uuid=' + encodeURIComponent(currentExportUUID);
      break;
    case 'pdf':
      url = '/exports/export_pdf.php?uuid=' + encodeURIComponent(currentExportUUID);
      break;
    case 'jsonld':
      url = 'export_jsonld.php?uuid=' + encodeURIComponent(currentExportUUID);
      break;
    case 'excel':
      url = 'export_excel.php?uuid=' + encodeURIComponent(currentExportUUID);
      break;
    case 'ecospold':
      url = 'export_ecospold.php?uuid=' + encodeURIComponent(currentExportUUID);
      break;
    default:
      alert('Formato no válido');
      return;
  }

  // Abrir en nueva ventana para descargar
  window.open(url, '_blank');
  
  // Cerrar modal después de iniciar descarga
  setTimeout(() => {
    closeModal('exportModal');
  }, 500);
}
  </script>
</body>
</html>
