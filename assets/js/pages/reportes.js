/**
 * Lógica del Módulo: Reportes Oficiales de Recursos Humanos
 * Hospital Distrital de Pacasmayo - ETAPA 7
 */

let repState = {
  tabActiva: 'individual', // 'individual', 'consolidado', 'seti', 'rol'
  mes: 9,
  anio: 2026,
  servicioId: '',
  regimen: '',
  condicion: '',
  personalId: 6,
  personalLista: [],
  ultimoDatosRol: null,
  formatoTurnos: 'numerico' // 'numerico' (1, 2, 3, 1/2...) vs 'letras' (M, T, N, GD...)
};

document.addEventListener('DOMContentLoaded', () => {
  initReportesControls();
  cargarListaPersonalSelect();
  cargarReporteActivo();
});

/**
 * Inicializa los eventos de tabs y filtros
 */
function initReportesControls() {
  const tabBtns = document.querySelectorAll('.report-tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.getAttribute('data-tab');
      if (tab) {
        repState.tabActiva = tab;
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        const targetPane = document.getElementById(`pane-${tab}`);
        if (targetPane) targetPane.classList.add('active');

        // Mostrar / ocultar filtros específicos según la pestaña activa
        const workerSelectGroup = document.getElementById('groupFiltroPersonal');
        if (workerSelectGroup) {
          workerSelectGroup.style.display = (tab === 'individual') ? 'flex' : 'none';
        }

        const condSelectGroup = document.getElementById('groupFiltroCondicion');
        if (condSelectGroup) {
          condSelectGroup.style.display = (tab === 'consolidado') ? 'flex' : 'none';
        }

        const regimenSelectGroup = document.getElementById('groupFiltroRegimen');
        if (regimenSelectGroup) {
          regimenSelectGroup.style.display = (tab === 'rol' || tab === 'consolidado') ? 'flex' : 'none';
        }

        cargarReporteActivo();
      }
    });
  });

  const selectMes = document.getElementById('repMesSelect');
  const selectAnio = document.getElementById('repAnioSelect');
  const selectServicio = document.getElementById('repServicioSelect');
  const selectRegimen = document.getElementById('repRegimenSelect');
  const selectCondicion = document.getElementById('repCondicionSelect');
  const selectPersonal = document.getElementById('repPersonalSelect');

  const btnGenerar = document.getElementById('btnGenerarReporte');
  const btnExportar = document.getElementById('btnExportarExcel');
  const btnImprimir = document.getElementById('btnImprimirReporte');

  if (selectMes) {
    selectMes.addEventListener('change', (e) => {
      repState.mes = parseInt(e.target.value, 10);
      cargarReporteActivo();
    });
  }

  if (selectAnio) {
    selectAnio.addEventListener('change', (e) => {
      repState.anio = parseInt(e.target.value, 10);
      cargarReporteActivo();
    });
  }

  if (selectServicio) {
    selectServicio.addEventListener('change', (e) => {
      repState.servicioId = e.target.value;
      actualizarSelectPersonal();
      cargarReporteActivo();
    });
  }

  if (selectRegimen) {
    selectRegimen.addEventListener('change', (e) => {
      repState.regimen = e.target.value;
      cargarReporteActivo();
    });
  }

  if (selectCondicion) {
    selectCondicion.addEventListener('change', (e) => {
      repState.condicion = e.target.value;
      cargarReporteActivo();
    });
  }

  if (selectPersonal) {
    selectPersonal.addEventListener('change', (e) => {
      repState.personalId = parseInt(e.target.value, 10);
      cargarReporteActivo();
    });
  }

  if (btnGenerar) {
    btnGenerar.addEventListener('click', () => {
      cargarReporteActivo();
    });
  }

  if (btnExportar) {
    btnExportar.addEventListener('click', () => {
      exportarReporteExcel();
    });
  }

  const btnExportarPdf = document.getElementById('btnExportarPdfFicha');
  if (btnExportarPdf) {
    btnExportarPdf.addEventListener('click', () => {
      exportarFichaPdf();
    });
  }

  if (btnImprimir) {
    btnImprimir.addEventListener('click', () => {
      exportarFichaPdf();
    });
  }
}

/**
 * Carga el select de personal para la ficha individual
 */
async function cargarListaPersonalSelect() {
  try {
    const res = await API.get('personal/listar.php', { limit: 50 });
    const list = res.data || res.personal || [];
    if (list.length > 0) {
      repState.personalLista = list;
      actualizarSelectPersonal();
      if (repState.tabActiva === 'individual') {
        cargarRecordIndividual();
      }
    }
  } catch (e) {
    // Silencioso
  }
}

/**
 * Filtra el desplegable de trabajadores según el servicio seleccionado
 */
function actualizarSelectPersonal() {
  const select = document.getElementById('repPersonalSelect');
  if (!select) return;

  const servId = repState.servicioId ? String(repState.servicioId) : '';
  let filtrados = repState.personalLista;
  if (servId !== '') {
    filtrados = repState.personalLista.filter(p => String(p.servicio_id) === servId);
  }
  if (filtrados.length === 0) {
    filtrados = repState.personalLista;
  }

  select.innerHTML = filtrados.map(p => `
    <option value="${p.personal_id}">${escapeHtml(p.nombre_completo)} - ${escapeHtml(p.cargo_nombre || '')} (DNI: ${p.dni})</option>
  `).join('');

  if (filtrados.length > 0) {
    if (!filtrados.some(p => String(p.personal_id) === String(repState.personalId))) {
      repState.personalId = filtrados[0].personal_id;
    }
    select.value = repState.personalId;
  }
}

/**
 * Redirige la carga según la pestaña activa
 */
function cargarReporteActivo() {
  switch (repState.tabActiva) {
    case 'individual':
      cargarRecordIndividual();
      break;
    case 'consolidado':
      cargarConsolidadoPlanillas();
      break;
    case 'seti':
      cargarReporteSeti();
      break;
    case 'rol':
      cargarRolOficialMinsa();
      break;
  }
}

/**
 * Carga el Récord Individual Mensual
 */
async function cargarRecordIndividual() {
  const container = document.getElementById('recordIndividualContainer');
  if (!container) return;

  container.innerHTML = `
    <div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
      Cargando récord individual de turnos y asistencias...
    </div>
  `;

  try {
    const res = await API.get('reportes/individual.php', {
      personal_id: repState.personalId,
      mes: repState.mes,
      anio: repState.anio
    });

    if (res && res.ok) {
      repState.ultimoDatosIndividual = res;
      renderRecordIndividual(res);
    }
  } catch (e) {
    container.innerHTML = `
      <div style="text-align: center; padding: 2.5rem; color: var(--status-absent-accent);">
        Error al cargar el récord del trabajador. Verifique el servidor.
      </div>
    `;
  }
}

