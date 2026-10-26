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
      <p class="dash-subtitle">Monitoreo de puntualidad y dotación de personal en tiempo real</p>
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

  <!-- Fila de 4 Indicadores Clave Sobrios -->
  <section class="dash-kpi-row" aria-label="Indicadores de asistencia del día">
    <!-- 1. Programados -->
    <div class="dash-kpi-card" data-filter="TODOS">
      <span class="dash-kpi-title">Programados Hoy</span>
      <div class="dash-kpi-number" id="dashKpiProgramados">--</div>
      <span class="dash-kpi-note">Total en rol oficial</span>
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
      <span class="dash-kpi-note">Dentro de tolerancia</span>
    </div>

    <!-- 4. Faltas -->
    <div class="dash-kpi-card highlight-danger" data-filter="FALTA">
      <span class="dash-kpi-title">Inasistencias</span>
      <div class="dash-kpi-number text-danger" id="dashKpiFaltas">--</div>
      <span class="dash-kpi-note">Sin marcación / falta</span>
    </div>
  </section>

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
            <th>Servicio / UPSS</th>
            <th>Turno Programado</th>
            <th style="width: 100px; text-align: center;">Ingreso</th>
            <th style="width: 100px; text-align: center;">Salida</th>
            <th style="width: 120px; text-align: center;">Estado</th>
          </tr>
        </thead>
        <tbody id="dashTableBody">
          <tr>
            <td colspan="7" class="dash-loading-cell">
              Cargando información de asistencia...
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pie de Tabla: Resumen Rápido -->
    <div class="dash-table-footer">
      <span id="dashFooterCount">Mostrando 0 registros</span>
      <span class="dash-footer-source">Datos oficiales de Microsoft SQL Server 2022</span>
    </div>
  </section>
</div>
