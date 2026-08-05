<?php
session_start();
if (!empty($_SESSION['profile_image'])) {
    echo '<img src="' . $_SESSION['profile_image'] . '" alt="Foto de perfil" class="w-10 h-10 rounded-full">';
}
?>

<script type="text/javascript">
        var gk_isXlsx = false;
        var gk_xlsxFileLookup = {};
        var gk_fileData = {};
        function filledCell(cell) {
          return cell !== '' && cell != null;
        }
        function loadFileData(filename) {
        if (gk_isXlsx && gk_xlsxFileLookup[filename]) {
            try {
                var workbook = XLSX.read(gk_fileData[filename], { type: 'base64' });
                var firstSheetName = workbook.SheetNames[0];
                var worksheet = workbook.Sheets[firstSheetName];

                // Convert sheet to JSON to filter blank rows
                var jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: false, defval: '' });
                // Filter out blank rows (rows where all cells are empty, null, or undefined)
                var filteredData = jsonData.filter(row => row.some(filledCell));

                // Heuristic to find the header row by ignoring rows with fewer filled cells than the next row
                var headerRowIndex = filteredData.findIndex((row, index) =>
                  row.filter(filledCell).length >= filteredData[index + 1]?.filter(filledCell).length
                );
                // Fallback
                if (headerRowIndex === -1 || headerRowIndex > 25) {
                  headerRowIndex = 0;
                }

                // Convert filtered JSON back to CSV
                var csv = XLSX.utils.aoa_to_sheet(filteredData.slice(headerRowIndex)); // Create a new sheet from filtered array of arrays
                csv = XLSX.utils.sheet_to_csv(csv, { header: 1 });
                return csv;
            } catch (e) {
                console.error(e);
                return "";
            }
        }
        return gk_fileData[filename] || "";
        }