function renderRecordIndividual(data) {
  const container = document.getElementById('recordIndividualContainer');
  if (!container) return;

  const t = data.trabajador || {};
  const tot = data.totales || data.resumen_mensual || {};
  const p = data.periodo || {};

  const cumple150h = (tot.cumple_150h !== undefined) ? tot.cumple_150h : (tot.cumple_norma !== undefined ? tot.cumple_norma : true);
  const horasProg = tot.horas_programadas ?? tot.total_horas_programadas ?? 0;
  const horasAsist = tot.horas_asistidas ?? tot.total_horas_asistidas ?? 0;
  const minTard = tot.minutos_tardanza ?? tot.total_minutos_tardanza ?? 0;
  const totFaltas = tot.total_faltas ?? 0;
  const diasLab = tot.dias_laborados ?? tot.total_dias_laborados ?? 0;
  const totPerm = tot.total_permisos ?? 0;

  container.innerHTML = `
    <!-- Cabecera del Récord Individual -->
    <div class="worker-record-header">
      <div class="worker-record-info">
        <div class="worker-record-avatar">👤</div>
        <div>
          <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary);">
            ${escapeHtml(t.nombre_completo)}
          </div>
          <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.2rem;">
            DNI: <strong>${t.dni || ''}</strong> &bull; Cargo: <strong>${escapeHtml(t.cargo || '')}</strong> &bull; Servicio: <strong>${escapeHtml(t.servicio_nombre || '')}</strong>
          </div>
          <div style="display: flex; gap: 0.4rem; margin-top: 0.4rem;">
            <span class="badge ${t.condicion_laboral === 'NOMBRADO' ? 'badge-present' : 'badge-neutral'}">${t.condicion_laboral || 'CAS'}</span>
            <span class="badge badge-neutral">${t.regimen_laboral || 'ASISTENCIAL'}</span>
            <span class="badge ${cumple150h ? 'badge-present' : 'badge-absent'}">
              ${cumple150h ? 'Cumple Regla 150h ✓' : 'Observación en Carga Horaria ⚠'}
            </span>
          </div>
        </div>
      </div>

      <div class="worker-record-stats">
        <div class="record-stat-box">
          <div class="record-stat-val" style="color: var(--primary-600);">${horasProg}h</div>
          <div class="record-stat-lbl">Programadas</div>
        </div>
        <div class="record-stat-box">
          <div class="record-stat-val" style="color: var(--status-present-accent);">${horasAsist}h</div>
          <div class="record-stat-lbl">Efectivas</div>
        </div>
        <div class="record-stat-box">
          <div class="record-stat-val" style="color: var(--status-late-accent);">${minTard}m</div>
          <div class="record-stat-lbl">Tardanza</div>
        </div>
        <div class="record-stat-box">
          <div class="record-stat-val" style="color: var(--status-absent-accent);">${totFaltas}</div>
          <div class="record-stat-lbl">Faltas</div>
        </div>
      </div>
    </div>

    <!-- Tabla Detallada Día a Día -->
    <div class="report-table-card">
      <div class="report-section-title">
        <span>Detalle Cronológico - ${p.mes_nombre || ''} ${p.anio || ''} (${p.total_dias || 30} días)</span>
        <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">
          Total Días Laborados: <strong>${diasLab}</strong> &bull; Permisos / CITT: <strong>${totPerm}</strong>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th style="width: 60px;">Día</th>
              <th style="min-width: 100px;">Fecha</th>
              <th style="min-width: 140px;">Turno Asignado</th>
              <th style="min-width: 110px;">Horario Prog.</th>
              <th style="min-width: 110px;">Entrada Real</th>
              <th style="min-width: 110px;">Salida Real</th>
              <th style="min-width: 90px; text-align: center;">Tardanza</th>
              <th style="min-width: 130px;">Estado Asistencia</th>
              <th style="min-width: 180px;">Observación / Incidencia</th>
            </tr>
          </thead>
          <tbody>
            ${(data.dias || []).map(d => `
              <tr style="${d.es_fin_de_semana ? 'background: rgba(0,0,0,0.015);' : ''}">
                <td style="font-weight: 700; text-align: center;">${d.dia}</td>
                <td>
                  <span style="font-weight: 600;">${d.dia_semana}</span>
                  <span style="font-size: 0.76rem; color: var(--text-tertiary); font-family: monospace;">${d.fecha}</span>
                </td>
                <td>
                  <span class="badge ${d.turno_codigo === 'LIB' ? 'badge-neutral' : 'badge-present'}" style="font-size: 0.72rem;">${d.turno_codigo}</span>
                  <span style="font-size: 0.8rem;">${escapeHtml(d.turno_nombre || '')}</span>
                </td>
                <td style="font-family: monospace; font-size: 0.82rem;">
                  ${d.hora_prog_inicio ? `${d.hora_prog_inicio} - ${d.hora_prog_fin} (${d.horas_programadas}h)` : '--:--'}
                </td>
                <td style="font-family: monospace; font-weight: 600;">
                  ${d.hora_entrada ? `<span style="color: var(--status-present-accent);">${d.hora_entrada}</span>` : '<span style="color: var(--text-tertiary);">--:--</span>'}
                </td>
                <td style="font-family: monospace; font-weight: 600;">
                  ${d.hora_salida ? `<span style="color: var(--status-present-accent);">${d.hora_salida}</span>` : '<span style="color: var(--text-tertiary);">--:--</span>'}
                </td>
                <td style="text-align: center;">
                  ${d.minutos_tardanza > 0 ? `<span style="color: var(--status-late-accent); font-weight: 700;">+${d.minutos_tardanza}m</span>` : '<span style="color: var(--text-tertiary); font-size: 0.76rem;">0m</span>'}
                </td>
                <td>${getBadgeEstadoSimple(d.estado)}</td>
                <td style="font-size: 0.78rem; color: var(--text-secondary);">${escapeHtml(d.observacion || d.observaciones || '')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

/**
 * Carga el Consolidado Mensual para Planillas
 */
async function cargarConsolidadoPlanillas() {
  const container = document.getElementById('consolidadoPlanillasContainer');
  if (!container) return;

  container.innerHTML = `
    <div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
      Generando consolidado mensual para la Unidad de Planillas...
    </div>
  `;

  try {
    const res = await API.get('reportes/consolidado.php', {
      mes: repState.mes,
      anio: repState.anio,
      servicio_id: repState.servicioId,
      condicion: repState.condicion
    });

    if (res && res.ok) {
      repState.ultimoDatosConsolidado = res;
      renderConsolidadoPlanillas(res);
    }
  } catch (e) {
    container.innerHTML = `
      <div style="text-align: center; padding: 2.5rem; color: var(--status-absent-accent);">
        Error al generar consolidado de planillas.
      </div>
    `;
  }
}

function renderConsolidadoPlanillas(data) {
  const container = document.getElementById('consolidadoPlanillasContainer');
  if (!container) return;

  const r = data.resumen;
  const p = data.periodo;

  container.innerHTML = `
    <div class="report-table-card">
      <div class="report-section-title">
        <span>Consolidado de Asistencia y Tardanzas para Planillas - ${p.mes_nombre} ${p.anio}</span>
        <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">
          Total Personal: <strong>${r.total_personal}</strong> &bull; Tardanzas: <strong>${r.total_tardanzas_minutos} min</strong> &bull; Días Descuento: <strong>${r.total_dias_descontar}</strong> &bull; CITT: <strong>${r.total_descansos_citt}</strong>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th style="min-width: 220px;">Trabajador / DNI</th>
              <th style="min-width: 170px;">Servicio</th>
              <th style="min-width: 90px;">Condición</th>
              <th style="min-width: 90px; text-align: center;">Días Prog.</th>
              <th style="min-width: 90px; text-align: center;">Días Lab.</th>
              <th style="min-width: 100px; text-align: center;">Tardanza Total</th>
              <th style="min-width: 100px; text-align: center;">Faltas Desc.</th>
              <th style="min-width: 90px; text-align: center;">Permisos</th>
              <th style="min-width: 90px; text-align: center;">CITT</th>
              <th style="min-width: 90px; text-align: center;">Horas Netas</th>
              <th style="min-width: 110px;">Estado Planilla</th>
            </tr>
          </thead>
          <tbody>
            ${data.filas.map(f => `
              <tr>
                <td>
                  <div style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem;">${escapeHtml(f.nombre_completo)}</div>
                  <div style="font-size: 0.74rem; color: var(--text-secondary);">DNI: ${f.dni} &bull; ${escapeHtml(f.cargo)}</div>
                </td>
                <td style="font-size: 0.82rem;">${escapeHtml(f.servicio_nombre)}</td>
                <td><span class="badge ${f.condicion_laboral === 'NOMBRADO' ? 'badge-present' : 'badge-neutral'}" style="font-size: 0.68rem;">${f.condicion_laboral}</span></td>
                <td style="text-align: center; font-weight: 600;">${f.dias_programados}</td>
                <td style="text-align: center; font-weight: 600; color: var(--status-present-accent);">${f.dias_laborados}</td>
                <td style="text-align: center;">
                  ${f.minutos_tardanza_total > 0 ? `<strong style="color: var(--status-late-accent); font-family: monospace;">+${f.minutos_tardanza_total}m</strong>` : '<span style="color: var(--text-tertiary);">0m</span>'}
                </td>
                <td style="text-align: center;">
                  ${f.faltas_injustificadas > 0 ? `<strong style="color: var(--status-absent-accent);">${f.faltas_injustificadas} d</strong>` : '<span style="color: var(--text-tertiary);">0</span>'}
                </td>
                <td style="text-align: center;">${f.permisos_con_goce}</td>
                <td style="text-align: center;">
                  ${f.descansos_citt > 0 ? `<span class="badge" style="background: rgba(6, 182, 212, 0.15); color: #0891b2;">${f.descansos_citt} d</span>` : '0'}
                </td>
                <td style="text-align: center; font-weight: 700;">${f.horas_netas}h</td>
                <td>
                  <span class="badge ${f.estado_planilla === 'CONFORME' ? 'badge-present' : 'badge-late'}">
                    ${f.estado_planilla === 'CONFORME' ? 'Conforme' : 'Con Descuento'}
                  </span>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

/**
 * Carga el Reporte Oficial SETI
 */
async function cargarReporteSeti() {
  const container = document.getElementById('reporteSetiContainer');
  if (!container) return;

  container.innerHTML = `
    <div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
      Generando Reporte Oficial SETI (Norma 150h Asistencial MINSA)...
    </div>
  `;

  try {
    const res = await API.get('reportes/seti.php', {
      mes: repState.mes,
      anio: repState.anio,
      servicio_id: repState.servicioId
    });

    if (res && res.ok) {
      repState.ultimoDatosSeti = res;
      renderReporteSeti(res);
    }
  } catch (e) {
    container.innerHTML = `
      <div style="text-align: center; padding: 2.5rem; color: var(--status-absent-accent);">
        Error al generar el reporte SETI.
      </div>
    `;
  }
}

function renderReporteSeti(data) {
  const container = document.getElementById('reporteSetiContainer');
  if (!container) return;

  const r = data.resumen;
  const p = data.periodo;

  container.innerHTML = `
    <!-- Resumen Superior SETI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
      <div class="asist-kpi-card kpi-total">
        <span class="kpi-label">Asistenciales SETI</span>
        <div class="kpi-val-row"><span class="kpi-number">${r.total_asistenciales}</span></div>
      </div>
      <div class="asist-kpi-card kpi-present">
        <span class="kpi-label">Cumplen 150h</span>
        <div class="kpi-val-row"><span class="kpi-number" style="color: var(--status-present-accent);">${r.cumplen_150h}</span></div>
      </div>
      <div class="asist-kpi-card kpi-absent">
        <span class="kpi-label">Observados 150h</span>
        <div class="kpi-val-row"><span class="kpi-number" style="color: var(--status-absent-accent);">${r.observados_150h}</span></div>
      </div>
      <div class="asist-kpi-card kpi-late">
        <span class="kpi-label">% Cumplimiento</span>
        <div class="kpi-val-row"><span class="kpi-number">${r.porcentaje_cumplimiento}%</span></div>
      </div>
      <div class="asist-kpi-card kpi-citt">
        <span class="kpi-label">Total Guardias 12h</span>
        <div class="kpi-val-row"><span class="kpi-number" style="color: #0891b2;">${r.total_guardias_12h}</span></div>
      </div>
    </div>

    <!-- Sección 1: Personal Nombrado (DL 276) -->
    <div class="report-table-card">
      <div class="report-section-title">
        <span>1. Personal Asistencial Nombrado (Decreto Legislativo 276)</span>
        <span class="badge badge-present">${data.nombrados.length} Registros</span>
      </div>
      <div class="table-responsive">
        ${renderTablaSetiRegistros(data.nombrados)}
      </div>
    </div>

    <!-- Sección 2: Personal CAS (Decreto Legislativo 1057) -->
    <div class="report-table-card">
      <div class="report-section-title">
        <span>2. Personal Asistencial CAS (Decreto Legislativo 1057)</span>
        <span class="badge badge-neutral">${data.cas.length} Registros</span>
      </div>
      <div class="table-responsive">
        ${renderTablaSetiRegistros(data.cas)}
      </div>
    </div>
  `;
}

function renderTablaSetiRegistros(items) {
  return `
    <table class="table">
      <thead>
        <tr>
          <th style="min-width: 220px;">Trabajador / DNI</th>
          <th style="min-width: 170px;">Servicio</th>
          <th style="min-width: 90px; text-align: center;">Guardias Diurnas (12h)</th>
          <th style="min-width: 90px; text-align: center;">Guardias Noct. (12h)</th>
          <th style="min-width: 90px; text-align: center;">Turnos Ord. (6h)</th>
          <th style="min-width: 110px; text-align: center;">Total Horas</th>
          <th style="min-width: 110px; text-align: center;">Regla 150h</th>
          <th style="min-width: 90px; text-align: center;">Faltas</th>
          <th style="min-width: 90px; text-align: center;">CITT</th>
        </tr>
      </thead>
      <tbody>
        ${items.map(s => `
          <tr>
            <td>
              <div style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem;">${escapeHtml(s.nombre_completo)}</div>
              <div style="font-size: 0.74rem; color: var(--text-secondary);">DNI: ${s.dni} &bull; ${escapeHtml(s.cargo)}</div>
            </td>
            <td style="font-size: 0.82rem;">${escapeHtml(s.servicio_nombre)}</td>
            <td style="text-align: center; font-weight: 600;">${s.guardias_diurnas_12h}</td>
            <td style="text-align: center; font-weight: 600;">${s.guardias_nocturnas_12h}</td>
            <td style="text-align: center; font-weight: 600;">${s.turnos_ordinarios_6h}</td>
            <td style="text-align: center; font-weight: 800; font-size: 0.95rem;">${s.total_horas}h</td>
            <td style="text-align: center;">
              <span class="badge ${s.cumple_150h ? 'badge-present' : 'badge-absent'}">
                ${s.cumple_150h ? '150h Cumple ✓' : `${s.total_horas}h (${s.diferencia_horas}h)`}
              </span>
            </td>
            <td style="text-align: center;">${s.total_inasistencias}</td>
            <td style="text-align: center;">${s.total_licencias_citt}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function exportarReporteExcel() {
  const url = `api/reportes/exportar_excel.php?tipo=${repState.tabActiva}&mes=${repState.mes}&anio=${repState.anio}&personal_id=${repState.personalId}&servicio_id=${repState.servicioId}&regimen=${encodeURIComponent(repState.regimen || '')}`;
  window.location.href = url;
}

function exportarReporteCsv() {
  const url = `api/reportes/exportar_csv.php?tipo=${repState.tabActiva}&mes=${repState.mes}&anio=${repState.anio}&personal_id=${repState.personalId}&servicio_id=${repState.servicioId}`;
  window.location.href = url;
}

/**
 * Exporta la Ficha Oficial directamente a PDF o a la ventana de impresión
 * Aisla al 100% el documento oficial eliminando todo rastro de la interfaz web
 */
function exportarFichaPdf() {
  let fichaHtml = '';
  const nombresMeses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const mesNombre = nombresMeses[repState.mes] || 'Setiembre';
  const anio = repState.anio;

  if (repState.tabActiva === 'individual') {
    const data = repState.ultimoDatosIndividual;
    if (!data) {
      showToast('Espere a que cargue el récord individual para exportar.', 'warning');
      return;
    }
    const t = data.trabajador;
    const tot = data.totales;
    const p = data.periodo;

    fichaHtml = `
      <!DOCTYPE html>
      <html lang="es">
      <head>
        <meta charset="UTF-8">
        <title>Ficha Individual de Asistencia - ${t.nombre_completo}</title>
        <style>
          @page { size: A4 portrait; margin: 10mm 12mm; }
          body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #111; margin: 0; padding: 0; }
          .header-inst { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
          .header-inst h4 { margin: 0; font-size: 8.5pt; text-transform: uppercase; color: #444; }
          .header-inst h2 { margin: 3px 0; font-size: 13pt; text-transform: uppercase; font-weight: bold; }
          .header-inst h3 { margin: 2px 0; font-size: 10pt; text-transform: uppercase; color: #222; }
          .doc-title { text-align: center; background-color: #f2f4f8; border: 1px solid #000; padding: 6px; font-weight: bold; font-size: 11pt; margin-bottom: 12px; }
          .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
          .info-table td { padding: 4px 6px; font-size: 9pt; border: 1px solid #ccc; }
          .info-table td.lbl { background-color: #f7fafc; font-weight: bold; width: 18%; color: #333; }
          .stats-grid { display: flex; justify-content: space-between; border: 1px solid #000; padding: 8px 12px; margin-bottom: 14px; background: #fafafa; }
          .stat-item { text-align: center; }
          .stat-num { font-size: 13pt; font-weight: bold; display: block; }
          .stat-lbl { font-size: 7.5pt; text-transform: uppercase; color: #555; }
          .records-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 8pt; }
          .records-table th, .records-table td { border: 1px solid #444; padding: 3px 4px; text-align: center; }
          .records-table th { background-color: #e2e8f0; font-weight: bold; }
          .records-table td.text-left { text-align: left; }
          .signatures { display: flex; justify-content: space-between; margin-top: 35px; page-break-inside: avoid; }
          .sig-box { width: 28%; text-align: center; border-top: 1px solid #000; padding-top: 4px; font-size: 8.5pt; }
        </style>
      </head>
      <body>
        <div class="header-inst">
          <h4>Gobierno Regional La Libertad • Red Integrada de Salud Pacasmayo</h4>
          <h2>Hospital Distrital de Pacasmayo</h2>
          <h3>Unidad de Recursos Humanos - Control de Asistencia y Permanencia</h3>
        </div>

        <div class="doc-title">
          FICHA INDIVIDUAL DE ASISTENCIA Y CONTROL HORARIO - ${p.mes_nombre.toUpperCase()} ${p.anio}
        </div>

        <table class="info-table">
          <tr>
            <td class="lbl">TRABAJADOR:</td>
            <td style="font-weight: bold; font-size: 10pt;" colspan="3">${escapeHtml(t.nombre_completo)}</td>
          </tr>
          <tr>
            <td class="lbl">D.N.I.:</td>
            <td><strong>${t.dni}</strong></td>
            <td class="lbl">CARGO:</td>
            <td>${escapeHtml(t.cargo)}</td>
          </tr>
          <tr>
            <td class="lbl">SERVICIO / ÁREA:</td>
            <td>${escapeHtml(t.servicio_nombre)}</td>
            <td class="lbl">CONDICIÓN:</td>
            <td>${t.condicion_laboral} • ${t.regimen_laboral}</td>
          </tr>
        </table>

        <div class="stats-grid">
          <div class="stat-item">
            <span class="stat-num">${tot.horas_programadas}h</span>
            <span class="stat-lbl">Horas Programadas</span>
          </div>
          <div class="stat-item">
            <span class="stat-num" style="color: #15803d;">${tot.horas_asistidas}h</span>
            <span class="stat-lbl">Horas Efectivas</span>
          </div>
          <div class="stat-item">
            <span class="stat-num" style="color: #b45309;">${tot.minutos_tardanza} min</span>
            <span class="stat-lbl">Tardanza Total</span>
          </div>
          <div class="stat-item">
            <span class="stat-num">${tot.dias_laborados}</span>
            <span class="stat-lbl">Días Laborados</span>
          </div>
          <div class="stat-item">
            <span class="stat-num" style="color: #b91c1c;">${tot.total_faltas}</span>
            <span class="stat-lbl">Inasistencias</span>
          </div>
          <div class="stat-item">
            <span class="stat-num" style="color: #6b21a8;">${tot.total_permisos}</span>
            <span class="stat-lbl">Permisos / CITT</span>
          </div>
        </div>

        <table class="records-table">
          <thead>
            <tr>
              <th style="width: 25px;">Día</th>
              <th style="width: 70px;">Fecha</th>
              <th>Turno</th>
              <th>Horario Prog.</th>
              <th>Entrada</th>
              <th>Salida</th>
              <th>Tardanza</th>
              <th>Estado</th>
              <th>Observación / Sustento</th>
            </tr>
          </thead>
          <tbody>
            ${data.dias.map(d => `
              <tr style="${d.es_fin_de_semana ? 'background-color: #f8fafc;' : ''}">
                <td><strong>${d.dia}</strong></td>
                <td>${d.dia_semana} ${d.fecha.substring(8, 10)}</td>
                <td><strong>${d.turno_codigo}</strong></td>
                <td>${d.hora_prog_inicio ? `${d.hora_prog_inicio} - ${d.hora_prog_fin}` : '--:--'}</td>
                <td>${d.hora_entrada ? `<strong>${d.hora_entrada}</strong>` : '--'}</td>
                <td>${d.hora_salida ? `<strong>${d.hora_salida}</strong>` : '--'}</td>
                <td>${d.minutos_tardanza > 0 ? `+${d.minutos_tardanza}m` : '0m'}</td>
                <td>${d.estado}</td>
                <td class="text-left">${escapeHtml(d.observacion || '')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>

        <div class="signatures">
          <div class="sig-box">
            <strong>Firma del Servidor</strong><br>
            DNI: ${t.dni}
          </div>
          <div class="sig-box">
            <strong>Control de Asistencia</strong><br>
            Recursos Humanos
          </div>
          <div class="sig-box">
            <strong>V°B° Jefatura de RR.HH.</strong><br>
            Hospital Distrital de Pacasmayo
          </div>
        </div>
      </body>
      </html>
    `;
  } else if (repState.tabActiva === 'consolidado') {
    const data = repState.ultimoDatosConsolidado;
    if (!data) {
      showToast('Espere a que cargue el consolidado para exportar.', 'warning');
      return;
    }
    const r = data.resumen;
    const p = data.periodo;

    fichaHtml = `
      <!DOCTYPE html>
      <html lang="es">
      <head>
        <meta charset="UTF-8">
        <title>Ficha Consolidada para Planillas - ${p.mes_nombre} ${p.anio}</title>
        <style>
          @page { size: A4 landscape; margin: 8mm; }
          body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #111; margin: 0; padding: 0; }
          .header-inst { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 8px; }
          .header-inst h4 { margin: 0; font-size: 8pt; text-transform: uppercase; color: #444; }
          .header-inst h2 { margin: 2px 0; font-size: 12pt; text-transform: uppercase; font-weight: bold; }
          .doc-title { text-align: center; background-color: #f2f4f8; border: 1px solid #000; padding: 5px; font-weight: bold; font-size: 10pt; margin-bottom: 8px; }
          .records-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 8pt; }
          .records-table th, .records-table td { border: 1px solid #444; padding: 3px 5px; text-align: center; }
          .records-table th { background-color: #e2e8f0; font-weight: bold; }
          .records-table td.text-left { text-align: left; }
          .signatures { display: flex; justify-content: space-around; margin-top: 30px; page-break-inside: avoid; }
          .sig-box { width: 25%; text-align: center; border-top: 1px solid #000; padding-top: 4px; font-size: 8pt; }
        </style>
      </head>
      <body>
        <div class="header-inst">
          <h4>Gobierno Regional La Libertad • Red Integrada de Salud Pacasmayo</h4>
          <h2>Hospital Distrital de Pacasmayo</h2>
          <div style="font-size: 8pt; color: #555;">Unidad de Recursos Humanos • Insumo de Planillas y Remuneraciones</div>
        </div>

        <div class="doc-title">
          CONSOLIDADO MENSUAL DE ASISTENCIA Y TARDANZAS - ${p.mes_nombre.toUpperCase()} ${p.anio}
        </div>

        <table class="records-table">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellidos y Nombres</th>
              <th>Servicio</th>
              <th>Condición</th>
              <th>Días Prog.</th>
              <th>Días Lab.</th>
              <th>Tardanza Total</th>
              <th>Faltas Desc.</th>
              <th>Permisos</th>
              <th>CITT EsSalud</th>
              <th>Horas Netas</th>
              <th>Planilla</th>
            </tr>
          </thead>
          <tbody>
            ${data.filas.map(f => `
              <tr>
                <td><strong>${f.dni}</strong></td>
                <td class="text-left"><strong>${escapeHtml(f.nombre_completo)}</strong></td>
                <td class="text-left">${escapeHtml(f.servicio_nombre)}</td>
                <td>${f.condicion_laboral}</td>
                <td>${f.dias_programados}</td>
                <td>${f.dias_laborados}</td>
                <td>${f.minutos_tardanza_total > 0 ? `+${f.minutos_tardanza_total}m` : '0m'}</td>
                <td>${f.faltas_injustificadas > 0 ? `<strong>${f.faltas_injustificadas} d</strong>` : '0'}</td>
                <td>${f.permisos_con_goce}</td>
                <td>${f.descansos_citt > 0 ? `${f.descansos_citt} d` : '0'}</td>
                <td><strong>${f.horas_netas}h</strong></td>
                <td>${f.estado_planilla}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>

        <div class="signatures">
          <div class="sig-box">
            <strong>Elaborado por</strong><br>
            Control de Asistencia
          </div>
          <div class="sig-box">
            <strong>Revisado por</strong><br>
            Jefatura de Recursos Humanos
          </div>
          <div class="sig-box">
            <strong>V°B° Dirección Ejecutiva</strong><br>
            Hospital Pacasmayo
          </div>
        </div>
      </body>
      </html>
    `;
  } else if (repState.tabActiva === 'seti') {
    // SETI
    const data = repState.ultimoDatosSeti;
    if (!data) {
      showToast('Espere a que cargue el reporte SETI para exportar.', 'warning');
      return;
    }
    const r = data.resumen;
    const p = data.periodo;

    fichaHtml = `
      <!DOCTYPE html>
      <html lang="es">
      <head>
        <meta charset="UTF-8">
        <title>Ficha SETI Oficial - ${p.mes_nombre} ${p.anio}</title>
        <style>
          @page { size: A4 landscape; margin: 8mm; }
          body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #111; margin: 0; padding: 0; }
          .header-inst { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 8px; }
          .header-inst h4 { margin: 0; font-size: 8pt; text-transform: uppercase; color: #444; }
          .header-inst h2 { margin: 2px 0; font-size: 12pt; text-transform: uppercase; font-weight: bold; }
          .doc-title { text-align: center; background-color: #f2f4f8; border: 1px solid #000; padding: 5px; font-weight: bold; font-size: 10pt; margin-bottom: 8px; }
          .records-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8pt; }
          .records-table th, .records-table td { border: 1px solid #444; padding: 3px 4px; text-align: center; }
          .records-table th { background-color: #e2e8f0; font-weight: bold; }
          .records-table td.text-left { text-align: left; }
          .signatures { display: flex; justify-content: space-around; margin-top: 25px; page-break-inside: avoid; }
          .sig-box { width: 25%; text-align: center; border-top: 1px solid #000; padding-top: 4px; font-size: 8pt; }
        </style>
      </head>
      <body>
        <div class="header-inst">
          <h4>Gobierno Regional La Libertad • Red Integrada de Salud Pacasmayo</h4>
          <h2>Hospital Distrital de Pacasmayo</h2>
          <div style="font-size: 8pt; color: #555;">Sistema de Información de Turnos e Inasistencias de Personal Asistencial (SETI - MINSA)</div>
        </div>

        <div class="doc-title">
          REPORTE OFICIAL SETI ASISTENCIAL - ${p.mes_nombre.toUpperCase()} ${p.anio} (NORMA 150 HORAS)
        </div>

        <h4 style="margin: 6px 0;">1. Personal Asistencial Nombrado (D.L. 276)</h4>
        <table class="records-table">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellidos y Nombres</th>
              <th>Servicio</th>
              <th>Guardias Diurnas (12h)</th>
              <th>Guardias Noct. (12h)</th>
              <th>Turnos Ord. (6h)</th>
              <th>Total Horas</th>
              <th>Cumple 150h</th>
              <th>Inasistencias</th>
              <th>CITT EsSalud</th>
            </tr>
          </thead>
          <tbody>
            ${data.nombrados.map(s => `
              <tr>
                <td><strong>${s.dni}</strong></td>
                <td class="text-left"><strong>${escapeHtml(s.nombre_completo)}</strong></td>
                <td class="text-left">${escapeHtml(s.servicio_nombre)}</td>
                <td>${s.guardias_diurnas_12h}</td>
                <td>${s.guardias_nocturnas_12h}</td>
                <td>${s.turnos_ordinarios_6h}</td>
                <td><strong>${s.total_horas}h</strong></td>
                <td>${s.cumple_150h ? 'SÍ (150h) ✓' : `${s.total_horas}h ⚠`}</td>
                <td>${s.total_inasistencias}</td>
                <td>${s.total_licencias_citt}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>

        <h4 style="margin: 6px 0;">2. Personal Asistencial CAS (D.L. 1057)</h4>
        <table class="records-table">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellidos y Nombres</th>
              <th>Servicio</th>
              <th>Guardias Diurnas (12h)</th>
              <th>Guardias Noct. (12h)</th>
              <th>Turnos Ord. (6h)</th>
              <th>Total Horas</th>
              <th>Cumple 150h</th>
              <th>Inasistencias</th>
              <th>CITT EsSalud</th>
            </tr>
          </thead>
          <tbody>
            ${data.cas.map(s => `
              <tr>
                <td><strong>${s.dni}</strong></td>
                <td class="text-left"><strong>${escapeHtml(s.nombre_completo)}</strong></td>
                <td class="text-left">${escapeHtml(s.servicio_nombre)}</td>
                <td>${s.guardias_diurnas_12h}</td>
                <td>${s.guardias_nocturnas_12h}</td>
                <td>${s.turnos_ordinarios_6h}</td>
                <td><strong>${s.total_horas}h</strong></td>
                <td>${s.cumple_150h ? 'SÍ (150h) ✓' : `${s.total_horas}h ⚠`}</td>
                <td>${s.total_inasistencias}</td>
                <td>${s.total_licencias_citt}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>

        <div class="signatures">
          <div class="sig-box">
            <strong>Coordinador SETI</strong><br>
            Recursos Humanos
          </div>
          <div class="sig-box">
            <strong>Jefatura de RR.HH.</strong><br>
            Hospital Pacasmayo
          </div>
          <div class="sig-box">
            <strong>Dirección Ejecutiva</strong><br>
            Red de Salud Pacasmayo
          </div>
        </div>
      </body>
      </html>
    `;
  } else if (repState.tabActiva === 'rol') {
    fichaHtml = generarHtmlFichaRolMensual();
  }

  // Imprimir exclusivamente la Ficha en contenedor aislado
  imprimirFichaAislada(fichaHtml);
}

/**
 * Imprime exclusivamente la Ficha Oficial en un iframe aislado
 * Evita cualquier elemento de la interfaz web, menús, barras y fondos oscuros
 */
function imprimirFichaAislada(html) {
  let iframe = document.getElementById('printFichaIframe');
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id = 'printFichaIframe';
    iframe.style.position = 'fixed';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = 'none';
    document.body.appendChild(iframe);
  }

  const iframeDoc = iframe.contentWindow.document;
  iframeDoc.open();
  iframeDoc.write(html);
  iframeDoc.close();

  setTimeout(() => {
    try {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
    } catch (e) {
      const win = window.open('', '_blank', 'width=1100,height=850');
      if (win) {
        win.document.open();
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(() => win.print(), 350);
      }
    }
  }, 250);
}

const SERVICIOS_NOMBRES_OFICIALES = {
  0: 'PLANTEL COMPLETO DE SERVICIOS HOSPITALARIOS',
  7: 'EMERGENCIA Y CUIDADOS CRÍTICOS',
  8: 'UNIDAD DE CUIDADOS INTENSIVOS (UCI)',
  9: 'HOSPITALIZACIÓN MEDICINA',
  10: 'CONSULTA EXTERNA',
  11: 'PEDIATRÍA Y NEONATOLOGÍA',
  12: 'CENTRO OBSTÉTRICO',
  13: 'CENTRO QUIRÚRGICO',
  14: 'LABORATORIO CLÍNICO',
  15: 'FARMACIA Y DOSIS UNITARIA',
  16: 'UNIDAD DE PERSONAL / RR.HH.',
  17: 'UNIDAD DE INFORMÁTICA Y ESTADÍSTICA'
};

/**
 * Normaliza turnos al estándar MINSA oficial del hospital (1, 2, 3, 1/2, 2/3, 1/2/3...)
 */
function normalizarTurnoAMatrizMinsa(cod, formatoNumerico = true) {
  if (!cod || cod === '0' || cod === 'LIB' || cod === 'DESC') return 'LIB';
  const c = String(cod).trim().toUpperCase();

  if (formatoNumerico) {
    if (c === 'M' || c === 'ADM' || c === '1') return '1';
    if (c === 'T' || c === '2') return '2';
    if (c === 'N' || c === 'GN' || c === '3') return '3';
    if (c === 'GD' || c === '1/2') return '1/2';
    if (c === '2/3') return '2/3';
    if (c === 'G24' || c === '1/2/3') return '1/2/3';
    if (c === 'VAC') return 'V';
    if (c === 'VAC-1') return 'V1';
    if (c === 'F') return 'F';
    if (c === 'C' || c === 'CITT') return 'C';
    if (c === 'P' || c === 'PER') return 'P';
    return c;
  }

  // Formato Letras (M, T, N, GD...)
  if (c === '1') return 'M';
  if (c === '2') return 'T';
  if (c === '3') return 'GN';
  if (c === '1/2') return 'GD';
  if (c === '1/2/3') return 'G24';
  return c;
}

window.cambiarFormatoTurnosRol = function(fmt) {
  repState.formatoTurnos = fmt;
  cargarRolOficialMinsa();
};

/**
 * Carga e inyecta dinámicamente la vista previa del Rol Mensual Oficial (Matriz MINSA)
 * Conectado con la base de datos real y snapshots del motor de programación
 */
async function cargarRolOficialMinsa() {
  const container = document.getElementById('rolMensualContainer');
  if (!container) return;

  container.innerHTML = `
    <div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
      <div style="font-size: 2.2rem; margin-bottom: 0.75rem; animation: pulse 1.5s infinite;">📋</div>
      <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">
        Cargando Rol Mensual Oficial de Turnos (Matriz MINSA)...
      </div>
      <div style="font-size: 0.8rem; color: var(--text-secondary);">
        Consultando asignaciones programadas, turnos y reglas normativas
      </div>
    </div>
  `;

  const nombresMeses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SETIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
  const mesNombre = nombresMeses[repState.mes] || 'SETIEMBRE';
  const anio = repState.anio;
  const totalDias = new Date(anio, repState.mes, 0).getDate();
  const letrasDias = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];

  const sId = (repState.servicioId !== '' && repState.servicioId !== null) ? parseInt(repState.servicioId, 10) : 0;
  const areaNombre = SERVICIOS_NOMBRES_OFICIALES[sId] || 'SERVICIOS HOSPITALARIOS';
  const esNumerico = (repState.formatoTurnos !== 'letras');

  try {
    const res = await API.get('programacion/mensual.php', {
      mes: repState.mes,
      anio: repState.anio,
      servicio_id: sId
    });

    let lista = (res && Array.isArray(res.matriz)) ? res.matriz : [];

    // Si se seleccionó filtro por Régimen (ASISTENCIAL / ADMINISTRATIVO)
    if (repState.regimen) {
      const regFiltro = repState.regimen.toUpperCase();
      lista = lista.filter(item => (item.regimen_laboral || '').toUpperCase() === regFiltro);
    }

    if (lista.length === 0) {
      container.innerHTML = `
        <div class="empty-state" style="padding: 3rem 1.5rem; text-align: center; background: var(--bg-surface); border-radius: var(--radius-lg); border: 1px dashed var(--border-default); margin: 1rem 0;">
          <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📋</div>
          <h3 style="font-size: 1.15rem; color: var(--text-primary); margin-bottom: 0.35rem;">No se encontraron registros de programación</h3>
          <p style="font-size: 0.85rem; color: var(--text-secondary); max-width: 480px; margin: 0 auto;">
            No hay personal asignado con el filtro seleccionado (${repState.regimen ? 'Régimen ' + repState.regimen : 'Todos los regímenes'} en ${areaNombre}). Intente seleccionar otro servicio o restablecer el filtro.
          </p>
        </div>
      `;
      repState.ultimoDatosRol = null;
      return;
    }

    // Determinar categoría (ASISTENCIALES vs ADMINISTRATIVOS vs MIXTO)
    const tieneAsist = lista.some(p => (p.regimen_laboral || '').toUpperCase() === 'ASISTENCIAL');
    const tieneAdmin = lista.some(p => (p.regimen_laboral || '').toUpperCase() === 'ADMINISTRATIVO');
    let categoriaTitulo = 'PERSONAL ASISTENCIAL Y ADMINISTRATIVO';
    if (tieneAsist && !tieneAdmin) {
      categoriaTitulo = 'PERSONAL ASISTENCIAL (MÉDICOS, ENFERMERÍA, TÉCNICOS)';
    } else if (!tieneAsist && tieneAdmin) {
      categoriaTitulo = 'PERSONAL ADMINISTRATIVO (OFICINA Y GESTIÓN)';
    }

    // Agrupar en sub-áreas lógicas MINSA
    const seccionesMap = {};
    const esTodosServicios = (sId === 0);

    lista.forEach(p => {
      let grupo = '';
      if (esTodosServicios && !repState.regimen) {
        grupo = (p.servicio_nombre || 'SERVICIO GENERAL').toUpperCase();
      } else {
        const cargo = (p.cargo_nombre || '').toLowerCase();
        const reg = (p.regimen_laboral || '').toUpperCase();
        if (cargo.includes('médic') || cargo.includes('ciruj') || cargo.includes('pediatr') || cargo.includes('intensiv') || cargo.includes('gineco')) {
          grupo = 'CUERPO MÉDICO Y ESPECIALISTAS';
        } else if (cargo.includes('enferm') || cargo.includes('obstetr') || cargo.includes('tecnólog')) {
          grupo = 'PROFESIONALES DE ENFERMERÍA Y SALUD';
        } else if (cargo.includes('técnic') || cargo.includes('farmac')) {
          grupo = 'PERSONAL TÉCNICO Y ASISTENCIAL';
        } else if (reg === 'ADMINISTRATIVO' || cargo.includes('admin') || cargo.includes('informát') || cargo.includes('secretar') || cargo.includes('jefe')) {
          grupo = 'PERSONAL ADMINISTRATIVO Y GESTIÓN';
        } else {
          grupo = 'PERSONAL ASISTENCIAL DE APOYO';
        }
      }

      if (!seccionesMap[grupo]) seccionesMap[grupo] = [];
      seccionesMap[grupo].push(p);
    });

    // Guardar para exportación PDF e Impresión
    repState.ultimoDatosRol = {
      areaNombre,
      categoriaTitulo,
      mesNombre,
      anio,
      totalDias,
      seccionesMap,
      tieneAsist,
      lista,
      esNumerico
    };

    // Renderizar plantilla oficial MINSA
    container.innerHTML = `
      <!-- Selector de Formato de Nomenclatura (1, 2, 3 vs M, T, N) -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.65rem; background: var(--bg-surface); border: 1px solid var(--border-default); padding: 0.5rem 0.85rem; border-radius: var(--radius-md);">
        <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.4rem;">
          <span style="font-size: 1.1rem;">🏷</span> Formato de Nomenclatura de Turnos para el Rol:
        </div>
        <div style="display: flex; gap: 0.35rem; align-items: center;">
          <button type="button" class="btn btn-sm ${esNumerico ? 'btn-primary' : 'btn-outline'}" onclick="cambiarFormatoTurnosRol('numerico')" style="font-size: 0.76rem; padding: 0.25rem 0.65rem; font-weight: 700;" title="Nomenclatura oficial utilizada en Pacasmayo: 1=Día, 2=Tarde, 3=Noche, 1/2=Doble (12h), 1/2/3=Guardia 24h">
            🔢 1, 2, 3, 1/2... (Formato Oficial)
          </button>
          <button type="button" class="btn btn-sm ${!esNumerico ? 'btn-primary' : 'btn-outline'}" onclick="cambiarFormatoTurnosRol('letras')" style="font-size: 0.76rem; padding: 0.25rem 0.65rem;" title="Nomenclatura alternativa por iniciales: M=Mañana, T=Tarde, N=Noche, GD=Guardia Diurna">
            🔤 M, T, N, GD (Letras)
          </button>
        </div>
      </div>

      <div class="report-table-card" style="padding: 1.5rem; background: #ffffff; color: #000000; border-radius: var(--radius-lg); box-shadow: var(--shadow-md);">
        <!-- Membrete MINSA Oficial -->
        <div style="text-align: center; margin-bottom: 1rem;">
          <div style="font-size: 0.85rem; font-weight: 700; color: #333;">GOBIERNO REGIONAL LA LIBERTAD</div>
          <div style="font-size: 0.85rem; font-weight: 700; color: #333;">GERENCIA GENERAL DE SALUD</div>
          <div style="font-size: 0.85rem; font-weight: 700; color: #333;">RED INTEGRADA DE SALUD</div>
          <div style="font-size: 0.85rem; font-weight: 700; color: #333;">HOSPITAL DISTRITAL PACASMAYO - MINSA</div>
          <div style="font-size: 1.15rem; font-weight: 800; text-decoration: underline; margin-top: 0.5rem; color: #1e3a8a;">
            ${escapeHtml(areaNombre)}
          </div>
          <div style="font-size: 1rem; font-weight: 700; margin-top: 0.2rem; color: #111;">
            ${mesNombre} ${anio}
          </div>
          <div style="font-size: 0.88rem; font-weight: 800; text-align: left; margin-top: 0.75rem; color: #1f2937; border-left: 4px solid #2563eb; padding-left: 0.5rem;">
            ${escapeHtml(categoriaTitulo)} &bull; ${lista.length} trabajador(es) programado(s) &bull; Formato: <span style="color: #2563eb;">${esNumerico ? 'Oficial 1, 2, 3, 1/2' : 'Letras M, T, N'}</span>
          </div>
        </div>

        <!-- Tabla Roster Matriz MINSA -->
        <div style="overflow-x: auto; margin-bottom: 1.25rem;">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.76rem; font-family: Arial, sans-serif;">
            ${Object.keys(seccionesMap).map(grupo => {
              const personalGrupo = seccionesMap[grupo];
              return `
                <tr>
                  <td colspan="${totalDias + 2}" style="background-color: #D9E1F2; font-weight: 700; text-align: center; border: 1px solid #000; padding: 4px; font-size: 0.82rem; color: #1e3a8a;">
                    ${escapeHtml(grupo)} (${personalGrupo.length})
                  </td>
                </tr>
                <tr>
                  <td style="background-color: #D9D9D9; font-weight: 700; text-align: center; border: 1px solid #000; padding: 3px; min-width: 200px;">DÍAS</td>
                  ${Array.from({ length: totalDias }, (_, i) => {
                    const d = i + 1;
                    const dayNum = new Date(anio, repState.mes - 1, d).getDay();
                    const letra = letrasDias[dayNum];
                    const isWe = (dayNum === 0 || dayNum === 6);
                    return `<td style="background-color: ${isWe ? '#BFBFBF' : '#D9D9D9'}; font-weight: 700; text-align: center; border: 1px solid #000; width: 28px; padding: 3px;">${letra}</td>`;
                  }).join('')}
                  <td style="background-color: #D9D9D9; font-weight: 700; text-align: center; border: 1px solid #000; padding: 3px; min-width: 55px;">TOTAL</td>
                </tr>
                <tr>
                  <td style="background-color: #D9D9D9; font-weight: 700; text-align: center; border: 1px solid #000; padding: 3px;">APELLIDOS Y NOMBRES</td>
                  ${Array.from({ length: totalDias }, (_, i) => {
                    const d = i + 1;
                    const dayNum = new Date(anio, repState.mes - 1, d).getDay();
                    const isWe = (dayNum === 0 || dayNum === 6);
                    return `<td style="background-color: ${isWe ? '#BFBFBF' : '#D9D9D9'}; font-weight: 700; text-align: center; border: 1px solid #000; padding: 3px;">${d}</td>`;
                  }).join('')}
                  <td style="background-color: #D9D9D9; font-weight: 700; text-align: center; border: 1px solid #000; padding: 3px;">HORAS</td>
                </tr>
                ${personalGrupo.map(p => {
                  const esNombrado = ((p.condicion_laboral || '').includes('276') || (p.condicion_laboral || '').includes('NOMBRADO'));
                  const condStr = esNombrado ? 'NOM' : 'CAS';
                  const totHoras = p.total_horas || 0;
                  return `
                    <tr>
                      <td style="background-color: #E2EFDA; font-weight: 700; border: 1px solid #000; padding: 4px 6px; white-space: nowrap;">
                        <span style="font-size: 0.78rem;">${escapeHtml(p.nombre_completo)}</span>
                        <div style="font-size: 0.68rem; font-weight: normal; color: #4b5563;">
                          ${escapeHtml(p.cargo_nombre || '')} &bull; <span style="font-weight: 700;">${condStr}</span>
                        </div>
                      </td>
                      ${Array.from({ length: totalDias }, (_, i) => {
                        const d = i + 1;
                        const dayNum = new Date(anio, repState.mes - 1, d).getDay();
                        const isWe = (dayNum === 0 || dayNum === 6);
                        const isFeriado = esFeriadoNacional(repState.mes, d);
                        const tObj = p.programacion ? p.programacion[d] : null;
                        const rawCod = tObj ? tObj.codigo : (p.patron ? p.patron[d - 1] : 'LIB');
                        const cod = normalizarTurnoAMatrizMinsa(rawCod, esNumerico);

                        if (isFeriado) {
                          return `<td style="background-color: #fee2e2; color: #dc2626; font-weight: 800; text-align: center; border: 1px solid #000;">F</td>`;
                        } else if (cod === 'LIB' || cod === '0' || !cod) {
                          return `<td style="background-color: ${isWe ? '#D9D9D9' : '#ffffff'}; color: #94a3b8; text-align: center; border: 1px solid #000; font-size: 0.68rem;">LIB</td>`;
                        } else {
                          let bg = '#E2EFDA';
                          let fg = '#000000';
                          if (cod === '1/2' || cod === 'GD') {
                            bg = '#dbeafe'; // Doble turno (12h)
                            fg = '#0284c7';
                          } else if (cod === '3' || cod === 'N' || cod === 'GN') {
                            bg = '#ede9fe'; // Noche / Guardia (12h)
                            fg = '#6d28d9';
                          } else if (cod === '1' || cod === 'M' || cod === 'ADM') {
                            bg = '#eff6ff'; // Mañana / Día
                            fg = '#1d4ed8';
                          } else if (cod === '2' || cod === 'T') {
                            bg = '#fff7ed'; // Tarde
                            fg = '#ea580c';
                          } else if (cod === '1/2/3' || cod === 'G24') {
                            bg = '#fee2e2'; // Guardia 24 Horas
                            fg = '#b91c1c';
                          } else if (cod === '2/3') {
                            bg = '#f5f3ff'; // Tarde + Noche
                            fg = '#5b21b6';
                          } else if (cod === 'V' || cod === 'VAC' || cod === 'V1') {
                            bg = '#ecfeff'; // Vacaciones
                            fg = '#0891b2';
                          } else if (cod === 'C') {
                            bg = '#fef9c3'; // CITT
                            fg = '#a16207';
                          }
                          return `<td style="background-color: ${bg}; color: ${fg}; font-weight: 800; text-align: center; border: 1px solid #000; font-size: 0.78rem;">${escapeHtml(cod)}</td>`;
                        }
                      }).join('')}
                      <td style="background-color: #E2EFDA; font-weight: 700; text-align: center; border: 1px solid #000; padding: 3px; font-size: 0.78rem;">
                        ${totHoras}h
                      </td>
                    </tr>
                  `;
                }).join('')}
              `;
            }).join('')}
          </table>
        </div>

        <!-- Leyenda Oficial y Bloque de Firmas -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem; margin-top: 1.5rem;">
          <table style="border: 1px solid #000; border-collapse: collapse; font-size: 0.78rem; width: 440px;">
            <tr style="background-color: #f1f1f1; font-weight: 700;">
              <td colspan="2" style="border: 1px solid #000; padding: 4px;">
                LEYENDA OFICIAL HOSPITAL PACASMAYO (${esNumerico ? 'FORMATO 1, 2, 3, 1/2' : 'FORMATO M, T, N, GD'}):
              </td>
            </tr>
            <tr>
              <td style="font-weight: 700; border: 1px solid #000; padding: 3px 5px; width: 180px;">1 (Turno Día / Mañana)</td>
              <td style="border: 1px solid #000; padding: 3px 5px;">07:00 / 08:00 am - 14:00 pm (6h / 8h)</td>
            </tr>
            <tr>
              <td style="font-weight: 700; border: 1px solid #000; padding: 3px 5px;">2 (Turno Tarde)</td>
              <td style="border: 1px solid #000; padding: 3px 5px;">14:00 pm - 20:00 pm (6h)</td>
            </tr>
            <tr>
              <td style="font-weight: 700; border: 1px solid #000; padding: 3px 5px;">3 (Turno Noche / Guardia)</td>
              <td style="border: 1px solid #000; padding: 3px 5px;">20:00 pm - 08:00 am (12h)</td>
            </tr>
            <tr>
              <td style="font-weight: 700; border: 1px solid #000; padding: 3px 5px;">1/2 (Doble Turno M+T / GD)</td>
              <td style="border: 1px solid #000; padding: 3px 5px;">08:00 am - 20:00 pm (Guardia Diurna 12h)</td>
            </tr>
            <tr>
              <td style="font-weight: 700; border: 1px solid #000; padding: 3px 5px;">2/3 (Tarde + Noche)</td>
              <td style="border: 1px solid #000; padding: 3px 5px;">14:00 pm - 08:00 am (18h)</td>
            </tr>
            <tr>
              <td style="font-weight: 700; border: 1px solid #000; padding: 3px 5px;">1/2/3 (Guardia 24 Horas)</td>
              <td style="border: 1px solid #000; padding: 3px 5px;">08:00 am - 08:00 am día siguiente (24h)</td>
            </tr>
            <tr>
              <td colspan="2" style="border: 1px solid #000; padding: 4px; font-size: 0.72rem; background: #fafafa;">
                <strong>F</strong> = Feriado &bull; <strong>V / V1</strong> = Vacaciones &bull; <strong>C</strong> = CITT &bull; <strong>LIB</strong> = Libre / Descanso
              </td>
            </tr>
          </table>

          <div style="display: flex; gap: 2rem;">
            <div style="text-align: center; border-top: 1px solid #000; width: 140px; padding-top: 4px; font-size: 0.75rem;">
              <strong>Elaborado por</strong><br>Control de Asistencia
            </div>
            <div style="text-align: center; border-top: 1px solid #000; width: 140px; padding-top: 4px; font-size: 0.75rem;">
              <strong>Revisado por</strong><br>Jefatura de RR.HH.
            </div>
            <div style="text-align: center; border-top: 1px solid #000; width: 140px; padding-top: 4px; font-size: 0.75rem;">
              <strong>V°B° Dirección</strong><br>Hospital Pacasmayo
            </div>
          </div>
        </div>
      </div>
    `;
  } catch (err) {
    console.error('Error cargando rol oficial:', err);
    container.innerHTML = `
      <div style="padding: 2rem; text-align: center; color: var(--danger-500); background: var(--bg-surface); border-radius: var(--radius-md);">
        Error al cargar la información del Rol Mensual Oficial. Intente nuevamente.
      </div>
    `;
  }
}

/**
 * Genera el documento HTML institucional oficial para la Ficha de Rol Mensual (PDF / Impresión)
 */
function generarHtmlFichaRolMensual() {
  const data = repState.ultimoDatosRol;
  if (!data) {
    return `
      <!DOCTYPE html>
      <html><body><p>No hay datos cargados para generar la ficha oficial. Por favor actualice la vista previa primero.</p></body></html>
    `;
  }

  const { areaNombre, categoriaTitulo, mesNombre, anio, totalDias, seccionesMap, esNumerico } = data;
  const letrasDias = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];

  return `
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Rol de Turnos - ${areaNombre} - ${mesNombre} ${anio}</title>
      <style>
        @page { size: A4 landscape; margin: 6mm 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #000; margin: 0; padding: 0; background: #fff; }
        .header-inst { text-align: center; margin-bottom: 8px; }
        .header-inst h4 { margin: 0; font-size: 8.5pt; text-transform: uppercase; color: #333; font-weight: bold; }
        .area-title { text-align: center; font-size: 11pt; font-weight: bold; text-decoration: underline; margin-top: 4px; }
        .period-title { text-align: center; font-size: 10pt; font-weight: bold; margin-bottom: 6px; }
        .cat-title { font-weight: bold; font-size: 9pt; margin-bottom: 4px; }
        .rol-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 7.5pt; }
        .rol-table th, .rol-table td { border: 1px solid #000; padding: 2px 1px; text-align: center; }
        .subarea-bar { background-color: #D9E1F2; font-weight: bold; font-size: 8.5pt; padding: 3px; text-align: center; border: 1px solid #000; }
        .th-dias { background-color: #D9D9D9; font-weight: bold; }
        .th-weekend { background-color: #BFBFBF; font-weight: bold; }
        .th-name { background-color: #D9D9D9; font-weight: bold; width: 190px; text-align: center; }
        .td-name { background-color: #E2EFDA; font-weight: bold; text-align: left !important; padding-left: 5px !important; white-space: nowrap; width: 190px; }
        .cell-shift { background-color: #E2EFDA; font-weight: bold; }
        .cell-special { background-color: #dbeafe; font-weight: bold; }
        .cell-weekend { background-color: #D9D9D9; }
        .cell-feriado { background-color: #fee2e2; color: #C00000; font-weight: bold; }
        .bottom-section { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 10px; page-break-inside: avoid; }
        .leyenda-box { border: 1px solid #000; border-collapse: collapse; font-size: 7.5pt; width: 440px; }
        .leyenda-box td { border: 1px solid #000; padding: 2px 4px; }
        .signatures { display: flex; justify-content: space-between; width: 550px; }
        .sig-box { width: 30%; text-align: center; border-top: 1px solid #000; padding-top: 3px; font-size: 7.5pt; }
      </style>
    </head>
    <body>
      <div class="header-inst">
        <h4>GOBIERNO REGIONAL LA LIBERTAD</h4>
        <h4>GERENCIA GENERAL DE SALUD</h4>
        <h4>RED INTEGRADA DE SALUD</h4>
        <h4>HOSPITAL DISTRITAL PACASMAYO - MINSA</h4>
      </div>

      <div class="area-title">${escapeHtml(areaNombre)}</div>
      <div class="period-title">${mesNombre} ${anio}</div>
      <div class="cat-title">${escapeHtml(categoriaTitulo)} - FORMATO OFICIAL (${esNumerico ? '1, 2, 3, 1/2' : 'M, T, N'})</div>

      <table class="rol-table">
        ${Object.keys(seccionesMap).map(grupo => {
          const personalGrupo = seccionesMap[grupo];
          return `
            <tr>
              <td colspan="${totalDias + 2}" class="subarea-bar">${escapeHtml(grupo)} (${personalGrupo.length})</td>
            </tr>
            <tr>
              <td class="th-dias">DÍAS</td>
              ${Array.from({ length: totalDias }, (_, i) => {
                const d = i + 1;
                const dayNum = new Date(anio, repState.mes - 1, d).getDay();
                const letra = letrasDias[dayNum];
                const isWe = (dayNum === 0 || dayNum === 6);
                return `<td class="${isWe ? 'th-weekend' : 'th-dias'}">${letra}</td>`;
              }).join('')}
              <td class="th-dias" style="width: 45px;">TOTAL</td>
            </tr>
            <tr>
              <td class="th-name">APELLIDOS Y NOMBRES</td>
              ${Array.from({ length: totalDias }, (_, i) => {
                const d = i + 1;
                const dayNum = new Date(anio, repState.mes - 1, d).getDay();
                const isWe = (dayNum === 0 || dayNum === 6);
                return `<td class="${isWe ? 'th-weekend' : 'th-dias'}">${d}</td>`;
              }).join('')}
              <td class="th-dias">HORAS</td>
            </tr>
            ${personalGrupo.map(p => {
              const esNom = ((p.condicion_laboral || '').includes('276') || (p.condicion_laboral || '').includes('NOMBRADO'));
              const totH = p.total_horas || 0;
              return `
                <tr>
                  <td class="td-name">
                    ${escapeHtml(p.nombre_completo)}
                    <span style="font-size: 6.5pt; font-weight: normal; color: #333;">(${esNom ? 'NOM' : 'CAS'})</span>
                  </td>
                  ${Array.from({ length: totalDias }, (_, i) => {
                    const d = i + 1;
                    const dayNum = new Date(anio, repState.mes - 1, d).getDay();
                    const isWe = (dayNum === 0 || dayNum === 6);
                    const isFeriado = esFeriadoNacional(repState.mes, d);
                    const tObj = p.programacion ? p.programacion[d] : null;
                    const rawCod = tObj ? tObj.codigo : (p.patron ? p.patron[d - 1] : 'LIB');
                    const cod = normalizarTurnoAMatrizMinsa(rawCod, esNumerico);

                    if (isFeriado) {
                      return `<td class="cell-feriado">F</td>`;
                    } else if (cod === 'LIB' || cod === '0' || !cod) {
                      return `<td class="${isWe ? 'cell-weekend' : ''}">LIB</td>`;
                    } else {
                      const isSpecial = ['1/2', '2/3', '1/2/3', 'GD', 'GN', 'G24'].includes(cod);
                      const cls = isSpecial ? 'cell-special' : 'cell-shift';
                      return `<td class="${cls}">${escapeHtml(cod)}</td>`;
                    }
                  }).join('')}
                  <td style="font-weight: bold; background-color: #E2EFDA; text-align: center;">${totH}h</td>
                </tr>
              `;
            }).join('')}
          `;
        }).join('')}
      </table>

      <div class="bottom-section">
        <table class="leyenda-box">
          <tr style="background: #eee; font-weight: bold;">
            <td colspan="2">LEYENDA OFICIAL HOSPITAL PACASMAYO (${esNumerico ? 'FORMATO 1, 2, 3, 1/2' : 'FORMATO M, T, N'}):</td>
          </tr>
          <tr>
            <td style="font-weight: bold; width: 180px;">1 (Turno Día / Mañana)</td>
            <td>07:00 / 08:00 am - 14:00 pm (6h / 8h)</td>
          </tr>
          <tr>
            <td style="font-weight: bold;">2 (Turno Tarde)</td>
            <td>14:00 pm - 20:00 pm (6h)</td>
          </tr>
          <tr>
            <td style="font-weight: bold;">3 (Turno Noche / Guardia)</td>
            <td>20:00 pm - 08:00 am (12h)</td>
          </tr>
          <tr>
            <td style="font-weight: bold;">1/2 (Doble Turno M+T / GD)</td>
            <td>08:00 am - 20:00 pm (Guardia Diurna 12h)</td>
          </tr>
          <tr>
            <td style="font-weight: bold;">2/3 (Tarde + Noche)</td>
            <td>14:00 pm - 08:00 am (18h)</td>
          </tr>
          <tr>
            <td style="font-weight: bold;">1/2/3 (Guardia 24 Horas)</td>
            <td>08:00 am - 08:00 am día siguiente (24h)</td>
          </tr>
          <tr>
            <td colspan="2" style="font-size: 7pt;">
              <strong>F = FERIADO</strong> &bull; <strong>V / V1 = VACACIONES</strong> &bull; <strong>C = CITT</strong> &bull; <strong>LIB = LIBRE</strong>
            </td>
          </tr>
        </table>

        <div class="signatures">
          <div class="sig-box">
            <strong>Elaborado por</strong><br>
            Control de Asistencia<br>
            Hospital Pacasmayo
          </div>
          <div class="sig-box">
            <strong>Revisado por</strong><br>
            Jefatura de Recursos Humanos<br>
            Hospital Pacasmayo
          </div>
          <div class="sig-box">
            <strong>V°B° Dirección Ejecutiva</strong><br>
            Red de Salud Pacasmayo
          </div>
        </div>
      </div>
    </body>
    </html>
  `;
}

function esFeriadoNacional(mes, dia) {
  const feriados = {
    '1-1': true,
    '5-1': true,
    '6-7': true,
    '6-29': true,
    '7-23': true,
    '7-28': true,
    '7-29': true,
    '8-6': true,
    '8-30': true,
    '10-8': true,
    '11-1': true,
    '12-8': true,
    '12-9': true,
    '12-25': true
  };
  return !!feriados[`${mes}-${dia}`];
}

function getBadgeEstadoSimple(estado) {
  switch (estado) {
    case 'ASISTIO': return '<span class="badge badge-present">Asistió Puntual</span>';
    case 'TARDANZA': return '<span class="badge badge-late">Tardanza</span>';
    case 'FALTA': return '<span class="badge badge-absent">Inasistencia</span>';
    case 'PERMISO': return '<span class="badge badge-leave">Permiso Autorizado</span>';
    case 'DESCANSO': return '<span class="badge badge-neutral">Descanso</span>';
    default: return `<span class="badge badge-neutral">${escapeHtml(estado)}</span>`;
  }
}

function escapeHtml(t) {
  if (!t) return '';
  return String(t)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

