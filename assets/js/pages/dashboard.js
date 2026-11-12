/**
 * Lógica de Cliente: Inicio / Dashboard
 * Hospital Distrital de Pacasmayo
 * 
 * Carga directa y minimalista de asistencia diaria desde Microsoft SQL Server
 */

function getTodayIsoString() {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
  const value = type => parts.find(part => part.type === type).value;
  return `${value('year')}-${value('month')}-${value('day')}`;
}

function getYesterdayIsoString() {
  const date = new Date(`${getTodayIsoString()}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() - 1);
  return date.toISOString().slice(0, 10);
}

let dashState = {
  fecha: getTodayIsoString(),
  filtroEstado: 'TODOS',
  busqueda: '',
  todosRegistros: [],
  areaId: '',
  servicioId: '',
  requestId: 0,
  personalId: '',
  page: 1,
  pageSize: 10,
  loading: false
};

document.addEventListener('DOMContentLoaded', () => {
  dashState.servicioId = document.getElementById('dashServiceSelect')?.value || '';
  initDashboardControls();
  cargarAsistenciaDashboard();
});

/**
 * Inicializa controles de fecha, búsqueda y filtros
 */
function initDashboardControls() {
  const datePicker = document.getElementById('dashDatePicker');
  const btnHoy = document.getElementById('btnDateHoy');
  const btnAyer = document.getElementById('btnDateAyer');
  const searchInput = document.getElementById('dashSearchInput');
  const filterPills = document.querySelectorAll('.dash-pill-btn');
  const kpiCards = document.querySelectorAll('.dash-kpi-card');

  for (const [id, key] of [['dashAreaSelect','areaId'], ['dashServiceSelect','servicioId']]) {
    document.getElementById(id)?.addEventListener('change', event => {
      dashState[key] = event.target.value;
      cargarAsistenciaDashboard();
    });
  }
  document.getElementById('dashRefresh')?.addEventListener('click', () => cargarAsistenciaDashboard(true));

  document.getElementById('dashWorkerSelect')?.addEventListener('change', event => {
    dashState.personalId = event.target.value;
    dashState.page = 1;
    renderTablaAsistencia();
  });
  for (const [id, direction] of [['dashPrevPage', -1], ['dashNextPage', 1]]) {
    document.getElementById(id)?.addEventListener('click', () => {
      dashState.page += direction;
      renderTablaAsistencia();
    });
  }

  // Selector de fecha
  if (datePicker) {
    datePicker.value = dashState.fecha;
    datePicker.addEventListener('change', (e) => {
      dashState.fecha = e.target.value;
      if (btnHoy) btnHoy.classList.remove('active');
      if (btnAyer) btnAyer.classList.remove('active');
      if (dashState.fecha === getTodayIsoString()) btnHoy?.classList.add('active');
      cargarAsistenciaDashboard();
    });
  }

  // Botón Hoy
  if (btnHoy) {
    btnHoy.addEventListener('click', () => {
      dashState.fecha = getTodayIsoString();
      if (datePicker) datePicker.value = dashState.fecha;
      btnHoy.classList.add('active');
      if (btnAyer) btnAyer.classList.remove('active');
      cargarAsistenciaDashboard();
    });
  }

  // Botón Ayer
  if (btnAyer) {
    btnAyer.addEventListener('click', () => {
      dashState.fecha = getYesterdayIsoString();
      if (datePicker) datePicker.value = dashState.fecha;
      btnAyer.classList.add('active');
      if (btnHoy) btnHoy.classList.remove('active');
      cargarAsistenciaDashboard();
    });
  }

  // Buscador en tiempo real
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      dashState.busqueda = e.target.value.toLowerCase().trim();
      dashState.page = 1;
      renderTablaAsistencia();
    });
  }

  // Filtros tipo pill
  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      const estado = pill.getAttribute('data-estado');
      dashState.filtroEstado = estado;
      dashState.page = 1;
      filterPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      renderTablaAsistencia();
    });
  });

  // Clic en tarjetas KPI para filtrar rápido
  kpiCards.forEach(card => {
    card.addEventListener('click', () => {
      const filter = card.getAttribute('data-filter');
      if (!filter) return;
      dashState.filtroEstado = filter;
      dashState.page = 1;
      filterPills.forEach(p => {
        if (p.getAttribute('data-estado') === filter) {
          p.classList.add('active');
        } else {
          p.classList.remove('active');
        }
      });
      renderTablaAsistencia();
    });
  });
}

/**
 * Consulta la API en vivo de asistencia diaria
 */
async function cargarAsistenciaDashboard(forceRefresh = false) {
  const requestId = ++dashState.requestId;
  const date = dashState.fecha;
  dashState.loading = true;
  dashState.page = 1;
  const tbody = document.getElementById('dashTableBody');
  dashState.todosRegistros = [];
  actualizarKpisUI({total_programados:null, asistio_puntual:null, tardanzas:null, faltas:null});
  actualizarPillsUI();
  for (const id of ['dashPendingMarks','dashUnscheduled','dashRest']) setDashboardText(id, '—');
  for (const id of ['dashBiometric','dashDataQuality','dashHrPending','dashPendingDetail']) setDashboardText(id, 'Consultando…');
  setDashboardText('dashCoverageBody', '');
  setDashboardText('dashConnectionStatus', `Consultando datos del ${date}…`);
  setDashboardText('dashDataSource', 'Consulta en curso');
  renderDashboardSkeletons();
  if (!date) {
    dashState.loading = false;
    actualizarKpisUI({});
    actualizarDashboardPagination(0);
    clearDashboardAttendanceLoading('Seleccione una fecha válida.');
    for (const id of ['dashBiometric','dashDataQuality','dashHrPending','dashPendingDetail']) setDashboardText(id, 'Seleccione una fecha válida.');
    setDashboardText('dashConnectionStatus', 'Seleccione una fecha válida.');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7">Seleccione una fecha válida.</td></tr>';
    return;
  }
  const params = {fecha: date};
  if (dashState.servicioId) params.servicio_id = dashState.servicioId;
  const operations = API.get('dashboard/operativo.php', {...params, area: dashState.areaId}, {useCache:!forceRefresh})
    .then(data => {
      if (requestId === dashState.requestId) renderDashboardOperations(data);
    }).catch(error => {
      if (requestId !== dashState.requestId) return;
      for (const id of ['dashBiometric','dashDataQuality','dashHrPending']) setDashboardText(id, `No disponible. ${error.message}`);
    });
  try {
    const data = await API.get('asistencia/diaria.php', params, {useCache:!forceRefresh});
    if (requestId !== dashState.requestId) return;
    if (!data?.ok) throw new Error('Respuesta de asistencia inválida.');
    if (dashState.areaId && (data.asistencias || []).some(row => !Object.prototype.hasOwnProperty.call(row, 'area_id'))) {
      throw new Error('El respaldo anterior no contiene áreas. Actualice la consulta al recuperar SQL Server.');
    }
    dashState.todosRegistros = (data.asistencias || []).filter(row => !dashState.areaId || String(row.area_id) === dashState.areaId);
    dashState.loading = false;
    actualizarDashboardWorkerSelect();
    actualizarKpisUI(summarizeDashboard(dashState.todosRegistros));
    actualizarPillsUI();
    renderDashboardCoverage();
    renderTablaAsistencia();
    const backup = data.conexion_sql === false || data._cache?.solo_lectura;
    const saved = data.consulta?.respaldo_guardado_en || data._cache?.guardado_en;
    const status = document.getElementById('dashConnectionStatus');
    if (status) {
      status.classList.toggle('is-warning', Boolean(backup));
      status.textContent = backup
        ? `Respaldo de consulta del ${date}. Guardado ${saved || 'en fecha no informada'}.`
        : `Fecha consultada ${date}. Actualizado ${data.consulta?.fecha_hora || 'recién'}.`;
    }
    setDashboardText('dashDataSource', backup ? 'Respaldo de consulta' : data._cache?.origen === 'CACHE_VIGENTE' ? 'Caché verificada' : 'Consulta a SQL Server');
  } catch (error) {
    if (requestId !== dashState.requestId) return;
    dashState.loading = false;
    actualizarKpisUI({});
    actualizarDashboardPagination(0);
    clearDashboardAttendanceLoading('Asistencia sin confirmar.');
    setDashboardText('dashConnectionStatus', `Asistencia no disponible. ${error.message}`);
    document.getElementById('dashConnectionStatus')?.classList.add('is-warning');
    setDashboardText('dashDataSource', 'Asistencia sin confirmar');
    setDashboardText('dashPendingDetail', 'Asistencia sin confirmar.');
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="dash-loading-cell">${escapeHtml(error.message)}. Puede volver a consultar con Actualizar.</td></tr>`;
  }
  await operations;
}

