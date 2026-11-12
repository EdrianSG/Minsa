/**
 * Lógica del Módulo de Personal - Hospital Distrital de Pacasmayo
 * ETAPA 2
 */

let personalState = {
  page: 1,
  limit: 10,
  totalPages: 1,
  totalRecords: 0,
  filters: {
    q: '',
    area: '',
    servicio: '',
    condicion: '',
    regimen: '',
    estado: ''
  }
};

let searchDebounceTimer = null;
let personalLoadRequest = 0;

document.addEventListener('DOMContentLoaded', () => {
  initFilterControls();
  loadStaffData();
  try { initNewWorkerModal(); }
  catch (error) { console.error('Error inicializando el editor de personal:', error); }
});

/**
 * Inicializa los eventos de los controles de filtrado
 */
function initFilterControls() {
  const searchInput = document.getElementById('staffSearchInput');
  const areaSelect = document.getElementById('staffAreaFilter');
  const serviceSelect = document.getElementById('staffServiceFilter');
  const conditionSelect = document.getElementById('staffConditionFilter');
  const regimenSelect = document.getElementById('staffRegimenFilter');
  const stateSelect = document.getElementById('staffStateFilter');
  const resetBtn = document.getElementById('staffResetFiltersBtn');

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        personalState.filters.q = e.target.value.trim();
        personalState.page = 1;
        loadStaffData();
      }, 300);
    });
  }

  if (areaSelect) {
    areaSelect.addEventListener('change', (e) => {
      personalState.filters.area = e.target.value;
      personalState.page = 1;
      loadStaffData();
    });
  }

  if (serviceSelect) {
    serviceSelect.addEventListener('change', (e) => {
      personalState.filters.servicio = e.target.value;
      personalState.page = 1;
      loadStaffData();
    });
  }

  if (conditionSelect) {
    conditionSelect.addEventListener('change', (e) => {
      personalState.filters.condicion = e.target.value;
      personalState.page = 1;
      loadStaffData();
    });
  }

  if (regimenSelect) {
    regimenSelect.addEventListener('change', (e) => {
      personalState.filters.regimen = e.target.value;
      personalState.page = 1;
      loadStaffData();
    });
  }

  if (stateSelect) {
    stateSelect.addEventListener('change', (e) => {
      personalState.filters.estado = e.target.value;
      personalState.page = 1;
      loadStaffData();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      personalState.filters = { q: '', area: '', servicio: '', condicion: '', regimen: '', estado: '' };
      personalState.page = 1;

      if (searchInput) searchInput.value = '';
      if (areaSelect) areaSelect.value = '';
      if (serviceSelect) serviceSelect.value = '';
      if (conditionSelect) conditionSelect.value = '';
      if (regimenSelect) regimenSelect.value = '';
      if (stateSelect) stateSelect.value = '';

      loadStaffData();
      showToast('Filtros restablecidos.', 'info');
    });
  }

  // Paginación
  const prevBtn = document.getElementById('staffPrevPageBtn');
  const nextBtn = document.getElementById('staffNextPageBtn');

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (personalState.page > 1) {
        personalState.page--;
        loadStaffData();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      if (personalState.page < personalState.totalPages) {
        personalState.page++;
        loadStaffData();
      }
    });
  }
}

/**
 * Consulta la API y renderiza el listado de personal
 */
