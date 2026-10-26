/**
 * JavaScript Global de la Aplicación - Hospital Distrital de Pacasmayo
 * Control de temas, notificaciones, buscador global y modales
 */

document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();
  initNotificationPopover();
  initUserProfileDropdown();
  initGlobalSearch();
  initMobileSidebar();
  initModalHelpers();
  initDbStatusWatcher();
  initServiceScopeLocks();
  setTimeout(initServiceScopeLocks, 150);
});

/* =========================================================
   BLOQUEO Y ENFOQUE AUTOMÁTICO DE SERVICIO PARA ROLES RESTRINGIDOS
   ========================================================= */
function initServiceScopeLocks() {
  if (!window.currentUser || !window.currentUser.allowed_service_id) return;

  const allowedServId = String(window.currentUser.allowed_service_id);
  const servName = window.currentUser.servicio || 'Servicio Asignado';

  const selectorIds = [
    'progServiceSelect',
    'asistServicioSelect',
    'repServicioSelect',
    'ausServicioSelect'
  ];

  selectorIds.forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;

    sel.value = allowedServId;
    sel.disabled = true;
    sel.title = `Acceso restringido a su servicio: ${servName}`;

    if (!sel.parentNode.querySelector('.service-lock-badge')) {
      const badge = document.createElement('span');
      badge.className = 'badge badge-neutral service-lock-badge';
      badge.style.marginLeft = '0.5rem';
      badge.style.fontSize = '0.72rem';
      badge.textContent = '🔒 Su servicio';
      sel.parentNode.appendChild(badge);
    }
  });

  if (typeof progState !== 'undefined') progState.servicioId = parseInt(allowedServId, 10);
  if (typeof asistState !== 'undefined') asistState.servicioId = allowedServId;
  if (typeof repState !== 'undefined') repState.servicioId = allowedServId;
}

/* =========================================================
   CONTROL DE TEMA (CLARO / OSCURO)
   ========================================================= */
function initThemeToggle() {
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  const themeIcon = document.getElementById('themeIcon');
  
  // Preferencia de tema: Por defecto limpio institucional (claro)
  const savedTheme = localStorage.getItem('sgh_theme');
  const isDark = savedTheme === 'dark';

  applyTheme(isDark);

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
      document.body.classList.add('theme-transitioning');
      const currentIsDark = document.documentElement.classList.contains('dark');
      applyTheme(!currentIsDark);
      setTimeout(() => {
        document.body.classList.remove('theme-transitioning');
      }, 300);
    });
  }

  function applyTheme(dark) {
    if (dark) {
      document.documentElement.classList.add('dark');
      document.body.classList.add('dark');
      if (themeIcon) themeIcon.textContent = '☀️';
      if (themeToggleBtn) themeToggleBtn.title = 'Cambiar a Modo Claro';
      localStorage.setItem('sgh_theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      document.body.classList.remove('dark');
      if (themeIcon) themeIcon.textContent = '🌙';
      if (themeToggleBtn) themeToggleBtn.title = 'Cambiar a Modo Oscuro';
      localStorage.setItem('sgh_theme', 'light');
    }
  }
}

/* =========================================================
   POPOVER DE NOTIFICACIONES
   ========================================================= */
function initNotificationPopover() {
  const notifBtn = document.getElementById('notifBtn');
  const notifPopover = document.getElementById('notifPopover');

  if (!notifBtn || !notifPopover) return;

  notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notifPopover.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!notifPopover.contains(e.target) && e.target !== notifBtn) {
      notifPopover.classList.remove('open');
    }
  });
}

/* =========================================================
   MENÚ DESPLEGABLE DE PERFIL DE USUARIO
   ========================================================= */
function initUserProfileDropdown() {
  const container = document.getElementById('userProfileContainer');
  const toggleBtn = document.getElementById('userProfileToggle');

  if (!container || !toggleBtn) return;

  toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    container.classList.toggle('active');
  });

  document.addEventListener('click', (e) => {
    if (!container.contains(e.target)) {
      container.classList.remove('active');
    }
  });
}

/* =========================================================
   BÚSQUEDA GLOBAL DE TRABAJADOR
   ========================================================= */
