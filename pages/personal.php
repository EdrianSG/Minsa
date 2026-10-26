<?php
/**
 * Módulo: Personal
 * Sistema de Gestión de Horarios y Asistencia - Hospital Distrital de Pacasmayo
 * ETAPA 2
 * 
 * Reutiliza las entidades institucionales:
 * core.Persona, rrhh.Personal, rrhh.AsignacionPersonal, rrhh.Cargo,
 * rrhh.Profesion, organizacion.Area, organizacion.Servicio, rrhh.Contrato
 */

$pageTitle = "Personal - Hospital Pacasmayo";
$pageScript = "personal.js";
?>

<div class="personal-container">
  <!-- Encabezado del Módulo -->
  <div class="personal-header">
    <div class="personal-title-area">
      <h1>
        <span>👥</span> Directorio y Maestro de Personal
      </h1>
      <p>Gestión centralizada del personal asistencial y administrativo del Hospital Distrital de Pacasmayo</p>
    </div>

    <div style="display: flex; gap: 0.65rem; align-items: center;">
      <button type="button" class="btn btn-primary" id="btnOpenNewWorkerModal">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
        </svg>
        <span>Nuevo Trabajador</span>
      </button>

      <a href="index.php?page=dashboard" class="btn btn-secondary btn-sm">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>Volver a Inicio</span>
      </a>
    </div>
  </div>

  <!-- Barra de Métricas Rápidas (Dotación Oficial) -->
  <section class="personal-kpis-bar" aria-label="Métricas de Dotación">
    <!-- Total -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--primary-50); color: var(--primary-600);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="miniKpiTotal">--</span>
        <span class="mini-kpi-lbl">Total Personal</span>
      </div>
    </div>

    <!-- Asistenciales (150h) -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-justified-bg); color: var(--status-justified-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="miniKpiAsist">--</span>
        <span class="mini-kpi-lbl">Asistencial (SETI)</span>
      </div>
    </div>

    <!-- Administrativos -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--accent-50); color: var(--accent-600);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="miniKpiAdmin">--</span>
        <span class="mini-kpi-lbl">Administrativo</span>
      </div>
    </div>

    <!-- Nombrados -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--primary-50); color: var(--primary-700);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="miniKpiNombrados">--</span>
        <span class="mini-kpi-lbl">Nombrados</span>
      </div>
    </div>

    <!-- CAS -->
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--accent-50); color: var(--accent-700);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="miniKpiCas">--</span>
        <span class="mini-kpi-lbl">Contratados CAS</span>
      </div>
    </div>
  </section>

  <!-- Barra de Búsqueda y Filtros de Personal -->
  <div class="personal-filter-card">
    <div class="filter-grid">
      <!-- Búsqueda en Vivo -->
      <div class="filter-item">
        <label for="staffSearchInput">Búsqueda rápida</label>
        <div style="position: relative;">
          <input type="text" class="form-control" id="staffSearchInput" placeholder="Buscar por apellido, nombre, DNI o código..." autocomplete="off">
        </div>
      </div>

      <!-- Filtro por Servicio -->
      <div class="filter-item">
        <label for="staffServiceFilter">Servicio Hospitalario</label>
        <select class="form-select" id="staffServiceFilter">
          <option value="">Todos los servicios</option>
          <option value="Emergencia">Emergencia y Cuidados Críticos</option>
          <option value="Cuidados Intensivos">Unidad de Cuidados Intensivos (UCI)</option>
          <option value="Hospitalización">Hospitalización Medicina</option>
          <option value="Obstétrico">Centro Obstétrico</option>
          <option value="Quirúrgico">Centro Quirúrgico</option>
          <option value="Pediatría">Pediatría y Neonatología</option>
          <option value="Laboratorio">Laboratorio Clínico</option>
          <option value="Farmacia">Farmacia y Dosis Unitaria</option>
          <option value="Personal">Unidad de Personal / RR.HH.</option>
          <option value="Admisión">Admisión y Archivo</option>
        </select>
      </div>

      <!-- Filtro por Condición Laboral -->
      <div class="filter-item">
        <label for="staffConditionFilter">Condición Laboral</label>
        <select class="form-select" id="staffConditionFilter">
          <option value="">Todas las condiciones</option>
          <option value="NOMBRADO">Nombrado</option>
          <option value="CAS">CAS (D.L. 1057)</option>
          <option value="DESTACADO">Destacado</option>
        </select>
      </div>

      <!-- Filtro por Régimen / SETI -->
      <div class="filter-item">
        <label for="staffRegimenFilter">Régimen</label>
        <select class="form-select" id="staffRegimenFilter">
          <option value="">Todos los regímenes</option>
          <option value="ASISTENCIAL">Asistencial (150h / SETI)</option>
          <option value="ADMINISTRATIVO">Administrativo</option>
        </select>
      </div>

      <!-- Botón Limpiar Filtros -->
      <div class="filter-item" style="justify-content: flex-end;">
        <button type="button" class="btn btn-secondary" id="staffResetFiltersBtn" title="Restablecer filtros">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>Limpiar</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Tabla de Resultados de Personal -->
  <div class="table-responsive">
    <table class="staff-table" aria-label="Directorio de Personal">
      <thead>
        <tr>
          <th>Trabajador</th>
          <th>Cargo / Profesión</th>
          <th>Servicio y Área</th>
          <th>Condición</th>
          <th>Régimen / SETI</th>
          <th>Estado</th>
          <th style="text-align: right;">Acciones</th>
        </tr>
      </thead>
      <tbody id="staffTableBody">
        <!-- Generado dinámicamente por assets/js/pages/personal.js -->
        <tr>
          <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
            Cargando catálogo institucional de personal...
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Barra de Paginación -->
  <div class="table-pagination-row">
    <div id="staffPaginationInfo">Mostrando 0 de 0 trabajadores</div>
    <div class="pagination-controls">
      <button type="button" class="btn btn-secondary btn-sm" id="staffPrevPageBtn" disabled>
        &larr; Anterior
      </button>
      <button type="button" class="btn btn-secondary btn-sm" id="staffNextPageBtn">
        Siguiente &rarr;
      </button>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: FICHA INTEGRAL DEL TRABAJADOR
     ========================================================= -->
