<?php
/**
 * Módulo: Personal
 * Sistema de Gestión de Horarios y Asistencia - Hospital Distrital de Pacasmayo
 * ETAPA 2
 * 
 * Reutiliza las entidades institucionales:
 * rrhh.Personal, rrhh.AsignacionPersonal, rrhh.Cargo,
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

      <!-- Filtro por Área: catálogo independiente de los servicios -->
      <div class="filter-item">
        <label for="staffAreaFilter">Área</label>
        <select class="form-select" id="staffAreaFilter"><?php require_once __DIR__.'/../includes/OrganizationCatalog.php'; echo OrganizationCatalog::areaOptions(); ?></select>
      </div>

      <!-- Filtro por Servicio -->
      <div class="filter-item">
        <label for="staffServiceFilter">Servicio Hospitalario</label>
        <select class="form-select" id="staffServiceFilter"><?php require_once __DIR__.'/../includes/OrganizationCatalog.php'; echo OrganizationCatalog::options(''); ?></select>
      </div>

      <!-- Filtro por Condición Laboral -->
      <div class="filter-item">
        <label for="staffConditionFilter">Condición Laboral</label>
        <select class="form-select" id="staffConditionFilter">
          <option value="">Todas las condiciones</option>
          <option value="NOMBRADO">Nombrado</option>
          <option value="CAS">CAS (D.L. 1057)</option>
          <option value="DESTACADO">Destacado</option>
          <option value="TERCERO">Tercero</option>
        </select>
      </div>

      <!-- Filtro por Régimen / SETI -->
      <div class="filter-item">
        <label for="staffRegimenFilter">Clasificación</label>
        <select class="form-select" id="staffRegimenFilter">
          <option value="">Todas las clasificaciones</option>
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
          <th>Clasificación / SETI</th>
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
            <span class="ficha-field-lbl">Vigencia del contrato:</span>
            <span class="ficha-field-val" id="fichaVigencia">—</span>
          </div>
        </div>

        <!-- 4. Régimen Horario y SETI -->
        <div class="ficha-group">
          <div class="ficha-group-title">
            <span>⏱</span> Régimen y Cómputo de Horas
          </div>
          <div class="ficha-field">
            <span class="ficha-field-lbl">Régimen laboral:</span>
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
  <div class="modal-dialog modal-dialog-lg worker-editor-dialog">
    <div class="modal-header">
      <div><h3 class="modal-title" id="workerEditorTitle">Registrar trabajador</h3><p class="modal-subtitle">Identidad, ubicación y perfil profesional con datos verificados.</p></div>
      <button type="button" class="modal-close" aria-label="Cerrar">&times;</button>
    </div>
    <form id="formNewWorker">
      <div class="modal-body worker-editor-body">
        <div id="workerEditorError" class="worker-editor-error" role="alert" hidden></div>
        <section class="worker-origin-card" id="workerOriginCard" hidden>
          <strong>Datos conservados del padrón</strong>
          <dl class="worker-origin-grid"><div><dt>Nombre original</dt><dd id="workerOriginName"></dd></div><div><dt>BioStar (no es documento)</dt><dd id="workerOriginBiostar"></dd></div><div><dt>Área de origen</dt><dd id="workerOriginArea"></dd></div><div><dt>Perfil de origen</dt><dd id="workerOriginProfile"></dd></div></dl>
          <button type="button" id="workerUseOrigin" class="btn btn-outline btn-sm">Usar área y perfil de origen</button>
          <small>Se propone una coincidencia exacta del catálogo. La profesión se confirma por separado.</small>
        </section>
        <fieldset class="worker-editor-section"><legend>Identificación y contacto</legend>
          <div class="worker-editor-grid">
            <label>Tipo de documento<select id="newWorkerTipoDoc" name="tipo_documento_id" class="form-select"></select></label>
            <label>Documento<input id="newWorkerDni" name="dni" class="form-control" maxlength="20" autocomplete="off"></label>
            <label>Nombre completo de origen<input name="nombre_completo_original" class="form-control" maxlength="250"></label>
            <label>Nombres<input id="newWorkerNombres" name="nombres" class="form-control" maxlength="100"></label>
            <label>Apellido paterno<input id="newWorkerPaterno" name="apellido_paterno" class="form-control" maxlength="100"></label>
            <label>Apellido materno<input id="newWorkerMaterno" name="apellido_materno" class="form-control" maxlength="100"></label>
            <label>Sexo<select id="newWorkerSexo" name="sexo_id" class="form-select"></select></label>
            <label>Fecha de nacimiento<input id="newWorkerNacimiento" type="date" name="fecha_nacimiento" class="form-control"></label>
            <label>Celular<input id="newWorkerCelular" name="celular" class="form-control" maxlength="30"></label>
            <label>Teléfono<input name="telefono" class="form-control" maxlength="30"></label>
            <label>Correo<input id="newWorkerCorreo" type="email" name="correo" class="form-control" maxlength="150"></label>
          </div>
        </fieldset>
        <fieldset class="worker-editor-section"><legend>Área y cargo / perfil registrado</legend>
          <div class="worker-editor-grid">
            <label>Operación<select name="accion_asignacion" id="workerAssignmentAction" class="form-select"><option value="corregir">Registrar o corregir la relación actual</option><option value="trasladar">Registrar traslado con nueva vigencia</option><option value="finalizar">Finalizar la asignación actual</option><option value="conservar">Conservar la asignación sin cambios</option></select></label>
            <label>Departamento formal<select id="newWorkerDepartamento" name="departamento_id" class="form-select"></select></label>
            <label>Área funcional / institucional<select id="newWorkerArea" name="area_id" class="form-select"></select></label>
            <label>Servicio del área (opcional)<select id="newWorkerServicio" name="servicio_id" class="form-select"></select></label>
            <label>Cargo / perfil registrado<select id="newWorkerCargo" name="cargo_id" class="form-select"></select></label>
            <label>Inicio de vigencia, si se conoce<input type="date" id="workerAssignmentStart" name="fecha_asignacion" class="form-control"></label>
            <label id="workerAssignmentEndWrap" hidden>Fin de vigencia<input type="date" id="workerAssignmentEnd" name="fecha_fin_asignacion" class="form-control"></label>
            <label>Motivo de la modificación<input name="motivo_asignacion" class="form-control" maxlength="200" placeholder="Requerido al cambiar una relación existente"></label>
          </div>
          <label class="worker-check"><input type="checkbox" id="workerShowAllProfiles"> Mostrar también perfiles no observados en esta área</label>
          <p class="worker-editor-hint" id="workerAreaHint"></p>
          <p class="worker-editor-hint">Las áreas funcionales del mapa pueden no tener departamento formal documentado. Ese vínculo puede permanecer sin registrar.</p>
          <input type="hidden" name="asignacion_actual_id" value="0">
        </fieldset>
        <fieldset class="worker-editor-section"><legend>Profesión y especialidad verificadas</legend>
          <div class="worker-editor-grid">
            <label>Profesión<select id="newWorkerProfesion" name="profesion_id" class="form-select"></select></label>
            <label>Especialidad de esa profesión<select id="newWorkerEspecialidad" name="especialidad_id" class="form-select"></select></label>
          </div>
          <p class="worker-editor-hint" id="workerProfessionHint"></p>
          <button type="button" id="workerApplyProfession" class="btn btn-outline btn-sm" hidden>Aplicar propuesta profesional para revisión</button>
        </fieldset>
        <fieldset class="worker-editor-section"><legend>Datos laborales</legend>
          <div class="worker-editor-grid">
            <label>Situación confirmada<select name="activo" class="form-select"><option value="">Por confirmar</option><option value="1">Activo</option><option value="0">Inactivo</option></select></label>
            <label>Código de empleado<input id="newWorkerCodigo" name="codigo_empleado" class="form-control" maxlength="30"></label>
            <label>Fecha real de ingreso<input id="newWorkerFechaIngreso" type="date" name="fecha_ingreso" class="form-control"></label>
            <label>Grupo ocupacional<input name="grupo_ocupacional" class="form-control" maxlength="30"></label>
            <label>Régimen laboral
              <select name="regimen_laboral" id="newWorkerRegimen" class="form-select">
                <option value="">Sin régimen documentado</option>
                <option value="DL 276">DL 276</option>
                <option value="DL 728">DL 728</option>
                <option value="DL 1057 CAS">DL 1057 CAS</option>
                <option value="DL 1153">DL 1153</option>
                <option value="LEY 30057">LEY 30057</option>
                <option value="LOCACION">LOCACION</option>
                <option value="OTRO">OTRO</option>
              </select>
            </label>
            <label>Clasificación de personal
              <select name="tipo_personal" class="form-select">
                <option value="">Por confirmar</option>
                <option value="ADMINISTRATIVO">ADMINISTRATIVO</option>
                <option value="ASISTENCIAL">ASISTENCIAL (150h / SETI)</option>
              </select>
            </label>
            <label>Condición laboral
              <select name="condicion_laboral" id="newWorkerCondicion" class="form-select">
                <option value="">Sin definir</option>
                <option value="NOMBRADO">NOMBRADO</option>
                <option value="CAS">CAS (D.L. 1057)</option>
                <option value="DESTACADO">DESTACADO</option>
                <option value="TERCERO">Tercero</option>
              </select>
            </label>
          </div>
        </fieldset>
        <label class="worker-check"><input type="checkbox" name="registrar_contrato" id="workerEditContract" value="1"> Registrar o editar los datos del contrato</label>
        <fieldset class="worker-editor-section" id="workerContractFields" disabled><legend>Contrato</legend>
          <input type="hidden" name="contrato_id">
          <div class="worker-editor-grid">
            <label>Tipo de contrato<select id="newWorkerTipoContrato" name="tipo_contrato_id" class="form-select"></select></label>
            <label>Número de contrato<input id="newWorkerNumContrato" name="numero_contrato" class="form-control" maxlength="50"></label>
            <label>Inicio<input type="date" name="contrato_inicio" class="form-control"></label>
            <label>Fin<input id="newWorkerContratoFin" type="date" name="contrato_fin" class="form-control"></label>
          </div>
        </fieldset>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary modal-close">Cancelar</button><button type="submit" id="btnSubmitNewWorker" class="btn btn-primary"><span id="btnSubmitNewWorkerText">Guardar trabajador</span></button></div>
    </form>
  </div>
</div>

<?php if (Auth::hasRole('ADMIN','ADMIN_RRHH','JEFE_RRHH')): ?>
<div class="report-table-card" style="margin-top:1rem;padding:1rem">
  <h3>Identificadores BioStar pendientes de verificación</h3>
  <p>Los datos de origen se conservan sin convertir el ID biométrico en documento de identidad.</p>
  <div id="padronPendienteListado">Consultando registros pendientes…</div>
  <form id="formVincularBiostar" style="display:grid;gap:.5rem;margin-top:1rem">
    <label>ID BioStar exacto del origen <input name="biostar_user_id" class="form-control" maxlength="30" required></label>
    <label>Trabajador de la ficha verificada <select name="personal_id" id="bioWorkerSelect" class="form-select" required><option value="">Seleccione el trabajador</option></select></label>
    <label>Sustento de la verificación <input name="observacion" class="form-control" maxlength="200" required></label>
    <label><input name="verificado" type="checkbox" required> Confirmé que el identificador pertenece al trabajador indicado.</label>
    <button type="submit" class="btn btn-primary">Guardar vínculo verificado</button>
  </form>
</div>

<?php endif; ?>
