<?php
/**
 * Pantalla: Inicio / Dashboard
 * Hospital Distrital de Pacasmayo
 * 
 * Diseño Minimalista y Sobrio - Control de Asistencia Diaria
 */
$pageTitle = "Inicio - Hospital Distrital de Pacasmayo";
$pageScript = "dashboard.js";
?>

<div class="dash-clean-container">
  <!-- Encabezado Limpio del Dashboard -->
  <div class="dash-clean-header">
    <div class="dash-header-titles">
      <h1 class="dash-main-title">Resumen de Asistencia Diaria</h1>
      <p class="dash-subtitle">Programación, marcaciones conciliadas y pendientes de revisión</p>
    </div>

    <!-- Selector de Fecha Directo y Sencillo -->
    <div class="dash-date-controls">
      <button type="button" class="dash-btn-quick" id="btnDateAyer">Ayer</button>
      <button type="button" class="dash-btn-quick active" id="btnDateHoy">Hoy</button>
      <div class="dash-date-input-box">
        <input type="date" id="dashDatePicker" value="<?php echo date('Y-m-d'); ?>" class="dash-date-input">
      </div>
    </div>
  </div>

  <div class="dash-scope-controls">
    <label>Área<select id="dashAreaSelect" class="form-select"><?php require_once __DIR__.'/../includes/OrganizationCatalog.php'; echo OrganizationCatalog::areaOptions(); ?></select></label>
    <label>Servicio<select id="dashServiceSelect" class="form-select"><?php echo OrganizationCatalog::options(''); ?></select></label>
    <button type="button" class="btn btn-secondary" id="dashRefresh">Actualizar</button>
  </div>

  <!-- Fila de 4 Indicadores Clave Sobrios -->
  <section class="dash-kpi-row" aria-label="Indicadores de asistencia del día">
    <!-- 1. Programados -->
    <div class="dash-kpi-card" data-filter="PROGRAMADOS">
      <span class="dash-kpi-title">Con turno confirmado</span>
      <div class="dash-kpi-number" id="dashKpiProgramados">--</div>
      <span class="dash-kpi-note">Rol mensual o pauta semanal</span>
    </div>

    <!-- 2. Presentes -->
    <div class="dash-kpi-card highlight-success" data-filter="ASISTIO">
      <span class="dash-kpi-title">Presentes</span>
      <div class="dash-kpi-number text-success" id="dashKpiPresentes">--</div>
      <span class="dash-kpi-note" id="dashKpiPresentesPct">Asistencia puntual</span>
    </div>

    <!-- 3. Tardanzas -->
    <div class="dash-kpi-card highlight-warning" data-filter="TARDANZA">
      <span class="dash-kpi-title">Tardanzas</span>
      <div class="dash-kpi-number text-warning" id="dashKpiTardanzas">--</div>
      <span class="dash-kpi-note">Ingresos con tardanza</span>
    </div>

    <!-- 4. Faltas -->
    <div class="dash-kpi-card highlight-danger" data-filter="FALTA">
      <span class="dash-kpi-title">Inasistencias</span>
      <div class="dash-kpi-number text-danger" id="dashKpiFaltas">--</div>
      <span class="dash-kpi-note">Faltas registradas o evaluadas</span>
    </div>
  </section>

  <section class="dash-operation-grid" aria-label="Seguimiento operativo">
    <div class="dash-operation-card">
      <h2>Programación y verificación</h2>
      <div class="dash-action-counts">
        <button type="button" class="dash-kpi-card" data-filter="SIN_MARCACION">Sin marcación conciliada <strong id="dashPendingMarks">—</strong></button>
        <button type="button" class="dash-kpi-card" data-filter="SIN_PROGRAMACION">Sin turno confirmado <strong id="dashUnscheduled">—</strong></button>
        <button type="button" class="dash-kpi-card" data-filter="DESCANSO">Descanso o ausencia programada <strong id="dashRest">—</strong></button>
      </div>
      <p id="dashPendingDetail">Pendiente de consulta</p>
    </div>
    <div class="dash-operation-card">
      <h2>Última carga biométrica</h2>
      <div id="dashBiometric" aria-live="polite"><div class="skeleton dash-skeleton-line" aria-hidden="true"></div></div>
    </div>
    <div class="dash-operation-card">
      <h2>Calidad del padrón</h2>
      <div id="dashDataQuality"><div class="skeleton dash-skeleton-line" aria-hidden="true"></div></div>
      <?php if (Auth::checkPermission('personal')): ?><a href="index.php?page=personal">Revisar personal</a><?php endif; ?>
    </div>
    <div class="dash-operation-card">
      <h2>Pendientes de RR. HH.</h2>
      <div id="dashHrPending"><div class="skeleton dash-skeleton-line" aria-hidden="true"></div></div>
      <?php if (Auth::checkPermission('ausencias')): ?><a href="index.php?page=ausencias">Revisar solicitudes</a><?php endif; ?>
    </div>
  </section>
  <details class="dash-operation-card dash-coverage" open>
    <summary>Cobertura programada por área, servicio y turno</summary>
    <div class="dash-table-responsive"><table class="dash-clean-table">
      <thead><tr><th>Área</th><th>Servicio</th><th>Turno</th><th>Programados</th><th>Sin marcación conciliada</th></tr></thead>
      <tbody id="dashCoverageBody"><tr><td colspan="5"><div class="skeleton dash-skeleton-line" aria-hidden="true"></div></td></tr></tbody>
    </table></div>
  </details>

  <!-- Tarjeta Principal: Listado Diario Limpio -->
  <section class="dash-table-card">
    <!-- Barra de Filtros y Búsqueda -->
    <div class="dash-table-toolbar">
      <div class="dash-search-box">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="search-svg">
          <circle cx="11" cy="11" r="8" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
        </svg>
        <input 
          type="text" 
          id="dashSearchInput" 
          placeholder="Buscar servidor por nombre, DNI o servicio..." 
          autocomplete="off"
        >
      </div>

      <label class="dash-worker-filter" for="dashWorkerSelect">Trabajador
        <select id="dashWorkerSelect" class="form-select"><option value="">Todo el personal</option></select>
      </label>

      <div class="dash-filter-pills" id="dashFilterPills">
        <button type="button" class="dash-pill-btn active" data-estado="TODOS">
          Todos <span class="pill-count" id="countPillTodos">0</span>
        </button>
        <button type="button" class="dash-pill-btn" data-estado="ASISTIO">
          Presentes <span class="pill-count" id="countPillPresentes">0</span>
        </button>
        <button type="button" class="dash-pill-btn" data-estado="TARDANZA">
          Tardanzas <span class="pill-count" id="countPillTardanzas">0</span>
        </button>
        <button type="button" class="dash-pill-btn" data-estado="FALTA">
          Inasistencias <span class="pill-count" id="countPillFaltas">0</span>
        </button>
      </div>
    </div>

    <!-- Tabla Sobria de Asistencia -->
    <div class="dash-table-responsive">
      <table class="dash-clean-table">
        <thead>
          <tr>
            <th style="width: 100px;">DNI</th>
            <th>Servidor Público</th>
            <th>Área / Servicio</th>
            <th>Turno Programado</th>
            <th style="width: 100px; text-align: center;">Ingreso</th>
            <th style="width: 100px; text-align: center;">Salida</th>
            <th style="width: 120px; text-align: center;">Estado</th>
          </tr>
        </thead>
        <tbody id="dashTableBody" aria-busy="true">
          <?php for ($row=0; $row<5; $row++): ?>
            <tr class="skeleton-table-row"><?php for ($column=0; $column<7; $column++): ?><td><div class="skeleton dash-skeleton-line" aria-hidden="true"></div></td><?php endfor; ?></tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>

    <!-- Pie de Tabla: Resumen Rápido -->
    <div class="dash-table-footer">
      <span id="dashFooterCount">Mostrando 0 registros</span>
      <div class="dash-pagination" aria-label="Páginas del personal">
        <button type="button" class="dash-btn-quick" id="dashPrevPage" disabled>Anterior</button>
        <span id="dashPageLabel">Página 1 de 1</span>
        <button type="button" class="dash-btn-quick" id="dashNextPage" disabled>Siguiente</button>
      </div>
      <div class="dash-source-detail">
        <span class="dash-footer-source" id="dashDataSource">Consulta en curso</span>
        <small id="dashConnectionStatus" role="status" aria-live="polite">Consultando datos…</small>
      </div>
    </div>
  </section>
</div>