async function loadStaffData() {
  const requestId = ++personalLoadRequest;
  const tbody = document.getElementById('staffTableBody');
  if (tbody) {
    tbody.innerHTML = Array(6).fill(0).map(() => `
      <tr class="skeleton-table-row">
        <td>
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="skeleton skeleton-circle" style="width: 36px; height: 36px;"></div>
            <div style="flex: 1;">
              <div class="skeleton skeleton-text w-80" style="height: 0.95rem;"></div>
              <div class="skeleton skeleton-text w-40" style="height: 0.75rem;"></div>
            </div>
          </div>
        </td>
        <td><div class="skeleton skeleton-text" style="width: 80px;"></div></td>
        <td>
          <div class="skeleton skeleton-text w-80"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
        </td>
        <td><div class="skeleton skeleton-pill" style="width: 85px;"></div></td>
        <td><div class="skeleton skeleton-pill" style="width: 95px;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-pill" style="width: 65px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-btn" style="width: 70px; height: 1.8rem; margin: 0 auto;"></div></td>
      </tr>
    `).join('');
  }

  try {
    const params = {
      page: personalState.page,
      limit: personalState.limit,
      ...personalState.filters
    };

    const res = await API.get('personal/listar.php', params);
    if (requestId !== personalLoadRequest) return;
    if (!res || !res.ok) throw new Error('La consulta de personal no devolvió una respuesta válida.');
    if (res.ok) {
      personalState.totalRecords = res.total_registros;
      personalState.totalPages = res.total_paginas;

      updateStatsUI(res.estadisticas);
      renderStaffTable(res.data);
      updatePaginationUI();
    }
  } catch (error) {
    if (requestId !== personalLoadRequest) return;
    console.error('Error cargando personal:', error);
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 2rem; color: var(--status-absent-accent);">
            No se pudo cargar el personal: ${escapeHtml(error.message || 'Error de conexión con el servidor.')}<br><button type="button" class="btn btn-secondary" onclick="loadStaffData()">Reintentar</button>
          </td>
        </tr>
      `;
    }
  }
}

/**
 * Actualiza las tarjetas mini-KPI superiores
 */
function updateStatsUI(stats) {
  if (!stats) return;
  const setVal = (id, v) => {
    const el = document.getElementById(id);
    if (el) el.textContent = v;
  };

  setVal('miniKpiTotal', stats.total);
  setVal('miniKpiAsist', stats.asistenciales);
  setVal('miniKpiAdmin', stats.administrativos);
  setVal('miniKpiNombrados', stats.nombrados);
  setVal('miniKpiCas', stats.cas);
}

/**
 * Renderiza las filas de la tabla de personal
 */
function renderStaffTable(list) {
  const tbody = document.getElementById('staffTableBody');
  if (!tbody) return;

  if (!list || list.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-secondary);">
          <div style="font-size: 1.25rem; margin-bottom: 0.25rem;">🔍</div>
          <strong>No se encontraron trabajadores</strong>
          <div style="font-size: 0.78rem; color: var(--text-tertiary); margin-top: 0.2rem;">
            Intente modificando los términos de búsqueda o los filtros seleccionados.
          </div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = list.map(p => {
    const initials = getWorkerInitials(p.nombres, p.apellido_paterno);
    const isNombrado = (p.condicion_laboral || '').toUpperCase() === 'NOMBRADO';
    const regUpper = (p.tipo_personal || p.regimen_laboral || '').toUpperCase();
    const isAsist = regUpper.includes('ASIST');
    const isAdmin = regUpper.includes('ADMIN');
    const isActive = p.estado !== null && intval(p.estado) === 1;
    const pendingState = p.estado === null;

    return `
      <tr>
        <!-- 1. Trabajador -->
        <td>
          <div class="worker-cell">
            <div class="worker-avatar">${initials}</div>
            <div class="worker-info">
              <span class="worker-name">${escapeHtml(p.nombre_completo || (p.apellido_paterno + ' ' + p.nombres))}</span>
              <div class="worker-meta">
                <span class="worker-dni">Documento: ${escapeHtml(p.dni || 'Sin registrar')}<br>BioStar: ${escapeHtml(p.biostar_user_id || 'Sin vincular')}</span>
                <span>•</span>
                <span>${escapeHtml(p.codigo_empleado)}</span>
              </div>
            </div>
          </div>
        </td>

        <!-- 2. Cargo y Profesión -->
        <td>
          <div style="font-weight: 600; color: var(--text-primary); font-size: 0.88rem;">
            ${escapeHtml(p.cargo_nombre || 'Sin cargo homologado')}${p.cargo_origen ? `<div class="worker-meta">Origen: ${escapeHtml(p.cargo_origen)}</div>` : ''}
          </div>
          <div style="font-size: 0.76rem; color: var(--text-secondary); margin-top: 0.15rem;">
            ${escapeHtml(p.profesion_nombre || 'Sin registrar')}
            ${p.especialidad_nombre ? ` • <em style="color: var(--primary-600);">${escapeHtml(p.especialidad_nombre)}</em>` : ''}
          </div>
        </td>

        <!-- 3. Servicio y Área -->
        <td>
          <div style="font-weight: 500; color: var(--text-primary);">
            ${escapeHtml(p.servicio_nombre || 'Servicio General')}
          </div>
          <div style="font-size: 0.74rem; color: var(--text-tertiary);">
            ${escapeHtml(p.area_nombre || 'Sin área homologada')}${p.departamento_origen ? `<div class="worker-meta">Origen: ${escapeHtml(p.departamento_origen)}</div>` : ''}
          </div>
        </td>

        <!-- 4. Condición Laboral -->
        <td>
          <span class="badge ${isNombrado ? 'badge-nombrado' : 'badge-cas'}">
            ${escapeHtml(p.condicion_laboral || 'Sin registrar')}
          </span>
        </td>

        <!-- 5. Régimen / SETI -->
        <td>
          <span class="badge ${isAsist ? 'badge-asistencial' : (isAdmin ? 'badge-admin' : 'badge-neutral')}">
            ${isAsist ? 'Asistencial (150h)' : (isAdmin ? 'Administrativo' : escapeHtml(p.tipo_personal || p.regimen_laboral || 'Sin clasificar'))}
            ${isAsist ? '<span class="badge-tag-seti">SETI</span>' : ''}
          </span>
        </td>

        <!-- 6. Estado -->
        <td>
          <span class="badge ${isActive ? 'badge-present' : 'badge-absent'}">
            <span class="badge-dot"></span>
            ${pendingState ? 'Por confirmar' : (isActive ? 'Activo' : 'Inactivo')}
          </span>
        </td>

        <!-- 7. Acciones -->
        <td>
          <button type="button" class="btn btn-outline btn-sm" onclick="verFichaTrabajador(${p.personal_id})">
            Ver Ficha
          </button>
          ${personalCanWrite() ? `<button type="button" class="btn btn-outline btn-sm" onclick="editarTrabajador(${p.personal_id})">Editar ficha</button>` : ''}
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Actualiza la información de la barra de paginación
 */
function updatePaginationUI() {
  const infoEl = document.getElementById('staffPaginationInfo');
  const prevBtn = document.getElementById('staffPrevPageBtn');
  const nextBtn = document.getElementById('staffNextPageBtn');

  const startRecord = personalState.totalRecords > 0 ? (personalState.page - 1) * personalState.limit + 1 : 0;
  const endRecord = Math.min(personalState.page * personalState.limit, personalState.totalRecords);

  if (infoEl) {
    infoEl.textContent = `Mostrando ${startRecord} a ${endRecord} de ${personalState.totalRecords} trabajadores`;
  }

  if (prevBtn) prevBtn.disabled = (personalState.page <= 1);
  if (nextBtn) nextBtn.disabled = (personalState.page >= personalState.totalPages);
}

/**
 * Abre el modal y consulta los datos detallados de la ficha
 */
async function verFichaTrabajador(personalId) {
  try {
    const res = await API.get('personal/detalle.php', { id: personalId });
    if (res && res.ok && res.data) {
      populateFichaModal(res.data);
      openModal('modalFichaPersonal');
    } else {
      showToast('No se pudo obtener la ficha del trabajador.', 'danger');
    }
  } catch (error) {
    showToast('Error de comunicación al consultar ficha.', 'danger');
  }
}

/**
 * Llena el modal con los datos de la ficha
 */
function populateFichaModal(d) {
  const setEl = (id, v) => {
    const el = document.getElementById(id);
    if (el) el.textContent = v || '—';
  };

  const initials = getWorkerInitials(d.nombres, d.apellido_paterno);
  const avatarEl = document.getElementById('fichaAvatar');
  if (avatarEl) avatarEl.textContent = initials;

  setEl('fichaNombre', d.nombre_completo || (d.apellido_paterno + ' ' + d.nombres));
  setEl('fichaCargoHeader', `${d.cargo_nombre || 'Sin Cargo'} • ${d.servicio_nombre || 'Hospital'}`);

  // Badges
  const condBadge = document.getElementById('fichaCondicionBadge');
  if (condBadge) {
    condBadge.className = `badge ${(d.condicion_laboral || '').toUpperCase() === 'NOMBRADO' ? 'badge-nombrado' : 'badge-cas'}`;
    condBadge.textContent = d.condicion_laboral || 'Sin registrar';
  }

  const regBadge = document.getElementById('fichaRegimenBadge');
  if (regBadge) {
    const isAsist = (d.tipo_personal || '').toUpperCase() === 'ASISTENCIAL';
    regBadge.className = `badge ${isAsist ? 'badge-asistencial' : 'badge-neutral'}`;
    regBadge.innerHTML = isAsist ? 'Asistencial (150h) <span class="badge-tag-seti">SETI</span>' : escapeHtml(d.tipo_personal || 'Sin clasificar');
  }

  // Campos de Identidad
  setEl('fichaDni', d.dni);
  setEl('fichaNombres', d.nombres);
  setEl('fichaApellidos', `${d.apellido_paterno || ''} ${d.apellido_materno || ''}`.trim());
  setEl('fichaNacimiento', d.fecha_nacimiento);
  setEl('fichaSexo', d.sexo_nombre);
  setEl('fichaCelular', d.celular);
  setEl('fichaCorreo', d.correo);

  // Campos Laborales
  setEl('fichaCodigo', d.codigo_empleado);
  setEl('fichaIngreso', d.fecha_ingreso);
  setEl('fichaContrato', d.numero_contrato);
  setEl('fichaVigencia', `${d.contrato_inicio || 'Sin registrar'} al ${d.contrato_fin || 'Sin registrar'}`);

  // Campos Asignación Organizacional
  setEl('fichaArea', d.area_nombre);
  setEl('fichaServicio', d.servicio_nombre);
  setEl('fichaCargo', d.cargo_nombre);
  setEl('fichaProfesion', d.profesion_nombre);
  setEl('fichaEspecialidad', d.especialidad_nombre || 'Sin registrar');

  // Campos Régimen y Horas
  setEl('fichaRegimenTexto', d.regimen_laboral);
  setEl('fichaHorasMensuales', d.horas_mensuales ? `${d.horas_mensuales} horas computables / mes` : 'Sin clasificar');
  setEl('fichaSeti', d.tipo_personal ? (d.tipo_personal === 'ASISTENCIAL' ? 'SÍ' : 'NO') : 'Pendiente de reglas confirmadas');

  // Enlace directo a asignación de horarios
  const btnHorarios = document.getElementById('btnFichaIrHorarios');
  if (btnHorarios && d.personal_id) {
    btnHorarios.href = `index.php?page=horarios&worker_id=${d.personal_id}`;
  }
}

function getWorkerInitials(nombres, apellidos) {
  const n = (nombres || '').trim()[0] || 'H';
  const a = (apellidos || '').trim()[0] || 'P';
  return (n + a).toUpperCase();
}

function intval(val) {
  return parseInt(val, 10) || 0;
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * =========================================================
 * REGISTRO DE NUEVO TRABAJADOR
 * =========================================================
 */
let editingWorkerId = null;
let workerCatalogPromise = null;
let workerCatalogData = null;
let workerRelationModel = null;
let workerEditingData = null;

function personalCanWrite() {
  return ['ADMIN','ADMIN_RRHH','RRHH_ADMIN','JEFE_RRHH'].includes(window.currentUser?.rol);
}
function workerEditorError(message = '') {
  const box = document.getElementById('workerEditorError');
  if (box) { box.textContent = message; box.hidden = !message; }
}
function workerField(name) { return document.getElementById('formNewWorker')?.elements.namedItem(name); }
function fillWorkerOptions(select, rows, selected, emptyLabel = 'Sin registrar', group = null, allRows = rows) {
  if (!select) return;
  const value = String(selected ?? '');
  const available = rows.filter(row => Number(row.activo ?? 1) === 1 || String(row.id) === value);
  select.replaceChildren(new Option(emptyLabel, ''));
  const groups = new Map();
  for (const row of available) {
    const current = String(row.id) === value;
    const opt = new Option(`${row.nombre || row.codigo || 'Sin denominación'}${Number(row.activo ?? 1) !== 1 ? ' (inactivo · relación actual)' : ''}`, String(row.id));
    let target = select;
    if (group) {
      const label = group(row);
      if (!groups.has(label)) { const element = document.createElement('optgroup'); element.label = label; select.appendChild(element); groups.set(label, element); }
      target = groups.get(label);
    }
    target.appendChild(opt);
    if (current) opt.selected = true;
  }
  // Una relación fuera de un filtro nunca se sustituye por la primera opción.
  if (value && !available.some(row => String(row.id) === value)) {
    const record = allRows.find(row => String(row.id) === value);
    select.add(new Option(record ? `${record.nombre} (relación actual incompatible; revisar)` : 'Relación actual fuera del catálogo; revise antes de cambiar', value));
  }
  select.value = value;
}
function renderWorkerRelations() {
  if (!workerRelationModel) return;
  const model = workerRelationModel, state = model.state, c = workerCatalogData;
  fillWorkerOptions(document.getElementById('newWorkerDepartamento'), c.departamentos, state.departamento_id, 'Sin departamento formal / ver todas las áreas');
  fillWorkerOptions(document.getElementById('newWorkerArea'), model.areas(), state.area_id, 'Sin área asignada', row => row.operativa_pmyo ? 'Áreas operativas PMYO' : row.pmyo ? 'Áreas complementarias PMYO' : 'Otras áreas institucionales');
  fillWorkerOptions(document.getElementById('newWorkerServicio'), model.services(), state.servicio_id, model.services().length ? 'Sin servicio asignado' : 'Sin servicio documentado para esta área', null, c.servicios);
  fillWorkerOptions(document.getElementById('newWorkerCargo'), model.profiles(document.getElementById('workerShowAllProfiles').checked), state.cargo_id, 'Sin cargo / perfil asignado');
  fillWorkerOptions(document.getElementById('newWorkerProfesion'), c.profesiones, state.profesion_id, 'Profesión por verificar');
  fillWorkerOptions(document.getElementById('newWorkerEspecialidad'), model.specialties(), state.especialidad_id, 'Sin especialidad registrada', null, c.especialidades);
  const area = model.row('areas', state.area_id), profile = model.proposal();
  let text = area?.operativa_pmyo ? `Referencia del padrón operativo: ${area.dotacion_origen} trabajadores. ` : '';
  if (area?.pmyo && state.cargo_id && !(profile?.areas_pmyo || []).some(id => String(id) === state.area_id)) text += 'El perfil seleccionado no figura en esta área del mapa de origen; revise y documente la asignación.';
  else if (area?.pmyo) text += 'El selector muestra los perfiles observados en esta área. Puede consultar todos si corresponde a una asignación diferente.';
  document.getElementById('workerAreaHint').textContent = text;
  const proposal = profile?.profesion_propuesta_id ? model.row('profesiones', profile.profesion_propuesta_id) : null;
  const specialty = profile?.especialidad_propuesta_id ? model.row('especialidades', profile.especialidad_propuesta_id) : null;
  document.getElementById('workerProfessionHint').textContent = proposal
    ? `Propuesta para revisar según el perfil: ${proposal.nombre}${specialty ? ' · ' + specialty.nombre : ''}. Confirme el título y la especialidad del trabajador antes de aplicarlos.`
    : profile?.formacion ? 'Este perfil corresponde a formación o internado; no acredita una profesión titulada.' : 'El perfil de origen no determina por sí solo una profesión. Seleccione únicamente información verificada.';
  document.getElementById('workerApplyProfession').hidden = !proposal;
  updateWorkerAssignmentMode();
}
function updateWorkerAssignmentMode() {
  const action = workerField('accion_asignacion')?.value || 'corregir';
  const disabled = action === 'conservar' || action === 'finalizar';
  for (const id of ['newWorkerDepartamento','newWorkerArea','newWorkerServicio','newWorkerCargo','workerAssignmentStart','workerShowAllProfiles']) {
    const el = document.getElementById(id); if (el) el.disabled = disabled;
  }
  const start = document.getElementById('workerAssignmentStart'); if (start) start.required = action === 'trasladar';
  const end = document.getElementById('workerAssignmentEnd'); if (end) { end.required = action === 'finalizar'; end.disabled = action !== 'finalizar'; }
  document.getElementById('workerAssignmentEndWrap').hidden = action !== 'finalizar';
}
async function loadWorkerCatalogs() {
  if (workerCatalogData) return workerCatalogData;
  if (workerCatalogPromise) return workerCatalogPromise;
  workerCatalogPromise = (async () => {
    const json = await API.get('personal/catalogos.php', {}, {useCache: false});
    if (!json.success || !json.data) throw new Error(json.message || 'No se pudieron cargar los catálogos de V4.');
    workerCatalogData = json.data;
    workerRelationModel = new PersonalRelationsModel(workerCatalogData);
    return workerCatalogData;
  })();
  try { return await workerCatalogPromise; } finally { workerCatalogPromise = null; }
}
function populateWorkerEditor(data = {}) {
  const form = document.getElementById('formNewWorker');
  form.reset(); workerEditingData = data;
  for (const [key, value] of Object.entries(data)) {
    const field = form.elements.namedItem(key);
    if (field && field.tagName !== 'SELECT' && field.type !== 'checkbox') field.value = value ?? '';
  }
  fillWorkerOptions(document.getElementById('newWorkerTipoDoc'), workerCatalogData.tipos_documento, data.tipo_documento_id);
  fillWorkerOptions(document.getElementById('newWorkerSexo'), workerCatalogData.sexos, data.sexo_id);
  fillWorkerOptions(document.getElementById('newWorkerTipoContrato'), workerCatalogData.tipos_contrato, data.tipo_contrato_id);
  workerField('activo').value = data.activo ?? '';
  if (workerField('regimen_laboral')) workerField('regimen_laboral').value = data.regimen_laboral || data.tipo_personal || '';
  if (workerField('condicion_laboral')) workerField('condicion_laboral').value = data.condicion_laboral || '';
  workerField('accion_asignacion').value = 'corregir';
  workerField('asignacion_actual_id').value = data.asignacion_actual_id ?? 0;
  workerField('contrato_id').value = data.contrato_id ?? '';
  document.getElementById('workerContractFields').disabled = true;
  workerRelationModel.load(data); renderWorkerRelations();
  document.getElementById('workerEditorTitle').textContent = editingWorkerId ? 'Editar ficha y relaciones del trabajador' : 'Registrar trabajador';
  document.getElementById('btnSubmitNewWorkerText').textContent = editingWorkerId ? 'Guardar cambios' : 'Guardar trabajador';
  const original = workerField('nombre_completo_original'); if (original) original.readOnly = Boolean(editingWorkerId);
  document.getElementById('workerOriginCard').hidden = !editingWorkerId;
  for (const [id, value] of [['workerOriginName',data.nombre_completo_original],['workerOriginBiostar',data.biostar_user_id],['workerOriginArea',data.departamento_origen],['workerOriginProfile',data.cargo_origen]]) document.getElementById(id).textContent = value || 'Sin dato de origen';
  document.getElementById('workerUseOrigin').disabled = !data.departamento_origen && !data.cargo_origen;
  workerEditorError();
}
function initNewWorkerModal() {
  const openBtn = document.getElementById('btnOpenNewWorkerModal');
  const form = document.getElementById('formNewWorker');
  if (!form || !openBtn) return;
  if (!personalCanWrite()) { openBtn.hidden = true; return; }
  openBtn.addEventListener('click', async () => {
    openBtn.disabled = true;
    try { await loadWorkerCatalogs(); editingWorkerId = null; populateWorkerEditor(); openModal('modalNewWorker'); }
    catch (e) { showToast(e.message, 'danger'); }
    finally { openBtn.disabled = false; }
  });
  for (const [id, handler] of [
    ['newWorkerDepartamento', value => workerRelationModel.setDepartment(value)],
    ['newWorkerArea', value => workerRelationModel.setArea(value)],
    ['newWorkerServicio', value => { workerRelationModel.state.servicio_id = value; }],
    ['newWorkerCargo', value => { workerRelationModel.state.cargo_id = value; }],
    ['newWorkerProfesion', value => workerRelationModel.setProfession(value)],
    ['newWorkerEspecialidad', value => { workerRelationModel.state.especialidad_id = value; }]
  ]) document.getElementById(id)?.addEventListener('change', event => { handler(event.target.value); renderWorkerRelations(); });
  document.getElementById('workerShowAllProfiles').addEventListener('change', renderWorkerRelations);
  document.getElementById('workerAssignmentAction').addEventListener('change', updateWorkerAssignmentMode);
  document.getElementById('workerEditContract').addEventListener('change', event => { document.getElementById('workerContractFields').disabled = !event.target.checked; });
  document.getElementById('workerUseOrigin').addEventListener('click', () => {
    const result = workerRelationModel.applyOrigin(workerEditingData?.departamento_origen, workerEditingData?.cargo_origen);
    workerField('accion_asignacion').value = 'corregir'; renderWorkerRelations();
    workerEditorError(result.area && result.profile ? '' : 'Alguna denominación de origen no tiene una coincidencia única. Revise los selectores; no se creó ni supuso una relación.');
  });
  document.getElementById('workerApplyProfession').addEventListener('click', () => { workerRelationModel.applyProfessionalProposal(); renderWorkerRelations(); });
  form.addEventListener('submit', async event => {
    event.preventDefault(); workerEditorError();
    const payload = {};
    new FormData(form).forEach((value, key) => { payload[key] = String(value).trim(); });
    const action = payload.accion_asignacion;
    if (!['conservar','finalizar'].includes(action)) {
      const issues = workerRelationModel.issues();
      if (issues.length) { workerEditorError(issues.join(' ')); return; }
    } else if (workerRelationModel.state.especialidad_id && !workerRelationModel.specialties().some(row => String(row.id) === workerRelationModel.state.especialidad_id)) { workerEditorError('Revise la relación entre profesión y especialidad.'); return; }
    Object.assign(payload, workerRelationModel.state);
    if (action === 'conservar' || action === 'finalizar') for (const key of ['departamento_id','area_id','servicio_id','cargo_id','fecha_asignacion']) delete payload[key];
    payload.registrar_contrato = document.getElementById('workerEditContract').checked;
    if (!payload.registrar_contrato) for (const key of ['contrato_id','tipo_contrato_id','numero_contrato','contrato_inicio','contrato_fin']) delete payload[key];
    if (editingWorkerId) { payload.personal_id = editingWorkerId; payload.version_ficha = workerEditingData.version_ficha ?? 0; }
    const button = document.getElementById('btnSubmitNewWorker'); button.disabled = true;
    try {
      const res = await API.post(editingWorkerId ? 'personal/editar.php' : 'personal/crear.php', payload);
      if (!res.success) throw new Error(res.message || 'No se pudo guardar la ficha.');
      showToast(res.message || 'Cambios guardados en V4.', 'success'); closeModal('modalNewWorker');
      await loadStaffData(); if (typeof cargarPersonalParaVinculo === 'function') cargarPersonalParaVinculo(true);
    } catch (e) { workerEditorError(e.message); }
    finally { button.disabled = false; }
  });
}
async function editarTrabajador(id) {
  if (!personalCanWrite()) { showToast('La edición requiere autorización de Recursos Humanos.', 'danger'); return; }
  try {
    await loadWorkerCatalogs();
    const res = await API.get('personal/detalle.php', {id}, {useCache: false});
    if (!res?.ok || !res.data) throw new Error('No se pudo consultar la ficha.');
    editingWorkerId = id; populateWorkerEditor(res.data); openModal('modalNewWorker');
  } catch (e) { showToast(e.message, 'danger'); }
}

async function cargarPadronPendiente() {
  const target = document.getElementById('padronPendienteListado');
  if (!target) return;
  try {
    const res = await API.get('personal/pendientes.php');
    target.replaceChildren();
    const count = document.createElement('p');
    count.textContent = `${res.total} identificadores requieren revisión.`;
    target.appendChild(count);
    for (const row of res.data || []) {
      const item = document.createElement('p');
      item.textContent = `${row.biostar_user_id} — ${row.nombre_original || 'Nombre no registrado'} — ${row.marcas_pendientes} marcas pendientes`;
      target.appendChild(item);
    }
  } catch (e) { target.textContent = e.message; }
}
document.addEventListener('DOMContentLoaded', () => {
  cargarPadronPendiente();
  cargarPersonalParaVinculo();
  document.getElementById('formVincularBiostar')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button');
    try {
      button.disabled = true;
      const res = await API.post('personal/vincular_biometrico.php', {
        personal_id: Number(form.elements.personal_id.value), biostar_user_id: form.elements.biostar_user_id.value.trim(),
        observacion: form.elements.observacion.value.trim(), verificado: form.elements.verificado.checked
      });
      showToast(res.message, 'success'); form.reset(); cargarPadronPendiente(); loadStaffData();
    } catch (e) { showToast(e.message, 'danger'); }
    finally { button.disabled = false; }
  });
});

async function cargarPersonalParaVinculo(reset = false) {
  const select = document.getElementById('bioWorkerSelect');
  if (!select) return;
  if (reset) select.replaceChildren(new Option('Seleccione el trabajador', ''));
  try {
    const res = await API.get('personal/opciones.php', {contexto:'personal'}, {useCache:!reset});
    select.replaceChildren(new Option('Seleccione el trabajador', ''));
    for (const person of res.data || []) {
      select.add(new Option(`${person.nombre_completo || 'Nombre pendiente'} · ${person.codigo_empleado || 'Sin código'}`, person.personal_id));
    }
  } catch (e) { select.replaceChildren(new Option(e.message, '')); }
}
