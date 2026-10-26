<?php
/**
 * Módulo: Control Diario de Asistencia y ¿Quién faltó hoy? (ETAPA 5)
 * Hospital Distrital de Pacasmayo
 */
$pageTitle = "Control de Asistencia - Hospital Pacasmayo";
$pageScript = "asistencia.js";
?>

<!-- Encabezado Oficial Institucional para Impresión (Solo visible al imprimir) -->
<div class="print-official-header">
  <div style="font-size: 10pt; color: #555;">GOBIERNO REGIONAL LA LIBERTAD &bull; RED DE SALUD PACASMAYO</div>
  <h1>HOSPITAL DISTRITAL DE PACASMAYO</h1>
  <h2 id="printHeaderFecha">PARTE DIARIO OFICIAL DE ASISTENCIA</h2>
  <div style="font-size: 8.5pt; color: #666; margin-top: 4px;">
    Generado por el Sistema de Gestión de Horarios y Recursos Humanos &bull; Reporte Biométrico y Evaluación Normativa
  </div>
</div>

<!-- Pestañas Principales del Módulo: Control Diario vs Importación Biométrica -->
<div class="asist-nav-tabs">
  <button type="button" class="asist-nav-tab active" id="tabNavDiario" onclick="switchAsistTab('diario')">
    <span>📋</span> Control Diario y Parte Oficial
  </button>
  <button type="button" class="asist-nav-tab" id="tabNavBiometrico" onclick="switchAsistTab('biometrico')">
    <span>📥</span> Importación Biométrica (BioStar 2)
  </button>
</div>