<div class="modal-backdrop" id="modalFichaPersonal">
  <div class="modal-dialog" style="max-width: 680px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>👤</span> Ficha Institucional del Trabajador
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    <div class="modal-body">
      <!-- Cabecera de la Ficha -->
      <div class="ficha-header-card">
        <div class="ficha-avatar-lg" id="fichaAvatar">HP</div>
        <div class="ficha-headline">
          <h3 id="fichaNombre">Cargando...</h3>
          <p id="fichaCargoHeader">—</p>
          <div style="display: flex; gap: 0.5rem; margin-top: 0.4rem;">
            <span class="badge" id="fichaCondicionBadge">CAS</span>
            <span class="badge" id="fichaRegimenBadge">Asistencial</span>
          </div>
        </div>
      </div>

      <!-- Cuadrícula de Detalles Institucionales -->
      <div class="ficha-details-grid">
        <!-- 1. Identidad Personal (core.Persona) -->
        <div class="ficha-group">
          <div class="ficha-group-title">
            <span>📋</span> Datos de Identidad
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">DNI / Documento:</span>
            <span class="ficha-field-val" id="fichaDni">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Nombres:</span>
            <span class="ficha-field-val" id="fichaNombres">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Apellidos:</span>
            <span class="ficha-field-val" id="fichaApellidos">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Fecha Nacimiento:</span>
            <span class="ficha-field-val" id="fichaNacimiento">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Sexo:</span>
            <span class="ficha-field-val" id="fichaSexo">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Celular:</span>
            <span class="ficha-field-val" id="fichaCelular">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Correo:</span>
            <span class="ficha-field-val" id="fichaCorreo">—</span>
          </div>
        </div>

        <!-- 2. Asignación y Puesto (rrhh.AsignacionPersonal) -->
        <div class="ficha-group">
          <div class="ficha-group-title">
            <span>🏥</span> Asignación y Servicio
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Área Hospitalaria:</span>
            <span class="ficha-field-val" id="fichaArea">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Servicio Actual:</span>
            <span class="ficha-field-val" id="fichaServicio">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Cargo:</span>
            <span class="ficha-field-val" id="fichaCargo">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Profesión:</span>
            <span class="ficha-field-val" id="fichaProfesion">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Especialidad:</span>
            <span class="ficha-field-val" id="fichaEspecialidad">—</span>
          </div>
        </div>

        <!-- 3. Vínculo Contractual (rrhh.Personal y Contrato) -->
        <div class="ficha-group">
          <div class="ficha-group-title">
            <span>📝</span> Vínculo Laboral
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Código Empleado:</span>
            <span class="ficha-field-val" id="fichaCodigo">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Fecha Ingreso:</span>
            <span class="ficha-field-val" id="fichaIngreso">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">N° de Contrato:</span>
            <span class="ficha-field-val" id="fichaContrato">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Vigencia:</span>
            <span class="ficha-field-val" id="fichaVigencia">—</span>
          </div>
        </div>

        <!-- 4. Régimen Horario y SETI -->
        <div class="ficha-group">
          <div class="ficha-group-title">
            <span>⏱</span> Régimen y Cómputo de Horas
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Régimen:</span>
            <span class="ficha-field-val" id="fichaRegimenTexto">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Carga Mensual:</span>
            <span class="ficha-field-val" id="fichaHorasMensuales">—</span>
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Reporte SETI:</span>
            <span class="ficha-field-val" id="fichaSeti">—</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
      <a href="index.php?page=horarios" class="btn btn-outline btn-sm" id="btnFichaIrHorarios">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        <span>Asignar Horario a este Trabajador</span>
      </a>
      <button type="button" class="btn btn-secondary modal-close">Cerrar</button>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL DE REGISTRO: NUEVO TRABAJADOR
     ========================================================= -->
