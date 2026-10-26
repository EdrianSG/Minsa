<?php
/**
 * Módulo: Ausencias y Justificaciones (ETAPA 6)
 * Hospital Distrital de Pacasmayo
 */
$pageTitle = "Ausencias y Justificaciones - Hospital Pacasmayo";
$pageScript = "ausencias.js";
?>

<!-- Encabezado Oficial Institucional para Impresión -->
<div class="print-official-header">
  <div style="font-size: 10pt; color: #555;">GOBIERNO REGIONAL LA LIBERTAD &bull; RED DE SALUD PACASMAYO</div>
  <h1>HOSPITAL DISTRITAL DE PACASMAYO</h1>
  <h2>REGISTRO OFICIAL DE LICENCIAS, DESCANSOS MÉDICOS (CITT) Y JUSTIFICACIONES</h2>
  <div style="font-size: 8.5pt; color: #666; margin-top: 4px;">
    Unidad de Recursos Humanos &bull; Control y Evaluación Normativa de Ausencias Laborales
  </div>
</div>

<!-- Barra Superior Minimalista de Herramientas y Acciones -->
<div class="aus-clean-toolbar">
  <div class="aus-toolbar-left">
    <div class="aus-title-block">
      <h1 class="aus-main-title">Ausencias y Justificaciones</h1>
      <p class="aus-sub-title">Gestión de papeletas, descansos médicos (CITT) y licencias</p>
    </div>
  </div>

  <div class="aus-toolbar-actions">
    <button type="button" class="btn btn-secondary btn-sm" id="btnVerCatalogo">
      <span>📋</span> Catálogo de Permisos
    </button>
    <button type="button" class="btn btn-primary btn-sm" id="btnNuevaPapeleta">
      <span>+</span> Nueva Papeleta / Justificación
    </button>
    <button type="button" class="btn btn-secondary btn-sm" id="btnImprimirAusencias" title="Imprimir Reporte">
      <span>🖨</span> Imprimir
    </button>
  </div>
</div>

<!-- 4 Métricas Esenciales Sobrias -->
<div class="aus-kpi-clean-grid">
  <!-- 1. Total Papeletas -->
  <div class="aus-kpi-box active-filter" data-filtro="TODOS">
    <span class="aus-kpi-title">Total Papeletas</span>
    <div class="aus-kpi-num" id="kpiTotalPermisos">--</div>
    <span class="aus-kpi-sub">Periodo activo</span>
  </div>

  <!-- 2. Pendientes RR.HH. -->
  <div class="aus-kpi-box is-pending" data-filtro="PENDIENTE">
    <span class="aus-kpi-title">Pendientes</span>
    <div class="aus-kpi-num" id="kpiPendientes">--</div>
    <span class="aus-kpi-sub">Por dictaminar</span>
  </div>

  <!-- 3. Aprobadas -->
  <div class="aus-kpi-box is-approved" data-filtro="APROBADO">
    <span class="aus-kpi-title">Aprobadas</span>
    <div class="aus-kpi-num" id="kpiAprobadas">--</div>
    <span class="aus-kpi-sub">Conforme a norma</span>
  </div>

  <!-- 4. Descansos Médicos CITT -->
  <div class="aus-kpi-box is-citt" data-filtro="TODOS">
    <span class="aus-kpi-title">Médicos / CITT</span>
    <div class="aus-kpi-num" id="kpiCittActivos">--</div>
    <span class="aus-kpi-sub">Certificados EsSalud</span>
  </div>
</div>

<!-- Filtros Compactos y Buscador -->
<div class="aus-filter-clean">
  <div class="filter-inputs-row">
    <div class="search-input-wrap">
      <span class="search-icon-muted">🔍</span>
      <input type="text" id="ausSearchInput" class="search-input-field" placeholder="Buscar trabajador por nombre, DNI, CITT...">
    </div>

    <select id="ausServicioSelect" class="filter-select-field">
      <option value="">Todos los Servicios</option>
      <option value="1">Emergencia y Cuidados Críticos</option>
      <option value="2">Hospitalización Medicina y Cirugía</option>
      <option value="3">Gineco-Obstetricia y Centro Obstétrico</option>
      <option value="4">Pediatría y Neonatología</option>
      <option value="5">Cirugía y Centro Quirúrgico</option>
      <option value="6">Consulta Externa y Especialidades</option>
      <option value="7">Farmacia Hospitalaria</option>
      <option value="8">Laboratorio Clínico y Banco de Sangre</option>
      <option value="9">Dirección y Recursos Humanos</option>
    </select>

    <select id="ausTipoSelect" class="filter-select-field">
      <option value="">Todos los Tipos de Permiso</option>
    </select>

    <select id="ausEstadoSelect" class="filter-select-field">
      <option value="TODOS">Todos los Estados</option>
      <option value="PENDIENTE">Pendientes</option>
      <option value="APROBADO">Aprobados</option>
      <option value="RECHAZADO">Rechazados</option>
    </select>
  </div>

  <div class="aus-counter-label" id="ausRegistrosCount">
    Cargando solicitudes...
  </div>
</div>