<!-- ========================================================================= -->
<!-- VISTA 1: CONTROL DIARIO Y PARTE OFICIAL (ASISTENCIA DIARIA)               -->
<!-- ========================================================================= -->
<div id="vistaControlDiario">
  <!-- Barra Superior Minimalista de Control de Fecha y Acciones -->
  <div class="asist-clean-toolbar">
    <div class="asist-toolbar-left">
      <div class="asist-title-block">
        <h1 class="asist-main-title">Control de Asistencia</h1>
        <p class="asist-sub-title">Monitoreo de marcaciones biométricas y regularización</p>
      </div>

      <!-- Navegación Rápida de Fecha -->
      <div class="date-navigator-clean">
        <button type="button" class="btn-date-step" id="btnFechaPrev" title="Día anterior">&#8249;</button>
        <button type="button" class="btn-date-tag active" id="btnFechaHoy">Hoy</button>
        <button type="button" class="btn-date-tag" id="btnFechaAyer">Ayer</button>
        <button type="button" class="btn-date-step" id="btnFechaNext" title="Día siguiente">&#8250;</button>
        <input type="date" id="asistFechaInput" value="<?php echo date('Y-m-d'); ?>" class="date-picker-clean">
      </div>
    </div>

    <div class="asist-toolbar-actions">
      <!-- Píldora de Ausencias Urgentes (Discreta y no invasiva) -->
      <button type="button" class="btn btn-outline-danger btn-sm" id="btnQuienFaltoHoy">
        <span>🚨</span> ¿Quién faltó hoy?
        <span class="badge badge-absent" id="badgeQuienFaltoCount" style="margin-left: 0.35rem; display: none;">0</span>
      </button>

      <button type="button" class="btn btn-primary btn-sm" id="btnNuevaRegularizacion">
        <span>+</span> Regularizar Marcación
      </button>

      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnReconciliarBiometrico" title="Reconciliar marcaciones del biométrico para este día">
        <span>🔄</span> Reconciliar
      </button>

      <button type="button" class="btn btn-secondary btn-sm" id="btnImprimirParte" title="Imprimir Parte Diario">
        <span>🖨</span> Imprimir
      </button>
    </div>
  </div>

  <!-- Barra de 4 Indicadores Esenciales Sobrios -->
  <div class="asist-kpi-clean-grid">
    <!-- 1. Programados -->
    <div class="asist-kpi-box active-filter" data-filtro="TODOS">
      <span class="asist-kpi-title">Programados</span>
      <div class="asist-kpi-num" id="kpiTotalProg">--</div>
      <span class="asist-kpi-sub">Total en rol</span>
    </div>

    <!-- 2. Presentes -->
    <div class="asist-kpi-box is-present" data-filtro="ASISTIO">
      <span class="asist-kpi-title">Puntuales</span>
      <div class="asist-kpi-num" id="kpiAsistPuntual">--</div>
      <span class="asist-kpi-sub">&le; 5 min tolerancia</span>
    </div>

    <!-- 3. Tardanzas -->
    <div class="asist-kpi-box is-late" data-filtro="TARDANZA">
      <span class="asist-kpi-title">Tardanzas</span>
      <div class="asist-kpi-num" id="kpiTardanzas">--</div>
      <span class="asist-kpi-sub">6 a 30 min retraso</span>
    </div>

    <!-- 4. Faltas / Ausentes -->
    <div class="asist-kpi-box is-absent" data-filtro="AUSENTES">
      <span class="asist-kpi-title">Inasistencias</span>
      <div class="asist-kpi-num" id="kpiFaltas">--</div>
      <span class="asist-kpi-sub">Sin marcación / &gt;30 min</span>
    </div>
  </div>

  <!-- Filtros Compactos y Buscador -->
  <div class="asist-filter-clean">
    <div class="filter-inputs-row">
      <div class="search-input-wrap">
        <span class="search-icon-muted">🔍</span>
        <input type="text" id="asistSearchInput" class="search-input-field" placeholder="Buscar trabajador por nombre, DNI o cargo...">
      </div>

      <select id="asistServicioSelect" class="filter-select-field">
        <option value="">Todos los Servicios</option>
        <option value="7">Emergencia y Cuidados Críticos</option>
        <option value="8">Unidad de Cuidados Intensivos (UCI)</option>
        <option value="9">Hospitalización Medicina</option>
        <option value="10">Consulta Externa</option>
        <option value="11">Pediatría y Neonatología</option>
        <option value="12">Centro Obstétrico</option>
        <option value="13">Centro Quirúrgico</option>
        <option value="14">Laboratorio Clínico</option>
        <option value="15">Farmacia y Dosis Unitaria</option>
        <option value="16">Unidad de Personal / RR.HH.</option>
        <option value="17">Unidad de Informática y Estadística</option>
      </select>

      <select id="asistEstadoSelect" class="filter-select-field">
        <option value="TODOS">Todos los Estados</option>
        <option value="ASISTIO">Asistió Puntual</option>
        <option value="TARDANZA">Tardanza</option>
        <option value="AUSENTES">Faltas / Ausentes</option>
        <option value="PERMISO">Permiso o Licencia</option>
        <option value="DESCANSO">Descanso Programado</option>
      </select>
    </div>

    <div class="asist-counter-label" id="asistRegistrosCount">
      Cargando registros...
    </div>
  </div>

  <!-- Tabla Sobria del Parte Diario Oficial -->
  <div class="asist-table-card">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th style="min-width: 220px;">Trabajador</th>
            <th style="min-width: 160px;">Servicio</th>
            <th style="min-width: 140px;">Turno Prog.</th>
            <th style="min-width: 120px;">Entrada</th>
            <th style="min-width: 120px;">Salida</th>
            <th style="min-width: 90px; text-align: center;">Tardanza</th>
            <th style="min-width: 130px;">Estado</th>
            <th style="min-width: 100px; text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody id="asistTableBody">
          <tr>
            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
              Cargando registros de asistencia del biométrico...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- VISTA 2: IMPORTACIÓN BIOMÉTRICA (BIOSTAR 2 - FASE 3)                       -->
