/**
 * Lógica del Módulo de Horarios y Turnos - Hospital Distrital de Pacasmayo
 * ETAPA 3
 */

let horariosState = {
  activeTab: 'catalog',
  turnos: [],
  horariosSemanales: []
};

document.addEventListener('DOMContentLoaded', () => {
  initTabsNavigation();
  initShiftFormCalc();
  initShiftFormSubmit();
  initAssignScheduleForm();
  loadTurnosCatalog();
  loadWeeklySchedules();
  loadWorkersForScheduleAssign();
});

/**
 * Control de Pestañas (Catálogo de Turnos vs. Horarios Semanales)
 */
function initTabsNavigation() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

      btn.classList.add('active');
      const targetId = btn.getAttribute('data-tab');
      const targetContent = document.getElementById(targetId);
      if (targetContent) {
        targetContent.classList.add('active');
      }
      horariosState.activeTab = (targetId === 'tabCatalog') ? 'catalog' : 'weekly';
    });
  });
}

/**
 * Carga el catálogo parametrizado de turnos
 */
async function loadTurnosCatalog() {
  const grid = document.getElementById('turnosGrid');
  if (grid) {
    grid.innerHTML = Array(4).fill(0).map(() => `
      <div class="skeleton-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div class="skeleton skeleton-pill" style="width: 55px; height: 1.8rem;"></div>
          <div class="skeleton skeleton-pill" style="width: 60px;"></div>
        </div>
        <div class="skeleton skeleton-text w-80" style="height: 1.1rem; margin-top: 0.5rem;"></div>
        <div class="skeleton skeleton-text w-60"></div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem;">
          <div class="skeleton skeleton-text" style="width: 110px;"></div>
          <div class="skeleton skeleton-pill" style="width: 65px;"></div>
        </div>
      </div>
    `).join('');
  }

  try {
    const res = await API.get('turnos/listar.php');
    if (res && res.ok) {
      horariosState.turnos = res.data;
      updateTurnosStatsUI(res.estadisticas);
      renderTurnosGrid(res.data);
      populateShiftSelectOptions(res.data);
    }
  } catch (error) {
    console.error('Error cargando turnos:', error);
  }
}

/**
 * Carga las pautas de horarios semanales
 */
async function loadWeeklySchedules() {
  const tbody = document.getElementById('weeklyTableBody');
  if (tbody) {
    tbody.innerHTML = Array(5).fill(0).map(() => `
      <tr class="skeleton-table-row">
        <td>
          <div class="skeleton skeleton-text w-80" style="height: 0.95rem;"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.75rem;"></div>
        </td>
        <td>
          <div class="skeleton skeleton-text w-60"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
        </td>
        ${Array(7).fill(0).map(() => `
          <td style="text-align: center;"><div class="skeleton skeleton-pill" style="width: 48px; height: 1.6rem; margin: 0 auto;"></div></td>
        `).join('')}
        <td style="text-align: center;"><div class="skeleton skeleton-pill" style="width: 65px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-btn" style="width: 65px; height: 1.8rem; margin: 0 auto;"></div></td>
      </tr>
    `).join('');
  }

  try {
    const res = await API.get('horarios/listar.php');
    if (res && res.ok) {
      horariosState.horariosSemanales = res.data;
      renderWeeklyTable(res.data);
    }
  } catch (error) {
    console.error('Error cargando horarios semanales:', error);
  }
}

/**
 * Actualiza los contadores KPI de turnos
 */
function updateTurnosStatsUI(stats) {
  if (!stats) return;
  const setEl = (id, v) => {
    const el = document.getElementById(id);
    if (el) el.textContent = v;
  };

  setEl('kpiTotalTurnos', stats.total_turnos);
  setEl('kpiTurnos6h', stats.turnos_6h);
  setEl('kpiGuardias12h', stats.guardias_12h);
  setEl('kpiCruzaMedianoche', stats.cruza_medianoche);
}

/**
 * Renderiza la cuadrícula de tarjetas de turnos
 */