<!-- Tabla Sobria de Papeletas y Justificaciones -->
<div class="aus-table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th style="min-width: 220px;">Trabajador</th>
          <th style="min-width: 160px;">Servicio</th>
          <th style="min-width: 170px;">Tipo Permiso</th>
          <th style="min-width: 150px;">Periodo</th>
          <th style="min-width: 180px;">Sustento / CITT</th>
          <th style="min-width: 120px;">Estado</th>
          <th style="min-width: 100px; text-align: right;">Acciones</th>
        </tr>
      </thead>
      <tbody id="ausTableBody">
        <tr>
          <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
            Cargando registros de justificaciones...
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: REGISTRAR NUEVA PAPELETA / JUSTIFICACIÓN                         -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalNuevaPapeleta">
  <div class="modal" style="max-width: 620px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>📄</span> Registrar Nueva Papeleta o Justificación
      </div>
      <button type="button" class="modal-close" onclick="cerrarModal('modalNuevaPapeleta')">&times;</button>
    </div>
    <form id="formNuevaPapeleta">
      <div class="modal-body">
        <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
          Ingrese los datos de la papeleta. Al ser aprobada por RR.HH., el sistema regularizará automáticamente las inasistencias en el control de asistencia.
        </p>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="solPersonalId" style="font-size: 0.85rem; font-weight: 600;">Trabajador *</label>
          <select id="solPersonalId" class="input" required>
            <option value="">-- Seleccione Trabajador --</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="solTipoPermiso" style="font-size: 0.85rem; font-weight: 600;">Tipo de Permiso / Licencia *</label>
          <select id="solTipoPermiso" class="input" required>
            <option value="">-- Seleccione Tipo de Permiso --</option>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
          <div class="form-group">
            <label for="solFechaInicio" style="font-size: 0.85rem; font-weight: 600;">Fecha de Inicio *</label>
            <input type="date" id="solFechaInicio" class="input" required>
          </div>
          <div class="form-group">
            <label for="solFechaFin" style="font-size: 0.85rem; font-weight: 600;">Fecha de Fin *</label>
            <input type="date" id="solFechaFin" class="input" required>
          </div>
        </div>

        <!-- Calculadora de días -->
        <div class="duration-calculator-box">
          <span style="font-size: 0.84rem; color: var(--text-secondary);" id="calcDiasResultado">
            Duración calculada: <strong>1 día(s)</strong>
          </span>
          <span class="badge badge-neutral" style="font-size: 0.72rem;">Cálculo Normativo MINSA</span>
        </div>

        <div class="form-group" style="margin-bottom: 1rem;" id="groupDocSustento">
          <label for="solDocumentoSustento" style="font-size: 0.85rem; font-weight: 600;" id="labelDocSustento">
            Documento de Sustento
          </label>
          <input type="text" id="solDocumentoSustento" class="input" placeholder="Ej: Papeleta N° 045 / Memo N° 012-DIR">
        </div>

        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="solMotivo" style="font-size: 0.85rem; font-weight: 600;">Motivo Detallado de la Ausencia</label>
          <textarea id="solMotivo" class="input" rows="2" placeholder="Describa el motivo de la justificación o comisión..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevaPapeleta')">
          Cancelar
        </button>
        <button type="submit" class="btn btn-primary" id="btnGuardarPapeleta">
          💾 Registrar Solicitud
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: DICTAMEN DE RECURSOS HUMANOS                                     -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalDictamenRRHH">
  <div class="modal" style="max-width: 540px;">
    <div class="modal-header" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(16, 185, 129, 0.05) 100%);">
      <div class="modal-title">
        <span>⚖</span> Dictamen de Recursos Humanos
      </div>
      <button type="button" class="modal-close" onclick="cerrarModal('modalDictamenRRHH')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1rem;">
        Como Jefatura de Recursos Humanos, evalúe la solicitud y emita su resolución. La aprobación sincronizará el parte de asistencia convirtiendo la inasistencia en justificada.
      </p>

      <div id="dictamenTrabajadorInfo">
        <!-- Inyectado por JS -->
      </div>

      <div class="form-group" style="margin-bottom: 1rem;">
        <label for="dictamenObservacion" style="font-size: 0.85rem; font-weight: 600;">Observaciones / Dictamen de Jefatura</label>
        <textarea id="dictamenObservacion" class="input" rows="2" placeholder="Ingrese el fundamento de la aprobación o motivo de rechazo..."></textarea>
      </div>
    </div>
    <div class="modal-footer" style="display: flex; justify-content: space-between;">
      <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalDictamenRRHH')">
        Cancelar
      </button>
      <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-danger" onclick="ejecutarDictamen('RECHAZAR')">
          ✖ Rechazar Solicitud
        </button>
        <button type="button" class="btn btn-primary" onclick="ejecutarDictamen('APROBAR')">
          ✓ Aprobar y Sincronizar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: CATÁLOGO OFICIAL DE PERMISOS (rrhh.TipoPermisoLaboral)           -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalCatalogoPermisos">
  <div class="modal" style="max-width: 680px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>📋</span> Catálogo Oficial de Tipos de Permiso (MINSA)
      </div>
      <button type="button" class="modal-close" onclick="cerrarModal('modalCatalogoPermisos')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1rem;">
        Tipos de permisos parametrizados en la tabla <code>rrhh.TipoPermisoLaboral</code> según el marco normativo del Hospital Distrital de Pacasmayo:
      </p>
      <div id="catalogoPermisosLista" style="max-height: 400px; overflow-y: auto;">
        <!-- Inyectado por JS -->
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalCatalogoPermisos')">
        Cerrar Catálogo
      </button>
    </div>
  </div>
</div>