<!-- ========================================================================= -->
<div id="vistaImportacionBiometrica" style="display: none;">
  <!-- Barra de Encabezado de Importación -->
  <div class="asist-clean-toolbar">
    <div class="asist-toolbar-left">
      <div class="asist-title-block">
        <h1 class="asist-main-title">Importación de Marcaciones BioStar 2</h1>
        <p class="asist-sub-title">Carga e ingesta masiva de reportes CSV exportados de los biométricos del hospital</p>
      </div>
    </div>
    <div class="asist-toolbar-actions">
      <button type="button" class="btn btn-secondary btn-sm" id="btnRecargarHistorial" onclick="cargarHistorialImportaciones()">
        <span>🔄</span> Actualizar Historial
      </button>
    </div>
  </div>

  <!-- Tarjeta de Carga de Archivo (Zona Drag & Drop) -->
  <div class="asist-table-card" style="padding: 1.5rem; margin-bottom: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">
        1. Selección del Reporte CSV de BioStar 2
      </h3>
      <span style="font-size: 0.8rem; color: var(--text-secondary);">
        Compatible con BioStation A2 &bull; Codificación UTF-8 con BOM
      </span>
    </div>

    <!-- Dropzone interactivo -->
    <div class="biostar-dropzone" id="biostarDropzone" onclick="document.getElementById('inputBiostarCsv').click()">
      <span class="biostar-drop-icon">📂</span>
      <div class="biostar-drop-title">Haga clic aquí o arrastre su archivo CSV de BioStar 2</div>
      <div class="biostar-drop-hint">Formatos soportados: <code>.csv</code> (separado por comas) con hasta 50,000 registros</div>
      <input type="file" id="inputBiostarCsv" accept=".csv" style="display: none;">
    </div>

    <!-- Barra de Archivo Seleccionado (Oculta por defecto) -->
    <div class="biostar-file-selected-bar" id="biostarFileSelectedBar" style="display: none;">
      <div class="biostar-file-info">
        <span style="font-size: 1.5rem;">📄</span>
        <div>
          <strong id="biostarSelectedFileName" style="color: var(--text-primary);">archivo.csv</strong>
          <div id="biostarSelectedFileSize" style="font-size: 0.78rem; color: var(--text-secondary);">0 KB</div>
        </div>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center;">
        <button type="button" class="btn btn-secondary btn-sm" id="btnLimpiarArchivo" onclick="limpiarArchivoCsv()">
          <span>✖</span> Quitar
        </button>
        <button type="button" class="btn btn-primary btn-sm" id="btnAnalizarCsv" onclick="ejecutarPrevisualizacion()">
          <span>🔍</span> Analizar y Previsualizar
        </button>
      </div>
    </div>

    <!-- Indicador de Carga / Spinner -->
    <div id="biostarLoadingSpinner" style="display: none; text-align: center; padding: 2rem;">
      <div class="spinner-border" style="width: 2.5rem; height: 2.5rem; border: 3px solid var(--border-color); border-top-color: var(--primary-accent, #0284c7); border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.75rem;"></div>
      <div id="biostarLoadingText" style="font-weight: 600; color: var(--text-primary);">Analizando estructura del archivo CSV...</div>
      <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">Procesando en modo streaming eficiente sin sobrecargar la memoria</div>
    </div>
  </div>

  <!-- Tarjeta de Previsualización y Métricas (Se muestra tras analizar el archivo) -->
  <div id="cardBiostarPreview" class="asist-table-card" style="display: none; padding: 1.5rem; margin-bottom: 1.25rem;">
    <!-- Alerta de Idempotencia / Estado del Archivo -->
    <div id="biostarIdempotenciaBanner" style="margin-bottom: 1.25rem; padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.88rem;"></div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">
        2. Resultados del Análisis y Previsualización
      </h3>
      <span class="badge badge-info" id="badgeRangoFechasPreview">Periodo detectado: --</span>
    </div>

    <!-- 5 Tarjetas KPI de Previsualización -->
    <div class="biostar-preview-grid">
      <div class="biostar-preview-card">
        <span class="biostar-card-lbl">Total Registros</span>
        <div class="biostar-card-val" id="prevKpiTotalFilas">0</div>
        <span class="biostar-card-sub">Filas leídas en CSV</span>
      </div>

      <div class="biostar-preview-card" style="border-left: 3px solid #22c55e;">
        <span class="biostar-card-lbl">Marcaciones Válidas</span>
        <div class="biostar-card-val" id="prevKpiValidas" style="color: #22c55e;">0</div>
        <span class="biostar-card-sub">Autenticación Huella 1:N</span>
      </div>

      <div class="biostar-preview-card" style="border-left: 3px solid #f59e0b;">
        <span class="biostar-card-lbl">Eventos Omitidos</span>
        <div class="biostar-card-val" id="prevKpiOmitidas" style="color: #f59e0b;">0</div>
        <span class="biostar-card-sub">Fallidos y de sistema</span>
      </div>

      <div class="biostar-preview-card" style="border-left: 3px solid #a855f7;">
        <span class="biostar-card-lbl">Marcaciones en Ráfaga</span>
        <div class="biostar-card-val" id="prevKpiConsecutivas" style="color: #a855f7;">0</div>
        <span class="biostar-card-sub">Intervalo &le; 60 seg</span>
      </div>

      <div class="biostar-preview-card" style="border-left: 3px solid #0284c7;">
        <span class="biostar-card-lbl">Personal Identificado</span>
        <div class="biostar-card-val" id="prevKpiUsuarios" style="color: #0284c7;">0</div>
        <span class="biostar-card-sub" id="prevKpiUsuariosSub">0 vinculados / 0 pendientes</span>
      </div>
    </div>

    <!-- Subpestañas de Muestra -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.4rem;">
      <button type="button" class="btn btn-secondary btn-sm active" id="btnSubTabMarcaciones" onclick="switchPreviewSubTab('marcaciones')">
        Muestra de Marcaciones Válidas (<span id="countMuestraMarcaciones">0</span>)
      </button>
      <button type="button" class="btn btn-secondary btn-sm" id="btnSubTabOmitidos" onclick="switchPreviewSubTab('omitidos')">
        Eventos Omitidos del Cálculo (<span id="countMuestraOmitidos">0</span>)
      </button>
      <button type="button" class="btn btn-secondary btn-sm" id="btnSubTabDispositivos" onclick="switchPreviewSubTab('dispositivos')">
        Dispositivos Detectados (<span id="countDispositivos">0</span>)
      </button>
    </div>

    <!-- Subsección 1: Muestra de Marcaciones -->
    <div id="subseccionMarcaciones" class="table-responsive" style="max-height: 380px; overflow-y: auto;">
      <table class="table" style="font-size: 0.85rem;">
        <thead>
          <tr>
            <th style="width: 50px;">Fila</th>
            <th>Fecha y Hora</th>
            <th>Dispositivo</th>
            <th>Usuario en BioStar</th>
            <th>DNI Identificado</th>
            <th>Estado Vinculación</th>
            <th>Consecutiva (&le;60s)</th>
          </tr>
        </thead>
        <tbody id="tbodyMuestraMarcaciones">
          <!-- Inyectado por JS -->
        </tbody>
      </table>
    </div>

    <!-- Subsección 2: Eventos Omitidos -->
    <div id="subseccionOmitidos" class="table-responsive" style="display: none; max-height: 380px; overflow-y: auto;">
      <table class="table" style="font-size: 0.85rem;">
        <thead>
          <tr>
            <th style="width: 50px;">Fila</th>
            <th>Fecha y Hora</th>
            <th>Dispositivo</th>
            <th>Evento Registrado</th>
            <th>Usuario / Detalle</th>
            <th>Motivo de Exclusión</th>
          </tr>
        </thead>
        <tbody id="tbodyMuestraOmitidos">
          <!-- Inyectado por JS -->
        </tbody>
      </table>
    </div>

    <!-- Subsección 3: Dispositivos -->
    <div id="subseccionDispositivos" style="display: none;">
      <div id="listaDispositivosDetectados" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <!-- Inyectado por JS -->
      </div>
    </div>

    <!-- Barra de Confirmación e Importación Definitiva -->
    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
      <div style="flex: 1; min-width: 280px;">
        <label for="biostarObservacionesInput" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 0.35rem;">
          Observaciones de la carga institucional (Opcional):
        </label>
        <input type="text" id="biostarObservacionesInput" class="input" placeholder="Ej: Reporte de marcaciones quincena 1 a 15 de Octubre 2026 - Control BioStar" style="font-size: 0.85rem;">
      </div>
      <div>
        <button type="button" class="btn btn-success" id="btnEjecutarImportacion" onclick="ejecutarImportacionDefinitiva()" style="font-weight: 700; padding: 0.65rem 1.4rem;">
          <span>💾</span> Confirmar e Importar a Base de Datos
        </button>
      </div>
    </div>
  </div>

  <!-- Tarjeta de Historial de Importaciones -->
  <div class="asist-table-card" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;">
        Historial de Importaciones Biométricas
      </h3>
      <span style="font-size: 0.8rem; color: var(--text-secondary);" id="labelTotalHistorial">
        Cargando historial...
      </span>
    </div>

    <div class="table-responsive">
      <table class="table" style="font-size: 0.85rem;">
        <thead>
          <tr>
            <th style="width: 50px;">ID</th>
            <th>Fecha de Carga</th>
            <th>Archivo</th>
            <th>Usuario</th>
            <th style="text-align: right;">Total Filas</th>
            <th style="text-align: right;">Válidas</th>
            <th style="text-align: right;">Duplicadas</th>
            <th style="text-align: right;">Consecutivas</th>
            <th style="text-align: right;">Omitidas</th>
            <th style="text-align: center;">Estado</th>
            <th style="text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbodyHistorialImportaciones">
          <tr>
            <td colspan="11" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
              Cargando historial de cargas desde SQL Server...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- ========================================================================= -->
