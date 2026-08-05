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
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contactos</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="icon" type="image" href="icons/mail.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-white text-gray-800 min-h-screen flex">

<!-- Barra Lateral -->
  <aside class="fixed top-0 left-0 h-screen z-40 w-64 bg-white p-6 flex flex-col justify-between border-r border-gray-200 shadow-sm">
    <div>
      <img src="images/UNAM.png" class="w-50 mx-auto mb-8">
      <nav class="space-y-4 text-left text-sm">
        <a href="index.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="home" class="w-5 h-5"></i> Inicio</a>
        <a href="conjuntos.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="database" class="w-5 h-5"></i> Base de Datos</a>
        <a href hidden="informes.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="file-text" class="w-5 h-5"></i> Informes</a>
        <a href hidden="resultados.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="leaf" class="w-5 h-5"></i> Resultados del ACV</a>
        <a href="contactos.php" hidden class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700"><i data-lucide="mail" class="w-5 h-5"></i> Contactos</a>
        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === 'ADMIN'): ?>
            <?php
            // Contar procesos pendientes
            require_once 'conexion.php';
            $sql_count = "SELECT COUNT(*) as total FROM processes WHERE approval_status = 'pending'";
            $result_count = $conn->query($sql_count);
            $pending = $result_count ? $result_count->fetch_assoc()['total'] : 0;
            ?>
            
            <a href="Admin.php" class="flex items-center gap-3 hover:text-green-700">
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
            <!-- Botón principal que despliega el menú -->
            <button onclick="toggleDropdown('datasetMenu')" class="flex items-center gap-3 hover:text-green-700 w-full text-left">
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
<main class="ml-64 flex-1 p-10">
  <div class="max-w-5xl mx-auto">
    <header class="text-center mb-12">
      <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-green-800 mb-4">Contáctate con los responsables</h1>
      <h2 class="text-xl sm:text-2xl lg:text-3xl font-semibold text-green-600">del equipo de Análisis de Ciclo de Vida en México</h2>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
      <!-- Tarjeta 1 -->
      <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
        <img src="images/Lguereca.jpg" alt="Leonor Patricia Güereca Hernández" class="w-32 h-32 mx-auto rounded-full object-cover mb-4 border-4 border-green-100">
        <h3 class="text-center font-bold text-xl text-green-900 mb-2">Leonor Patricia Güereca Hernández</h3>
        <p class="text-center text-sm mb-4">
          <a href="mailto:Lguereca@iingen.unam.mx" class="text-green-600 hover:underline font-medium">Lguereca@iingen.unam.mx</a>
        </p>
        <p class="text-sm text-gray-600 text-justify">
          Investigadora Titular A Definitiva del Instituto de Ingeniería de la UNAM, dirige la línea de investigación en Análisis de Ciclo de Vida, Cambio Climático y Sostenibilidad.
        </p>
      </div>

      <!-- Tarjeta 2 -->
      <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
        <img src="images/Apadilla.jpg" alt="Alejandro de Jesus Padilla Rivera" class="w-32 h-32 mx-auto rounded-full object-cover mb-4 border-4 border-green-100">
        <h3 class="text-center font-bold text-xl text-green-900 mb-2">Alejandro de Jesus Padilla Rivera</h3>
        <p class="text-center text-sm mb-4">
          <a href="mailto:APadillaR@iingen.unam.mx" class="text-green-600 hover:underline font-medium">APadillaR@iingen.unam.mx</a>
        </p>
        <p class="text-sm text-gray-600 text-justify">
          Maestría y miembro de comité tutor de Doctorado. Disponible como tutor principal (dirección de alumnos).
        </p>
      </div>

      <!-- Tarjeta 3 -->
      <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
        <img src="images/Arivera.jfif" alt="Adriana Rivera Huerta" class="w-32 h-32 mx-auto rounded-full object-cover mb-4 border-4 border-green-100">
        <h3 class="text-center font-bold text-xl text-green-900 mb-2">Adriana Rivera Huerta</h3>
        <p class="text-center text-sm mb-4">
          <a href="mailto:ARiveraH@iingen.unam.mx" class="text-green-600 hover:underline font-medium">ARiveraH@iingen.unam.mx</a>
        </p>
        <p class="text-sm text-gray-600 text-justify">
          Doctora en Ciencias de la Sostenibilidad, Instituto de Ingeniería, UNAM (7 de enero de 2021). Maestra en Ciencias de la Producción Animal, Facultad de Medicina Veterinaria y Zootecnia, UNAM (15 de octubre de 2014).
        </p>
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
    <input name="foto" type="file" accept="image/*" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-4" required />
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
  userMenuBtn.addEventListener('click', () => {
    userDropdown.classList.toggle('hidden');
  });

  // Configuración - abrir modales internos
  document.getElementById('btnChangePhoto').addEventListener('click', () => {
    closeModal('configModal');
    openModal('changePhotoModal');
  });
  document.getElementById('btnChangePassword').addEventListener('click', () => {
    closeModal('configModal');
    openModal('changePasswordModal');
  });
  document.getElementById('btnRequestAdmin').addEventListener('click', function() {
  fetch('request_admin.php', { method: 'POST' })
    .then(res => res.json())
    .then(data => {
      alert(data.message);
      if(data.success) {
        this.disabled = true;  // deshabilitar botón tras solicitar
      }
    });
});

  // Cambiar foto - Explorador de archivos
  const profileImg = document.getElementById('profileImg');
  const profileUpload = document.getElementById('profileUpload');
  const profileUploadModal = document.getElementById('profileUploadModal');

  // Cerrar menú si se hace click fuera
  document.addEventListener('click', (e) => {
    if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.classList.add('hidden');
    }
  });
    // Menu desplegable
     document.addEventListener('DOMContentLoaded', () => {
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileButton && profileDropdown) {
      profileButton.addEventListener('click', () => {
        profileDropdown.classList.toggle('hidden');
      });

      // Cerrar el menú si se da click fuera
      document.addEventListener('click', (e) => {
        if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
          profileDropdown.classList.add('hidden');
        }
      });
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
