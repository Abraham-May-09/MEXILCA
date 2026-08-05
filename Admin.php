<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'php_actions/verificar_permisos.php';

// Cargar config
$config = include __DIR__ . '/config.php';
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($conn->connect_error) {
  die("ERROR: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

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

// Contar procesos pendientes
$sql_count = "SELECT COUNT(*) as total FROM processes WHERE approval_status = 'pending'";
$result_count = $conn->query($sql_count);
$pending = $result_count ? $result_count->fetch_assoc()['total'] : 0;
// Contar solicitudes de admin
$sql_admin = "SELECT COUNT(*) as total FROM users WHERE admin_request = 1";
$result_admin = $conn->query($sql_admin);
$admin_requests = $result_admin ? $result_admin->fetch_assoc()['total'] : 0;

$datasets_pendientes = $pending;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración</title>
  <link rel="stylesheet" href="/src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image" href="icons/shield-user.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script defer>
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
      lucide.createIcons();
      
      // Menú de usuario
      const userMenuBtn = document.getElementById('userMenuBtn');
      const userDropdown = document.getElementById('userDropdown');
      if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', () => userDropdown.classList.toggle('hidden'));
        document.addEventListener('click', (e) => {
          if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.classList.add('hidden');
          }
        });
      }

      // Botones de modal
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
              if(data.success) this.disabled = true;
            });
        });
      }

      // Cerrar dropdown al hacer clic fuera
      document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('datasetMenu');
        const button = event.target.closest('button');
        if (!button && dropdown && !dropdown.classList.contains('hidden')) {
          dropdown.classList.add('hidden');
        }
      });
    });
    
    // ✅ FUNCIONES GLOBALES (disponibles para onclick)
    function switchTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
      document.querySelectorAll('nav button').forEach(tab => {
        tab.classList.remove('tab-active');
        tab.classList.add('text-gray-600');
      });
      document.getElementById('content-' + tabName).classList.remove('hidden');
      const activeTab = document.getElementById('tab-' + tabName);
      activeTab.classList.add('tab-active');
      activeTab.classList.remove('text-gray-600');
      if (tabName === 'usuarios') loadUsers();
    }

    function loadUsers() {
      fetch('get_users.php')
        .then(res => res.json())
        .then(data => {
          if (data.success) displayUsers(data.users);
          else document.getElementById('usersList').innerHTML = '<p class="text-red-500">Error al cargar usuarios</p>';
        })
        .catch(err => {
          console.error(err);
          document.getElementById('usersList').innerHTML = '<p class="text-red-500">Error de conexión</p>';
        });
    }

    function displayUsers(users) {
      const usersList = document.getElementById('usersList');
      if (users.length === 0) {
        usersList.innerHTML = '<p class="text-gray-500">No hay usuarios registrados</p>';
        return;
      }
      let html = '<div class="space-y-3">';
      users.forEach(user => {
        const isAdmin = user.role === 'ADMIN';
        const nameClass = isAdmin ? 'admin-user' : '';
        const roleBadge = isAdmin ? '<span class="ml-2 px-2 py-1 bg-green-700 text-white text-xs rounded">ADMIN</span>' : '';
        
        const downloadBadge = user.can_download == 1 
          ? '<span class="ml-2 px-2 py-1 bg-blue-600 text-white text-xs rounded flex items-center gap-1"><i data-lucide="download" class="w-3 h-3"></i> Descarga</span>' 
          : '';
        
        const actions = isAdmin ? '' : `
          <button onclick="openEditUser('${user.uuid}', '${user.name.replace(/'/g, "\\'")}  ')" class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1">
            <i data-lucide="edit" class="w-4 h-4"></i> Editar
          </button>
          ${user.can_download == 1 
            ? `<button onclick="revokeDownloadPermission('${user.uuid}', '${user.name.replace(/'/g, "\\'")}')" class="text-orange-600 hover:text-orange-800 text-sm flex items-center gap-1">
                <i data-lucide="download-off" class="w-4 h-4"></i> Revocar Descarga
              </button>`
            : `<button onclick="grantDownloadPermission('${user.uuid}', '${user.name.replace(/'/g, "\\'")}')" class="text-green-600 hover:text-green-800 text-sm flex items-center gap-1">
                <i data-lucide="download" class="w-4 h-4"></i> Permitir Descarga
              </button>`
          }
          <button onclick="deleteUser('${user.uuid}', '${user.name.replace(/'/g, "\\'")}')" class="text-red-600 hover:text-red-800 text-sm flex items-center gap-1">
            <i data-lucide="trash-2" class="w-4 h-4"></i> Eliminar
          </button>
        `;
        
        html += `
          <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
            <div>
              <p class="${nameClass} flex items-center">${user.name}${roleBadge}${downloadBadge}</p>
              <p class="text-sm text-gray-600">${user.email}</p>
              <p class="text-xs text-gray-400">Registrado: ${new Date(user.created_at).toLocaleDateString()}</p>
            </div>
            <div class="flex gap-3">${actions}</div>
          </div>
        `;
      });
      html += '</div>';
      usersList.innerHTML = html;
      lucide.createIcons();
    }

    function grantDownloadPermission(uuid, name) {
      if (!confirm(`¿Otorgar permiso de descarga a "${name}"?`)) return;
      
      const formData = new FormData();
      formData.append('user_uuid', uuid);
      formData.append('action', 'grant');
      
      fetch('php_actions/grant_download_permission.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        alert(data.message);
        if (data.success) loadUsers();
      })
      .catch(err => {
        console.error(err);
        alert('Error en la solicitud');
      });
    }

    function revokeDownloadPermission(uuid, name) {
      if (!confirm(`¿Revocar permiso de descarga a "${name}"?`)) return;
      
      const formData = new FormData();
      formData.append('user_uuid', uuid);
      formData.append('action', 'revoke');
      
      fetch('php_actions/grant_download_permission.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        alert(data.message);
        if (data.success) loadUsers();
      })
      .catch(err => {
        console.error(err);
        alert('Error en la solicitud');
      });
    }

    function openEditUser(uuid, name) {
      document.getElementById('editUserUuid').value = uuid;
      document.getElementById('editUserName').value = name;
      openModal('editUserModal');
    }

    function saveUserName() {
      const uuid = document.getElementById('editUserUuid').value;
      const newName = document.getElementById('editUserName').value.trim();
      if (!newName) {
        alert('Por favor ingresa un nombre');
        return;
      }
      fetch('edit_user_name.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_uuid: uuid, new_name: newName })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          closeModal('editUserModal');
          loadUsers();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error en la solicitud');
      });
    }

    function deleteUser(uuid, name) {
      if (!confirm(`¿Estás seguro de eliminar al usuario "${name}"?`)) return;
      fetch('delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_uuid: uuid })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          loadUsers();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error en la solicitud');
      });
    }

    function toggleSection(sectionId) {
      const section = document.getElementById(sectionId);
      if (section) section.classList.toggle('hidden');
    }

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

    function handleAdminRequest(button, action, userUuid) {
      const card = button.closest('[data-useruuid]');
      const note = card?.querySelector('textarea[name="admin_note"]')?.value || '';
      fetch('handle_admin_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_uuid: userUuid, action: action, note: note })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          card.remove();
          alert(data.message);
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error en la solicitud.');
      });
    }

    function toggleDropdown(menuId) {
      const menu = document.getElementById(menuId);
      menu.classList.toggle('hidden');
    }

    function handleDatasetRequest(button, action, procesoUuid) {
      const card = button.closest('[data-procesouuid]');
      const reasonTextarea = card?.querySelector('textarea[name="rejection_reason"]');
      const reason = reasonTextarea?.value.trim() || '';
      
      if (action === 'reject' && !reason) {
        alert('Debes proporcionar un motivo para el rechazo');
        reasonTextarea.focus();
        return;
      }
      
      const confirmMsg = action === 'approve' ? '¿Aprobar este proceso?' : '¿Rechazar este proceso?';
      if (!confirm(confirmMsg)) return;
      
      const buttons = card.querySelectorAll('button');
      buttons.forEach(btn => btn.disabled = true);
      button.textContent = 'Procesando...';
      
      fetch('php_actions/procesar_aprobacion_proceso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proceso_uuid: procesoUuid, action: action, reason: reason })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          card.remove();
          const remainingCards = document.querySelectorAll('#content-datasets [data-procesouuid]');
          if (remainingCards.length === 0) {
            document.getElementById('content-datasets').innerHTML = '<p class="text-gray-500">No hay solicitudes pendientes.</p>';
          }
        } else {
          alert('Error: ' + data.message);
          buttons.forEach(btn => btn.disabled = false);
          button.textContent = action === 'approve' ? 'Aprobar' : 'Rechazar';
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error en la solicitud');
        buttons.forEach(btn => btn.disabled = false);
        button.textContent = action === 'approve' ? 'Aprobar' : 'Rechazar';
      });
    }
  </script>
  
  <style>
    body { font-family: 'Inter', sans-serif; }
    .tab-active { border-bottom: 2px solid #15803d; color: #15803d; font-weight: 600; }
    .admin-user { font-weight: 700; }
    
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
        <a href="contactos.php" hidden class="flex items-center gap-3 hover:text-green-700"><i data-lucide="mail" class="w-5 h-5"></i> Contactos</a>
        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'ADMIN'): ?>
            <a href="Admin.php" class="flex items-center gap-3 hover:text-green-700 font-semibold text-gray-900">
                <i data-lucide="shield-user" class="w-5 h-5"></i>
                Administración
                <?php if ($pending > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full ml-auto">
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
                    <i data-lucide="file-pen" class="w-4 h-4"></i>
                    Mis Borradores
                    <?php if (count($borradores) > 0): ?>
                        <span class="bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full ml-auto">
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
        <p class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION["email"] ?? 'Correo no disponible') ?></p>
      </div>
    </button>
    <div id="userDropdown" class="absolute bottom-16 left-0 bg-white border border-gray-200 shadow-lg rounded-md hidden w-44 z-50 text-sm">
      <button onclick="openModal('configModal')" class="block w-full px-4 py-2 text-left hover:bg-gray-100">Configuración</button>
      <a href="logout.php" class="block w-full px-4 py-2 text-left hover:bg-gray-100">Cerrar sesión</a>
    </div>
  </nav>
