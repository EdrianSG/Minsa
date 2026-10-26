<?php
/**
 * Pantalla de Inicio de Sesión Oficial
 * Hospital Distrital de Pacasmayo - MINSA
 * Diseño Minimalista y Limpio
 */

require_once __DIR__ . '/includes/auth.php';

// Si ya tiene sesión activa, redirigir al sistema
if (Auth::isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$redirect = $_GET['redirect'] ?? 'index.php';
$msg = $_GET['msg'] ?? '';

$mensajeAlerta = '';
$tipoAlerta = 'info';

if ($msg === 'logout') {
    $mensajeAlerta = 'Su sesión ha sido cerrada correctamente.';
    $tipoAlerta = 'success';
} elseif ($msg === 'expirada') {
    $mensajeAlerta = 'Su sesión ha expirado. Por favor ingrese nuevamente.';
    $tipoAlerta = 'info';
} elseif ($msg === 'denegado') {
    $mensajeAlerta = 'Debe iniciar sesión para acceder al sistema.';
    $tipoAlerta = 'danger';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Acceso al Sistema de Control de Asistencia del Hospital Distrital de Pacasmayo">
  <title>Acceso al Sistema - Hospital Distrital de Pacasmayo</title>

  <!-- Favicon e Icono Institucional -->
  <link rel="icon" type="image/svg+xml" href="assets/img/logo-hospital.svg">

  <!-- Estilos del Login -->
  <link rel="stylesheet" href="assets/css/pages/login.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/login.css') ?>">
</head>
<body class="login-page-body">
  <div class="login-wrapper">
    <div class="login-card">
      <!-- Encabezado Institucional -->
      <div class="login-header">
        <div class="login-logo">
          <img src="assets/img/logo-hospital.svg" alt="Hospital Pacasmayo">
        </div>
        <h1 class="login-title">Hospital Pacasmayo</h1>
        <p class="login-subtitle">Control de Asistencia y Personal</p>
      </div>

      <!-- Contenedor de Alertas Dinámicas -->
      <div id="loginAlertContainer">
        <?php if (!empty($mensajeAlerta)): ?>
          <div class="login-alert <?= htmlspecialchars($tipoAlerta) ?>">
            <?php if ($tipoAlerta === 'success'): ?>
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php else: ?>
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php endif; ?>
            <div><?= htmlspecialchars($mensajeAlerta) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Formulario de Acceso -->
      <form id="loginForm" autocomplete="on">
        <input type="hidden" id="redirectUrl" value="<?= htmlspecialchars($redirect) ?>">

        <div class="form-group-login">
          <label for="identificador" class="form-label-login">Usuario o DNI</label>
          <div class="input-icon-wrapper">
            <input 
              type="text" 
              id="identificador" 
              name="identificador" 
              class="form-control-login" 
              placeholder="Ingrese su usuario o número de DNI" 
              required 
              autofocus
            >
            <span class="input-icon-left">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </span>
          </div>
        </div>

        <div class="form-group-login">
          <label for="password" class="form-label-login">Contraseña</label>
          <div class="input-icon-wrapper">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-control-login" 
              placeholder="Ingrese su contraseña" 
              required
            >
            <span class="input-icon-left">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </span>
            <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Ver / Ocultar contraseña" tabindex="-1">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login-submit" id="btnSubmitLogin">
          <span class="spinner-login"></span>
          <span class="btn-text-content">Iniciar Sesión</span>
        </button>
      </form>
    </div>

    <div class="login-footer-text">
      <span>Ministerio de Salud &bull; Hospital Distrital de Pacasmayo</span>
    </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/pages/login.js"></script>
</body>
</html>