function renderTurnosGrid(turnos) {
  const grid = document.getElementById('turnosGrid');
  if (!grid) return;

  if (!turnos || turnos.length === 0) {
    grid.innerHTML = `
      <div style="grid-column: 1 / -1; padding: 3rem; text-align: center; color: var(--text-secondary);">
        No hay turnos registrados en el catálogo.
      </div>
    `;
    return;
  }

  grid.innerHTML = turnos.map(t => {
    const isMidnight = intval(t.cruza_medianoche) === 1;
    const isGuardia = intval(t.es_guardia) === 1;
    const hours = parseFloat(t.horas_computables).toFixed(1);

    return `
      <div class="turno-card" style="--turno-color: ${t.color || '#0284c7'};">
        <div>
          <div class="turno-card-header">
            <div class="turno-code-box">
              <span class="turno-code-badge" style="background-color: ${t.color ? t.color + '15' : 'var(--primary-50)'}; color: ${t.color || 'var(--primary-700)'}; border-color: ${t.color ? t.color + '40' : 'var(--primary-200)'};">
                ${escapeHtml(t.codigo)}
              </span>
              <div class="turno-title-info">
                <h3>${escapeHtml(t.nombre)}</h3>
                <p>${escapeHtml(t.descripcion || 'Sin descripción')}</p>
              </div>
            </div>
            <span class="badge ${intval(t.estado) === 1 ? 'badge-present' : 'badge-neutral'}">
              ${intval(t.estado) === 1 ? 'Activo' : 'Inactivo'}
            </span>
          </div>

          <!-- Bloque Horario -->
          <div class="turno-schedule-block" style="margin-top: 0.85rem;">
            <div class="time-range">
              <span>⏰</span>
              <span>${escapeHtml(t.hora_inicio)} - ${escapeHtml(t.hora_fin)}</span>
              ${isMidnight ? '<span style="font-size: 0.65rem; color: var(--status-justified-accent); font-weight: 700;">(+1d)</span>' : ''}
            </div>
            <span class="hours-comp-badge">
              ${hours} hrs
            </span>
          </div>

          <!-- Indicadores y Flags de Regla de Negocio -->
          <div class="turno-flags" style="margin-top: 0.75rem;">
            ${isGuardia ? '<span class="badge badge-guardia">🛡 Guardia Hospitalaria</span>' : ''}
            ${isMidnight ? '<span class="badge badge-midnight">🌙 Cruza Medianoche</span>' : ''}
            ${parseFloat(t.horas_computables) === 6.0 ? '<span class="badge badge-present">Jornada 6h</span>' : ''}
            ${parseFloat(t.horas_computables) === 12.0 ? '<span class="badge badge-late">Jornada 12h</span>' : ''}
          </div>
        </div>

        <div class="turno-card-footer">
          <span>Código: <strong>${escapeHtml(t.codigo)}</strong></span>
          <button type="button" class="btn btn-secondary btn-sm" onclick="editarTurno(${t.turno_id})">
            Editar Parámetros
          </button>
        </div>
      </div>
    `;
  }).join('');
}

/**
 * Renderiza la tabla de asignaciones semanales (rrhh.Horario)
 */
