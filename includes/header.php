<?php
/**
 * Header Superior Institucional - Hospital Distrital de Pacasmayo
 * Estilo Minimalista y Limpio - Sin saturación visual
 */
$currentUser = Auth::getCurrentUser();
?>
<header class="app-header">
  <div class="header-left">
    <!-- Botón Hamburguesa para Móvil / Tablet -->
    <button type="button" class="header-mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir Menú">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- Título Institucional Sobrio -->
    <div class="header-title-box">
      <span class="header-hospital-name">Hospital Distrital de Pacasmayo</span>
      <span class="header-system-tag">MINSA &bull; La Libertad</span>
    </div>
  </div>

  <!-- Controles a la Derecha: Tema y Perfil de Usuario -->
  <div class="header-right">
    <!-- Botón de Tema (Claro / Oscuro) -->
    <button type="button" class="header-icon-btn" id="themeToggleBtn" title="Cambiar tema visual">
      <span id="themeIcon">🌙</span>
    </button>

    <!-- Perfil de Usuario con Menú Desplegable -->
    <div class="user-profile-container" id="userProfileContainer">
      <button type="button" class="user-profile-btn" id="userProfileToggle" aria-haspopup="true" aria-expanded="false">
        <div class="user-avatar-circle"><?= htmlspecialchars($currentUser['iniciales'] ?? 'US') ?></div>
        <div class="user-profile-meta">
          <span class="user-profile-name"><?= htmlspecialchars($currentUser['nombre_corto'] ?? 'Usuario') ?></span>
          <span class="user-profile-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Personal') ?></span>
        </div>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="chevron-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <!-- Menú Desplegable de Usuario -->
      <div class="user-profile-dropdown" id="userProfileMenu">
        <div class="dropdown-user-header">
          <div class="dropdown-full-name"><?= htmlspecialchars($currentUser['nombre_completo']) ?></div>
          <div class="dropdown-cargo-name"><?= htmlspecialchars($currentUser['cargo']) ?></div>
          <div class="dropdown-extra-info">
            <span><strong>DNI:</strong> <?= htmlspecialchars($currentUser['dni'] ?: 'No registrado') ?></span>
            <span><strong>Servicio:</strong> <?= htmlspecialchars($currentUser['servicio']) ?></span>
          </div>
        </div>

        <?php if (Auth::checkPermission('administracion')): ?>
        <a href="index.php?page=administracion" class="dropdown-menu-item">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
          <span>Administración del Sistema</span>
        </a>
        <?php endif; ?>

        <a href="logout.php" class="dropdown-menu-item logout">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          <span>Cerrar Sesión</span>
        </a>
      </div>
    </div>
  </div>
</header>
