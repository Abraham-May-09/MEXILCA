<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
</head>
<body class="relative min-h-screen flex items-center justify-center p-4 overflow-hidden">
  <!-- Imagen de fondo con opacidad -->
  <div class="absolute inset-0 bg-cover bg-center opacity-60 -z-10" style="background-image: url('images/ESCULTORICO-UNAM.jpg');"></div>
  <!-- Contenedor del formulario -->
  <form action="procesar_registro.php" method="POST" class="bg-white/80 backdrop-blur-md p-8 rounded-2xl shadow-lg w-full max-w-sm space-y-5 z-10 relative">
    <!-- Header -->
    <div class="text-center">
      <div class="mx-auto mb-4 w-14 h-14 flex items-center justify-center bg-green-100 rounded-full text-green-600 hover:rotate-6">
        <i data-lucide="user-plus" class="w-5 h-5"></i>
      </div>
      <h2 class="text-2xl font-bold text-gray-800">Crear cuenta</h2>
      <p class="text-sm text-gray-500">
        <span id="stepLabel">Paso 1 de 2 · <strong>Cuenta</strong></span>
      </p>
    </div>
  <!-- ===== PASO 1: CUENTA ===== -->
    <section id="step1" class="space-y-3">
      <div class="grid grid-cols-1 gap-3">
        <input type="text" name="nombres" placeholder="Nombre(s)" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
        <input type="text" name="apellidos" placeholder="Apellidos" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>
      <div>
        <input id="email" type="email" name="email" placeholder="Correo" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
        <p id="emailError" class="hidden text-xs text-red-600 mt-1">Usa un correo institucional válido.</p>
      </div>
      <div class="relative">
        <input id="password" type="password" name="password" placeholder="Contraseña" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500 pr-24">
          <!-- Botón para ver/ocultar -->
          <button type="button" id="togglePasswordBtn" class="absolute top-1/2 -translate-y-1/2 right-24 text-gray-500 hover:text-gray-700">
            <i id="toggleIcon" data-lucide="eye" class="w-5 h-5"></i>
          </button>
        <!-- Indicador de fortaleza -->
        <div class="absolute right-2 top-1/2 -translate-y-1/2 w-20">
          <div class="h-2 rounded bg-gray-200 overflow-hidden">
            <div id="pwBar" class="h-2 w-0 bg-red-500 transition-all"></div>
          </div>
          <span id="pwLabel" class="block text-[10px] text-right text-gray-500 mt-1">Débil</span>
        </div>
      </div>
      <div>
    <!-- CAMPO CONFIRMAR CONTRASEÑA CON BOTÓN PARA VER -->
    <div>
      <div class="relative">
        <input id="confirm" type="password" name="confirm_password" placeholder="Confirmar contraseña" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500 pr-10">
        <button type="button" id="toggleConfirmBtn" class="absolute top-1/2 -translate-y-1/2 right-2 text-gray-500 hover:text-gray-700">
          <i id="toggleConfirmIcon" data-lucide="eye" class="w-5 h-5"></i>
        </button>
      </div>
      <p id="matchError" class="hidden text-xs text-red-600 mt-1">Las contraseñas no coinciden.</p>
    </div>
      <label class="flex items-start gap-2 text-sm text-gray-700 select-none">
        <input id="terms" type="checkbox" name="terms" class="mt-1 rounded border-gray-300 text-green-700 focus:ring-green-600" required>
        <span>Acepto los <a href="terminos.php" class="text-green-700 underline">Términos</a> y la <a href="privacidad.php" class="text-green-700 underline">Política de Privacidad</a>.</span>
      </label>
    </section>
  <!-- ===== PASO 2: PERFIL ===== -->
    <section id="step2" class="space-y-3 hidden">
      <div>
        <select name="rol" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
          <option value="">Rol</option>
          <option>Investigador/a</option>
          <option>Gobierno</option>
          <option>Industria</option>
          <option>Academia/Estudiante</option>
          <option>Otro</option>
        </select>
      </div>
      <input type="text" name="institucion" placeholder="Institución / Dependencia" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
      <div class="grid grid-cols-1 gap-3">
        <input type="text" name="pais" placeholder="País" required class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
        <input type="text" name="estado" placeholder="Estado (opcional)" class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>
      <!-- Sectores de Interes -->
      <div>
        <p class="text-sm text-gray-700 mb-2">Sectores de interés (CREAA)</p>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="sectores[]" value="Construcción" class="rounded text-green-700 focus:ring-green-600"> Construcción
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="sectores[]" value="Residuos" class="rounded text-green-700 focus:ring-green-600"> Residuos
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="sectores[]" value="Energía" class="rounded text-green-700 focus:ring-green-600"> Energía
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="sectores[]" value="Agua" class="rounded text-green-700 focus:ring-green-600"> Agua
          </label>
          <label class="flex items-center gap-2 text-sm col-span-2">
            <input type="checkbox" name="sectores[]" value="Alimentos" class="rounded text-green-700 focus:ring-green-600"> Alimentos
          </label>
        </div>
      </div>
      <!-- ¿Contribuirás datasets? -->
      <div class="space-y-2">
        <p class="text-sm text-gray-700">¿Contribuirás datasets?</p>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input type="radio" name="contribuye" value="Si" class="text-green-700 focus:ring-green-600"> Sí
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input type="radio" name="contribuye" value="No" class="text-green-700 focus:ring-green-600" checked> No
          </label>
        </div>
        <!-- Extra "Sí" -->
        <div id="datasetExtra" class="hidden  grid-cols-1 gap-3">
          <select name="tipo" class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">Tipo de dataset</option>
            <option>ICV/LCA</option>
            <option>Inventarios</option>
            <option>LCIA</option>
            <option>Metadatos</option>
          </select>
          <select name="formato" class="w-full px-4 py-2 border rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">Formato</option>
            <option>CSV</option>
            <option>Excel</option>
            <option>JSON</option>
            <option>openLCA</option>
          </select>
        </div>
      </div>
    </section>
    <!-- Acciones (Botones) -->
    <div class="space-y-2 pt-1">
      <div class="flex items-center justify-between">
        <button id="backBtn" type="button" class="hidden text-gray-700 text-sm px-3 py-2 rounded-lg border hover:bg-gray-50">
          ← Atrás
        </button>
        <div class="ml-auto flex items-center gap-2">
          <button id="nextBtn" type="button" class="bg-green-600 text-white py-2 px-3 rounded-lg hover:bg-green-700 transition font-semibold shadow text-sm">
            Siguiente →
          </button>
          <button id="submitBtn" type="submit" class="hidden bg-green-600 text-white py-2 px-3 rounded-lg hover:bg-green-700 transition font-semibold shadow text-sm">
            Crear cuenta
          </button>
        </div>
      </div>
      <a href="index.php" class="text-gray-700 text-sm px-3 py-2 rounded-lg border hover:bg-gray-50">
        ← Volver al inicio
      </a>
    </div>
  </form>
  <script>
    lucide.createIcons();

    // ====== Config opcional de dominio institucional ======
    const ENFORCE_DOMAIN = false; // true para exigir dominio institucional
    const ALLOWED_DOMAINS = ["unam.mx","edu.mx"];

    // ====== Navegación de pasos ======
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const stepLabel = document.getElementById('stepLabel');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');
    const submitBtn = document.getElementById('submitBtn');

    function showStep2(){
      step1.classList.add('hidden');
      step2.classList.remove('hidden');
      nextBtn.classList.add('hidden');
      backBtn.classList.remove('hidden');
      submitBtn.classList.remove('hidden');
      stepLabel.textContent = 'Paso 2 de 2 · Perfil';
      window.scrollTo({top:0, behavior:'smooth'});
    }
    function showStep1(){
      step2.classList.add('hidden');
      step1.classList.remove('hidden');
      nextBtn.classList.remove('hidden');
      backBtn.classList.add('hidden');
      submitBtn.classList.add('hidden');
      stepLabel.textContent = 'Paso 1 de 2 · Cuenta';
      window.scrollTo({top:0, behavior:'smooth'});
    }

    // Validaciones del Paso 1
    const email = document.getElementById('email');
    const emailError = document.getElementById('emailError');
    const pw = document.getElementById('password');
    const confirmPw = document.getElementById('confirm');
    const matchError = document.getElementById('matchError');
    const terms = document.getElementById('terms');

    function isStrong(v){
      return v.length >= 8 && /[a-z]/.test(v) && /[A-Z]/.test(v) && /\d/.test(v);
    }
    function domainOk(mail){
      if (!ENFORCE_DOMAIN) return true;
      const at = mail.lastIndexOf('@');
      if (at < 0) return false;
      const dom = mail.slice(at+1).toLowerCase();
      return ALLOWED_DOMAINS.some(d => dom.endsWith(d));
    }
    function validateStep1(){
      emailError.classList.add('hidden');
      matchError.classList.add('hidden');

      let ok = true;
      if (!domainOk(email.value.trim())) { emailError.classList.remove('hidden'); ok = false; }
      if (pw.value !== confirmPw.value) { matchError.classList.remove('hidden'); ok = false; }
      if (!isStrong(pw.value)) ok = false;
      if (!terms.checked) ok = false;

      // también rely en required
      return ok;
    }

    nextBtn.addEventListener('click', () => {
      if (validateStep1()) showStep2();
      else alert('Revisa los campos del Paso 1 (correo, contraseña y términos).');
    });
    backBtn.addEventListener('click', showStep1);

    // Indicador de fortaleza de contraseña
    const pwBar = document.getElementById('pwBar');
    const pwLabel = document.getElementById('pwLabel');
    pw.addEventListener('input', e => {
      const v = e.target.value;
      let score = 0;
      if (v.length>=8) score++;
      if (/[a-z]/.test(v)) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      const pct = [0,25,50,75,100][Math.min(score,4)];
      pwBar.style.width = pct + '%';
      if (pct<25){ pwBar.className='h-2 bg-red-500'; pwLabel.textContent='Débil'; }
      else if (pct<50){ pwBar.className='h-2 bg-orange-500'; pwLabel.textContent='Básica'; }
      else if (pct<75){ pwBar.className='h-2 bg-yellow-500'; pwLabel.textContent='Media'; }
      else { pwBar.className='h-2 bg-green-600'; pwLabel.textContent='Fuerte'; }
    });

    // Mostrar campos extra si "Sí" en contribución de datasets
    const dsRadios = document.querySelectorAll('input[name="contribuye"]');
    const dsExtra = document.getElementById('datasetExtra');
    dsRadios.forEach(r => r.addEventListener('change', () => {
      if (r.checked && r.value === 'Si') dsExtra.classList.remove('hidden');
      if (r.checked && r.value === 'No') dsExtra.classList.add('hidden');
    }));
    
 // VER / OCULTAR contraseña
    function setupPasswordToggle(buttonId, inputId) {
      const toggleButton = document.getElementById(buttonId);
      const passwordInput = document.getElementById(inputId);

      if (toggleButton && passwordInput) {
        toggleButton.addEventListener('click', () => {
          const isPassword = passwordInput.type === 'password';
  
          passwordInput.type = isPassword ? 'text' : 'password';

          const newIconName = isPassword ? 'eye-off' : 'eye';

          toggleButton.innerHTML = `<i data-lucide="${newIconName}" class="w-5 h-5"></i>`;

          lucide.createIcons();
        });
      }
    }

    // No necesitas cambiar estas líneas, seguirán funcionando
    setupPasswordToggle('togglePasswordBtn', 'password');
    setupPasswordToggle('toggleConfirmBtn', 'confirm');

  </script>
</body>
</html>
