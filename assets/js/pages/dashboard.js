/**
 * Lógica de Cliente: Inicio / Dashboard
 * Hospital Distrital de Pacasmayo
 * 
 * Carga directa y minimalista de asistencia diaria desde Microsoft SQL Server
 */

function getTodayIsoString() {
  const d = new Date();
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getYesterdayIsoString() {
  const d = new Date();
  d.setDate(d.getDate() - 1);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

let dashState = {
  fecha: getTodayIsoString(),
  filtroEstado: 'TODOS',
  busqueda: '',
  todosRegistros: []
};

document.addEventListener('DOMContentLoaded', () => {
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
      renderTablaAsistencia();
    });
  }

  // Filtros tipo pill
  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      const estado = pill.getAttribute('data-estado');
      dashState.filtroEstado = estado;
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
async function cargarAsistenciaDashboard() {
  const tbody = document.getElementById('dashTableBody');
  if (tbody) {
    tbody.innerHTML = Array(5).fill(0).map(() => `
      <tr class="skeleton-table-row">
        <td><div class="skeleton skeleton-text" style="width: 75px;"></div></td>
        <td>
          <div class="skeleton skeleton-text w-80" style="height: 0.95rem;"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.75rem;"></div>
        </td>
        <td>
          <div class="skeleton skeleton-text w-60"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
        </td>
        <td><div class="skeleton skeleton-pill" style="width: 85px;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-text" style="width: 50px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-pill" style="width: 75px; margin: 0 auto;"></div></td>
      </tr>
    `).join('');
  }

  try {
    const data = await API.get('asistencia/diaria.php', { fecha: dashState.fecha });

    if (data && data.ok) {
      dashState.todosRegistros = data.asistencias || [];
      actualizarKpisUI(data.resumen || {});
      actualizarPillsUI();
      renderTablaAsistencia();
    } else {
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" class="dash-loading-cell" style="color: var(--text-secondary);">
              No se encontraron registros de programación para esta fecha.
            </td>
          </tr>
        `;
      }
    }
  } catch (error) {
    console.error('Error al cargar datos del dashboard:', error);
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="dash-loading-cell" style="color: #dc2626;">
            Error de conexión al cargar la asistencia.
          </td>
        </tr>
      `;
    }
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

  const totalProg = resumen.total_programados ?? dashState.todosRegistros.length;
  const totalPres = resumen.asistio_puntual ?? 0;
  const totalTard = resumen.tardanzas ?? 0;
  const totalFalt = resumen.faltas ?? 0;
  const pctPres   = resumen.porcentaje_asistencia;

  if (elProg) elProg.textContent = totalProg;
  if (elPres) elPres.textContent = totalPres;
  if (elPct)  elPct.textContent = pctPres == null ? 'Sin datos de puntualidad' : `${pctPres}% puntualidad`;
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
  const footerCount = document.getElementById('dashFooterCount');
  if (!tbody) return;

  const filtrados = dashState.todosRegistros.filter(row => {
    // Filtro por estado
    if (dashState.filtroEstado === 'ASISTIO' && row.estado_evaluado !== 'ASISTIO') return false;
    if (dashState.filtroEstado === 'TARDANZA' && row.estado_evaluado !== 'TARDANZA') return false;
    if (dashState.filtroEstado === 'FALTA' && !['FALTA', 'FALTA_ADMINISTRATIVA'].includes(row.estado_evaluado)) return false;

    // Filtro por texto de búsqueda
    if (dashState.busqueda) {
      const nom = (row.nombre_completo || '').toLowerCase();
      const dni = (row.dni || '').toLowerCase();
      const srv = (row.servicio_nombre || '').toLowerCase();
      const crg = (row.cargo || '').toLowerCase();
      if (!nom.includes(dashState.busqueda) && !dni.includes(dashState.busqueda) && !srv.includes(dashState.busqueda) && !crg.includes(dashState.busqueda)) {
        return false;
      }
    }

    return true;
  });

  if (footerCount) {
    footerCount.textContent = `Mostrando ${filtrados.length} de ${dashState.todosRegistros.length} servidores`;
  }

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

  const html = filtrados.map(r => {
    // Formatear Badge de estado
    let badgeClass = 'asistio';
    let badgeText = 'Asistió';

    if (r.estado_evaluado === 'TARDANZA') {
      badgeClass = 'tardanza';
      badgeText = `Tardanza (+${r.minutos_tardanza || 0}m)`;
    } else if (['FALTA', 'FALTA_ADMINISTRATIVA'].includes(r.estado_evaluado)) {
      badgeClass = 'falta';
      badgeText = 'Falta';
    } else if (r.estado_evaluado === 'PERMISO') {
      badgeClass = 'permiso';
      badgeText = 'Permiso / CITT';
    }

    // Formatear horas
    const horaIngreso = r.hora_ingreso ? r.hora_ingreso.substring(0, 5) : '--:--';
    const horaSalida = r.hora_salida ? r.hora_salida.substring(0, 5) : '--:--';

    let ingresoClass = 'hora-cell';
    if (r.estado_evaluado === 'ASISTIO') ingresoClass += ' presente';
    else if (r.estado_evaluado === 'TARDANZA') ingresoClass += ' tarde';
    else ingresoClass += ' vacia';

    const turnoProg = r.hora_prog_inicio && r.hora_prog_fin
      ? `${r.hora_prog_inicio.substring(0, 5)} - ${r.hora_prog_fin.substring(0, 5)}`
      : (r.turno_nombre || 'Programado');

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
          <span style="font-size: 0.84rem; color: var(--text-secondary);">${escapeHtml(r.servicio_nombre || '')}</span>
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
          <span class="badge-clean ${badgeClass}">${badgeText}</span>
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