function clearDashboardAttendanceLoading(message) {
  document.getElementById('dashTableBody')?.setAttribute?.('aria-busy', 'false');
  for (const id of ['dashPendingMarks','dashUnscheduled','dashRest']) setDashboardText(id, '—');
  const coverage = document.getElementById('dashCoverageBody');
  if (coverage) coverage.innerHTML = `<tr><td colspan="5">${escapeHtml(message)}</td></tr>`;
  const worker = document.getElementById('dashWorkerSelect');
  if (worker) { worker.innerHTML = '<option value="">Personal sin confirmar</option>'; worker.disabled = true; }
}

function dashboardSkeletonRows(columns, rows = 5) {
  return Array.from({length:rows}, () => `<tr class="skeleton-table-row">${Array.from({length:columns}, () => '<td><div class="skeleton dash-skeleton-line" aria-hidden="true"></div></td>').join('')}</tr>`).join('');
}

function renderDashboardSkeletons() {
  document.getElementById('dashTableBody')?.setAttribute?.('aria-busy', 'true');
  for (const [id, columns] of [['dashTableBody',7], ['dashCoverageBody',5]]) {
    const element = document.getElementById(id);
    if (element) element.innerHTML = dashboardSkeletonRows(columns);
  }
  for (const id of ['dashBiometric','dashDataQuality','dashHrPending','dashPendingDetail']) {
    const element = document.getElementById(id);
    if (element) element.innerHTML = '<div class="skeleton dash-skeleton-line" aria-hidden="true"></div><div class="skeleton dash-skeleton-line" aria-hidden="true"></div>';
  }
  for (const id of ['dashKpiProgramados','dashKpiPresentes','dashKpiTardanzas','dashKpiFaltas','dashPendingMarks','dashUnscheduled','dashRest']) {
    const element = document.getElementById(id);
    if (element) element.innerHTML = '<span class="skeleton dash-skeleton-number" aria-hidden="true"></span>';
  }
  const worker = document.getElementById('dashWorkerSelect');
  if (worker) { worker.innerHTML = '<option value="">Cargando personal…</option>'; worker.disabled = true; }
  setDashboardText('dashFooterCount', 'Cargando personal…');
  for (const id of ['dashPrevPage','dashNextPage']) {
    const button = document.getElementById(id);
    if (button) button.disabled = true;
  }
}

