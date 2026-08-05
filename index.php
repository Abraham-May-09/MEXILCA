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
  <title>Menú Principal</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image" href="icons/house.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
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
        <a href="index.php" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700"><i data-lucide="home" class="w-5 h-5"></i> Inicio</a>
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
    Bienvenido(a),
    <span class="text-green-600 underline">
      <?php echo htmlspecialchars($_SESSION["name"] ?? "Invitado"); ?>
  </span>
  </h1>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-2xl font-bold mb-2 text-green-800 flex items-center gap-2">
        <i data-lucide="globe" class="w-6 h-6"></i>
        La primera base mexicana abierta y colaborativa de datos de Ciclo de Vida
      </h2>
      <p class="text-sm mb- text-justify"><strong>MexiLCA</strong> es una plataforma open access que reúne, organiza y publica inventarios de ciclo de vida (LCI) elaborados en México, o adaptados al contexto nacional, bajo estándares internacionales como ISO 14040/44, ILCD, y las guías de interoperabilidad de GLAD.</p>
      <p class="text-sm mb-4 text-justify">Su objetivo es fortalecer la toma de decisiones sostenibles en la industria, la academia y las políticas públicas, brindando datos ambientales transparentes, comparables y trazables sobre productos, procesos y servicios en los principales sectores del país.</p>
    </div>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-2xl font-bold mb-2 text-green-800 flex items-center gap-2">
        <i data-lucide="users" class="w-6 h-6"></i>
        Un esfuerzo nacional y colaborativo
      </h2>
      <p class="text-sm mb- text-justify"><strong>MexiLCA</strong> surge como una iniciativa colectiva impulsada por instituciones académicas, como el Instituto de Ingeniería de la UNAM y la Red Mexicana de Análisis de Ciclo de Vida (RM-ACV), junto con organismos gubernamentales y centros de investigación comprometidos con la transición hacia una economía circular y baja en carbono. Cada conjunto de datos es revisado, documentado y validado por especialistas, garantizando calidad, coherencia metodológica y compatibilidad con herramientas reconocidas internacionalmente como openLCA, SimaPro y Brightway.</p>
    </div>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-xl font-bold mb-2 text-green-800 flex items-center gap-2">
        <i data-lucide="sparkles" class="w-5 h-5"></i>
        ¿Por qué MexiLCA?
      </h2>
      <ul class="text-sm">
        <li class="mb-2 mt-2 flex items-start gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5"></i>
          <span><strong>Primer repositorio mexicano de LCA </strong> con acceso abierto.</span>
        </li>
        <li class="mb-2 flex items-start gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5"></i>
          <span><strong>Datos verificados y contextualizados </strong> al entorno productivo y ambiental del país.</span>
        </li>
        <li class="mb-2 flex items-start gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5"></i>
          <span><strong>Interoperable internacionalmente, </strong>conectado con otros nodos y formatos globales.</span>
        </li>
        <li class="mb-2 flex items-start gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5"></i>
          <span><strong>Soporte a políticas públicas, </strong> innovación industrial y evaluación académica.</span>
        </li>
        <li class="mb-2 flex items-start gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5"></i>
          <span><strong>Transparencia y ciencia abierta </strong> como principios rectores.</span>
        </li>
      </ul>
    </div>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-xl font-bold mb-2 text-green-800 flex items-center gap-2">
        <i data-lucide="layers" class="w-5 h-5"></i>
        Sectores cubiertos
      </h2>
      <p class="text-sm mb-2 text-justify"><strong>MexiLCA</strong> cuenta con alrededor de 250 datasets interconectados entre sí, que representan los principales sectores productivos del país. Estos datos permiten analizar flujos materiales, energéticos y ambientales bajo un enfoque de ciclo de vida, ofreciendo una visión integral del desempeño ambiental en México.</p>
      <p class="text-sm mb-2 text-justify">Sectores incluidos:</p>
      <ul class="text-sm">
        <li class="mb-2 mt-2"><strong>- 🏗️ Construcción️ </strong> </li>
        <li class="mb-2"><strong>- 🌾 Agroalimentario </strong></li>
        <li class="mb-2"><strong>- ⚡ Energía </strong></li>
        <li class="mb-2"><strong>- 💧 Agua y saneamiento </strong></li>
        <li class="mb-2"><strong>- ♻️ Residuos </strong></li>
        <li class="mb-2"><strong>- 🚛 Transporte </strong></li>
        <li class="mb-2"><strong>- 🔋 Manufactura </strong></li>
      </ul>
    </div>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-xl font-bold mb-2 text-green-800 flex items-center gap-2">
        <i data-lucide="lightbulb" class="w-5 h-5"></i>
        Un espacio para construir conocimiento
      </h2>
      <p class="text-sm mb-1 text-justify"><strong>MexiLCA</strong> invita a investigadores, empresas y entidades públicas a compartir sus datos bajo principios de interoperabilidad, transparencia y reutilización (FAIR principles).</p>
      <p class="text-sm text-justify">La plataforma promueve la creación de un ecosistema nacional de información que impulse la investigación aplicada, el ecodiseño y la gestión ambiental basada en evidencia.</p>
    </div>
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-xl font-bold mb-2 text-green-800 flex items-center gap-2">
        <i data-lucide="handshake" class="w-5 h-5"></i>
        Contribuye, cita y colabora
      </h2>
      <p class="text-sm mb-1 text-justify">Los datos publicados en <strong>MexiLCA</strong> pueden ser utilizados libremente y sin fines de lucro, siempre que se reconozca adecuadamente la fuente.</p>
      <p class="text-sm text-justify">Cada dataset cuenta con su versión, metadatos, referencia y licencia, lo que facilita su uso en proyectos académicos, técnicos o institucionales, promoviendo la transparencia y la ciencia abierta.</p>
    </div>