<!-- MODAL 1: ¿QUIÉN FALTÓ HOY? - INASISTENCIAS Y REEMPLAZOS URGENTES          -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalAusenciasUrgentes">
  <div class="modal" style="max-width: 700px;">
    <div class="modal-header" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(245, 158, 11, 0.05) 100%);">
      <div class="modal-title" style="color: var(--status-absent-accent); display: flex; align-items: center; gap: 0.5rem;">
        <span>🚨</span> ¿Quién faltó hoy? - Inasistencias del Turno Actual
      </div>
      <button type="button" class="modal-close" onclick="cerrarModal('modalAusenciasUrgentes')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
          Personal programado sin marcación de entrada en el biométrico o con retraso mayor a 30 minutos:
        </span>
        <span class="badge badge-absent" id="modalAusentesTotal">0 ausentes</span>
      </div>

      <div id="listaAusentesUrgentes" style="max-height: 420px; overflow-y: auto;">
        <!-- Inyectado por JS -->
      </div>
    </div>
    <div class="modal-footer" style="display: flex; justify-content: space-between;">
      <span style="font-size: 0.78rem; color: var(--text-tertiary);">
        Priorice la cobertura en Emergencia y Centro Obstétrico llamando a los reemplazos de retén.
      </span>
      <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalAusenciasUrgentes')">
        Cerrar Ventana
      </button>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: REGULARIZAR / REGISTRAR MARCACIÓN MANUAL                         -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalRegularizarMarcacion">
  <div class="modal" style="max-width: 580px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>✎</span> Regularización Manual de Marcación
      </div>
      <button type="button" class="modal-close" onclick="cerrarModal('modalRegularizarMarcacion')">&times;</button>
    </div>
    <form id="formRegularizar">
      <div class="modal-body">
        <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
          Este formulario permite subsanar marcaciones de asistencia ante contingencias del reloj biométrico, comisiones de servicio o permisos de ley debidamente sustentados.
        </p>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="regPersonalId" style="font-size: 0.85rem; font-weight: 600;">Trabajador a Regularizar *</label>
          <select id="regPersonalId" class="input" required>
            <option value="">-- Seleccione Trabajador --</option>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div class="form-group">
            <label for="regFecha" style="font-size: 0.85rem; font-weight: 600;">Fecha *</label>
            <input type="date" id="regFecha" class="input" required>
          </div>
          <div class="form-group">
            <label for="regMotivo" style="font-size: 0.85rem; font-weight: 600;">Motivo de Regularización *</label>
            <select id="regMotivo" class="input" required>
              <option value="FALLA_BIOMETRICO">Falla Técnica del Reloj Biométrico</option>
              <option value="COMISION_SERVICIO">Comisión Oficial de Servicio</option>
              <option value="OMISION_INVOLUNTARIA">Omisión Involuntaria de Marcación</option>
              <option value="PAPELETA_AUTORIZADA">Papeleta de Permiso / Jefatura</option>
              <option value="COMPENSACION_HORAS">Compensación de Horas Autorizada</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div class="form-group">
            <label for="regHoraEntrada" style="font-size: 0.85rem; font-weight: 600;">Hora Entrada Real *</label>
            <input type="time" id="regHoraEntrada" class="input" required>
          </div>
          <div class="form-group">
            <label for="regHoraSalida" style="font-size: 0.85rem; font-weight: 600;">Hora Salida Real</label>
            <input type="time" id="regHoraSalida" class="input">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="regSustento" style="font-size: 0.85rem; font-weight: 600;">Documento de Sustento</label>
          <input type="text" id="regSustento" class="input" placeholder="Ej: Papeleta N° 084-2026 / Memo N° 012-DIR">
        </div>

        <div class="form-group">
          <label for="regObservacion" style="font-size: 0.85rem; font-weight: 600;">Observaciones Adicionales</label>
          <textarea id="regObservacion" class="input" rows="2" placeholder="Detalles de la regularización aprobada por RR.HH."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalRegularizarMarcacion')">
          Cancelar
        </button>
        <button type="submit" class="btn btn-primary">
          💾 Guardar y Regularizar
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: INCIDENCIAS TÉCNICAS DE LA IMPORTACIÓN BIOMÉTRICA                 -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalIncidenciasImportacion">
  <div class="modal" style="max-width: 780px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>⚠️</span> Incidencias y Anomalías de Importación #<span id="modalIncidenciasId">--</span>
      </div>
      <button type="button" class="modal-close" onclick="cerrarModal('modalIncidenciasImportacion')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1rem;">
        Eventos técnicos registrados durante el procesamiento (cambios de reloj en biométricos, lecturas fallidas o usuarios no estructurados):
      </p>

      <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table class="table" style="font-size: 0.84rem;">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Tipo Incidencia</th>
              <th>Nivel</th>
              <th>Detalle del Evento</th>
              <th>Fecha/Hora Evento</th>
            </tr>
          </thead>
          <tbody id="tbodyDetalleIncidencias">
            <tr>
              <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                Cargando incidencias...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalIncidenciasImportacion')">
        Cerrar
      </button>
    </div>
  </div>
</div>

