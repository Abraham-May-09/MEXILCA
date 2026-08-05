<?php
session_start();
require_once 'php_actions/verificar_permisos.php';
require_once 'conexion.php';
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

if (!isset($_SESSION['user_uuid'])) {
    header('Location: login.php');
    exit;
}

$uid   = $_SESSION['user_uuid'] ?? $_SESSION['user_id'] ?? null;
$name  = $_SESSION['name'] ?? $_SESSION['nombre'] ?? null;
$email = $_SESSION['email'] ?? null;
$photo = $_SESSION['photo_url'] ?? $_SESSION['foto'] ?? 'default-profile.png';

// Config
$config = require __DIR__ . '/config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

// Obtener borradores del usuario
$sql = "SELECT p.uuid, p.name, p.description, p.category, p.approval_status, 
        p.is_imported, l.name as location, p.created_at, p.last_change
        FROM processes p
        LEFT JOIN locations l ON l.uuid = p.location_uuid
        WHERE p.is_draft = 1 AND p.created_by_uuid = ?
        ORDER BY p.last_change DESC, p.created_at DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $uid);  // 👈 Usar $uid en lugar de $user_uuid
$stmt->execute();
$result = $stmt->get_result();
$borradores = [];
while ($row = $result->fetch_assoc()) {
    $borradores[] = $row;
}
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Borradores</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image/png" href="icons/file-pen.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    
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
                <a href="mis_borradores.php" class="flex items-center gap-3 text-gray-900 font-bold hover:text-green-700 text-sm">
                    <i data-lucide="file-edit" class="w-4 h-4"></i>
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

    <!-- Perfil de usuario -->
    <?php if (isset($_SESSION["user_uuid"])): ?>
      <nav class="relative border-t pt-4 mt-4 text-sm text-gray-700">
        <button id="userMenuBtn" class="w-full text-center hover:text-green-700 transition">
          <div class="flex flex-col items-center gap-1">
            <img id="profileImg" src="<?= htmlspecialchars($photo) ?>?v=<?= time() ?>" alt="Foto de Perfil" class="w-20 h-20 rounded-full object-cover border border-gray-300" />
            <p class="font-semibold"><?= htmlspecialchars($name) ?></p>
            <p class="text-xs text-gray-500"><?= htmlspecialchars($email) ?></p>
          </div>
        </button>
        <div id="userDropdown" class="absolute bottom-16 left-0 bg-white border border-gray-200 shadow-lg rounded-md hidden w-44 z-50 text-sm">
          <button onclick="openModal('configModal')" class="block w-full px-4 py-2 text-left hover:bg-gray-100">Configuración</button>
          <a href="logout.php" class="block w-full px-4 py-2 text-left hover:bg-gray-100">Cerrar sesión</a>
        </div>
      </nav>
    <?php endif; ?>
  </aside>

  <!-- Contenido Principal -->
  <main class="ml-64 flex-1 p-10">
    <h1 class="text-4xl font-bold mb-6 text-gray-900">Mis Borradores</h1>

    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl">
      
      <?php if (empty($borradores)): ?>
        <!-- Sin borradores -->
        <div class="text-center py-12">
          <i data-lucide="inbox" class="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
          <h2 class="text-xl font-semibold text-gray-700 mb-2">No tienes borradores</h2>
          <p class="text-gray-500 mb-6">Los datasets que estés editando aparecerán aquí hasta que los publiques.</p>
          <a href="search_dataset.php" class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
            <i data-lucide="edit" class="w-5 h-5"></i>
            Editar un Dataset
          </a>
        </div>
      <?php else: ?>
        <!-- Lista de borradores -->
        <div class="mb-4">
            Tienes <strong><?= count($borradores) ?></strong> borrador<?= count($borradores) > 1 ? 'es' : '' ?> en edición. Solo tú puedes verlos hasta que los publiques.
        </div>

        <div class="space-y-4">
          <?php foreach($borradores as $borrador): ?>
            <div class="border border-yellow-300 bg-yellow-50 rounded-xl p-6 hover:shadow-md transition">
              <div class="flex items-center justify-between gap-6">
                <!-- Contenido principal -->
                <div class="flex-1">
                  <!-- Encabezado con badge y título -->
                  <div class="flex items-center gap-3 mb-3">
                    <h3 class="text-xl font-semibold text-gray-900">
                      <?= htmlspecialchars($borrador['name']) ?>
                    </h3>
                  </div>
                  <!-- Información de categoría y ubicación -->
                  <div class="flex items-center gap-3 mb-3 text-sm text-gray-700">
                    <span class="flex items-center gap-1">
                      <i data-lucide="folder" class="w-4 h-4 inline-block align-middle"></i>
                      <strong class="inline-block align-middle">Categoría:</strong>
                      <span class="inline-block align-middle"><?= htmlspecialchars($borrador['category'] ?? 'Sin categoría') ?></span>
                    </span>
                    <?php if (!empty($borrador['location'])): ?>
                      <span class="text-gray-400">|</span>
                      <span class="flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-4 h-4 inline-block align-middle"></i>
                        <strong class="inline-block align-middle">Ubicación:</strong>
                        <span class="inline-block align-middle"><?= htmlspecialchars($borrador['location']) ?></span>
                      </span>
                    <?php endif; ?>
                  </div>
                  <!-- Descripción -->
                  <?php if (!empty($borrador['description'])): ?>
                    <p class="text-sm text-gray-600 mb-3 leading-relaxed">
                      <?= htmlspecialchars(substr($borrador['description'], 0, 200)) ?><?= strlen($borrador['description']) > 200 ? '...' : '' ?>
                    </p>
                  <?php endif; ?>
                  <!-- Fecha de modificación -->
                  <div class="flex items-center gap-2 text-xs text-gray-500">
                    <i data-lucide="clock" class="w-3.5 h-3.5 inline-block align-middle"></i>
                    <span class="inline-block align-middle">
                      Última modificación: <?= date('d/m/Y H:i', strtotime($borrador['last_change'] ?? $borrador['created_at'])) ?>
                    </span>
                  </div>
                </div>
                <!-- Botones de acción -->
                <div class="flex flex-col gap-3 self-center">
                <?php if ($borrador['approval_status'] === 'draft' && $borrador['is_imported'] == 0): ?>
                  <!-- Botón VERDE: datasets manuales en construcción -->
                  <a href="Añadir Conjunto de Datos.php?draft_uuid=<?= urlencode($borrador['uuid']) ?>" 
                     class="bg-green-600 text-white px-5 py-2.5 rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2 text-sm font-medium whitespace-nowrap shadow-sm">
                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                    Continuar Construyendo
                  </a>
                <?php else: ?>
                  <!-- Botón AZUL: datasets importados o editados -->
                  <a href="edit_dataset.php?uuid=<?= urlencode($borrador['uuid']) ?>" 
                     class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2 text-sm font-medium whitespace-nowrap shadow-sm">
                    <i data-lucide="edit" class="w-4 h-4"></i>
                    Editar Dataset
                  </a>
                <?php endif; ?>
                  
                  <!-- Botón eliminar (siempre visible) -->
                  <button onclick="eliminarBorrador('<?= htmlspecialchars($borrador['uuid']) ?>')"
                          class="bg-red-600 text-white px-5 py-2.5 rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-2 text-sm font-medium whitespace-nowrap shadow-sm">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Eliminar
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
lucide.createIcons();

  // ========== FUNCIONES GLOBALES ==========
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

  // ========== ELIMINAR BORRADOR ==========
  function eliminarBorrador(uuid) {
    if (!confirm('¿Estás seguro de eliminar este borrador?\n\nEsta acción no se puede deshacer y perderás todos los cambios no publicados.')) {
      return;
    }
    
    fetch('php_actions/eliminar_borrador.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ uuid: uuid })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('✅ Borrador eliminado exitosamente');
        location.reload();
      } else {
        alert('❌ Error: ' + data.message);
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error al eliminar el borrador');
    });
  }

  // ========== ESPERAR A QUE EL DOM ESTÉ LISTO ==========
  document.addEventListener('DOMContentLoaded', () => {
    
    // Menú de usuario (dropdown perfil)
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuBtn && userDropdown) {
      userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('hidden');
      });

      document.addEventListener('click', (e) => {
        if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
          userDropdown.classList.add('hidden');
        }
      });
    }

    // Botón: Cambiar foto
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

    // Botón: Solicitar admin (si existe)
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

    // Cerrar menú dataset al hacer clic fuera
    document.addEventListener('click', function(event) {
      const dropdown = document.getElementById('datasetMenu');
      const button = event.target.closest('button');
      
      if (!button && dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
      }
    });
  });
  </script>
</body>
</html>