function actualizarDashboardWorkerSelect() {
  const select = document.getElementById('dashWorkerSelect');
  if (!select) return;
  const workers = new Map(dashState.todosRegistros.map(row => [String(row.personal_id), row]));
  if (!workers.has(dashState.personalId)) dashState.personalId = '';
  select.innerHTML = '<option value="">Todo el personal</option>' + Array.from(workers.values()).map(row =>
    `<option value="${Number(row.personal_id)}">${escapeHtml(row.nombre_completo || 'Nombre sin registrar')}${row.dni ? ' · '+escapeHtml(row.dni) : ''}</option>`
  ).join('');
  select.value = dashState.personalId;
  select.disabled = workers.size === 0;
}

function actualizarDashboardPagination(total) {
  const pages = Math.max(1, Math.ceil(total / dashState.pageSize));
  dashState.page = Math.max(1, Math.min(dashState.page, pages));
  const start = (dashState.page - 1) * dashState.pageSize;
  setDashboardText('dashPageLabel', `Página ${dashState.page} de ${pages}`);
  setDashboardText('dashFooterCount', `Mostrando ${total ? start+1 : 0} a ${Math.min(start+dashState.pageSize,total)} de ${total} trabajadores`);
  const previous = document.getElementById('dashPrevPage');
  const next = document.getElementById('dashNextPage');
  if (previous) previous.disabled = dashState.page === 1;
  if (next) next.disabled = dashState.page === pages;
  return start;
}

function setDashboardText(id, value) {
  const element = document.getElementById(id);
  if (element) element.textContent = value;
}

function hasConfirmedShift(row) {
  return Boolean(row.turno_id && row.hora_prog_inicio && row.hora_prog_fin);
}