function initGlobalSearch() {
  const searchInput = document.getElementById('searchInput');
  const searchClearBtn = document.getElementById('searchClearBtn');
  const searchResults = document.getElementById('searchResults');

  if (!searchInput || !searchResults) return;

  // Catálogo base de búsqueda simulada de personal del hospital
  const mockStaff = [
    { dni: '45892104', nombre: 'Ana María Torres Benites', cargo: 'Enfermera Asistencial', servicio: 'Emergencia y Cuidados Críticos', estado: 'Tardanza (24 min)' },
    { dni: '70445708', nombre: 'Lic. Luis Alberto Díaz Rojas', cargo: 'Médico Cirujano', servicio: 'Unidad de Cuidados Intensivos', estado: 'Justificación Pendiente' },
    { dni: '18942301', nombre: 'Carlos Eduardo Vega Morales', cargo: 'Técnico en Enfermería', servicio: 'Hospitalización Medicina', estado: 'Sin Salida' },
    { dni: '41209845', nombre: 'Dra. Rosa Patricia Mendoza Campos', cargo: 'Médico Pediatra', servicio: 'Pediatría', estado: 'Presente (Puntual)' },
    { dni: '72109483', nombre: 'Manuel Alejandro Silva Paredes', cargo: 'Obstetra', servicio: 'Centro Obstétrico', estado: 'Vacaciones' },
    { dni: '43920194', nombre: 'Carmen Rosa Urcia Ventura', cargo: 'Técnico Administrativo', servicio: 'Admisión y Archivo', estado: 'Presente (Puntual)' }
  ];

  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim().toLowerCase();
    
    if (query.length > 0) {
      if (searchClearBtn) searchClearBtn.style.display = 'block';

      const filtered = mockStaff.filter(p => 
        p.nombre.toLowerCase().includes(query) ||
        p.dni.includes(query) ||
        p.cargo.toLowerCase().includes(query) ||
        p.servicio.toLowerCase().includes(query)
      );

      renderSearchResults(filtered);
    } else {
      if (searchClearBtn) searchClearBtn.style.display = 'none';
      searchResults.style.display = 'none';
    }
  });

  if (searchClearBtn) {
    searchClearBtn.addEventListener('click', () => {
      searchInput.value = '';
      searchClearBtn.style.display = 'none';
      searchResults.style.display = 'none';
      searchInput.focus();
    });
  }

  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.style.display = 'none';
    }
  });

  function renderSearchResults(items) {
    if (items.length === 0) {
      searchResults.innerHTML = `
        <div style="padding: 0.85rem; text-align: center; color: var(--text-tertiary); font-size: 0.82rem;">
          No se encontraron trabajadores con ese criterio.
        </div>
      `;
    } else {
      searchResults.innerHTML = items.map(p => `
        <div class="search-result-item" onclick="selectSearchedWorker('${p.dni}', '${p.nombre}')">
          <div>
            <div style="font-weight: 600; font-size: 0.88rem; color: var(--text-primary);">${p.nombre}</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary);">${p.cargo} • ${p.servicio}</div>
          </div>
          <span class="badge ${p.estado.includes('Tardanza') ? 'badge-late' : p.estado.includes('Pendiente') ? 'badge-neutral' : p.estado.includes('Sin') ? 'badge-absent' : 'badge-present'}">
            ${p.estado}
          </span>
        </div>
      `).join('');
    }
    searchResults.style.display = 'block';
  }
}

function selectSearchedWorker(dni, nombre) {
  const searchResults = document.getElementById('searchResults');
  if (searchResults) searchResults.style.display = 'none';
  showToast(`Trabajador seleccionado: ${nombre} (DNI: ${dni})`, 'info');
}

/* =========================================================
   SIDEBAR MÓVIL (DRAWER)
   ========================================================= */
function initMobileSidebar() {
  const mobileToggle = document.getElementById('mobileSidebarToggle');
  const sidebar = document.getElementById('appSidebar');
  const backdrop = document.getElementById('sidebarBackdrop');

  if (!mobileToggle || !sidebar) return;

  mobileToggle.addEventListener('click', () => {
    sidebar.classList.toggle('mobile-open');
    if (backdrop) backdrop.classList.toggle('active');
  });

  if (backdrop) {
    backdrop.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      backdrop.classList.remove('active');
    });
  }
}

/* =========================================================
   MODALES Y TOASTS REUTILIZABLES
   ========================================================= */
function initModalHelpers() {
  // Cerrar modales con botones de clase .modal-close o clic en el backdrop
  document.querySelectorAll('.modal-backdrop').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal(modal.id);
      }
    });

    const closeBtns = modal.querySelectorAll('.modal-close, [data-modal-close]');
    closeBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        closeModal(modal.id);
      });
    });
  });

  // Cerrar con Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const openModals = document.querySelectorAll('.modal-backdrop.open');
      openModals.forEach(m => closeModal(m.id));
    }
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }
}

function showToast(message, type = 'success') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  
  const icon = type === 'success' ? '✓' : type === 'warning' ? '⚠' : type === 'danger' ? '✕' : 'ℹ';
  toast.innerHTML = `
    <span style="font-weight: 700; font-size: 1.1rem;">${icon}</span>
    <div style="flex: 1;">${message}</div>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

/* =========================================================
   ESTADO DE CONEXIÓN A BASE DE DATOS (EN LÍNEA / OFFLINE)
   ========================================================= */
function initDbStatusWatcher() {
  const pill = document.getElementById('dbStatusPill');
  const dot = document.getElementById('dbStatusDot');
  const text = document.getElementById('dbStatusText');

  if (!pill || !dot || !text) return;

  async function checkStatus() {
    try {
      const res = await fetch('api/db_status.php', {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      updateDbStatus(Boolean(data.online));
    } catch (e) {
      updateDbStatus(false);
    }
  }

  function updateDbStatus(isOnline) {
    if (isOnline) {
      pill.classList.remove('offline');
      pill.classList.add('online');
      pill.title = 'Conexión activa con Microsoft SQL Server';
      dot.className = 'status-dot-active';
      text.textContent = 'En línea';
    } else {
      pill.classList.remove('online');
      pill.classList.add('offline');
      pill.title = 'Sin conexión con la base de datos (Modo contingencia)';
      dot.className = 'status-dot-offline';
      text.textContent = 'Offline';
    }
  }

  // Verificar periódicamente cada 20 segundos
  setInterval(checkStatus, 20000);

  // También verificar cuando la pestaña vuelve a tener foco
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      checkStatus();
    }
  });
}
