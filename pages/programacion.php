<?php
/**
 * Módulo: Programación Mensual
 * Sistema de Gestión de Horarios y Asistencia - Hospital Distrital de Pacasmayo
 * ETAPA 4
 * 
 * Matriz interactiva de turnos trabajador x días del mes
 * Validación de 150 horas asistenciales (SETI) y control por servicios
 */

$pageTitle = "Programación Mensual - Hospital Pacasmayo";
$pageScript = "programacion.js";
?>

<div class="programacion-container">
  <!-- Encabezado del Módulo y Acciones Principales -->
  <div class="prog-header">
    <div class="prog-title-area">
      <h1>
        <span>📅</span> Programación Mensual del Rol
        <span class="badge badge-neutral" id="progEstadoBadge" style="font-size: 0.72rem; vertical-align: middle;">BORRADOR</span>
      </h1>
      <p id="progPeriodoTitulo">Emergencia y Cuidados Críticos • Setiembre 2026</p>
    </div>

    <div class="prog-actions">
      <!-- Asignar / Editar Horario Semanal -->
      <button type="button" class="btn btn-outline btn-sm" id="btnAsignarHorarioProg" onclick="abrirModalAsignarHorarioProg()" title="Configurar horario semanal y pauta base (rrhh.Horario)">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        <span>Asignar / Editar Horario</span>
      </button>

      <!-- Validar Cumplimiento 150h -->
      <button type="button" class="btn btn-secondary btn-sm" id="btnValidar150h" title="Ejecutar motor de validación de 150 horas">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Validar 150 Horas</span>
      </button>

      <!-- Guardar Cambios -->
      <button type="button" class="btn btn-outline btn-sm" id="btnGuardarRol" title="Guardar cambios de la matriz en el servidor">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
        </svg>
        <span>Guardar Cambios</span>
      </button>

      <!-- Exportar a Excel (Formato MINSA) -->
      <button type="button" class="btn btn-outline btn-sm" id="btnExportarExcelMINSA" title="Descargar en formato Excel oficial de MINSA">
        <span>📥</span>
        <span>Excel MINSA</span>
      </button>

      <!-- Exportar Ficha a PDF -->
      <button type="button" class="btn btn-outline btn-sm" id="btnExportarPdfFichaProg" title="Exportar Ficha Oficial del Rol a PDF">
        <span>📄</span>
        <span>Ficha Rol PDF</span>
      </button>

      <!-- Enviar a RR.HH. -->
      <button type="button" class="btn btn-primary btn-sm" id="btnEnviarRol" title="Enviar rol mensual aprobado a la Unidad de Personal">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
        </svg>
        <span>Enviar a RR.HH.</span>
      </button>
    </div>
  </div>

  <!-- Métricas Rápidas de Cobertura y Cumplimiento -->
  <section class="mini-kpis-bar personal-kpis-bar" aria-label="Métricas del Rol Mensual">
    <!-- Total Personal en Servicio -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--primary-50); color: var(--primary-600);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiProgPersonal">--</span>
        <span class="mini-kpi-lbl">Personal en Rol</span>
      </div>
    </div>

    <!-- Total Horas del Rol -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-justified-bg); color: var(--status-justified-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="9" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiProgHoras">-- hrs</span>
        <span class="mini-kpi-lbl">Horas Programadas</span>
      </div>
    </div>

    <!-- Cumplen 150h -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-present-bg); color: var(--status-present-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiProgCumplen" style="color: var(--status-present-text);">--</span>
        <span class="mini-kpi-lbl">Cumplen 150h ✓</span>
      </div>
    </div>

    <!-- Casos Observados -->
    <div class="mini-kpi-card" id="kpiCardObservados">
      <div class="mini-kpi-icon" style="background-color: var(--status-late-bg); color: var(--status-late-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiProgObservados" style="color: var(--status-late-text);">--</span>
        <span class="mini-kpi-lbl">Observados (≠ 150h)</span>
      </div>
    </div>
  </section>

  <!-- Barra de Filtros y Periodo -->
  <div class="prog-filter-card">
    <div class="prog-filter-group">
      <!-- Selector de Servicio / UPSS -->
      <div class="prog-filter-item">
        <label for="progServiceSelect">Servicio / UPSS:</label>
        <select class="form-select" id="progServiceSelect">
          <option value="0" selected>Todos los Servicios (Plantel Completo - Médicos y Asistenciales)</option>
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
      </div>

      <!-- Selector de Mes -->
      <div class="prog-filter-item">
        <label for="progMonthSelect">Mes:</label>
        <select class="form-select" id="progMonthSelect">
          <option value="1">Enero</option>
          <option value="2">Febrero</option>
          <option value="3">Marzo</option>
          <option value="4">Abril</option>
          <option value="5">Mayo</option>
          <option value="6">Junio</option>
          <option value="7">Julio</option>
          <option value="8">Agosto</option>
          <option value="9" selected>Setiembre</option>
          <option value="10">Octubre</option>
          <option value="11">Noviembre</option>
          <option value="12">Diciembre</option>
        </select>
      </div>

      <!-- Selector de Año -->
      <div class="prog-filter-item">
        <label for="progYearSelect">Año:</label>
        <select class="form-select" id="progYearSelect" style="width: 85px;">
          <option value="2026" selected>2026</option>
          <option value="2025">2025</option>
        </select>
      </div>

      <!-- Filtro Condición -->
      <div class="prog-filter-item">
        <label for="progConditionSelect">Condición:</label>
        <select class="form-select" id="progConditionSelect">
          <option value="">Todas</option>
          <option value="NOMBRADO">Nombrado</option>
          <option value="CAS">CAS</option>
        </select>
      </div>
    </div>

    <!-- Leyenda Rápida y Atajos de Teclado -->
    <div style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.76rem; color: var(--text-secondary); flex-wrap: wrap;">
      <span style="font-weight: 800; color: var(--primary-700);">⚡ Edición Rápida:</span>
      <span style="display: flex; align-items: center; gap: 0.25rem;"><kbd style="background:var(--bg-surface-muted); padding:1px 4px; border-radius:3px; border:1px solid var(--border-default); font-weight:700;">1</kbd> ó <strong>M</strong> (6h)</span>
      <span style="display: flex; align-items: center; gap: 0.25rem;"><kbd style="background:var(--bg-surface-muted); padding:1px 4px; border-radius:3px; border:1px solid var(--border-default); font-weight:700;">2</kbd> ó <strong>T</strong> (6h)</span>
      <span style="display: flex; align-items: center; gap: 0.25rem;"><kbd style="background:var(--bg-surface-muted); padding:1px 4px; border-radius:3px; border:1px solid var(--border-default); font-weight:700;">3</kbd> ó <strong>N</strong> (12h)</span>
      <span style="display: flex; align-items: center; gap: 0.25rem;"><kbd style="background:var(--bg-surface-muted); padding:1px 4px; border-radius:3px; border:1px solid var(--border-default); font-weight:700;">0</kbd> ó <strong>L</strong> (Libre)</span>
      <span style="color: var(--text-tertiary);">| Clic en celda para paleta completa</span>
    </div>
  </div>

  <!-- Contenedor de la Matriz Mensual (Spreadsheet Grid) -->
  <div class="matrix-wrapper">
    <div class="matrix-scroll-area">
      <table class="matrix-table" id="monthlyMatrixTable" aria-label="Matriz Mensual de Turnos">
        <thead>
          <tr id="matrixHeaderRow">
            <!-- Inyectado dinámicamente por assets/js/pages/programacion.js -->
          </tr>
        </thead>
        <tbody id="matrixTableBody">
          <!-- Inyectado dinámicamente -->
        </tbody>
        <tfoot>
          <tr class="row-coverage" id="matrixCoverageRow">
            <!-- Cobertura diaria inyectada dinámicamente -->
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: ASIGNAR Y EDITAR TURNO / HORARIO DEL DÍA
     ========================================================= -->
<div class="modal-backdrop" id="modalPaletteTurnos">
  <div class="modal-dialog" style="max-width: 540px;">
    <div class="modal-header">
      <div class="modal-title" id="paletteModalTitle">
        <span>⏰</span> Asignar Turno Oficial
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    
    <!-- Pestañas del Modal -->
    <div style="display: flex; border-bottom: 1px solid var(--border-subtle); padding: 0 1rem; background: var(--bg-surface-muted);">
      <button type="button" class="prog-modal-tab active" id="tabBtnPaletteFast" onclick="switchPaletteTab('fast')" style="padding: 0.65rem 1rem; font-size: 0.8rem; font-weight: 700; border: none; background: transparent; cursor: pointer; color: var(--primary-600); border-bottom: 2px solid var(--primary-600);">
        ⚡ Catálogo Rápido
      </button>
      <button type="button" class="prog-modal-tab" id="tabBtnPaletteDetail" onclick="switchPaletteTab('detail')" style="padding: 0.65rem 1rem; font-size: 0.8rem; font-weight: 600; border: none; background: transparent; cursor: pointer; color: var(--text-secondary);">
        ⚙ Editar Parámetros y Emergencia
      </button>
    </div>

    <div class="modal-body" style="padding-top: 1rem;">
      <!-- PESTAÑA 1: PALETA RÁPIDA -->
      <div id="paletteTabFast">
        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
          Haga clic en un turno para asignarlo de inmediato a la celda seleccionada:
        </div>
        <div class="shift-quick-palette" id="quickShiftPaletteList">
          <!-- Inyectado dinámicamente -->
        </div>
      </div>

      <!-- PESTAÑA 2: EDICIÓN DETALLADA Y GUARDIAS DE EMERGENCIA EN VACACIONES -->
      <div id="paletteTabDetail" style="display: none;">
        <form id="formTurnoDetallado" onsubmit="event.preventDefault(); aplicarTurnoDetallado();">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="customTurnoCodigo" style="font-size: 0.76rem;">Código del Turno *</label>
              <select class="form-select form-select-sm" id="customTurnoCodigo" onchange="actualizarValoresTurnoDetallado()">
                <option value="1">1 - Mañana (6h)</option>
                <option value="2">2 - Tarde (6h)</option>
                <option value="3">3 - Noche / Guardia (12h)</option>
                <option value="1/2">1/2 - Doble Turno (12h)</option>
                <option value="2/3">2/3 - Tarde + Noche (18h)</option>
                <option value="G24">1/2/3 - Guardia 24 Horas</option>
                <option value="GD">GD - Guardia Diurna (12h)</option>
                <option value="GN">GN - Guardia Nocturna (12h)</option>
                <option value="ADM">ADM - Administrativo (8h)</option>
                <option value="VAC">VAC - Vacaciones Programadas</option>
                <option value="DESC">DESC - Descanso Médico / CITT</option>
                <option value="LIB">LIB - Libre / Descanso (0h)</option>
              </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="customTurnoHoras" style="font-size: 0.76rem;">Horas Computables *</label>
              <input type="number" step="0.5" class="form-control form-control-sm" id="customTurnoHoras" value="6.0" required>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="customTurnoInicio" style="font-size: 0.76rem;">Hora Inicio</label>
              <input type="time" class="form-control form-control-sm" id="customTurnoInicio" value="07:00">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="customTurnoFin" style="font-size: 0.76rem;">Hora Fin</label>
              <input type="time" class="form-control form-control-sm" id="customTurnoFin" value="13:00">
            </div>
          </div>

          <!-- Asignación por Emergencia durante Vacaciones -->
          <div style="background-color: rgba(225, 29, 72, 0.06); border: 1px solid rgba(225, 29, 72, 0.25); border-radius: var(--radius-md); padding: 0.75rem; margin-top: 0.75rem;">
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; margin-bottom: 0;">
              <input type="checkbox" id="checkEmergenciaVac" style="width: 17px; height: 17px; margin-top: 2px;">
              <div>
                <div style="font-weight: 700; font-size: 0.82rem; color: #e11d48;">
                  🚨 Cobertura de Emergencia durante Vacaciones
                </div>
                <div style="font-size: 0.74rem; color: var(--text-secondary); margin-top: 2px; line-height: 1.35;">
                  El trabajador se encuentra en periodo vacacional, pero asume este turno por necesidad imperiosa del servicio. <strong>El día trabajado se repondrá automáticamente a su saldo de vacaciones.</strong>
                </div>
              </div>
            </label>
          </div>

          <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="switchPaletteTab('fast')">Volver a Paleta</button>
            <button type="submit" class="btn btn-primary btn-sm">Aplicar Horario al Día</button>
          </div>
        </form>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: ASIGNAR Y EDITAR HORARIO SEMANAL DEL TRABAJADOR (rrhh.Horario)
     ========================================================= -->
<div class="modal-backdrop" id="modalEditarHorarioProg">
  <div class="modal-dialog" style="max-width: 680px;">
    <div class="modal-header">
      <div class="modal-title" id="modalEditarHorarioProgTitle">
        <span>🗓</span> Editar Horario y Pauta Semanal
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    <form id="formEditarHorarioProg">
      <input type="hidden" id="progEditPersonalId" value="0">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom: 0.85rem;">
          <label class="form-label" for="progEditWorkerSelect">Trabajador *</label>
          <select class="form-select" id="progEditWorkerSelect" required onchange="onWorkerChangeInScheduleModal()">
            <!-- Inyectado dinámicamente con los trabajadores del servicio -->
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group">
            <label class="form-label" for="progEditFechaInicio">Fecha de Vigencia *</label>
            <input type="date" class="form-control" id="progEditFechaInicio" value="2026-09-01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Régimen Laboral</label>
            <div id="progEditRegimenBadge" class="badge badge-asistencial" style="margin-top: 6px; display: inline-block;">
              ASISTENCIAL (Meta 150h)
            </div>
          </div>
        </div>

        <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-primary); margin: 0.75rem 0 0.4rem;">
          Definición del Turno por Día de la Semana (Lunes a Domingo):
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 0.75rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Lunes</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay1" data-day="1" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Martes</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay2" data-day="2" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Miércoles</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay3" data-day="3" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Jueves</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay4" data-day="4" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Viernes</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay5" data-day="5" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px; color: var(--primary-600); font-weight: 700;">Sábado</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay6" data-day="6" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px; color: var(--primary-600); font-weight: 700;">Domingo</label>
            <select class="form-select form-select-sm prog-day-select" id="progDay7" data-day="7" onchange="recalcularResumenModalHorario()"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
            <button type="button" class="btn btn-secondary btn-sm" id="btnProgPautaRapida" style="width: 100%; font-size: 0.72rem; padding: 0.35rem 0.2rem;" onclick="aplicarPlantillaRapida()">
              ⚡ Pauta L-V
            </button>
          </div>
        </div>

        <!-- Indicador de Carga Horaria Proyectada -->
        <div style="background: var(--bg-surface-muted); padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
          <div>
            <div style="font-size: 0.72rem; color: var(--text-tertiary);">Carga Semanal Proyectada</div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-primary);" id="progEditWeeklyHours">0.0 hrs / semana</div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.72rem; color: var(--text-tertiary);">Cómputo en el Mes Actual (30 días)</div>
            <div style="font-size: 1.1rem; font-weight: 800;" id="progEditMonthlyEstimate">0.0 hrs</div>
          </div>
        </div>

        <!-- Sección de Periodo Especial (Vacaciones / Licencias) -->
        <div style="border-top: 1px dashed var(--border-subtle); padding-top: 0.75rem; margin-top: 0.5rem;">
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.35rem;">
            <span>🏖</span> Asignar Rango de Vacaciones o Licencia en el Mes:
          </div>
          <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 0.75rem; color: var(--text-secondary);">Desde día:</span>
            <input type="number" id="progRangeStart" min="1" max="31" value="1" class="form-control form-control-sm" style="width: 60px;">
            <span style="font-size: 0.75rem; color: var(--text-secondary);">Hasta día:</span>
            <input type="number" id="progRangeEnd" min="1" max="31" value="15" class="form-control form-control-sm" style="width: 60px;">
            <select class="form-select form-select-sm" id="progRangeType" style="width: 140px;">
              <option value="VAC">Vacaciones (VAC)</option>
              <option value="DESC">CITT / Médico</option>
              <option value="LIB">Días Libres (LIB)</option>
            </select>
            <button type="button" class="btn btn-outline btn-sm" onclick="aplicarRangoEspecialTrabajador()" style="font-size: 0.75rem;">
              Aplicar Rango
            </button>
          </div>
        </div>
      </div>

      <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
        <div style="display: flex; gap: 0.5rem;">
          <button type="button" class="btn btn-outline" id="btnAplicarPautaAlMes" onclick="aplicarPautaAlMesActual()" title="Rellenar los 30 días del mes para este trabajador según la pauta">
            ⚡ Aplicar Pauta al Mes
          </button>
          <button type="submit" class="btn btn-primary" id="btnGuardarHorarioDB" title="Guardar pauta en rrhh.Horario de SQL Server">
            💾 Guardar en Base de Datos
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     MODAL: INFORME DE VALIDACIÓN DE 150 HORAS
     ========================================================= -->
