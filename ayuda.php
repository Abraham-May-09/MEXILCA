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
  <title>Ayuda</title>
  <link rel="stylesheet" href="src/output.css">
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
  <link rel="icon" type="image/png" href="icons/info.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    mark { padding: .1rem .2rem; border-radius: .25rem; }
    
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
        <a href="ayuda.php" class="flex items-center gap-3 text-gray-900 font-semibold hover:text-green-700"><i data-lucide="info" class="w-5 h-5"></i>Manual y Ayuda</a>
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
      Ayuda y manual de usuario
    </h1>
    
    <!-- Toolbar: Buscador + Descargas + Reporte -->
    <div class="bg-white border border-gray-200 shadow-sm p-4 rounded-xl max-w-5xl mb-6">
      <div class="flex flex-col md:flex-row gap-3 md:items-center">
        <div class="relative flex-1">
          <input id="searchManual" type="text" placeholder="Buscar en el manual…" class="w-full pl-10 pr-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-green-200">
        </div>
        <div class="flex items-center gap-2">
          <a href="assets/manual.pdf" download class="inline-flex items-center gap-2 px-3 py-2 text-sm rounded-lg border border-gray-200 bg-white hover:bg-gray-50 shadow-sm">
            <i data-lucide="file-down" class="w-4 h-4"></i> PDF del manual
          </a>
          <button id="reportBtn" class="inline-flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700 shadow-sm">
            <i data-lucide="flag" class="w-4 h-4"></i> Reportar un problema
          </button>
        </div>
      </div>
    </div>

    <!-- TARJETA 1: Introducción a CREAA -->
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-2xl font-bold mb-4 text-green-800">Introducción a MexiLCA</h2>
      <p class="text-justify mb-3">MexiLCA es una plataforma web diseñada para trabajar con datasets de Análisis de Ciclo de Vida (ACV) de forma colaborativa. Su objetivo general es facilitar el acceso, la gestión y el intercambio de conjuntos de datos de ACV, apoyando a la comunidad de profesionales y académicos en este campo. En MexiLCA, los usuarios pueden explorar bases de datos de ACV, compartir sus propios datos y encontrar información fiable para sus estudios, todo en un entorno unificado y accesible. La plataforma se enfoca en centralizar datos de ACV de calidad, evitando la dispersión de información, y así agilizar el trabajo con datasets complejos a lo largo del ciclo de vida de productos o procesos.</p>
      <p class="text-justify italic"><strong>Nota:</strong> Para aspectos técnicos consulte el Manual Técnico de la plataforma en la sección correspondiente.</p>
    </div>

    <!-- TARJETA 2: Guía de usuario -->
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-2xl font-bold mb-4 text-green-800">Guía de usuario</h2>
      <p class="text-justify mb-4">Esta guía proporciona los pasos básicos para comenzar a utilizar la plataforma MexiLCA de forma sencilla. Siga estas indicaciones para navegar por el sitio, visualizar datos y cargar nuevos datasets:</p>
      
      <h3 class="text-lg font-bold text-green-700 mb-2">1. Navegación de la plataforma</h3>
      <p class="text-justify mb-4">Una vez iniciada su sesión en MexiLCA (tras registrarse o ingresar con su cuenta), utilice el menú principal para acceder a las diferentes secciones. Por lo general, encontrará secciones como <em>Inicio</em>, <em>Base de Datos</em> (o <em>Datasets</em>), <em>Añadir Dataset</em> y <em>Ayuda</em>. La interfaz está pensada para ser intuitiva: puede buscar datasets específicos usando la barra de búsqueda o filtrar por categorías (por ejemplo, sector industrial, región, tipo de impacto, etc.). Navegue por la base de datos explorando las categorías o utilizando palabras clave relevantes.</p>
      
      <h3 class="text-lg font-bold text-green-700 mb-2">2. Visualización de la base de datos</h3>
      <p class="text-justify mb-4">En la sección de Base de Datos, se listan los conjuntos de datos de ACV disponibles. Al seleccionar un dataset, podrá ver su ficha de metadatos con información clave: descripción general, alcance del estudio (límites del sistema y unidad funcional), fuente de los datos, año de referencia, entre otros detalles. También podrá visualizar los flujos de entrada y salida asociados. La plataforma ofrece herramientas de visualización sencillas; por ejemplo, tablas resumidas que le ayudan a entender rápidamente el contenido del dataset. Esto le permite evaluar si un conjunto de datos es relevante para su proyecto antes de descargarlo o usarlo.</p>
      
      <h3 class="text-lg font-bold text-green-700 mb-2">3. Carga de nuevos datasets</h3>
      <p class="text-justify mb-4">Si desea agregar un nuevo conjunto de datos de ACV a MexiLCA, diríjase a la opción <strong>"Añadir Dataset"</strong> en el menú principal. Dentro de esta sección encontrará diferentes formas de incorporar información según sus necesidades:</p>
      
      <h4 class="font-bold text-gray-900 mb-2 ml-4">➤ Opción Manual</h4>
      <p class="text-justify mb-3 ml-4">En esta modalidad, el usuario debe completar directamente cada uno de los campos requeridos que describen un proceso o actividad. Los campos marcados con un asterisco (*) son obligatorios, mientras que los demás son opcionales, aunque se recomienda llenarlos para mejorar la calidad y trazabilidad del dataset. Aquí podrá introducir información como el nombre del proceso, su categoría, la unidad funcional, los flujos de entrada y salida, y las fuentes de datos utilizadas. Esta modalidad es ideal cuando se cuenta con información estructurada o se desea ingresar datos nuevos que aún no existen en la base de datos.</p>
      
      <h4 class="font-bold text-gray-900 mb-2 ml-4">➤ Opción Automática</h4>
      <p class="text-justify mb-3 ml-4">Esta alternativa permite subir directamente un archivo PDF que contenga un reporte, tesis, artículo o documento técnico en el cual se haya realizado un ACV y se presente un inventario de ciclo de vida (ICV). El sistema utiliza un algoritmo de lectura y extracción automática que identifica los datos relevantes del documento y genera el inventario correspondiente sin necesidad de llenado manual. Una vez procesado, el dataset se envía igualmente a <strong>revisión por parte del equipo administrador</strong>, antes de incorporarse oficialmente a la base de datos.</p>
      
      <h4 class="font-bold text-gray-900 mb-2 ml-4">➤ Opción Editar</h4>
      <p class="text-justify mb-3 ml-4">En esta sección puede modificar datasets existentes que se encuentren en la base de datos. El usuario podrá actualizar información, ajustar parámetros, cambiar nombres o corregir metadatos. Toda modificación deberá ser enviada nuevamente a revisión, garantizando que los cambios cumplan con los estándares de calidad de MexiLCA antes de publicarse.</p>
      
      <h4 class="font-bold text-gray-900 mb-2 ml-4">➤ Mis Borradores</h4>
      <p class="text-justify mb-3 ml-4">Aquí se almacenan los datasets que aún están en proceso de revisión o pendientes de aprobación por parte de los administradores. Incluye todos los conjuntos de datos creados mediante las modalidades manual, automática o editada, de modo que el usuario pueda realizar un seguimiento de su estado, realizar correcciones si se solicitan o completar la información antes de la publicación final.</p>
      
      <h4 class="font-bold text-gray-900 mb-2 ml-4">➤ Mis Contribuciones</h4>
      <p class="text-justify mb-4 ml-4">En esta pestaña se mostrarán todos los datasets que usted haya creado o editado y que ya fueron aprobados por los revisores. Es un espacio de consulta y gestión personal donde podrá verificar sus aportes publicados dentro de la plataforma MexiLCA, descargar sus propios datasets, o utilizarlos como referencia para futuras actualizaciones.</p>
      
      <p class="italic font-semibold text-sm mt-4"><strong>Nota:</strong> Para conocer los requisitos técnicos específicos, formatos aceptados y procedimientos de validación, consulte el <a href="https://ciclodevida.mx/assets/manual.pdf" class="text-green-700 hover:underline" target="_blank">Manual Técnico</a> disponible en la sección de documentación.</p>
    </div>

    <!-- TARJETA 3: Guía de contribuidor -->
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-2xl font-bold mb-4 text-green-800">Guía de contribuidor</h2>
      <p class="text-justify mb-4">Si desea contribuir sus propios conjuntos de datos a la plataforma MexiLCA, ¡esta sección es para usted! A continuación se describen las pautas y recomendaciones clave para colaboradores, asegurando que los datasets cargados cumplan con los estándares de calidad y las normas de la plataforma:</p>

      <h3 class="text-lg font-bold text-green-700 mb-2">Requisitos de calidad</h3>
      <p class="text-justify mb-3">Antes de subir un dataset, verifique que cumple con criterios mínimos de calidad. El conjunto de datos debe ser completo, coherente y transparente. Esto significa incluir todos los flujos relevantes de entradas y salidas de su sistema, usar unidades y referencias consistentes, y documentar claramente cualquier suposición o dato fuente. Por ejemplo, si su dataset es el ACV de un producto, asegúrese de cubrir todas las etapas definidas en su límite del sistema (p. ej., extracción de materias primas, manufactura, distribución, uso y fin de vida, según aplique). Asimismo, los datos numéricos deben ser razonables y provenir de fuentes confiables (o cálculos justificables).</p>
      <p class="text-justify mb-4"><strong>Consejo:</strong> Evite cargar datos duplicados que ya existan en la plataforma; en caso de datos similares, considere si su aporte ofrece una actualización o una mejora significativa.</p>

      <h3 class="text-lg font-bold text-green-700 mb-2">Plantilla de metadatos</h3>
      <p class="text-justify mb-4">MexiLCA proporciona una plantilla de metadatos estandarizada para ayudar a los contribuidores a incluir toda la información necesaria de forma estructurada. Es recomendable descargar y utilizar esta plantilla al preparar su dataset. En ella encontrará campos predefinidos para: título, autor(es) o fuente del dataset, descripción, unidad funcional, alcance y límites del sistema, metodología ACV empleada (por ejemplo, si sigue ISO 14040/14044, si utiliza algún método de impacto específico), y palabras clave, entre otros.</p>

      <h3 class="text-lg font-bold text-green-700 mb-2">Licencias y permisos</h3>
      <p class="text-justify mb-4">Antes de compartir un dataset, asegúrese de tener derecho a hacerlo. Verifique la licencia bajo la cual distribuye los datos. MexiLCA promueve el uso de licencias abiertas que permitan la reutilización de la información (por ejemplo, una licencia Creative Commons específica para datos, como CC-BY 4.0, u otras licencias de dominio público o abiertas). Al cargar el conjunto de datos, se le pedirá que especifique la licencia aplicable. Si el dataset proviene de un tercero, obtenga los permisos necesarios o verifique que la licencia original permita su redistribución. Es responsabilidad del contribuidor confirmar que no se infringen derechos de autor, confidencialidad u otros aspectos legales. Además, incluir la fuente original de los datos en la metadata (por ejemplo, referencia a un artículo, reporte o base de datos de donde provienen) es una buena práctica que aumenta la transparencia y credibilidad del dataset.</p>

      <h3 class="text-lg font-bold text-green-700 mb-2">Proceso de revisión y verificación</h3>
      <p class="text-justify mb-3">Todo dataset enviado pasará por un proceso de revisión antes de ser publicado en MexiLCA. Una vez que envíe su conjunto de datos, el equipo de la plataforma (o revisores designados) verificará varios aspectos: que la información esté completa, que el contenido tenga coherencia técnica (ej. que las unidades de medida sean correctas, que no haya errores evidentes en los balances de masa/energía), y que cumpla con los lineamientos de calidad y formato. También se revisará el cumplimiento de los aspectos legales (licencia adecuada, permisos, etc.). Si durante la revisión se encuentran detalles a corregir o aclarar, es posible que le contacten con comentarios o solicitando ajustes. Después de la aprobación, el dataset se hará público en la plataforma para que otros usuarios puedan consultarlo. Recuerde que este proceso de revisión es una garantía de calidad: ayuda a mantener la confianza en los datos disponibles en MexiLCA.</p>
    </div>

    <!-- TARJETA 4: Buenas prácticas ACV -->
    <div class="bg-white border border-gray-200 shadow-sm p-6 rounded-xl max-w-5xl mb-8">
      <h2 class="text-2xl font-bold mb-4 text-green-800">Buenas prácticas ACV</h2>
      <p class="text-justify mb-4">En esta sección de ayuda, abordamos algunas <strong>buenas prácticas</strong> para el uso de datos y la realización de Análisis de Ciclo de Vida dentro de la plataforma MexiLCA, alineadas con los principios de las normas ISO 14040 y 14044. El objetivo es asegurar que tanto los creadores de datasets como los usuarios que consumen esos datos apliquen enfoques sólidos y coherentes en sus estudios de ACV.</p>

      <h3 class="text-lg font-bold text-green-700 mb-2">ISO 14040/44 en la práctica</h3>
      <p class="text-justify mb-3">Las normas ISO 14040 e ISO 14044 establecen el marco y los principios para llevar a cabo un ACV de calidad. Dentro de MexiLCA, se recomienda seguir estos principios al crear y usar datasets. En la práctica, esto significa:</p>
      
      <h4 class="font-semibold text-gray-900 mb-2 ml-4">• Definir claramente el objetivo y alcance</h4>
      <p class="text-justify mb-3 ml-4">Cada conjunto de datos debe corresponder a un propósito definido (por ejemplo, evaluar el impacto ambiental de la producción de 1 kg de determinado producto en la región X durante el año Y). Identifique y documente la unidad funcional y los límites del sistema del dataset. Por ejemplo, especifique si el límite es <em>de cuna a puerta</em> (cradle-to-gate), <em>de cuna a tumba</em> (cradle-to-grave), etc., y qué procesos exactos incluye.</p>
      
      <h4 class="font-semibold text-gray-900 mb-2 ml-4">• Asegurar la transparencia y completitud</h4>
      <p class="text-justify mb-3 ml-4">Documente todas las suposiciones, fuentes de datos y métodos utilizados al generar el dataset. La transparencia implica que otro experto podría revisar su dataset y entender cómo fue elaborado y de dónde provienen los datos. Asimismo, la completitud se refiere a incluir todos los elementos relevantes: no omita etapas del ciclo de vida ni flujos significativos sin justificarlo. Por ejemplo, si en un ACV de un envase se excluye la fase de uso, esto debe quedar explícito en los metadatos con su justificación.</p>
      
      <h4 class="font-semibold text-gray-900 mb-2 ml-4">• Mantener la consistencia y relevancia</h4>
      <p class="text-justify mb-4 ml-4">Use metodologías y criterios consistentes a lo largo de sus datos. Si está combinando varios datasets dentro de MexiLCA para un estudio, verifique que sean compatibles en supuestos y alcance (no conviene mezclar, por ejemplo, un dataset que incluye reciclaje de fin de vida con otro que no lo incluye, sin los ajustes pertinentes). Seleccione datasets que sean relevantes para su caso de estudio: por ejemplo, prefiera datos representativos de la misma región o tecnología que esté analizando, para mantener la relevancia de la comparación. La plataforma MexiLCA ofrece metadatos precisamente para ayudar a juzgar esta adecuación.</p>

      <h3 class="text-lg font-bold text-green-700 mb-2">Datasets comunes</h3>
      <p class="text-justify mb-3">Dentro de la plataforma encontrará principalmente datasets de inventario de ciclo de vida. Estos suelen presentarse en dos formas:</p>
      
      <h4 class="font-semibold text-gray-900 mb-2 ml-4">• Procesos unitarios</h4>
      <p class="text-justify mb-3 ml-4">Datos de ACV que representan un proceso específico (p. ej., "Producción de 1 kWh de electricidad en red eléctrica mexicana" o "Transporte de 1 tonelada de carga por camión diesel en distancia de 100 km"). Estos datasets de procesos unitarios se pueden combinar en modelos más grandes.</p>
      
      <h4 class="font-semibold text-gray-900 mb-2 ml-4">• Sistemas de producto completos</h4>
      <p class="text-justify mb-3 ml-4">Inventarios que abarcan todo el ciclo de vida de un producto o servicio hasta cierto límite (por ejemplo, "ACV de 1 litro de agua embotellada, de cuna a tumba"). Estos ya integran múltiples procesos en un solo conjunto de resultados.</p>
    </div>
