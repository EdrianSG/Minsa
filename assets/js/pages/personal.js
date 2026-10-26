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
    servicio: '',
    condicion: '',
    regimen: '',
    estado: ''
  }
};

let searchDebounceTimer = null;

document.addEventListener('DOMContentLoaded', () => {
  initFilterControls();
  initNewWorkerModal();
  loadStaffData();
});

/**
 * Inicializa los eventos de los controles de filtrado
 */
function initFilterControls() {
  const searchInput = document.getElementById('staffSearchInput');
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
      personalState.filters = { q: '', servicio: '', condicion: '', regimen: '', estado: '' };
      personalState.page = 1;

      if (searchInput) searchInput.value = '';
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
    if (res && res.ok) {
      personalState.totalRecords = res.total_registros;
      personalState.totalPages = res.total_paginas;

      updateStatsUI(res.estadisticas);
      renderStaffTable(res.data);
      updatePaginationUI();
    }
  } catch (error) {
    console.error('Error cargando personal:', error);
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 2rem; color: var(--status-absent-accent);">
            No se pudo cargar la información de personal. Por favor verifique la conexión con el servidor.
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
    const isAsist = (p.regimen_laboral || '').toUpperCase() === 'ASISTENCIAL';
    const isActive = intval(p.estado) === 1;

    return `
      <tr>
        <!-- 1. Trabajador -->
        <td>
          <div class="worker-cell">
            <div class="worker-avatar">${initials}</div>
            <div class="worker-info">
              <span class="worker-name">${escapeHtml(p.nombre_completo || (p.apellido_paterno + ' ' + p.nombres))}</span>
              <div class="worker-meta">
                <span class="worker-dni">DNI: ${escapeHtml(p.dni)}</span>
                <span>•</span>
                <span>${escapeHtml(p.codigo_empleado)}</span>
              </div>
            </div>
          </div>
        </td>

        <!-- 2. Cargo y Profesión -->
        <td>
          <div style="font-weight: 600; color: var(--text-primary); font-size: 0.88rem;">
            ${escapeHtml(p.cargo_nombre || 'Sin Cargo')}
          </div>
          <div style="font-size: 0.76rem; color: var(--text-secondary); margin-top: 0.15rem;">
            ${escapeHtml(p.profesion_nombre || 'General')}
            ${p.especialidad_nombre ? ` • <em style="color: var(--primary-600);">${escapeHtml(p.especialidad_nombre)}</em>` : ''}
          </div>
        </td>

        <!-- 3. Servicio y Área -->
        <td>
          <div style="font-weight: 500; color: var(--text-primary);">
            ${escapeHtml(p.servicio_nombre || 'Servicio General')}
          </div>
          <div style="font-size: 0.74rem; color: var(--text-tertiary);">
            ${escapeHtml(p.area_nombre || 'Área')}
          </div>
        </td>

        <!-- 4. Condición Laboral -->
        <td>
          <span class="badge ${isNombrado ? 'badge-nombrado' : 'badge-cas'}">
            ${escapeHtml(p.condicion_laboral || 'CAS')}
          </span>
        </td>

        <!-- 5. Régimen / SETI -->
        <td>
          <span class="badge ${isAsist ? 'badge-asistencial' : 'badge-neutral'}">
            ${isAsist ? 'Asistencial (150h)' : 'Administrativo'}
            ${isAsist ? '<span class="badge-tag-seti">SETI</span>' : ''}
          </span>
        </td>

        <!-- 6. Estado -->
        <td>
          <span class="badge ${isActive ? 'badge-present' : 'badge-absent'}">
            <span class="badge-dot"></span>
            ${isActive ? 'Activo' : 'Cesado'}
          </span>
        </td>

        <!-- 7. Acciones -->
        <td>
          <button type="button" class="btn btn-outline btn-sm" onclick="verFichaTrabajador(${p.personal_id})">
            Ver Ficha
          </button>
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
    condBadge.textContent = d.condicion_laboral || 'CAS';
  }

  const regBadge = document.getElementById('fichaRegimenBadge');
  if (regBadge) {
    const isAsist = (d.regimen_laboral || '').toUpperCase() === 'ASISTENCIAL';
    regBadge.className = `badge ${isAsist ? 'badge-asistencial' : 'badge-neutral'}`;
    regBadge.innerHTML = isAsist ? 'Asistencial (150h) <span class="badge-tag-seti">SETI</span>' : 'Administrativo';
  }

  // Campos de Identidad
  setEl('fichaDni', d.dni);
  setEl('fichaNombres', d.nombres);
  setEl('fichaApellidos', `${d.apellido_paterno} ${d.apellido_materno || ''}`);
  setEl('fichaNacimiento', d.fecha_nacimiento);
  setEl('fichaSexo', d.sexo_nombre || (d.sexo_id == 1 ? 'Masculino' : 'Femenino'));
  setEl('fichaCelular', d.celular);
  setEl('fichaCorreo', d.correo);

  // Campos Laborales
  setEl('fichaCodigo', d.codigo_empleado);
  setEl('fichaIngreso', d.fecha_ingreso);
  setEl('fichaContrato', d.numero_contrato || 'Contrato Vigente');
  setEl('fichaVigencia', `${d.contrato_inicio || '2026-01-01'} al ${d.contrato_fin || 'Indeterminado'}`);

  // Campos Asignación Organizacional
  setEl('fichaArea', d.area_nombre);
  setEl('fichaServicio', d.servicio_nombre);
  setEl('fichaCargo', d.cargo_nombre);
  setEl('fichaProfesion', d.profesion_nombre);
  setEl('fichaEspecialidad', d.especialidad_nombre || 'Ninguna');

  // Campos Régimen y Horas
  setEl('fichaRegimenTexto', d.regimen_laboral);
  setEl('fichaHorasMensuales', `${d.horas_mensuales || 150} horas computables / mes`);
  setEl('fichaSeti', d.aplica_seti == 1 ? 'SÍ (Reporte SETI Regional habilitado)' : 'NO');

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
let workerCatalogsLoaded = false;
let workerServicesList = [];

function initNewWorkerModal() {
  const openBtn = document.getElementById('btnOpenNewWorkerModal');
  const modal = document.getElementById('modalNewWorker');
  const form = document.getElementById('formNewWorker');
  const areaSelect = document.getElementById('newWorkerArea');
  const servSelect = document.getElementById('newWorkerServicio');

  if (!openBtn || !modal || !form) return;

  // Abrir Modal
  openBtn.addEventListener('click', () => {
    openModal('modalNewWorker');
    loadWorkerCatalogs();
    
    // Asignar fecha de ingreso por defecto (hoy) si está vacía
    const ingresoInput = document.getElementById('newWorkerFechaIngreso');
    if (ingresoInput && !ingresoInput.value) {
      ingresoInput.value = new Date().toISOString().split('T')[0];
    }
  });

  // Filtrado reactivo de Servicios según el Área seleccionada
  if (areaSelect && servSelect) {
    areaSelect.addEventListener('change', () => {
      const selectedAreaId = areaSelect.value;
      servSelect.innerHTML = '<option value="">Seleccionar Servicio...</option>';

      const filtered = selectedAreaId 
        ? workerServicesList.filter(s => s.area_id == selectedAreaId)
        : workerServicesList;

      filtered.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.nombre;
        servSelect.appendChild(opt);
      });
    });
  }

  // Enviar Formulario
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const submitBtn = document.getElementById('btnSubmitNewWorker');
    const submitBtnText = document.getElementById('btnSubmitNewWorkerText');

    const formData = new FormData(form);
    const payload = {};
    formData.forEach((value, key) => {
      payload[key] = value.trim();
    });

    // Validar DNI
    if (!payload.dni || payload.dni.length < 8) {
      showToast('Por favor ingrese un número de documento válido (mínimo 8 dígitos).', 'warning');
      document.getElementById('newWorkerDni')?.focus();
      return;
    }

    if (!payload.area_id || !payload.cargo_id) {
      showToast('Debe seleccionar Área y Cargo institucional.', 'warning');
      return;
    }

    try {
      if (submitBtn) submitBtn.disabled = true;
      if (submitBtnText) submitBtnText.textContent = 'Guardando en BD...';

      const response = await fetch('api/personal/crear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const res = await response.json();

      if (res.success) {
        showToast(res.message || 'Trabajador registrado exitosamente.', 'success');
        closeModal('modalNewWorker');
        form.reset();
        
        // Recargar listado y refrescar KPIs
        personalState.page = 1;
        loadStaffData();
      } else {
        showToast(res.message || 'Error al guardar el trabajador.', 'danger');
      }
    } catch (err) {
      console.error('Error registrando trabajador:', err);
      showToast('Error de comunicación con el servidor al registrar trabajador.', 'danger');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
      if (submitBtnText) submitBtnText.textContent = 'Guardar Trabajador';
    }
  });
}

/**
 * Carga las listas maestras de Áreas, Servicios, Cargos y Profesiones
 */
async function loadWorkerCatalogs() {
  if (workerCatalogsLoaded) return;

  try {
    const res = await fetch('api/personal/catalogos.php');
    const json = await res.json();

    if (!json.success || !json.data) {
      console.warn('No se pudieron obtener los catálogos:', json.message);
      return;
    }

    const { tipos_documento, sexos, areas, servicios, cargos, profesiones, tipos_contrato, suggested_code } = json.data;

    // 1. Áreas
    const areaSelect = document.getElementById('newWorkerArea');
    if (areaSelect) {
      areaSelect.innerHTML = '<option value="">Seleccionar Área...</option>';
      areas.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.nombre;
        areaSelect.appendChild(opt);
      });
    }

    // 2. Servicios
    workerServicesList = servicios || [];
    const servSelect = document.getElementById('newWorkerServicio');
    if (servSelect) {
      servSelect.innerHTML = '<option value="">Seleccionar Servicio...</option>';
      workerServicesList.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.nombre;
        servSelect.appendChild(opt);
      });
    }

    // 3. Cargos
    const cargoSelect = document.getElementById('newWorkerCargo');
    if (cargoSelect) {
      cargoSelect.innerHTML = '<option value="">Seleccionar Cargo...</option>';
      cargos.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.nombre;
        cargoSelect.appendChild(opt);
      });
    }

    // 4. Profesiones
    const profSelect = document.getElementById('newWorkerProfesion');
    if (profSelect) {
      profSelect.innerHTML = '<option value="">Seleccionar Profesión...</option>';
      profesiones.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        profSelect.appendChild(opt);
      });
    }

    // 5. Tipos de Contrato
    const contSelect = document.getElementById('newWorkerTipoContrato');
    if (contSelect && tipos_contrato) {
      contSelect.innerHTML = '';
      tipos_contrato.forEach(tc => {
        const opt = document.createElement('option');
        opt.value = tc.id;
        opt.textContent = tc.nombre;
        contSelect.appendChild(opt);
      });
    }

    // 6. Código sugerido
    const codInput = document.getElementById('newWorkerCodigo');
    if (codInput && suggested_code && !codInput.value) {
      codInput.value = suggested_code;
    }

    workerCatalogsLoaded = true;
  } catch (err) {
    console.error('Error cargando catálogos para nuevo trabajador:', err);
  }
}