</script>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Resultados del ACV</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <link rel="icon" type="image" href="icons/leaf.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: "Inter", sans-serif;
    }
    .tooltip {
      position: relative;
    }
    .tooltip:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #333;
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      white-space: nowrap;
      z-index: 10;
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
        <a href="informes.php" class="flex items-center gap-3 hover:text-green-700"><i data-lucide="file-text" class="w-5 h-5"></i> Informes</a>
        <a href="resultados.php" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700"><i data-lucide="leaf" class="w-5 h-5"></i> Resultados del ACV</a>
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
  <main class="ml-64 p-10 max-w-7xl w-full overflow-y-auto">
    <h1 class="text-4xl font-bold mb-6 text-gray-900">Resultados del <span class="text-green-600">Análisis de Ciclo de Vida (ACV)</span></h1>
    
    <!-- Selección de dataset -->
    <section class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-lg mx-auto mb-8">
      <label for="datasetSearch" class="block mb-3 font-semibold text-gray-900">Selecciona el conjunto de datos para análisis:</label>
      <div class="flex gap-3 items-center">
        <div class="relative flex-grow">
          <input type="text" id="datasetSearch" class="w-full py-1.5 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-green-400 text-sm" placeholder="Buscar dataset..." autocomplete="off">
          <select id="datasetSelect" class="hidden">
            <option value="" disabled selected>-- Elegir conjunto de datos --</option>
            <!-- Opciones dinámicas cargadas desde la base de datos -->
          </select>
        </div>
        <button id="loadResultsBtn" class="bg-green-600 text-white font-semibold py-1.5 px-4 rounded-lg shadow hover:bg-green-700 transition duration-300 flex items-center gap-2 disabled:opacity-50" disabled>
          <i data-lucide="loader" class="w-3 h-3 animate-spin hidden"></i> Cargar Resultados
        </button>
      </div>
      <p id="datasetPreview" class="text-sm text-gray-600 mt-2"></p>
    </section>
    <section id="resultsContent" class="space-y-8">
      <!-- Resumen de impactos -->
      <section id="resultsSection" class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold">Resumen de Impactos Ambientales</h2>
          <button id="exportTableBtn" class="bg-blue-600 text-white font-semibold py-1 px-3 rounded-lg hover:bg-blue-700">Exportar a CSV</button>
        </div>
        <p class="text-sm mb-4" id="datasetDescription"></p>
        <div id="noDataMessage" class="hidden text-sm text-red-600">No hay datos disponibles para este conjunto.</div>
        <table class="w-full text-left border-collapse border border-gray-300">
          <thead>
            <tr class="bg-white text-green-900">
              <th class="border border-gray-300 px-4 py-2 cursor-pointer tooltip" data-tooltip="Ordenar por categoría" onclick="sortTable('category')">Categoría de Impacto</th>
              <th class="border border-gray-300 px-4 py-2">Unidad</th>
              <th class="border border-gray-300 px-4 py-2 cursor-pointer tooltip" data-tooltip="Ordenar por valor" onclick="sortTable('value')">Valor</th>
              <th class="border border-gray-300 px-4 py-2">Interpretación</th>
            </tr>
          </thead>
          <tbody id="resultsTableBody"></tbody>
        </table>
      </section>

      <!-- Gráficos -->
      <section id="visualizationsSection" class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold">Gráficos y Visualizaciones</h2>
          <button id="exportChartsBtn" class="bg-blue-600 text-white font-semibold py-1 px-3 rounded-lg hover:bg-blue-700">Descargar Gráficos</button>
        </div>
        <p class="text-sm mb-6">Gráficos de barras y de pastel para comprender los impactos ambientales por categoría.</p>
        <div class="flex flex-col sm:flex-row gap-8">
          <div class="w-full sm:w-1/2 bg-white border border-green-200 rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold mb-2 text-green-700">Impactos por categoría</h3>
            <canvas id="barChart" aria-label="Gráfico de barras de impactos" role="img"></canvas>
          </div>
          <div class="w-full sm:w-1/2 bg-white border border-green-200 rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold mb-2 text-green-700">Distribución porcentual</h3>
            <canvas id="pieChart" aria-label="Gráfico de pastel de distribución" role="img"></canvas>
          </div>
        </div>
      </section>

      <!-- Interpretación -->
      <section id="interpretationSection" class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold">Interpretación y Conclusiones</h2>
          <button id="exportReportBtn" class="bg-blue-600 text-white font-semibold py-1 px-3 rounded-lg hover:bg-blue-700">Exportar Informe</button>
        </div>
        <p class="text-sm mb-4" id="interpretationText"></p>
        <textarea id="userNotes" class="w-full px-4 py-2 rounded-md border border-gray-300 text-sm" rows="4" placeholder="Añade tus notas o conclusiones adicionales..."></textarea>
        <button id="saveNotesBtn" class="mt-2 bg-green-600 text-white font-semibold py-1 px-3 rounded-lg hover:bg-green-700">Guardar Notas</button>
        <p class="text-sm text-gray-600 italic mt-4">*Datos obtenidos con base en la metodología ISO 14040 y 14044 para Análisis de Ciclo de Vida.</p>
      </section>
    </section>

    <!-- Modales -->
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

    <div id="changePhotoModal" class="fixed inset-0 bg-black/30 backdrop-blur-sm items-center justify-center z-50 hidden">
      <div class="bg-white p-6 rounded-2xl shadow-2xl w-[90%] max-w-md transition-all">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Cambiar foto de perfil</h2>
        <input id="profileUploadModal" type="file" accept="image/*" class="w-full border border-gray-300 rounded-md px-4 py-2 mb-4" />
        <div class="text-right">
          <button onclick="closeModal('changePhotoModal')" class="text-sm text-green-700 hover:underline">Cerrar</button>
        </div>
      </div>
    </div>

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
  </main>

  <script>
    // Iconos Lucide
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
    // Cambiar foto - Explorador de archivos
    const profileImg = document.getElementById('profileImg');
    const profileUpload = document.getElementById('profileUpload');
    const profileUploadModal = document.getElementById('profileUploadModal');
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
    // Lógica para resultados de ACV
    let selectedDatasetId = null;
    let barChartInstance = null;
    let pieChartInstance = null;
    // Búsqueda dinámica de datasets
    document.getElementById('datasetSearch').addEventListener('input', async (e) => {
      const query = e.target.value;
      const loadBtn = document.getElementById('loadResultsBtn');
      const loader = loadBtn.querySelector('i');
      try {
        loader.classList.remove('hidden');
        loadBtn.disabled = true;
        // Simula una llamada a la API (reemplazar con tu endpoint real)
        const response = await fetch(`/api/datasets?search=${query}`);
        const datasets = await response.json();
        const select = document.getElementById('datasetSelect');
        select.innerHTML = '<option value="" disabled selected>-- Elegir conjunto de datos --</option>';
        datasets.forEach(dataset => {
          select.innerHTML += `<option value="${dataset.id}">${dataset.name} (${dataset.category})</option>`;
        });
        loadBtn.disabled = false;
      } catch (error) {
        console.error('Error al buscar datasets:', error);
        alert('Error al cargar datasets');
      } finally {
        loader.classList.add('hidden');
      }
    });
    // Cargar resultados
    document.getElementById('loadResultsBtn').addEventListener('click', async () => {
      selectedDatasetId = document.getElementById('datasetSelect').value;
      if (!selectedDatasetId) {
        alert('Por favor, selecciona un conjunto de datos');
        return;
      }
      const loadBtn = document.getElementById('loadResultsBtn');
      const loader = loadBtn.querySelector('i');
      loader.classList.remove('hidden');
      loadBtn.disabled = true;
      try {
// --- Simulación: Datos mock (sólo para mostrar ejemplo) ---
if (selectedDatasetId === "demo") {
  const data = {
    description: "Evaluación ambiental de producción de ladrillos en CDMX",
    functionalUnit: "1 tonelada de ladrillo cocido",
    dataSource: "Estudio de caso UNAM 2025",
    interpretation: "El mayor impacto se asocia al uso de combustible fósil durante el proceso de cocción.",
    results: [
      { category: "Cambio climático", unit: "kg CO2 eq", value: 120.5, interpretation: "Elevado impacto por combustión." },
      { category: "Toxicidad humana", unit: "kg 1,4-DB eq", value: 15.3, interpretation: "Moderado, por emisiones atmosféricas." },
      { category: "Uso del agua", unit: "m3", value: 2.8, interpretation: "Relativamente bajo." },
      { category: "Acidificación", unit: "kg SO2 eq", value: 4.2, interpretation: "Relacionado a procesos térmicos." },
      { category: "Eutrofización", unit: "kg PO4 eq", value: 0.7, interpretation: "Asociado al manejo de residuos líquidos." }
    ]
  };

  const tableBody = document.getElementById('resultsTableBody');
  tableBody.innerHTML = '';
  data.results.forEach(result => {
    tableBody.innerHTML += `
      <tr>
        <td class="border border-gray-300 px-4 py-2">\${result.category}</td>
        <td class="border border-gray-300 px-4 py-2">\${result.unit}</td>
        <td class="border border-gray-300 px-4 py-2">\${result.value}</td>
        <td class="border border-gray-300 px-4 py-2">\${result.interpretation}</td>
      </tr>`;
  });

  document.getElementById('datasetDescription').textContent = data.description;
  document.getElementById('datasetPreview').textContent = `Unidad funcional: \${data.functionalUnit}, Fuente: \${data.dataSource}`;
  document.getElementById('interpretationText').textContent = data.interpretation;

  if (barChartInstance) barChartInstance.destroy();
  if (pieChartInstance) pieChartInstance.destroy();

  barChartInstance = new Chart(document.getElementById('barChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: data.results.map(r => r.category),
      datasets: [{
        label: 'Impactos Ambientales',
        data: data.results.map(r => r.value),
        backgroundColor: ['#16A34A', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6']
      }]
    }
  });

  pieChartInstance = new Chart(document.getElementById('pieChart').getContext('2d'), {
    type: 'pie',
    data: {
      labels: data.results.map(r => r.category),
      datasets: [{
        data: data.results.map(r => r.value),
        backgroundColor: ['#16A34A', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6']
      }]
    }
  });

  loader.classList.add('hidden');
  loadBtn.disabled = false;
  return;
}

      await loadResults(selectedDatasetId);
        await loadCharts(selectedDatasetId);
        document.getElementById('noDataMessage').classList.add('hidden');
      } catch (error) {
        console.error('Error al cargar resultados:', error);
        document.getElementById('noDataMessage').classList.remove('hidden');
      } finally {
        loader.classList.add('hidden');
        loadBtn.disabled = false;
      }
    });

    // Cargar datos de la tabla
    async function loadResults(datasetId) {
      // Simula una llamada a la API (reemplazar con tu endpoint real)
      const response = await fetch(`/api/results/${datasetId}`);
      const data = await response.json();
      const tableBody = document.getElementById('resultsTableBody');
      tableBody.innerHTML = '';
      if (data.results.length === 0) {
        document.getElementById('noDataMessage').classList.remove('hidden');
        return;
      }
      data.results.forEach(result => {
        tableBody.innerHTML += `
          <tr>
            <td class="border border-gray-300 px-4 py-2">${result.category}</td>
            <td class="border border-gray-300 px-4 py-2">${result.unit}</td>
            <td class="border border-gray-300 px-4 py-2">${result.value}</td>
            <td class="border border-gray-300 px-4 py-2">${result.interpretation}</td>
          </tr>`;
      });
      document.getElementById('datasetDescription').textContent = data.description || `Análisis para el dataset ${datasetId} con unidad funcional: ${data.functionalUnit}`;
      document.getElementById('interpretationText').textContent = data.interpretation || 'Los resultados muestran los impactos ambientales calculados según la metodología ISO 14040/14044.';
      document.getElementById('datasetPreview').textContent = `Unidad funcional: ${data.functionalUnit}, Fuente: ${data.dataSource || 'No especificada'}`;
    }

    // Cargar gráficos
    async function loadCharts(datasetId) {
      const response = await fetch(`/api/results/${datasetId}`);
      const data = await response.json();
      if (barChartInstance) barChartInstance.destroy();
      if (pieChartInstance) pieChartInstance.destroy();
      barChartInstance = new Chart(document.getElementById('barChart').getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.results.map(r => r.category),
          datasets: [{
            label: 'Impactos Ambientales',
            data: data.results.map(r => r.value),
            backgroundColor: ['#16A34A', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6'],
            borderColor: ['#14532D', '#1E40AF', '#B45309', '#B91C1C', '#5B21B6'],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'top' }, title: { display: true, text: 'Impactos por Categoría' } },
          scales: { y: { beginAtZero: true, title: { display: true, text: 'Impacto (kg CO2 eq)' } }, x: { title: { display: true, text: 'Categoría de Impacto' } } }
        }
      });
      pieChartInstance = new Chart(document.getElementById('pieChart').getContext('2d'), {
        type: 'pie',
        data: {
          labels: data.results.map(r => r.category),
          datasets: [{
            label: 'Distribución',
            data: data.results.map(r => r.percentage || (r.value / data.results.reduce((sum, r) => sum + r.value, 0)) * 100),
            backgroundColor: ['#16A34A', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6'],
            borderColor: ['#14532D', '#1E40AF', '#B45309', '#B91C1C', '#5B21B6'],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'right' }, title: { display: true, text: 'Distribución Porcentual de Impactos' } }
        }
      });
    }

    // Ordenar tabla
    function sortTable(key) {
      const tableBody = document.getElementById('resultsTableBody');
      const rows = Array.from(tableBody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        const aValue = a.querySelector(`td:nth-child(${key === 'category' ? 1 : 3})`).textContent;
        const bValue = b.querySelector(`td:nth-child(${key === 'category' ? 1 : 3})`).textContent;
        return key === 'value' ? parseFloat(bValue) - parseFloat(aValue) : aValue.localeCompare(bValue);
      });
      tableBody.innerHTML = '';
      rows.forEach(row => tableBody.appendChild(row));
    }

    // Exportar tabla a CSV
    document.getElementById('exportTableBtn').addEventListener('click', () => {
      const table = document.querySelector('#resultsSection table');
      const rows = Array.from(table.querySelectorAll('tr'));
      const csv = rows.map(row => Array.from(row.querySelectorAll('th, td')).map(cell => `"${cell.textContent}"`).join(',')).join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'resultados_acv.csv';
      a.click();
      URL.revokeObjectURL(url);
    });

    // Exportar gráficos
    document.getElementById('exportChartsBtn').addEventListener('click', () => {
      const barCanvas = document.getElementById('barChart');
      const pieCanvas = document.getElementById('pieChart');
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      doc.text('Resultados del ACV - Gráficos', 10, 10);
      doc.addImage(barCanvas.toDataURL('image/png'), 'PNG', 10, 20, 180, 100);
      doc.addPage();
      doc.text('Distribución Porcentual', 10, 10);
      doc.addImage(pieCanvas.toDataURL('image/png'), 'PNG', 10, 20, 180, 100);
      doc.save('graficos_acv.pdf');
    });

    // Guardar notas
    document.getElementById('saveNotesBtn').addEventListener('click', async () => {
      const notes = document.getElementById('userNotes').value;
      if (!selectedDatasetId) {
        alert('Por favor, selecciona un conjunto de datos primero');
        return;
      }
      try {
        await fetch('/api/notes', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ datasetId: selectedDatasetId, notes })
        });
        alert('Notas guardadas exitosamente');
      } catch (error) {
        console.error('Error al guardar notas:', error);
        alert('Error al guardar notas');
      }
    });

    // Exportar informe completo
    document.getElementById('exportReportBtn').addEventListener('click', async () => {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      doc.text('Informe de Resultados del ACV', 10, 10);
      doc.text(document.getElementById('datasetDescription').textContent, 10, 20);
      const table = document.querySelector('#resultsSection table');
      const rows = Array.from(table.querySelectorAll('tr'));
      let y = 30;
      rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('th, td')).map(cell => cell.textContent);
        doc.text(cells.join(' | '), 10, y);
        y += 10;
      });
      doc.addPage();
      doc.text('Gráficos', 10, 10);
      doc.addImage(document.getElementById('barChart').toDataURL('image/png'), 'PNG', 10, 20, 180, 100);
      doc.addPage();
      doc.addImage(document.getElementById('pieChart').toDataURL('image/png'), 'PNG', 10, 20, 180, 100);
      doc.addPage();
      doc.text('Interpretación y Conclusiones', 10, 10);
      doc.text(document.getElementById('interpretationText').textContent, 10, 20);
      doc.text(document.getElementById('userNotes').value, 10, 40);
      doc.save('informe_acv.pdf');
    });
  </script>
</body>
</html>