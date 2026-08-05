<?php
session_start();
require_once 'php_actions/verificar_permisos.php';
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

// Cargar config
$config = require __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

// Obtener todos los procesos
$processes = [];
$query = "SELECT p.uuid, p.name, p.category, p.description, l.name as location 
          FROM processes p 
          LEFT JOIN locations l ON l.uuid = p.location_uuid 
          WHERE p.is_draft = 0
          ORDER BY p.name ASC";
$result = $mysqli->query($query);
while ($row = $result->fetch_assoc()) {
    $processes[] = $row;
}
$result->free();
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buscar Datasets</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image" href="icons/file-search.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    /* Estilos para el botón Cancelar del modal */
    .btn-cancel {
      flex: 1;
      background-color: #6b7280;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-weight: 500;
      transition: all 0.2s;
      cursor: pointer;
      border: none;
    }
    .btn-cancel:hover {
      background-color: #4b5563;
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
                <a href="search_dataset.php" class="flex items-center gap-3 text-gray-900 font-bold hover:text-green-700 text-sm">
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
  <h1 class="text-4xl font-bold mb-6 text-gray-900">
    Buscar Dataset para Editar
  </h1>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <!-- Buscador -->
      <div class="mb-6">
        <div class="relative">
          <input type="text" id="searchInput" placeholder="Buscar por nombre o categoría..." 
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg pr-10 focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>
      </div>

      <!-- Lista de datasets -->
      <div id="datasetList" class="space-y-3">
        <?php foreach ($processes as $proc): ?>
          <div class="dataset-item border border-gray-200 rounded-lg p-4 hover:bg-green-50 transition cursor-pointer"
               data-name="<?= strtolower(htmlspecialchars($proc['name'])) ?>"
               data-category="<?= strtolower(htmlspecialchars($proc['category'] ?? '')) ?>"
               data-uuid="<?= htmlspecialchars($proc['uuid']) ?>">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($proc['name']) ?></h3>
                <p class="text-sm text-gray-600">
                  <?= htmlspecialchars($proc['category'] ?? 'Sin categoría') ?> 
                  <?php if (!empty($proc['location'])): ?>
                    | <?= htmlspecialchars($proc['location']) ?>
                  <?php endif; ?>
                </p>
                <?php if (!empty($proc['description'])): ?>
                  <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars(substr($proc['description'], 0, 150)) ?>...</p>
                <?php endif; ?>
              </div>
              <button onclick="openEditModal('<?= htmlspecialchars($proc['uuid']) ?>', '<?= htmlspecialchars(addslashes($proc['name'])) ?>')"
                      class="ml-4 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                <i data-lucide="edit" class="w-4 h-4"></i>
                Editar
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="noResults" class="hidden text-center py-8 text-gray-500">
        No se encontraron datasets con ese criterio de búsqueda.
      </div>
    </div>
  </main>

<!-- Modal de confirmación -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Crear Copia para Editar</h2>
    <p class="text-gray-600 mb-6">
      Se creará una copia del dataset "<span id="datasetName" class="font-semibold"></span>" 
      que podrás editar. El dataset original no se modificará.
    </p>
    <input type="hidden" id="originalUuid">
    <div class="flex gap-3">
      <button onclick="createCopy()" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition">
        Continuar
      </button>
      <button onclick="closeEditModal()" class="btn-cancel">
        Cancelar
      </button>
    </div>
  </div>
</div>

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

  // Búsqueda en tiempo real
  document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.dataset-item');
    let visibleCount = 0;

    items.forEach(item => {
      const name = item.getAttribute('data-name');
      const category = item.getAttribute('data-category');
      
      if (name.includes(searchTerm) || category.includes(searchTerm)) {
        item.style.display = '';
        visibleCount++;
      } else {
        item.style.display = 'none';
      }
    });

    document.getElementById('noResults').classList.toggle('hidden', visibleCount > 0);
  });

  function openEditModal(uuid, name) {
    document.getElementById('originalUuid').value = uuid;
    document.getElementById('datasetName').textContent = name;
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
  }

  function closeEditModal() {
    document.getElementById('editModal').classList.remove('flex');
    document.getElementById('editModal').classList.add('hidden');
  }

  function createCopy() {
    const uuid = document.getElementById('originalUuid').value;

    fetch('create_copy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ original_uuid: uuid })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.location.href = 'edit_dataset.php?uuid=' + data.new_uuid;
      } else {
        console.error('Error al crear copia:', data.message);
        alert('Error al crear copia: ' + data.message);
      }
    })
    .catch(err => {
      console.error('Error en la petición:', err);
      alert('Error en la petición al crear copia');
    });
  }

  // Funciones para abrir y cerrar modales
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

  // Toggle menú perfil
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');
  if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', () => {
      userDropdown.classList.toggle('hidden');
    });
  }

  // Configuración - abrir modales internos
  document.getElementById('btnChangePhoto')?.addEventListener('click', () => {
    closeModal('configModal');
    openModal('changePhotoModal');
  });
  document.getElementById('btnChangePassword')?.addEventListener('click', () => {
    closeModal('configModal');
    openModal('changePasswordModal');
  });
  document.getElementById('btnRequestAdmin')?.addEventListener('click', function() {
  fetch('request_admin.php', { method: 'POST' })
    .then(res => res.json())
    .then(data => {
      alert(data.message);
      if(data.success) {
        this.disabled = true;  // deshabilitar botón tras solicitar
      }
    });
});

  // Cerrar menú si se hace click fuera
  document.addEventListener('click', (e) => {
    if (userMenuBtn && userDropdown && !userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.classList.add('hidden');
    }
  });
  
  //MENU DESPLEGABLE DE AÑADIR DATASETS  
  function toggleDropdown(menuId) {
    const menu = document.getElementById(menuId);
    menu.classList.toggle('hidden');
}

// Cerrar el menú al hacer clic fuera de él (opcional)
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('datasetMenu');
    const button = event.target.closest('button');
    
    if (!button && dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
    }
});
  </script>
</body>
</html>
