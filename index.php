<?php
/**
 * Punto de Entrada y Enrutador Principal
 * Sistema de Gestión de Horarios y Asistencia
 * Hospital Distrital de Pacasmayo
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Control de Acceso: Exigir autenticación obligatoria
Auth::requireAuth();

$currentUser = Auth::getCurrentUser();

// Definición de módulos permitidos (Arquitectura funcional del sistema)
$allowedPages = [
    'dashboard'      => 'pages/dashboard.php',
    'personal'       => 'pages/personal.php',
    'horarios'       => 'pages/horarios.php',
    'programacion'   => 'pages/programacion.php',
    'asistencia'     => 'pages/asistencia.php',
    'ausencias'      => 'pages/ausencias.php',
    'reportes'       => 'pages/reportes.php',
    'administracion' => 'pages/administracion.php'
];

$page = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($page, $allowedPages)) {
    $page = 'dashboard';
}

// Validar permisos del rol sobre el módulo
if (!Auth::checkPermission($page)) {
    $page = 'dashboard';
}

$pageFile = $allowedPages[$page];
$pageTitle = "Hospital Distrital de Pacasmayo - SGH RR.HH.";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sistema de Gestión de Horarios, Turnos y Control de Asistencia - Hospital Distrital de Pacasmayo">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- Evitar destello blanco al cambiar de página (Flash of Unstyled Theme - FOUC) -->
  <script>
    (function() {
      try {
        var theme = localStorage.getItem('sgh_theme');
        if (theme === 'dark') {
          document.documentElement.classList.add('dark');
        }
      } catch (e) {}
    })();
  </script>

  <!-- Favicon e Icono Institucional -->
  <link rel="icon" type="image/svg+xml" href="assets/img/logo-hospital.svg">

  <!-- Hojas de Estilo del Sistema de Diseño Propio -->
  <link rel="stylesheet" href="assets/css/variables.css?v=<?= filemtime(__DIR__ . '/assets/css/variables.css') ?>">
  <link rel="stylesheet" href="assets/css/layout.css?v=<?= filemtime(__DIR__ . '/assets/css/layout.css') ?>">
  <link rel="stylesheet" href="assets/css/components.css?v=<?= filemtime(__DIR__ . '/assets/css/components.css') ?>">

  <!-- Estilo específico de la página actual si existe -->
  <?php if (file_exists(__DIR__ . '/assets/css/pages/' . $page . '.css')): ?>
    <link rel="stylesheet" href="assets/css/pages/<?= htmlspecialchars($page) ?>.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/' . $page . '.css') ?>">
  <?php endif; ?>
</head>
<body>
  <div class="app-container">
    <!-- Barra Lateral Reutilizable (8 Módulos) -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Contenedor Principal -->
    <div class="app-main">
      <!-- Header Superior Reutilizable (Buscador, Alertas, Perfil) -->
      <?php include __DIR__ . '/includes/header.php'; ?>

      <!-- Área de Contenido del Módulo Actual -->
      <main class="app-content">
        <?php 
        if (file_exists(__DIR__ . '/' . $pageFile)) {
            include __DIR__ . '/' . $pageFile;
        } else {
            include __DIR__ . '/pages/dashboard.php';
        }
        ?>
      </main>

      <!-- Pie de Página Institucional y Modales Compartidos -->
      <?php include __DIR__ . '/includes/footer.php'; ?>
    </div>
  </div>
</body>
</html>