function isPendingMark(row) {
  return hasConfirmedShift(row) && !row.hora_entrada && ['SIN_DATOS','PROGRAMADO','EN_CURSO'].includes(row.estado_evaluado);
}

function summarizeDashboard(rows) {
  const count = state => rows.filter(row => row.estado_evaluado === state).length;
  const scheduled = rows.filter(hasConfirmedShift).length;
  const pending = rows.filter(isPendingMark);
  const unknown = rows.filter(row => !hasConfirmedShift(row) && row.estado_evaluado !== 'DESCANSO').length;
  setDashboardText('dashPendingMarks', pending.length);
  setDashboardText('dashUnscheduled', unknown);
  setDashboardText('dashRest', count('DESCANSO'));
  const before = pending.filter(row => row.estado_evaluado === 'PROGRAMADO').length;
  const during = pending.filter(row => row.estado_evaluado === 'EN_CURSO').length;
  const after = pending.filter(row => row.estado_evaluado === 'SIN_DATOS').length;
  setDashboardText('dashPendingDetail', `Sin marcación conciliada: ${before} por iniciar, ${during} en curso y ${after} con turno terminado. Requieren verificación.`);
  return {
    total_programados: scheduled, asistio_puntual: count('ASISTIO'), tardanzas: count('TARDANZA'),
    faltas: rows.filter(row => ['FALTA','FALTA_ADMINISTRATIVA'].includes(row.estado_evaluado)).length,
    porcentaje_puntualidad: scheduled > 0 && pending.length === 0 && unknown === 0 ? Math.round(count('ASISTIO') / scheduled * 1000) / 10 : null
  };
}

function renderDashboardCoverage() {
  const groups = new Map();
  for (const row of dashState.todosRegistros.filter(hasConfirmedShift)) {
    const key = JSON.stringify([row.area_id, row.servicio_id, row.turno_id, row.hora_prog_inicio, row.hora_prog_fin]);
    if (!groups.has(key)) groups.set(key, {row, total:0, pending:0});
    const group = groups.get(key);
    group.total++;
    if (isPendingMark(row)) group.pending++;
  }
  const body = document.getElementById('dashCoverageBody');
  if (!body) return;
  body.innerHTML = Array.from(groups.values()).map(({row,total,pending}) => `<tr>
    <td>${escapeHtml(row.area_nombre || 'Sin área asignada')}</td>
    <td>${escapeHtml(row.servicio_nombre || 'Sin servicio asignado')}</td>
    <td>${escapeHtml(row.turno_codigo || row.turno_nombre || '')} ${escapeHtml(row.hora_prog_inicio)}–${escapeHtml(row.hora_prog_fin)}</td>
    <td>${total}</td><td>${pending}</td></tr>`).join('') || '<tr><td colspan="5">Sin turnos confirmados para los filtros seleccionados.</td></tr>';
}

function renderDashboardOperations(data) {
  const values = (id, rows) => {
    const element = document.getElementById(id);
    if (element) element.innerHTML = '<dl>' + rows.map(([label,value]) => `<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value ?? 'No disponible')}</dd>`).join('') + '</dl>';
  };
  const quality = data.calidad;
  if (quality) values('dashDataQuality', [['Personal habilitado',quality.total],['Sin área',quality.sin_area],['Sin cargo',quality.sin_cargo],['Sin documento',quality.sin_documento],['Sin vínculo BioStar',quality.sin_vinculo]]);
  else setDashboardText('dashDataQuality', 'No se pudo consultar el padrón.');
  const hr = data.pendientes;
  if (hr) {
    values('dashHrPending', [['Contratos que vencen en 30 días',hr.contratos_por_vencer],['Solicitudes pendientes de dictamen',hr.solicitudes]]);
    const element = document.getElementById('dashHrPending');
    if (element) element.innerHTML += `<p>Vencimientos calculados desde ${escapeHtml(data.fecha)}.</p>`;
  } else setDashboardText('dashHrPending', 'No se pudieron consultar los pendientes.');
  const bio = data.biometrico;
  if (!bio) { setDashboardText('dashBiometric', 'No se pudo consultar la información biométrica.'); return; }
  const load = bio.ultima_carga;
  values('dashBiometric', [['Marcaciones de la fecha consultada',bio.marcaciones_fecha],['Última marca de esa fecha',bio.ultima_marcacion || 'Sin marcaciones'],['Última carga',load?.fecha_carga || 'Sin cargas registradas'],['Estado de la carga',load?.estado || 'Sin cargas registradas']]);
  const element = document.getElementById('dashBiometric');
  if (load && element) {
    element.innerHTML += `<p>Periodo con eventos almacenados: ${escapeHtml(load.desde || 'Sin eventos')} — ${escapeHtml(load.hasta || 'Sin eventos')}.</p>`;
    if (bio.pendientes_vinculacion !== null) element.innerHTML += `<p>Última carga: ${Number(bio.pendientes_vinculacion)} marcaciones sin vincular y ${Number(bio.incidencias_ultima_carga)} incidencias registradas.</p>`;
  }
}