<!-- Footer -->
<footer class="mt-12 pt-10" role="contentinfo">
  <div class="max-w-6xl mx-auto px-6 space-y-10">
    <!-- Copyright y Licencia -->
    <section class="pt-6 text-center space-y-3">
      <p class="text-xs text-gray-500">
        &copy; <?= date('Y'); ?> <strong>MexiLCA </strong>— Los datos y metadatos se publican bajo licencia <strong class="italic"> Creative Commons Atribución 4.0 Internacional (CC BY 4.0)</a></strong> salvo indicación distinta. 
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

  // =========================
  // Componentes del Manual
  // =========================

  // Toast minimal
  function toast(msg){
    const t=document.createElement('div');
    t.textContent=msg;
    t.className='fixed bottom-6 left-1/2 -translate-x-1/2 bg-black text-white text-xs px-3 py-2 rounded-lg shadow z-50';
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),1600);
  }

  // 1) Buscador con highlight
  const searchInput=document.getElementById('searchManual');
  const searchableEls=[];
  document.querySelectorAll('main h2, main h3, main p, main li').forEach(el=>{
    el.dataset.orig=el.innerHTML;
    searchableEls.push(el);
  });
  function clearHighlights(){
    searchableEls.forEach(el=>{ if(el.dataset.orig) el.innerHTML=el.dataset.orig; });
  }
  function highlight(term){
    if(!term || term.trim()===''){ clearHighlights(); return; }
    const q=term.trim().replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    const re=new RegExp('(' + q + ')','gi');
    searchableEls.forEach(el=>{
      const src=el.dataset.orig || el.innerHTML;
      el.innerHTML=src.replace(re,'<mark>$1</mark>');
    });
  }
  if(searchInput){
    searchInput.addEventListener('input',(e)=>{
      const val=e.target.value;
      if(!val) clearHighlights(); else highlight(val);
    });
  }

    // 2) Anclas y "copiar enlace a sección" (arriba derecha, sin icono)
    function slugify(text){
    return (text || '')
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g,'') // quita acentos
        .replace(/[^a-z0-9\s-]/g,'')
        .trim().replace(/\s+/g,'-').replace(/-+/g,'-');
    }

    function ensureSectionAnchors(){
    // Recorre cada "tarjeta" de contenido en el main
    document.querySelectorAll('main .rounded-xl').forEach(card => {
        if (card.dataset.hasSectionCopy) return;

        // Busca el primer h2/h3 para usarlo como ancla
        const h = card.querySelector('h2, h3');
        if (!h) return;

        // Asigna id si no existe
        if (!h.id) h.id = slugify(h.textContent || 'seccion');

        // Asegura posicionamiento relativo del contenedor
        card.classList.add('relative');

        // Crea botón arriba-derecha (sin icono)
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Copiar enlace';
        btn.className = 'absolute top-3 right-3 text-xs px-2 py-1 rounded border border-gray-200 hover:bg-gray-50';
        btn.addEventListener('click', () => {
        const url = new URL(window.location.href);
        url.hash = h.id;
        navigator.clipboard.writeText(url.toString()).then(() => toast('Enlace copiado'));
        });

        card.appendChild(btn);
        card.dataset.hasSectionCopy = '1';
    });
    }

    ensureSectionAnchors();

  // 3) Botón "Reportar un problema"
  const reportBtn=document.getElementById('reportBtn');
  if(reportBtn){
    reportBtn.addEventListener('click',()=>{
      fetch('report_issue.php',{method:'HEAD'}).then(res=>{
        if(res.ok){ window.location.href='report_issue.php'; }
        else{
          window.location.href='mailto:contacto@ciclodevida.mx?subject=Reporte%20Manual,%20Ciclo de Vida&body=Describe%20el%20problema%20y%20la%20URL:%20' + encodeURIComponent(window.location.href);
        }
      }).catch(()=>{
        window.location.href='mailto:contacto@ciclodevida.mx?subject=Reporte%20Manual,%20Ciclo de Vida&body=Describe%20el%20problema%20y%20la%20URL:%20' + encodeURIComponent(window.location.href);
      });
    });
  }

  // Efecto visual al entrar por hash
  window.addEventListener('load',()=>{
    const h=location.hash ? location.hash.slice(1) : '';
    if(h){
      const el=document.getElementById(h);
      if(el){ el.classList.add('ring-2','ring-green-200','rounded'); setTimeout(()=>el.classList.remove('ring-2','ring-green-200','rounded'),1500); }
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