<?php else: ?>
  <div class="border-t pt-4 mt-4 w-full max-w-xs mx-auto">
    <div class="flex flex-col gap-3">
      <a href="login.php" class="flex items-center justify-center gap-2 bg-green-700 text-white text-sm py-2 rounded-lg hover:bg-green-600 transition shadow">
        <i data-lucide="log-in" class="w-4 h-4"></i>
        Iniciar sesión
      </a>
    </div>
  </div>
<?php endif; ?>
  </aside>

  <!-- Contenido Principal -->
  <main class="ml-64 flex-1 p-10">
    <h1 class="text-3xl font-bold mb-6 text-green-700">Panel de Administración</h1>

      <!-- PESTAÑAS -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex gap-8">
        <button onclick="switchTab('solicitudes')" id="tab-solicitudes" class="tab-active pb-3 text-sm flex items-center gap-2">
          Solicitudes de Admin
          <?php if ($admin_requests > 0): ?>
            <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-full">
              <?= $admin_requests ?>
            </span>
          <?php endif; ?>
        </button>
        <button onclick="switchTab('usuarios')" id="tab-usuarios" class="pb-3 text-sm text-gray-600 hover:text-green-700">
          Gestión de Usuarios
        </button>
        <button onclick="switchTab('datasets')" id="tab-datasets" class="pb-3 text-sm text-gray-600 hover:text-green-700 flex items-center gap-2">
          Solicitudes de Datasets
          <?php if ($datasets_pendientes > 0): ?>
            <span class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
              <?= $datasets_pendientes ?>
            </span>
          <?php endif; ?>
        </button>
      </nav>
    </div>

    <!-- TAB: Solicitudes de Admin -->
    <div id="content-solicitudes" class="tab-content">
      <?php
        $stmt = $conn->prepare('SELECT uuid, name, email FROM users WHERE admin_request = 1 ORDER BY COALESCE(admin_requested_at, created_at) DESC');
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows > 0):
          while ($row = $resultado->fetch_assoc()):
            $userUuid = htmlspecialchars($row['uuid']);
            $nombre = htmlspecialchars($row['name']);
            $email = htmlspecialchars($row['email']);
      ?>
        <div class="border border-gray-200 rounded-xl shadow-sm mb-4" data-useruuid="<?= $userUuid ?>">
          <button onclick="toggleSection('admin<?= $userUuid ?>')" class="w-full text-left p-4 flex justify-between items-center hover:bg-green-50">
            <span class="font-medium"><?= $nombre ?> [<?= $email ?>]</span>
            <i data-lucide="chevron-down" class="w-5 h-5"></i>
          </button>
          <div id="admin<?= $userUuid ?>" class="hidden border-t border-gray-200 p-4 space-y-3">
            <p><strong>Nombre:</strong> <?= $nombre ?></p>
            <p><strong>Correo:</strong> <?= $email ?></p>
            <textarea name="admin_note" placeholder="Comentario (opcional)" class="w-full border border-gray-300 rounded-lg p-2 text-sm"></textarea>
            <div class="flex gap-3">
              <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm" onclick="handleAdminRequest(this, 'approve', '<?= $userUuid ?>')">Aprobar</button>
              <button class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm" onclick="handleAdminRequest(this, 'reject', '<?= $userUuid ?>')">Rechazar</button>
            </div>
          </div>
        </div>
      <?php endwhile; else: ?>
        <p class="text-gray-500">No hay solicitudes pendientes.</p>
      <?php endif; $stmt->close(); ?>
    </div>

    <!-- TAB: Gestión de Usuarios -->
    <div id="content-usuarios" class="tab-content hidden">
      <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Usuarios del Sistema</h2>
        <div id="usersList" class="space-y-3">
          <p class="text-gray-500">Cargando usuarios...</p>
        </div>
      </div>
    </div>

    <!-- TAB: Solicitudes de Datasets -->
    <div id="content-datasets" class="tab-content hidden">
      <?php
        $stmt = $conn->prepare('SELECT p.uuid, p.name, p.description, p.process_type, p.category, p.sector_principal, p.created_at, u.name as creator_name, u.email as creator_email FROM processes p JOIN users u ON p.created_by_uuid = u.uuid WHERE p.approval_status = "pending" ORDER BY p.created_at DESC');
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows > 0):
          while ($row = $resultado->fetch_assoc()):
            $procesoUuid = htmlspecialchars($row['uuid']);
            $nombre = htmlspecialchars($row['name']);
            $descripcion = htmlspecialchars($row['description']);
            $tipo = htmlspecialchars($row['process_type']);
            $categoria = htmlspecialchars($row['category']);
            $sector = htmlspecialchars($row['sector_principal']);
            $creatorName = htmlspecialchars($row['creator_name']);
            $creatorEmail = htmlspecialchars($row['creator_email']);
            $fecha = date('d/m/Y H:i', strtotime($row['created_at']));
      ?>
        <div class="border border-gray-200 rounded-xl shadow-sm mb-4" data-procesouuid="<?= $procesoUuid ?>">
          <button onclick="toggleSection('proceso<?= $procesoUuid ?>')" class="w-full text-left p-4 flex justify-between items-center hover:bg-green-50">
            <span class="font-medium"><?= $nombre ?> [<?= $creatorEmail ?>]</span>
            <i data-lucide="chevron-down" class="w-5 h-5"></i>
          </button>
          
          <div id="proceso<?= $procesoUuid ?>" class="hidden border-t border-gray-200 p-4 space-y-3">
            <div class="bg-gray-50 p-4 rounded-lg space-y-2">
              <p><strong>Nombre:</strong> <?= $nombre ?></p>
              <p><strong>Propuesto por:</strong> <?= $creatorName ?></p>
              <p><strong>Correo:</strong> <?= $creatorEmail ?></p>
              <p><strong>Fecha:</strong> <?= $fecha ?></p>
              <p><strong>Tipo:</strong> <?= $tipo ?></p>
              <p><strong>Categoría:</strong> <?= $categoria ?></p>
              <p><strong>Sector:</strong> <?= $sector ?></p>
              <?php if (!empty($descripcion)): ?>
                <p><strong>Descripción:</strong> <?= $descripcion ?></p>
              <?php endif; ?>
            </div>
            
            <div class="flex gap-3 pb-4 border-b border-gray-200">
              <a href="Procesos.php?uuid=<?= $procesoUuid ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm flex items-center gap-2 inline-flex transition shadow-sm">
                <i data-lucide="file-search" class="w-4 h-4"></i>
                Ver Dataset Completo
              </a>
            </div>
            
            <textarea name="rejection_reason" placeholder="Motivo del rechazo (obligatorio si rechazas)" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
            
            <div class="flex gap-3">
              <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm transition flex items-center gap-2" onclick="handleDatasetRequest(this, 'approve', '<?= $procesoUuid ?>')">
                <i data-lucide="check" class="w-4 h-4"></i>
                Aprobar
              </button>
              <button class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm transition flex items-center gap-2" onclick="handleDatasetRequest(this, 'reject', '<?= $procesoUuid ?>')">
                <i data-lucide="x" class="w-4 h-4"></i>
                Rechazar
              </button>
            </div>
          </div>
        </div>
      <?php endwhile; else: ?>
        <p class="text-gray-500">No hay solicitudes pendientes.</p>
      <?php endif; $stmt->close(); ?>
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
      <form action="change_password.php" method="POST">
        <input name="new_password" type="password" placeholder="Nueva contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" required />
        <input name="confirm_password" type="password" placeholder="Confirmar contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" required />
        <button type="submit" class="w-full bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition">Guardar</button>
      </form>
      <div class="text-right mt-4">
        <button onclick="closeModal('changePasswordModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
      </div>
    </div>
  </div>

  <div id="editUserModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">Editar Nombre de Usuario</h2>
      <input type="hidden" id="editUserUuid">
      <input type="text" id="editUserName" placeholder="Nuevo nombre" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-4" />
      <div class="flex gap-3">
        <button onclick="saveUserName()" class="flex-1 bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition">Guardar</button>
        <button onclick="closeModal('editUserModal')" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition">Cancelar</button>
      </div>
    </div>
  </div>


</body>
</html>
<?php $conn->close(); ?>