/**
 * Actualiza los 4 indicadores KPI
 */
function actualizarKpisUI(resumen) {
  const elProg = document.getElementById('dashKpiProgramados');
  const elPres = document.getElementById('dashKpiPresentes');
  const elPct  = document.getElementById('dashKpiPresentesPct');
  const elTard = document.getElementById('dashKpiTardanzas');
  const elFalt = document.getElementById('dashKpiFaltas');

  const totalProg = resumen.total_programados ?? '—';
  const totalPres = resumen.asistio_puntual ?? '—';
  const totalTard = resumen.tardanzas ?? '—';
  const totalFalt = resumen.faltas ?? '—';
  const pctPres   = resumen.porcentaje_puntualidad;

  if (elProg) elProg.textContent = totalProg;
  if (elPres) elPres.textContent = totalPres;
  if (elPct)  elPct.textContent = pctPres == null ? 'Porcentaje pendiente de evaluación' : `${pctPres}% puntualidad`;
  if (elTard) elTard.textContent = totalTard;
  if (elFalt) elFalt.textContent = totalFalt;
}

/**
 * Actualiza los contadores de los botones de filtro
 */
function actualizarPillsUI() {
  const regs = dashState.todosRegistros;

  let countTodos = regs.length;
  let countPres = regs.filter(r => r.estado_evaluado === 'ASISTIO').length;
  let countTard = regs.filter(r => r.estado_evaluado === 'TARDANZA').length;
  let countFalt = regs.filter(r => ['FALTA', 'FALTA_ADMINISTRATIVA'].includes(r.estado_evaluado)).length;

  const pTodos = document.getElementById('countPillTodos');
  const pPres  = document.getElementById('countPillPresentes');
  const pTard  = document.getElementById('countPillTardanzas');
  const pFalt  = document.getElementById('countPillFaltas');

  if (pTodos) pTodos.textContent = countTodos;
  if (pPres)  pPres.textContent  = countPres;
  if (pTard)  pTard.textContent  = countTard;
  if (pFalt)  pFalt.textContent  = countFalt;
}

/**
 * Renderiza las filas de la tabla con filtros aplicados
 */
