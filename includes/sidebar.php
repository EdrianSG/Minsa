<?php
/**
 * Barra Lateral / Sidebar Institucional - Hospital Distrital de Pacasmayo
 * Estilo Minimalista y Limpio - Sin saturación visual
 */
$currentPage = $page ?? 'dashboard';
?>
<!-- Fondo oscuro para backdrop móvil -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="app-sidebar" id="appSidebar">
  <!-- Cabecera del Sidebar con Logo Institucional -->
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <img src="assets/img/logo-hospital.svg" alt="Hospital Pacasmayo" width="32" height="32">
    </div>
    <div class="sidebar-brand-text">
      <span class="brand-title">Hospital Pacasmayo</span>
      <span class="brand-subtitle">Gestión de Personal</span>
    </div>
  </div>

  <!-- Navegación de Módulos Principal -->
  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Módulos</div>

    <!-- 1. Inicio -->
    <a href="index.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
        </span>
        <span>Inicio</span>
      </div>
    </a>

    <!-- 2. Personal -->
    <a href="index.php?page=personal" class="nav-item <?= $currentPage === 'personal' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </span>
        <span>Personal</span>
      </div>
    </a>

    <!-- 3. Horarios y Turnos -->
    <a href="index.php?page=horarios" class="nav-item <?= $currentPage === 'horarios' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3" />
          </svg>
        </span>
        <span>Horarios y Turnos</span>
      </div>
    </a>

    <!-- 4. Programación Mensual -->
    <a href="index.php?page=programacion" class="nav-item <?= $currentPage === 'programacion' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18" />
          </svg>
        </span>
        <span>Programación</span>
      </div>
    </a>

    <!-- 5. Control de Asistencia -->
    <a href="index.php?page=asistencia" class="nav-item <?= $currentPage === 'asistencia' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </span>
        <span>Asistencia</span>
      </div>
    </a>

    <!-- 6. Ausencias y Justificaciones -->
    <a href="index.php?page=ausencias" class="nav-item <?= $currentPage === 'ausencias' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </span>
        <span>Ausencias y CITT</span>
      </div>
    </a>

    <div class="sidebar-section-label" style="margin-top: 1rem;">Gestión</div>

    <!-- 7. Reportes -->
    <a href="index.php?page=reportes" class="nav-item <?= $currentPage === 'reportes' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </span>
        <span>Reportes</span>
      </div>
    </a>

    <!-- 8. Administración -->
    <?php if (Auth::checkPermission('administracion')): ?>
    <a href="index.php?page=administracion" class="nav-item <?= $currentPage === 'administracion' ? 'active' : '' ?>">
      <div class="nav-item-content">
        <span class="nav-icon">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
        </span>
        <span>Administración</span>
      </div>
    </a>
    <?php endif; ?>
  </nav>

  <!-- Pie del Sidebar: Estado de Base de Datos -->
  <?php
  require_once __DIR__ . '/../config/database.php';
  $isDbOnline = (Database::getConnection() !== null);
  ?>
  <div class="sidebar-footer-minimal">
    <span class="db-status-pill <?= $isDbOnline ? 'online' : 'offline' ?>" id="dbStatusPill" title="<?= $isDbOnline ? 'Conexión activa con Microsoft SQL Server' : 'Sin conexión con la base de datos (Modo contingencia)' ?>">
      <span class="<?= $isDbOnline ? 'status-dot-active' : 'status-dot-offline' ?>" id="dbStatusDot"></span>
      <span id="dbStatusText"><?= $isDbOnline ? 'En línea' : 'Offline' ?></span>
    </span>
  </div>
</aside>
