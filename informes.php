<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Informes</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" type="image" href="icons/file-text.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: "Inter", sans-serif;
    }
  </style>
</head>
<body class="bg-white text-gray-800 min-h-screen flex">

  <!-- Barra Lateral -->
  <aside class="fixed top-0 left-0 h-screen z-40 w-64 bg-white p-6 flex flex-col justify-between border-r border-gray-200 shadow-sm">
    <div>
      <img src="images/UNAM.png" class="w-50 mx-auto mb-8">
      <nav class="space-y-4 text-left text-sm">
        <a href="index.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="home" class="w-5 h-5"></i> Inicio</a>
        <a href="conjuntos.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="database" class="w-5 h-5"></i> Conjuntos de datos</a>
        <a href="informes.php" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700"><i data-lucide="file-text" class="w-5 h-5"></i> Informes</a>
        <a href="resultados.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="leaf" class="w-5 h-5"></i> Resultados del ACV</a>
        <a href="contactos.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="mail" class="w-5 h-5"></i> Contactos</a>
        <?php if (isset($_SESSION["rol"]) && $_SESSION["rol"] === 'admin'): ?>
          <a href="Admin.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="shield-user" class="w-5 h-5"></i>Administración</a>
        <?php endif; ?>
        <?php if (isset($_SESSION["rol"]) && in_array($_SESSION["rol"], ['admin', 'usuario'])): ?>
          <a href="Añadir Conjunto de Datos.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="file-plus-2" class="w-5 h-5"></i>Añadir Dataset</a> 
        <?php endif; ?>
        <a href="import.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="import" class="w-5 h-5"></i>Importar Dataset</a>
      </nav>
    </div>

    <!-- Perfil de usuario -->
<?php if (isset($_SESSION["user_id"])): ?>
  <nav class="relative border-t pt-4 mt-4 text-sm text-gray-700">
    <button id="userMenuBtn" class="w-full text-center hover:text-green-700 transition">
      <div class="flex flex-col items-center gap-1">
        <img id="profileImg" src="<?= isset($_SESSION['foto']) ? $_SESSION['foto'] : 'default-profile.png' ?>" alt="Foto de Perfil" class="w-20 h-20 rounded-full object-cover border border-gray-300" />
        <p class="font-semibold"><?= htmlspecialchars($_SESSION["nombre"]) ?></p>
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

<!-- Contenido principal -->
<main class="ml-64 p-10 max-w-6xl w-full">
  <h1 class="text-4xl font-bold text-gray-900 mb-2">Informes</h1>
  <p class="text-gray-700 mb-6 text-sm">Un repositorio de información adicional para proyectos seleccionados.</p>

  <div class="bg-white p-6 border border-gray-200 rounded-xl shadow-sm">
    <!-- Buscador -->
    <div class="mb-4">
      <input type="text" placeholder="Filtrar informes" class="w-full border border-gray-300 px-4 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-400" />
    </div>

    <!-- Encabezados -->
    <div class="grid grid-cols-12 text-sm font-semibold text-gray-500 border-b border-gray-200 pb-2 mb-2">
      <div class="col-span-7 border-r border-gray-100 pr-4">DESCRIPCIÓN DEL ARCHIVO</div>
      <div class="col-span-2 border-r border-gray-100 px-4">TAMAÑO</div>
      <div class="col-span-3 text-right pl-4">DESCARGA</div>
    </div>

    <!-- Informe individual -->
    <div class="grid grid-cols-12 items-start py-4 border-b border-gray-100">
      <div class="col-span-7 border-r border-gray-100 pr-4">
        <p class="font-semibold text-gray-900">Asignación, corte, EN15804_documentación.pdf</p>
        <p class="text-sm text-gray-600">Este documento proporciona una documentación sobre el cálculo de los indicadores en el modelo de sistema “Asignación, corte, EN15804”.</p>
      </div>
      <div class="col-span-2 text-sm text-gray-700 mt-1 border-r border-gray-100 px-4">4 Mb</div>
      <div class="col-span-3 flex justify-end mt-1 pl-4">
        <a href="#" target="_blank" class="text-green-600 hover:text-green-800">
          <i data-lucide="download" class="w-5 h-5"></i>
        </a>
      </div>
    </div>

    <!-- Otro informe -->
    <div class="grid grid-cols-12 items-start py-4 border-b border-gray-100">
      <div class="col-span-7 border-r border-gray-100 pr-4">
        <p class="font-semibold text-gray-900">Informe ecoinvent 3_Agricultura.zip</p>
        <p class="text-sm text-gray-600">Este archivo contiene varios informes .pdf y archivos .xlsx que tratan del sector agrícola en ecoinvent v3.</p>
      </div>
      <div class="col-span-2 text-sm text-gray-700 mt-1 border-r border-gray-100 px-4">5 Mb</div>
      <div class="col-span-3 flex justify-end mt-1 pl-4">
        <a href="#" target="_blank" class="text-green-600 hover:text-green-800">
          <i data-lucide="download" class="w-5 h-5"></i>
        </a>
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
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Cambiar foto de perfil
      </button>
      <button id="btnChangePassword" class="w-full flex items-center gap-3 border border-gray-200 rounded-lg px-4 py-3 hover:bg-gray-100 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-2.21 1.79-4 4-4s4 1.79 4 4v3H8v-3c0-2.21 1.79-4 4-4z" />
        </svg>
        Cambiar contraseña
      </button>
      <button id="btnRequestAdmin" class="w-full flex items-center gap-3 border border-gray-200 rounded-lg px-4 py-3 hover:bg-gray-100 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 00-2-2H5a2 2 0 00-2 2v2m16 0v-2a2 2 0 00-2-2h-2a2 2 0 00-2 2v2M7 7h.01M7 7a4 4 0 110 0h-.01M17 7h.01M17 7a4 4 0 110 0h-.01" />
        </svg>
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
    <input id="profileUploadModal" type="file" accept="image/*" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-4" />
    <div class="text-right">
      <button onclick="closeModal('changePhotoModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
    </div>
  </div>
</div>

<!-- Cambiar contraseña -->
<div id="changePasswordModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Cambiar contraseña</h2>
    <input type="password" placeholder="Nueva contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" />
    <input type="password" placeholder="Confirmar contraseña" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-3" />
    <button onclick="alert('Contraseña cambiada')" class="w-full bg-green-700 text-white py-2 rounded-lg hover:bg-green-800 transition">Guardar</button>
    <div class="text-right mt-4">
      <button onclick="closeModal('changePasswordModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
    </div>
  </div>
</div>
<script>

  // Lucide Iconos
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

  // Logica del Menu Desplegable
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

  // Cambiar foto - Explorador de archivos
  const profileImg = document.getElementById('profileImg');
  const profileUpload = document.getElementById('profileUpload');
  const profileUploadModal = document.getElementById('profileUploadModal');

  // Cambiar foto desde modal
  profileUploadModal.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        profileImg.src = e.target.result;
        closeModal('changePhotoModal');
      };
      reader.readAsDataURL(e.target.files[0]);
    }
  });

  // Cerrar menú si se hace click fuera
  document.addEventListener('click', (e) => {
    if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.classList.add('hidden');
    }
  });

  function toggleSection(id) {
    const section = document.getElementById(id);
    section.classList.toggle('hidden');
  }
</script>
</body>
</html>