function renderTablaAsistencia() {
  const tbody = document.getElementById('dashTableBody');
  if (!tbody || dashState.loading) return;

  const filtrados = dashState.todosRegistros.filter(row => {
    if (dashState.personalId && String(row.personal_id) !== dashState.personalId) return false;
    // Filtro por estado
    if (dashState.filtroEstado === 'PROGRAMADOS' && !hasConfirmedShift(row)) return false;
    if (dashState.filtroEstado === 'ASISTIO' && row.estado_evaluado !== 'ASISTIO') return false;
    if (dashState.filtroEstado === 'TARDANZA' && row.estado_evaluado !== 'TARDANZA') return false;
    if (dashState.filtroEstado === 'FALTA' && !['FALTA', 'FALTA_ADMINISTRATIVA'].includes(row.estado_evaluado)) return false;

    if (dashState.filtroEstado === 'SIN_MARCACION' && !isPendingMark(row)) return false;
    if (dashState.filtroEstado === 'SIN_PROGRAMACION' && (hasConfirmedShift(row) || row.estado_evaluado === 'DESCANSO')) return false;
    if (dashState.filtroEstado === 'DESCANSO' && row.estado_evaluado !== 'DESCANSO') return false;

    // Filtro por texto de búsqueda
    if (dashState.busqueda) {
      const nom = (row.nombre_completo || '').toLowerCase();
      const dni = (row.dni || '').toLowerCase();
      const srv = (`${row.servicio_nombre || ''} ${row.area_nombre || ''}`).toLowerCase();
      const crg = (row.cargo || '').toLowerCase();
      if (!nom.includes(dashState.busqueda) && !dni.includes(dashState.busqueda) && !srv.includes(dashState.busqueda) && !crg.includes(dashState.busqueda)) {
        return false;
      }
    }

    return true;
  });

  tbody.setAttribute?.('aria-busy', 'false');
  const start = actualizarDashboardPagination(filtrados.length);

  if (filtrados.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="dash-loading-cell">
          No se encontraron servidores que coincidan con el criterio seleccionado.
        </td>
      </tr>
    `;
    return;
  }

  const html = filtrados.slice(start, start + dashState.pageSize).map(r => {
    // Solo un estado evaluado ASISTIO acredita asistencia en este panel.
    const estados = {
      ASISTIO: ['asistio', 'Asistió'],
      TARDANZA: ['tardanza', `Tardanza (+${Number(r.minutos_tardanza) || 0}m)`],
      FALTA: ['falta', 'Falta'],
      FALTA_ADMINISTRATIVA: ['falta', 'Falta administrativa'],
      PERMISO: ['permiso', 'Permiso'],
      LICENCIA: ['permiso', 'Licencia'],
      JUSTIFICADO: ['permiso', 'Justificado'],
      VACACIONES: ['permiso', 'Vacaciones'],
      VAC: ['permiso', 'Vacaciones'],
      DESCANSO: ['neutral', 'Descanso'],
      SIN_DATOS: ['neutral', hasConfirmedShift(r) ? 'Sin marcación conciliada' : 'Sin programación confirmada'],
      PROGRAMADO: ['neutral', 'Programado / sin marcación'],
      EN_CURSO: ['neutral', 'En curso / sin marcación']
    };
    const [badgeClass, badgeText] = Object.prototype.hasOwnProperty.call(estados, r.estado_evaluado)
      ? estados[r.estado_evaluado]
      : ['neutral', 'Pendiente de verificación'];

    // Formatear horas
    const horaIngreso = r.hora_entrada ? r.hora_entrada.substring(0, 5) : '--:--';
    const horaSalida = r.hora_salida ? r.hora_salida.substring(0, 5) : '--:--';

    let ingresoClass = 'hora-cell';
    if (r.estado_evaluado === 'ASISTIO') ingresoClass += ' presente';
    else if (r.estado_evaluado === 'TARDANZA') ingresoClass += ' tarde';
    else ingresoClass += ' vacia';

    const turnoProg = r.hora_prog_inicio && r.hora_prog_fin
      ? `${r.hora_prog_inicio.substring(0, 5)} - ${r.hora_prog_fin.substring(0, 5)}`
      : (r.turno_nombre || 'Sin programación confirmada');

    return `
      <tr>
        <td style="font-variant-numeric: tabular-nums; font-weight: 500; color: var(--text-secondary);">${escapeHtml(r.dni || '')}</td>
        <td>
          <div class="servidor-cell">
            <span class="servidor-nombre">${escapeHtml(r.nombre_completo || '')}</span>
            <span class="servidor-cargo">${escapeHtml(r.cargo || 'Servidor Público')}</span>
          </div>
        </td>
        <td>
          <span style="font-size: 0.84rem; color: var(--text-secondary);">${escapeHtml(r.servicio_nombre || 'Sin servicio asignado')}</span><br><small>${escapeHtml(r.area_nombre || 'Sin área asignada')}</small>
        </td>
        <td>
          <span style="font-size: 0.82rem; color: var(--text-secondary);">${escapeHtml(turnoProg)}</span>
        </td>
        <td style="text-align: center;">
          <span class="${ingresoClass}">${escapeHtml(horaIngreso)}</span>
        </td>
        <td style="text-align: center;">
          <span class="hora-cell ${r.hora_salida ? '' : 'vacia'}">${escapeHtml(horaSalida)}</span>
        </td>
        <td style="text-align: center;">
          <span class="badge-clean ${badgeClass}">${escapeHtml(badgeText)}</span>
        </td>
      </tr>
    `;
  }).join('');

  tbody.innerHTML = html;
}

function escapeHtml(text) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}