function renderWeeklyTable(list) {
  const tbody = document.getElementById('weeklyTableBody');
  if (!tbody) return;

  if (!list || list.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
          No se registran horarios semanales habituales configurados.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = list.map(h => {
    return `
      <tr>
        <!-- Trabajador -->
        <td>
          <div style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem;">
            ${escapeHtml(h.nombre_completo)}
          </div>
          <div style="font-size: 0.74rem; color: var(--text-secondary);">
            DNI: ${escapeHtml(h.dni)} • ${escapeHtml(h.cargo_nombre || 'Personal')}
          </div>
        </td>

        <!-- Servicio -->
        <td>
          <div style="font-size: 0.82rem; font-weight: 500;">
            ${escapeHtml(h.servicio_nombre || 'Servicio General')}
          </div>
          <div style="font-size: 0.72rem; color: var(--text-tertiary);">
            ${escapeHtml(h.tipo_patron || 'Horario Fijo')}
          </div>
        </td>

        <!-- Días Lunes a Domingo (1 al 7) -->
        ${[1, 2, 3, 4, 5, 6, 7].map(dia => {
          const dInfo = h.dias[dia];
          if (!dInfo) {
            return `<td><span class="shift-chip chip-lib">LIB<span class="shift-chip-time">Libre</span></span></td>`;
          }
          const cod = dInfo.codigo ? dInfo.codigo.toLowerCase() : 'm';
          return `
            <td>
              <span class="shift-chip chip-${cod}" title="${dInfo.hora || ''}">
                ${escapeHtml(dInfo.codigo || 'M')}
                <span class="shift-chip-time">${dInfo.horas ? dInfo.horas + 'h' : ''}</span>
              </span>
            </td>
          `;
        }).join('')}

        <!-- Total Horas Semanales -->
        <td style="font-weight: 800; font-family: var(--font-display); color: var(--primary-700); font-size: 0.95rem;">
          ${h.total_horas_semana ? h.total_horas_semana.toFixed(1) + 'h' : '—'}
        </td>

        <!-- Acciones -->
        <td style="text-align: center;">
          <button type="button" class="btn btn-outline btn-xs" onclick="editarHorarioSemanal(${h.personal_id})" title="Editar pauta semanal del trabajador" style="font-size: 0.72rem; padding: 0.2rem 0.5rem; display: inline-flex; align-items: center; gap: 0.25rem;">
            <span>✏</span>
            <span>Editar</span>
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Abre el modal de asignación semanal precargando el trabajador y sus turnos actuales
 */
function editarHorarioSemanal(personalId) {
  const item = (horariosState.horariosSemanales || []).find(w => String(w.personal_id) === String(personalId));
  const workerSelect = document.getElementById('assignWorkerSelect');
  if (workerSelect) {
    let opt = workerSelect.querySelector(`option[value="${personalId}"]`);
    if (!opt && item) {
      opt = document.createElement('option');
      opt.value = personalId;
      opt.textContent = `${item.dni} - ${item.nombre_completo} (${item.cargo_nombre || ''})`;
      workerSelect.appendChild(opt);
    }
    workerSelect.value = String(personalId);
  }

  // Precargar turnos en los selects Lunes a Domingo
  if (item && item.dias) {
    for (let d = 1; d <= 7; d++) {
      const sel = document.getElementById(`assignDia${d}`);
      if (sel) {
        sel.value = item.dias[d]?.turno_id ? String(item.dias[d].turno_id) : '0';
      }
    }
  }

  const modalTitle = document.querySelector('#modalAsignarHorario .modal-title');
  if (modalTitle) {
    modalTitle.innerHTML = '<span>🗓</span> Editar Pauta de Horario Semanal';
  }
  const submitBtn = document.getElementById('btnSubmitAsignarHorario');
  if (submitBtn) {
    submitBtn.textContent = 'Guardar Cambios de Pauta';
  }

  openModal('modalAsignarHorario');
  recalculateModalWorkload();
}

function abrirModalAsignarHorario() {
  const modalTitle = document.querySelector('#modalAsignarHorario .modal-title');
  if (modalTitle) {
    modalTitle.innerHTML = '<span>🗓</span> Asignar Pauta de Horario Semanal';
  }
  const submitBtn = document.getElementById('btnSubmitAsignarHorario');
  if (submitBtn) {
    submitBtn.textContent = 'Asignar Pauta Semanal';
  }
  openModal('modalAsignarHorario');
  recalculateModalWorkload();
}

window.editarHorarioSemanal = editarHorarioSemanal;
window.abrirModalAsignarHorario = abrirModalAsignarHorario;

/**
 * Cálculo dinámico en el formulario de turnos
 */
function initShiftFormCalc() {
  const inicioEl = document.getElementById('turnoHoraInicio');
  const finEl = document.getElementById('turnoHoraFin');
  const horasCompEl = document.getElementById('turnoHorasComputables');
  const cruzaEl = document.getElementById('turnoCruzaMedianoche');
  const guardiaEl = document.getElementById('turnoEsGuardia');
  const calcText = document.getElementById('turnoCalcPreview');

  if (!inicioEl || !finEl) return;

  const recalculate = () => {
    const valInicio = inicioEl.value;
    const valFin = finEl.value;

    if (!valInicio || !valFin) return;

    const [hI, mI] = valInicio.split(':').map(Number);
    const [hF, mF] = valFin.split(':').map(Number);

    const minInicio = hI * 60 + mI;
    const minFin = hF * 60 + mF;

    let diffMinutes = 0;
    let cruza = false;

    if (minFin < minInicio) {
      cruza = true;
      diffMinutes = (1440 - minInicio) + minFin;
    } else {
      diffMinutes = minFin - minInicio;
    }

    const diffHours = (diffMinutes / 60);

    if (cruzaEl) cruzaEl.checked = cruza;
    if (horasCompEl) horasCompEl.value = diffHours.toFixed(2);
    if (guardiaEl && diffHours >= 11.5) guardiaEl.checked = true;

    if (calcText) {
      calcText.innerHTML = `
        <span>⏱ <strong>${diffHours.toFixed(1)} horas</strong> calculadas</span>
        <span>${cruza ? '🌙 <strong style="color: var(--status-justified-accent);">Cruza Medianoche (+1 día)</strong>' : '☀️ En el mismo día'}</span>
      `;
    }
  };

  inicioEl.addEventListener('change', recalculate);
  finEl.addEventListener('change', recalculate);
}

/**
 * Enviar formulario de nuevo/editar turno
 */
function initShiftFormSubmit() {
  const form = document.getElementById('formTurno');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
      turno_id: intval(document.getElementById('turnoId').value),
      codigo: document.getElementById('turnoCodigo').value.trim(),
      nombre: document.getElementById('turnoNombre').value.trim(),
      descripcion: document.getElementById('turnoDescripcion').value.trim(),
      hora_inicio: document.getElementById('turnoHoraInicio').value,
      hora_fin: document.getElementById('turnoHoraFin').value,
      horas_computables: parseFloat(document.getElementById('turnoHorasComputables').value),
      cruza_medianoche: document.getElementById('turnoCruzaMedianoche').checked ? 1 : 0,
      es_guardia: document.getElementById('turnoEsGuardia').checked ? 1 : 0,
      color: document.getElementById('turnoColor').value,
      estado: 1
    };

    try {
      const res = await API.post('turnos/guardar.php', payload);
      if (res && res.ok) {
        closeModal('modalTurno');
        showToast(res.message || 'Turno guardado exitosamente.', 'success');
        form.reset();
        loadTurnosCatalog();
      } else {
        showToast(res.message || 'No se pudo guardar el turno.', 'danger');
      }
    } catch (error) {
      showToast('Error al procesar la solicitud del turno.', 'danger');
    }
  });
}

function abrirModalNuevoTurno() {
  const form = document.getElementById('formTurno');
  if (form) form.reset();

  document.getElementById('turnoId').value = '0';
  document.getElementById('modalTurnoTitle').textContent = 'Registrar Nuevo Turno Oficial';
  document.getElementById('turnoHoraInicio').value = '08:00';
  document.getElementById('turnoHoraFin').value = '14:00';
  document.getElementById('turnoHorasComputables').value = '6.00';
  document.getElementById('turnoCruzaMedianoche').checked = false;
  document.getElementById('turnoEsGuardia').checked = false;
  document.getElementById('turnoColor').value = '#0284c7';

  openModal('modalTurno');
}

function editarTurno(turnoId) {
  const t = horariosState.turnos.find(item => item.turno_id === turnoId);
  if (!t) return;

  document.getElementById('turnoId').value = t.turno_id;
  document.getElementById('modalTurnoTitle').textContent = `Editar Turno: ${t.codigo} - ${t.nombre}`;
  document.getElementById('turnoCodigo').value = t.codigo;
  document.getElementById('turnoNombre').value = t.nombre;
  document.getElementById('turnoDescripcion').value = t.descripcion || '';
  document.getElementById('turnoHoraInicio').value = t.hora_inicio;
  document.getElementById('turnoHoraFin').value = t.hora_fin;
  document.getElementById('turnoHorasComputables').value = t.horas_computables;
  document.getElementById('turnoCruzaMedianoche').checked = (intval(t.cruza_medianoche) === 1);
  document.getElementById('turnoEsGuardia').checked = (intval(t.es_guardia) === 1);
  document.getElementById('turnoColor').value = t.color || '#0284c7';

  openModal('modalTurno');
}

/**
 * Formulario de asignación de horario semanal
 */
function initAssignScheduleForm() {
  const form = document.getElementById('formAsignarHorario');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const workerSelect = document.getElementById('assignWorkerSelect');
    const workerId = intval(workerSelect.value);

    if (workerId <= 0) {
      showToast('Seleccione un trabajador para asignar horario.', 'danger');
      return;
    }

    const dias = {};
    for (let i = 1; i <= 7; i++) {
      const select = document.getElementById(`assignDia${i}`);
      if (select) {
        dias[i] = intval(select.value);
      }
    }

    try {
      const res = await API.post('horarios/asignar.php', {
        personal_id: workerId,
        fecha_inicio: document.getElementById('assignFechaInicio').value || '2026-09-01',
        dias
      });

      if (res && res.ok) {
        closeModal('modalAsignarHorario');
        showToast(res.message || 'Pauta de horario semanal asignada exitosamente.', 'success');
        loadWeeklySchedules();
      } else {
        const errorMsg = (res && res.message) ? res.message : 'Error al asignar horario semanal.';
        showToast(errorMsg, 'danger');
      }
    } catch (error) {
      showToast(error.message || 'Error de comunicación con el servidor.', 'danger');
    }
  });
}

function populateShiftSelectOptions(turnos) {
  for (let i = 1; i <= 7; i++) {
    const sel = document.getElementById(`assignDia${i}`);
    if (sel) {
      sel.innerHTML = `
        <option value="0">LIB - Libre / Descanso (0h)</option>
        ${turnos.map(t => `
          <option value="${t.turno_id}">${t.codigo} - ${t.nombre} (${t.horas_computables}h)</option>
        `).join('')}
      `;
      // Por defecto L-V: Mañana (TurnoID 1) o Administrativo (TurnoID 5); Fin de semana: Libre (0)
      if (i <= 5) {
        sel.value = "1";
      } else {
        sel.value = "0";
      }

      // Escuchar cambios reactivos para recalcular carga horaria
      sel.addEventListener('change', recalculateModalWorkload);
    }
  }

  // Recalcular una vez poblado
  recalculateModalWorkload();
}

function intval(v) {
  return parseInt(v, 10) || 0;
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
 * Carga dinámicamente todo el personal de la BD para el modal de asignación de horario semanal
 */
async function loadWorkersForScheduleAssign() {
  const select = document.getElementById('assignWorkerSelect');
  if (!select) return;

  try {
    const res = await API.get('personal/listar.php', { limit: 100 });
    if (res && res.ok && Array.isArray(res.data)) {
      horariosState.workers = res.data;
      select.innerHTML = '<option value="">Seleccione personal...</option>';
      res.data.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.personal_id;
        opt.textContent = `${p.dni} - ${p.nombre_completo} (${p.cargo_nombre || 'Personal'} - ${p.servicio_nombre || 'Servicio'})`;
        select.appendChild(opt);
      });

      // Escuchar cambios en el trabajador y fecha de vigencia
      select.addEventListener('change', recalculateModalWorkload);
      const dateInput = document.getElementById('assignFechaInicio');
      if (dateInput) {
        dateInput.addEventListener('change', recalculateModalWorkload);
      }

      // Si vino un worker_id por URL (por ejemplo desde la Ficha de Personal)
      const urlParams = new URLSearchParams(window.location.search);
      const targetWorkerId = urlParams.get('worker_id');
      if (targetWorkerId) {
        select.value = targetWorkerId;
        // Cambiar a la pestaña de horarios semanales y abrir modal
        const weeklyTabBtn = document.querySelector('.tab-btn[data-tab="tabWeekly"]');
        if (weeklyTabBtn) weeklyTabBtn.click();
        openModal('modalAsignarHorario');
      }

      recalculateModalWorkload();
    }
  } catch (err) {
    console.error('Error cargando lista de personal para asignar horario:', err);
  }
}

/**
 * Obtiene la duración en horas de un turno según el catálogo
 */
function getTurnoDuration(turnoId) {
  const tid = intval(turnoId);
  if (tid <= 0) return 0;
  const t = horariosState.turnos.find(x => intval(x.turno_id) === tid);
  if (t) {
    return parseFloat(t.horas_computables) || 0;
  }
  return 0;
}

/**
 * Recalcula la Carga Horaria (Semanal y Proyección Mensual Real)
 * Aplica reglas de negocio: Asistencial (150h meta) vs Administrativo (L-V)
 */
function recalculateModalWorkload() {
  const workerSelect = document.getElementById('assignWorkerSelect');
  const dateInput = document.getElementById('assignFechaInicio');
  const weeklyText = document.getElementById('workloadWeeklyText');
  const weeklyLimit = document.getElementById('workloadWeeklyLimit');
  const monthlyText = document.getElementById('workloadMonthlyText');
  const monthlyPeriod = document.getElementById('workloadMonthlyPeriod');
  const targetText = document.getElementById('workloadTargetText');
  const pendingText = document.getElementById('workloadPendingText');
  const regimeBadge = document.getElementById('workloadRegimeBadge');
  const statusBox = document.getElementById('workloadStatusBox');
  const statusIcon = document.getElementById('workloadStatusIcon');
  const statusMsg = document.getElementById('workloadStatusMessage');
  const submitBtn = document.getElementById('btnSubmitAsignarHorario');

  if (!weeklyText) return;

  const workerId = intval(workerSelect ? workerSelect.value : 0);
  const worker = (horariosState.workers || []).find(w => intval(w.personal_id) === workerId);
  
  // Determinar régimen: ASISTENCIAL vs ADMINISTRATIVO
  let isAdministrativo = false;
  let regimeName = 'Personal Asistencial';
  let maxSemanal = 48.0;
  let targetMensual = 150.0;
  let maxMensual = null; // NULL: No bloquea por superar 150h

  if (worker) {
    const rawRegimen = (worker.regimen_laboral || '').toUpperCase();
    const cargo = (worker.cargo_nombre || '').toLowerCase();
    if (rawRegimen === 'ADMINISTRATIVO' || cargo.includes('admin') || cargo.includes('informát') || cargo.includes('contab') || cargo.includes('secretar') || cargo.includes('rrhh') || cargo.includes('asistente')) {
      isAdministrativo = true;
      regimeName = 'Personal Administrativo';
      maxSemanal = 44.0;
      targetMensual = null; // Administrativo se rige por días laborables de su jornada
    }
  }

  if (regimeBadge) {
    regimeBadge.textContent = `Tipo: ${regimeName}`;
    regimeBadge.className = isAdministrativo ? 'badge badge-neutral' : 'badge badge-present';
  }

  // 1. CÁLCULO DE HORAS SEMANALES PROGRAMADAS
  const turnosPorDia = {};
  let totalHorasSemana = 0;

  for (let i = 1; i <= 7; i++) {
    const sel = document.getElementById(`assignDia${i}`);
    const tid = sel ? intval(sel.value) : 0;
    turnosPorDia[i] = tid;
    totalHorasSemana += getTurnoDuration(tid);
  }

  weeklyText.textContent = `${totalHorasSemana.toFixed(1)} h`;
  weeklyLimit.textContent = `Máx: ${maxSemanal.toFixed(0)}h`;

  // 2. PROYECCIÓN MENSUAL REAL POR CALENDARIO (Día por día desde FechaInicio hasta Fin de Mes)
  const fechaStr = (dateInput && dateInput.value) ? dateInput.value : '2026-09-01';
  const parts = fechaStr.split('-');
  const year = intval(parts[0]) || 2026;
  const month = intval(parts[1]) || 9;
  const startDay = intval(parts[2]) || 1;

  // Días totales del mes correspondiente (28, 29, 30 o 31)
  const daysInMonth = new Date(year, month, 0).getDate();
  const validStartDay = Math.min(Math.max(1, startDay), daysInMonth);

  let totalHorasMes = 0;
  let diasProgramados = 0;

  for (let d = validStartDay; d <= daysInMonth; d++) {
    // JavaScript getDay(): 0=Domingo, 1=Lunes, 2=Martes... 6=Sábado
    const dt = new Date(year, month - 1, d);
    let jsDay = dt.getDay();
    let minsaDay = (jsDay === 0) ? 7 : jsDay; // Convertir a 1=Lunes .. 7=Domingo

    const tid = turnosPorDia[minsaDay] || 0;
    const dur = getTurnoDuration(tid);
    totalHorasMes += dur;
    if (dur > 0) diasProgramados++;
  }

  monthlyText.textContent = `${totalHorasMes.toFixed(1)} h`;
  monthlyPeriod.textContent = `Del ${validStartDay} al ${daysInMonth} (${daysInMonth - validStartDay + 1} días)`;

  // 3. TARGET Y HORAS PENDIENTES
  if (targetMensual !== null) {
    targetText.textContent = `Obj: ${targetMensual.toFixed(0)}h`;
    const diff = targetMensual - totalHorasMes;
    if (diff > 0) {
      pendingText.textContent = `Faltan: ${diff.toFixed(1)}h`;
      pendingText.style.color = '#eab308'; // Ámbar advertencia
    } else if (diff < 0) {
      pendingText.textContent = `+${Math.abs(diff).toFixed(1)}h sobre obj.`;
      pendingText.style.color = '#38bdf8'; // Azul información
    } else {
      pendingText.textContent = `Meta cumplida exacto`;
      pendingText.style.color = '#4ade80';
    }
  } else {
    // Administrativo
    targetText.textContent = `Jornada L-V`;
    pendingText.textContent = `${diasProgramados} días act.`;
    pendingText.style.color = 'var(--text-secondary)';
  }

  // 4. EVALUACIÓN DE ESTADO (CORRECTO / ADVERTENCIA / BLOQUEO)
  let estado = 'CORRECTO';
  let icon = '✓';
  let message = 'Programación válida dentro de los parámetros';

  // Regla BLOQUEO: Superar el máximo semanal configurado
  if (totalHorasSemana > maxSemanal) {
    estado = 'BLOQUEO';
    icon = '🛑';
    message = `Bloqueo: Carga semanal (${totalHorasSemana.toFixed(1)}h) excede el máximo permitido (${maxSemanal.toFixed(0)}h)`;
  } else if (maxMensual !== null && totalHorasMes > maxMensual) {
    // Si hubiere un tope mensual obligatorio estricto
    estado = 'BLOQUEO';
    icon = '🛑';
    message = `Bloqueo: Horas proyectadas (${totalHorasMes.toFixed(1)}h) superan el tope mensual (${maxMensual.toFixed(0)}h)`;
  } else if (totalHorasSemana === 0 && workerId > 0) {
    estado = 'ADVERTENCIA';
    icon = '⚠️';
    message = 'Atención: No se ha asignado ningún turno computable en la semana (todos libres)';
  } else if (targetMensual !== null && totalHorasMes < targetMensual) {
    // ADVERTENCIA para asistencial si proyecta menos de 150h (NO BLOQUEA, solo alerta)
    estado = 'ADVERTENCIA';
    icon = '⚠️';
    message = `Advertencia: Proyección de ${totalHorasMes.toFixed(1)}h no alcanza la meta mensual de ${targetMensual.toFixed(0)}h (faltan ${(targetMensual - totalHorasMes).toFixed(1)}h)`;
  } else if (targetMensual !== null && totalHorasMes > targetMensual) {
    // No bloquea si supera 150h, es admisible como turno extraordinario/guardias
    estado = 'CORRECTO';
    icon = 'ℹ️';
    message = `Cumple objetivo mensual de ${targetMensual.toFixed(0)}h (cuenta con +${(totalHorasMes - targetMensual).toFixed(1)}h proyectadas)`;
  }

  // Aplicar Estilos del Estado
  if (statusBox && statusIcon && statusMsg) {
    statusIcon.textContent = icon;
    statusMsg.textContent = message;

    if (estado === 'BLOQUEO') {
      statusBox.style.background = 'rgba(239, 68, 68, 0.15)';
      statusBox.style.borderColor = 'rgba(239, 68, 68, 0.4)';
      statusBox.style.color = '#f87171';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.title = 'Corrija los excesos de carga horaria antes de guardar';
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
      }
    } else if (estado === 'ADVERTENCIA') {
      statusBox.style.background = 'rgba(234, 179, 8, 0.12)';
      statusBox.style.borderColor = 'rgba(234, 179, 8, 0.35)';
      statusBox.style.color = '#facc15';
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.title = '';
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
      }
    } else {
      statusBox.style.background = 'rgba(34, 197, 94, 0.1)';
      statusBox.style.borderColor = 'rgba(34, 197, 94, 0.25)';
      statusBox.style.color = '#4ade80';
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.title = '';
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
      }
    }
  }
}

/**
 * Atajos de productividad para la asignación semanal
 */
function copiarLunesATodos() {
  const lunesSel = document.getElementById('assignDia1');
  if (!lunesSel) return;
  const val = lunesSel.value;
  // Copiar a Martes (2), Miércoles (3), Jueves (4) y Viernes (5)
  for (let i = 2; i <= 5; i++) {
    const sel = document.getElementById(`assignDia${i}`);
    if (sel) sel.value = val;
  }
  recalculateModalWorkload();
  if (typeof showToast === 'function') {
    showToast('Turno de Lunes replicado a Martes, Miércoles, Jueves y Viernes.', 'info');
  }
}

function limpiarTodosADescanso() {
  for (let i = 1; i <= 7; i++) {
    const sel = document.getElementById(`assignDia${i}`);
    if (sel) sel.value = '0';
  }
  recalculateModalWorkload();
  if (typeof showToast === 'function') {
    showToast('Todos los días configurados como Libre / Descanso (0h).', 'info');
  }
}

window.copiarLunesATodos = copiarLunesATodos;
window.limpiarTodosADescanso = limpiarTodosADescanso;
