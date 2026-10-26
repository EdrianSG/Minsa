<?php
/**
 * Módulo: Horarios y Turnos
 * Sistema de Gestión de Horarios y Asistencia - Hospital Distrital de Pacasmayo
 * ETAPA 3
 * 
 * Reutiliza y extiende:
 * rrhh.Turno (Catálogo parametrizable: 6h, 12h, cruce de medianoche, guardias)
 * rrhh.Horario (Asignación semanal de horario habitual por trabajador)
 */

$pageTitle = "Horarios y Turnos - Hospital Pacasmayo";
$pageScript = "horarios.js";
?>

<div class="horarios-container">
  <!-- Encabezado del Módulo y Acciones Principales -->
  <div class="horarios-header">
    <div class="horarios-title-area">
      <h1>
        <span>🕒</span> Catálogo Oficial de Turnos y Horarios
      </h1>
      <p>Parametrización de turnos (6h y 12h, guardias, medianoche) y administración de pautas semanales</p>
    </div>

    <div class="horarios-actions">
      <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalAsignarHorario()">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        <span>Asignar Horario Semanal</span>
      </button>

      <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalNuevoTurno()">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        <span>+ Nuevo Turno Oficial</span>
      </button>
    </div>
  </div>

  <!-- Barra de Métricas Rápidas del Catálogo de Turnos -->
  <section class="mini-kpis-bar personal-kpis-bar" aria-label="Métricas de Turnos">
    <!-- Total Turnos -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--primary-50); color: var(--primary-600);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="9" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiTotalTurnos">--</span>
        <span class="mini-kpi-lbl">Turnos Oficiales</span>
      </div>
    </div>

    <!-- Jornadas de 6 Horas -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-present-bg); color: var(--status-present-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiTurnos6h">--</span>
        <span class="mini-kpi-lbl">Jornadas 6 Horas</span>
      </div>
    </div>

    <!-- Guardias Hospitalarias (12h) -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--accent-50); color: var(--accent-600);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiGuardias12h">--</span>
        <span class="mini-kpi-lbl">Guardias 12 Horas</span>
      </div>
    </div>

    <!-- Cruce de Medianoche -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-justified-bg); color: var(--status-justified-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiCruzaMedianoche">--</span>
        <span class="mini-kpi-lbl">Cruzan Medianoche</span>
      </div>
    </div>
  </section>

  <!-- Pestañas de Selección -->
  <div class="horarios-tabs-nav">
    <button type="button" class="tab-btn active" data-tab="tabCatalog">
      <span>📑</span> Catálogo Oficial de Turnos
    </button>
    <button type="button" class="tab-btn" data-tab="tabWeekly">
      <span>🗓</span> Asignación de Horarios Semanales (L-D)
    </button>
  </div>

  <!-- CONTENIDO PESTAÑA 1: CATÁLOGO DE TURNOS -->
  <div class="tab-content active" id="tabCatalog">
    <div class="turnos-grid" id="turnosGrid">
      <div style="grid-column: 1 / -1; padding: 2rem; text-align: center; color: var(--text-tertiary);">
        Cargando catálogo oficial de turnos...
      </div>
    </div>
  </div>

  <!-- CONTENIDO PESTAÑA 2: HORARIOS SEMANALES (rrhh.Horario) -->
  <div class="tab-content" id="tabWeekly">
    <div class="card" style="margin-bottom: 1rem;">
      <div class="card-header" style="justify-content: space-between;">
        <div>
          <div class="card-title">
            <span>📅</span> Pautas Semanales Asignadas (Personal Administrativo y Patrones Base)
          </div>
          <div class="card-subtitle">
            Horario habitual de Lunes a Domingo registrado en <code>rrhh.Horario</code> con cómputo semanal
          </div>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalAsignarHorario()">
          + Asignar Pauta
        </button>
      </div>

      <div class="table-responsive" style="border: none; box-shadow: none;">
        <table class="weekly-table" aria-label="Tabla de horarios semanales">
          <thead>
            <tr>
              <th>Trabajador</th>
              <th>Servicio / Pauta</th>
              <th>Lunes</th>
              <th>Martes</th>
              <th>Miércoles</th>
              <th>Jueves</th>
              <th>Viernes</th>
              <th>Sábado</th>
              <th>Domingo</th>
              <th>Carga Semanal</th>
              <th style="text-align: center;">Acciones</th>
            </tr>
          </thead>
          <tbody id="weeklyTableBody">
            <tr>
              <td colspan="11" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                Cargando pautas semanales...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: REGISTRAR / EDITAR TURNO OFICIAL
     ========================================================= -->
<div class="modal-backdrop" id="modalTurno">
  <div class="modal-dialog" style="max-width: 600px;">
    <div class="modal-header">
      <div class="modal-title" id="modalTurnoTitle">
        <span>🕒</span> Registrar Nuevo Turno Oficial
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    <form id="formTurno">
      <input type="hidden" id="turnoId" value="0">
      
      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 2.5fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label" for="turnoCodigo">Código Corto *</label>
            <input type="text" class="form-control" id="turnoCodigo" placeholder="Ej: M, T, GD" maxlength="8" required style="text-transform: uppercase; font-family: var(--font-mono); font-weight: 700;">
          </div>
          <div class="form-group">
            <label class="form-label" for="turnoNombre">Nombre / Denominación *</label>
            <input type="text" class="form-control" id="turnoNombre" placeholder="Ej: Mañana Asistencial" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="turnoDescripcion">Descripción o Función Operativa</label>
          <input type="text" class="form-control" id="turnoDescripcion" placeholder="Ej: Turno diurno rotativo de 6 horas para personal médico">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label" for="turnoHoraInicio">Hora de Inicio *</label>
            <input type="time" class="form-control" id="turnoHoraInicio" value="08:00" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="turnoHoraFin">Hora de Fin *</label>
            <input type="time" class="form-control" id="turnoHoraFin" value="14:00" required>
          </div>
        </div>

        <!-- Vista previa y cálculo automático -->
        <div class="hours-calc-preview" id="turnoCalcPreview">
          <span>⏱ <strong>6.0 horas</strong> computables</span>
          <span>☀️ En el mismo día</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
          <div class="form-group">
            <label class="form-label" for="turnoHorasComputables">Horas Computables (Cálculo Oficial) *</label>
            <input type="number" step="0.5" class="form-control" id="turnoHorasComputables" value="6.00" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="turnoColor">Color Identificador en Grilla</label>
            <select class="form-select" id="turnoColor">
              <option value="#0284c7">Azul Zafiro (Mañana / General)</option>
              <option value="#d97706">Ámbar Dorado (Tarde)</option>
              <option value="#7c3aed">Púrpura Amatista (Noche)</option>
              <option value="#0d9488">Teal Quirúrgico (Guardia Diurna)</option>
              <option value="#4f46e5">Índigo Profundo (Guardia Nocturna)</option>
              <option value="#475569">Gris Pizarra (Administrativo)</option>
              <option value="#0891b2">Cian Glaciar (Vacaciones)</option>
            </select>
          </div>
        </div>

        <!-- Checkboxes de Reglas de Negocio -->
        <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.5rem; background-color: var(--bg-surface-muted); padding: 0.85rem; border-radius: var(--radius-md);">
          <label style="display: flex; align-items: center; gap: 0.65rem; font-size: 0.84rem; font-weight: 600; cursor: pointer;">
            <input type="checkbox" id="turnoCruzaMedianoche" style="width: 16px; height: 16px;">
            <span>🌙 Cruza Medianoche (El turno culmina al día calendario siguiente)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.65rem; font-size: 0.84rem; font-weight: 600; cursor: pointer;">
            <input type="checkbox" id="turnoEsGuardia" style="width: 16px; height: 16px;">
            <span>🛡 Es Guardia Hospitalaria (Jornada continuada de 12 horas asistenciales)</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar Turno en Catálogo</button>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     MODAL: ASIGNAR HORARIO SEMANAL (rrhh.Horario)
     ========================================================= -->
<div class="modal-backdrop" id="modalAsignarHorario">
  <div class="modal-dialog" style="max-width: 660px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>🗓</span> Asignar Pauta de Horario Semanal
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    <form id="formAsignarHorario">
      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 0.85rem; margin-bottom: 0.75rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="assignWorkerSelect">Trabajador *</label>
            <select class="form-select" id="assignWorkerSelect" required>
              <option value="">Seleccione personal...</option>
              <option value="10">47309642 - Carmen Rosa Urcia Ventura (Especialista Administrativo)</option>
              <option value="16">73291045 - Miguel Arturo Cortez García (Jefe de Informática)</option>
              <option value="5">45892104 - Ana María Torres Benites (Enfermera Asistencial)</option>
              <option value="6">70445708 - Luis Alberto Díaz Rojas (Médico Especialista)</option>
              <option value="17">19623584 - José Ramirez Lamela Esta (Médico Especialista)</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="assignFechaInicio">Fecha de Inicio *</label>
            <input type="date" class="form-control" id="assignFechaInicio" value="2026-09-01" required>
          </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.65rem 0 0.45rem;">
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary);">
            Turno por Día de la Semana (Lunes a Domingo):
          </div>
          <div style="display: flex; gap: 0.35rem;">
            <button type="button" class="btn btn-secondary btn-sm" id="btnCopiarLunes" style="padding: 0.2rem 0.55rem; font-size: 0.72rem; line-height: 1.2;" onclick="copiarLunesATodos()" title="Copia el turno del Lunes a Martes, Miércoles, Jueves y Viernes">
              ⚡ Copiar Lunes a V
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="btnLimpiarDescanso" style="padding: 0.2rem 0.55rem; font-size: 0.72rem; line-height: 1.2;" onclick="limpiarTodosADescanso()" title="Asigna Libre/Descanso a todos los días">
              🧹 Todos Libre
            </button>
          </div>
        </div>

        <div class="schedule-days-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 0.75rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Lunes</label>
            <select class="form-select form-select-sm" id="assignDia1"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Martes</label>
            <select class="form-select form-select-sm" id="assignDia2"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Miércoles</label>
            <select class="form-select form-select-sm" id="assignDia3"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Jueves</label>
            <select class="form-select form-select-sm" id="assignDia4"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px;">Viernes</label>
            <select class="form-select form-select-sm" id="assignDia5"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px; color: var(--primary-600); font-weight: 700;">Sábado</label>
            <select class="form-select form-select-sm" id="assignDia6"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.72rem; margin-bottom: 2px; color: var(--primary-600); font-weight: 700;">Domingo</label>
            <select class="form-select form-select-sm" id="assignDia7"></select>
          </div>
          <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
            <div style="font-size: 0.7rem; color: var(--text-tertiary); line-height: 1.2; padding: 0.2rem 0.35rem;">
              7 días configurados
            </div>
          </div>
        </div>

        <!-- =========================================================
             RESUMEN DE CARGA HORARIA (REGLAS DE NEGOCIO MINSA)
             ========================================================= -->
        <div id="scheduleWorkloadSummary" style="margin-top: 0.65rem; padding: 0.7rem 0.85rem; background: var(--bg-surface-muted, #1a1a1a); border: 1px solid var(--border-color, #333); border-radius: var(--radius-md, 8px);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">
              📊 Resumen de Carga Horaria
            </span>
            <span id="workloadRegimeBadge" class="badge badge-neutral" style="font-size: 0.72rem;">
              Tipo: Personal Asistencial
            </span>
          </div>

          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.6rem;">
            <div style="background: var(--bg-surface, #242424); padding: 0.45rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-subtle, #383838);">
              <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Carga Semanal</div>
              <div id="workloadWeeklyText" style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary);">0 h</div>
              <div id="workloadWeeklyLimit" style="font-size: 0.65rem; color: var(--text-secondary);">Máx: 48h</div>
            </div>

            <div style="background: var(--bg-surface, #242424); padding: 0.45rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-subtle, #383838);">
              <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Mes Proyectado</div>
              <div id="workloadMonthlyText" style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary);">0 h</div>
              <div id="workloadMonthlyPeriod" style="font-size: 0.65rem; color: var(--text-secondary);">Desde vigencia</div>
            </div>

            <div style="background: var(--bg-surface, #242424); padding: 0.45rem 0.6rem; border-radius: 6px; border: 1px solid var(--border-subtle, #383838);">
              <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Meta / Pendiente</div>
              <div id="workloadTargetText" style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary);">Obj: 150h</div>
              <div id="workloadPendingText" style="font-size: 0.65rem; color: var(--text-secondary);">Pend: 150h</div>
            </div>
          </div>

          <!-- Estado de Validación -->
          <div id="workloadStatusBox" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.6rem; border-radius: 6px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #4ade80; font-size: 0.76rem; font-weight: 600;">
            <span id="workloadStatusIcon">✓</span>
            <span id="workloadStatusMessage">Programación dentro de los límites válidos</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
        <button type="submit" id="btnSubmitAsignarHorario" class="btn btn-primary">Asignar Pauta Semanal</button>
      </div>
    </form>
  </div>
</div>
