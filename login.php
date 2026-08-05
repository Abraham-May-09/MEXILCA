<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@0.485.0/dist/umd/lucide.min.js"></script>
</head>

<body class="relative min-h-screen flex items-center justify-center p-4 overflow-hidden">
  <!-- Imagen de fondo con transparencia -->
  <div class="absolute inset-0 bg-cover bg-center opacity-60 -z-10"
       style="background-image: url('images/ESCULTORICO-UNAM.jpg');">
  </div>
  <!-- Contenedor del formulario -->
  <div class="w-full max-w-md rounded-lg border bg-white/80 backdrop-blur-md text-black shadow-md dark:border-primary/20 dark:bg-gray-800/80 relative z-10">
    <div class="flex flex-col space-y-1.5 p-6 text-center">
      <div class="mx-auto mb-4 w-16 h-16 flex items-center justify-center bg-green-100 dark:bg-primary/10 rounded-full text-green-600 dark:text-primary hover:rotate-6 transition-transform">
        <i data-lucide="leaf" class="w-6 h-6"></i>
      </div>
      <h3 class="text-2xl font-semibold leading-none tracking-tight">¡Bienvenido!</h3>
      <p class="text-sm text-muted-foreground text-gray-500">Inicia sesión para acceder a la base de datos CREAA.</p>
    </div>
    <div class="p-6 pt-0">
  <!-- Mensaje de error -->
  <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 p-3 text-sm text-red-700 bg-red-100 border border-red-300 rounded">
      <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
  <?php endif; ?>
    <form class="space-y-4" method="POST" action="procesar_login.php">
        <div class="grid gap-2 text-left">
          <label for="usuario" class="text-sm font-medium">Email</label>
          <input type="email" id="email" name="email" required
            class="flex h-10 w-full rounded-md border border-input bg-gray-50 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-400 focus-visible:ring-offset-2" />
        </div>
      
        <div class="grid gap-2 text-left">
          <label for="password" class="text-sm font-medium">Contraseña</label>
          <div class="relative">
            <input type="password" id="password" name="password" required
              class="flex h-10 w-full rounded-md border border-input bg-gray-50 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-400 focus-visible:ring-offset-2 pr-10" />
            <button type="button" id="togglePasswordBtn" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700">
              <i data-lucide="eye" id="toggleIcon" class="w-5 h-5"></i>
            </button>
          </div>
        </div>
      
        <button type="submit"
          class="inline-flex items-center justify-center rounded-md bg-green-600 text-white text-sm font-medium px-4 py-2 w-full hover:bg-green-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-400 focus-visible:ring-offset-2">
          <i data-lucide="log-in" class="w-4 h-4 mr-2"></i> Iniciar sesión
        </button>
      </form>
    </div>
    <div class="text-center text-sm text-gray-600 p-4">
      ¿Eres nuevo? <a href="register.php" class="text-green-600 hover:underline">Regístrate aquí</a>
    </div>
    <a href="index.php" class="block text-center text-sm text-gray-600 hover:underline">
      ← Ve a la Base de Datos
    </a>
  </div>

  <script>
  lucide.createIcons();

  const toggleButton = document.getElementById('togglePasswordBtn');
  const passwordInput = document.getElementById('password');
  const toggleIconContainer = document.querySelector('#togglePasswordBtn'); // El botón mismo es el contenedor

  toggleButton.addEventListener('click', function() {
    const isPassword = passwordInput.getAttribute('type') === 'password';
    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

    const newIconName = isPassword ? 'eye-off' : 'eye';
    
    toggleIconContainer.innerHTML = `<i data-lucide="${newIconName}" id="toggleIcon" class="w-5 h-5"></i>`;
    
    lucide.createIcons();
  });
  
  </script>
</body>
</html>