<div class="modal-backdrop" id="modalValidacion150h">
  <div class="modal-dialog" style="max-width: 680px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>🔍</span> Dictamen de Validación Normativa de 150 Horas
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    <div class="modal-body">
      <!-- Alerta de Estado General -->
      <div id="valReportAlert" class="toast">
        <!-- Inyectado dinámicamente -->
      </div>

      <!-- Sección de Observados -->
      <div style="margin-top: 1.25rem;">
        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
          <span>⚠</span> Personal con Inconsistencia en la Carga Mensual
        </div>
        <div id="valReportObsList" style="display: flex; flex-direction: column; gap: 0.65rem;">
          <!-- Inyectado dinámicamente -->
        </div>
      </div>

      <!-- Sección de Conformes -->
      <div style="margin-top: 1.25rem;">
        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
          <span>✓</span> Personal Conforme (150 Horas Exactas)
        </div>
        <div id="valReportOkList" style="max-height: 180px; overflow-y: auto; background-color: var(--bg-surface-muted); padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
          <!-- Inyectado dinámicamente -->
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary modal-close">Cerrar</button>
      <button type="button" class="btn btn-primary" id="btnAprobarModal" onclick="closeModal('modalValidacion150h'); enviarRolRRHH();">
        Aprobar y Enviar a RR.HH.
      </button>
    </div>
  </div>
</div>