<!-- Footer -->
<footer class="mt-12 pt-10" role="contentinfo">
  <div class="max-w-6xl mx-auto px-6 space-y-10">
    <!-- Instituciones responsables -->
    <section class="text-center">
      <h3 class="font-semibold tracking-widest text-gray-600 uppercase mb-4">
        INSTITUCIONES RESPONSABLES
      </h3>
      <div class="flex flex-wrap items-center justify-center gap-8">
        <figure class="w-[160px] transition-transform duration-300 ease-in-out hover:scale-110 cursor-pointer">
          <div class="flex items-center justify-center h-20">
            <img src="images/UNAM-LOGO.png" alt="UNAM" class="h-full w-auto max-h-full object-contain" loading="lazy" decoding="async">
          </div>
        </figure>
        <figure class="w-[160px] transition-transform duration-300 ease-in-out hover:scale-110 cursor-pointer">
          <div class="flex items-center justify-center h-20">
            <img src="images/UNAM - copia.png" alt="Instituto de Ingeniería, UNAM (IINGEN)" class="h-full w-auto max-h-full object-contain" loading="lazy" decoding="async">
          </div>
        </figure>
      </div>
    </section>
    
    <!-- Financiamiento -->
    <section class="text-center">
      <h3 class="font-semibold tracking-widest text-gray-600 uppercase mb-4">
        FINANCIAMIENTO
      </h3>
      <div class="flex flex-wrap items-center justify-center gap-8">
        <figure class="w-[80px] transition-transform duration-300 ease-in-out hover:scale-80 cursor-pointer">
          <div class="flex items-center justify-center h-16">
            <img src="images/SECIHTI_2025-2030.svg.png" alt="SECIHTI" class="h-full w-auto max-h-full object-contain" loading="lazy" decoding="async">
          </div>
        </figure>
      </div>
    </section>

    <!-- Copyright y Licencia -->
    <section class="pt-6 text-center space-y-3">
      <p class="text-xs text-gray-500">
        &copy; <?= date('Y'); ?> <strong>MexiLCA </strong>— Los datos y metadatos se publican bajo licencia <strong class="italic"> Creative Commons Atribución 4.0 Internacional (CC BY 4.0)</strong> salvo indicación distinta. 
      </p>
      <p class="text-xs text-gray-500 italic font-semibold">
        Uso libre y sin fines de lucro con reconocimiento a la fuente.
      </p>
    </section>
  </div>
</footer>

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
</script>
</body>
</html>