<div class="modal-backdrop" id="modalNewWorker">
  <div class="modal-dialog modal-dialog-lg" style="max-width: 760px;">
    <div class="modal-header">
      <div class="modal-title-wrap">
        <h3 class="modal-title" style="display: flex; align-items: center; gap: 0.5rem;">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
          </svg>
          <span>Registrar Nuevo Trabajador</span>
        </h3>
        <p class="modal-subtitle" style="font-size: 0.8rem; color: var(--text-tertiary); margin-top: 2px;">
          Ingresa los datos personales y contractuales para incorporarlo al rol de turnos y asistencias.
        </p>
      </div>
      <button type="button" class="modal-close-btn modal-close" aria-label="Cerrar">&times;</button>
    </div>

    <form id="formNewWorker">
      <div class="modal-body" style="padding: 1.5rem; max-height: calc(85vh - 140px); overflow-y: auto;">
        
        <!-- Sección 1: Datos de Identidad -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.88rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.4rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
            <span>🪪</span> 1. Identificación Personal
          </h4>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
              <label for="newWorkerTipoDoc" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Tipo Documento *</label>
              <select id="newWorkerTipoDoc" name="tipo_documento_id" class="form-select" required>
                <option value="4">DNI - Documento Nacional de Identidad</option>
                <option value="5">CE - Carné de Extranjería</option>
                <option value="6">PAS - Pasaporte</option>
              </select>
            </div>

            <div class="form-group">
              <label for="newWorkerDni" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Número Documento (DNI) *</label>
              <input type="text" id="newWorkerDni" name="dni" class="form-control" placeholder="8 dígitos numéricos" maxlength="20" required autocomplete="off">
            </div>

            <div class="form-group">
              <label for="newWorkerSexo" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Sexo</label>
              <select id="newWorkerSexo" name="sexo_id" class="form-select">
                <option value="">Seleccionar...</option>
                <option value="3">Masculino</option>
                <option value="4">Femenino</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 0.85rem;">
            <div class="form-group">
              <label for="newWorkerNombres" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Nombres *</label>
              <input type="text" id="newWorkerNombres" name="nombres" class="form-control" placeholder="Ej: Juan Carlos" required autocomplete="off">
            </div>

            <div class="form-group">
              <label for="newWorkerPaterno" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Apellido Paterno *</label>
              <input type="text" id="newWorkerPaterno" name="apellido_paterno" class="form-control" placeholder="Ej: Pérez" required autocomplete="off">
            </div>

            <div class="form-group">
              <label for="newWorkerMaterno" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Apellido Materno</label>
              <input type="text" id="newWorkerMaterno" name="apellido_materno" class="form-control" placeholder="Ej: Rodríguez" autocomplete="off">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 0.85rem;">
            <div class="form-group">
              <label for="newWorkerNacimiento" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Fecha de Nacimiento</label>
              <input type="date" id="newWorkerNacimiento" name="fecha_nacimiento" class="form-control">
            </div>

            <div class="form-group">
              <label for="newWorkerCelular" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Celular / Móvil</label>
              <input type="tel" id="newWorkerCelular" name="celular" class="form-control" placeholder="Ej: 948123456" maxlength="20" autocomplete="off">
            </div>

            <div class="form-group">
              <label for="newWorkerCorreo" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Correo Electrónico</label>
              <input type="email" id="newWorkerCorreo" name="correo" class="form-control" placeholder="ejemplo@minsa.gob.pe" autocomplete="off">
            </div>
          </div>
        </div>

        <!-- Sección 2: Asignación y Puesto -->
        <div style="margin-bottom: 1.25rem;">
          <h4 style="font-size: 0.88rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.4rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
            <span>🏥</span> 2. Asignación Institucional
          </h4>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="form-group">
              <label for="newWorkerArea" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Área Organizacional *</label>
              <select id="newWorkerArea" name="area_id" class="form-select" required>
                <option value="">Cargando áreas...</option>
              </select>
            </div>

            <div class="form-group">
              <label for="newWorkerServicio" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Servicio Hospitalario</label>
              <select id="newWorkerServicio" name="servicio_id" class="form-select">
                <option value="">Seleccionar Servicio...</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 0.85rem;">
            <div class="form-group">
              <label for="newWorkerCargo" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Cargo Funcional *</label>
              <select id="newWorkerCargo" name="cargo_id" class="form-select" required>
                <option value="">Cargando cargos...</option>
              </select>
            </div>

            <div class="form-group">
              <label for="newWorkerProfesion" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Profesión</label>
              <select id="newWorkerProfesion" name="profesion_id" class="form-select">
                <option value="">Seleccionar Profesión...</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Sección 3: Datos Laborales y Contrato -->
        <div>
          <h4 style="font-size: 0.88rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.4rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.4rem;">
            <span>📄</span> 3. Contrato y Código
          </h4>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
              <label for="newWorkerCodigo" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Código de Empleado</label>
              <input type="text" id="newWorkerCodigo" name="codigo_empleado" class="form-control" placeholder="Generación automática (EMP-XXXXX)" autocomplete="off">
            </div>

            <div class="form-group">
              <label for="newWorkerFechaIngreso" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Fecha de Ingreso *</label>
              <input type="date" id="newWorkerFechaIngreso" name="fecha_ingreso" class="form-control" required>
            </div>

            <div class="form-group">
              <label for="newWorkerTipoContrato" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Régimen / Condición *</label>
              <select id="newWorkerTipoContrato" name="tipo_contrato_id" class="form-select" required>
                <option value="1">CAS (D.L. 1057)</option>
                <option value="2">NOMBRADO (D.L. 276)</option>
                <option value="3">REGIMEN 728</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 0.85rem;">
            <div class="form-group">
              <label for="newWorkerNumContrato" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Número de Contrato</label>
              <input type="text" id="newWorkerNumContrato" name="numero_contrato" class="form-control" placeholder="Opcional: CONTRATO-2026-XXXX" autocomplete="off">
            </div>

            <div class="form-group">
              <label for="newWorkerContratoFin" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Vigencia Hasta</label>
              <input type="date" id="newWorkerContratoFin" name="contrato_fin" class="form-control">
            </div>
          </div>
        </div>

      </div>

      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-subtle);">
        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btnSubmitNewWorker">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          <span id="btnSubmitNewWorkerText">Guardar Trabajador</span>
        </button>
      </div>
    </form>
  </div>
</div>

