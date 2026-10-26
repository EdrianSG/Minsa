/**
 * Lógica del Módulo de Programación Mensual - Hospital Distrital de Pacasmayo
 * Matriz interactiva de turnos y validador normativo de 150 horas
 * ETAPA 4
 */

let progState = {
  servicioId: 0,
  mes: 9,
  anio: 2026,
  condicion: '',
  versionBase: 1,
  dias: [],
  catalogTurnos: {},
  turnosDisponibles: [],
  matriz: [],
  cambiosPendientes: [],
  selectedCell: null // { personalId, dia, cellEl }
};

document.addEventListener('DOMContentLoaded', () => {
  const selServ = document.getElementById('progServiceSelect');
  if (selServ) {
    progState.servicioId = selServ.value !== '' ? parseInt(selServ.value, 10) : 0;
  }
  initProgramacionControls();
  loadMonthlyMatrix();

  window.addEventListener('beforeunload', (e) => {
    if (progState.cambiosPendientes && progState.cambiosPendientes.length > 0) {
      e.preventDefault();
      e.returnValue = 'Tiene cambios en la programación pendientes de guardar en disco. ¿Está seguro que desea salir?';
      return e.returnValue;
    }
  });
});

/**
 * Inicializa los eventos de controles de filtrado y acciones
 */
function initProgramacionControls() {
  const serviceSelect = document.getElementById('progServiceSelect');
  const monthSelect = document.getElementById('progMonthSelect');
  const yearSelect = document.getElementById('progYearSelect');
  const conditionSelect = document.getElementById('progConditionSelect');
  const btnValidar = document.getElementById('btnValidar150h');
  const btnGuardar = document.getElementById('btnGuardarRol');
  const btnEnviar = document.getElementById('btnEnviarRol');

  const formHorarioProg = document.getElementById('formEditarHorarioProg');
  if (formHorarioProg) {
    formHorarioProg.addEventListener('submit', (e) => {
      e.preventDefault();
      guardarHorarioTrabajadorDB();
    });
  }

  if (serviceSelect) {
    serviceSelect.addEventListener('change', (e) => {
      progState.servicioId = intval(e.target.value);
      loadMonthlyMatrix();
    });
  }

  if (monthSelect) {
    monthSelect.addEventListener('change', (e) => {
      progState.mes = intval(e.target.value);
      loadMonthlyMatrix();
    });
  }

  if (yearSelect) {
    yearSelect.addEventListener('change', (e) => {
      progState.anio = intval(e.target.value);
      loadMonthlyMatrix();
    });
  }

  if (conditionSelect) {
    conditionSelect.addEventListener('change', (e) => {
      progState.condicion = e.target.value;
      loadMonthlyMatrix();
    });
  }

  if (btnValidar) {
    btnValidar.addEventListener('click', () => {
      ejecutarValidacion150h();
    });
  }

  if (btnGuardar) {
    btnGuardar.addEventListener('click', () => {
      guardarCambiosRol();
    });
  }

  if (btnEnviar) {
    btnEnviar.addEventListener('click', () => {
      enviarRolRRHH();
    });
  }

  const btnExportarExcel = document.getElementById('btnExportarExcelMINSA');
  if (btnExportarExcel) {
    btnExportarExcel.addEventListener('click', () => {
      const url = `api/reportes/exportar_excel.php?tipo=rol&mes=${progState.mes}&anio=${progState.anio}&servicio_id=${progState.servicioId}`;
      window.location.href = url;
    });
  }

  const btnExportarPdf = document.getElementById('btnExportarPdfFichaProg');
  if (btnExportarPdf) {
    btnExportarPdf.addEventListener('click', () => {
      imprimirFichaRolProg();
    });
  }

  // Atajos de teclado en la matriz (1=Mañana, 2=Tarde, 3=Noche, 0/L=Libre, etc.)
  document.addEventListener('keydown', (e) => {
    // Si hay un modal abierto o el foco está en un input/select, no interceptar
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
      return;
    }

    if (!progState.selectedCell) return;

    const key = e.key.toUpperCase();
    const keyMap = {
      '1': 'M',
      '2': 'T',
      '3': 'N',
      '4': 'G24',
      '0': 'LIB',
      'L': 'LIB',
      'M': 'M',
      'T': 'T',
      'N': 'N',
      'V': 'VAC',
      'D': 'DESC'
    };

    if (keyMap[key]) {
      e.preventDefault();
      seleccionarTurnoDePaleta(keyMap[key]);

      // Avanzar automáticamente a la siguiente celda del día siguiente
      const nextDia = progState.selectedCell.dia + 1;
      const pid = progState.selectedCell.personalId;
      const nextCell = document.querySelector(`.matrix-cell[data-personal-id="${pid}"][data-dia="${nextDia}"]`);
      if (nextCell) {
        document.querySelectorAll('.matrix-cell.selected-cell').forEach(c => c.classList.remove('selected-cell'));
        nextCell.classList.add('selected-cell');
        progState.selectedCell = { personalId: pid, dia: nextDia, cellEl: nextCell };
      }
    }
  });
}

/**
 * Carga la matriz mensual desde la API
 */
async function loadMonthlyMatrix() {
  const tableBody = document.getElementById('matrixTableBody');
  const headerRow = document.getElementById('matrixHeaderRow');
  if (tableBody) {
    tableBody.innerHTML = Array(6).fill(0).map(() => `
      <tr class="skeleton-table-row">
        <td>
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <div class="skeleton skeleton-circle" style="width: 28px; height: 28px;"></div>
            <div style="flex: 1;">
              <div class="skeleton skeleton-text w-80" style="height: 0.9rem;"></div>
              <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
            </div>
          </div>
        </td>
        ${Array(30).fill(0).map(() => `
          <td style="padding: 4px !important; text-align: center;">
            <div class="skeleton" style="width: 26px; height: 26px; border-radius: var(--radius-xs); margin: 0 auto;"></div>
          </td>
        `).join('')}
        <td style="text-align: center;">
          <div class="skeleton skeleton-pill" style="width: 55px; margin: 0 auto;"></div>
        </td>
      </tr>
    `).join('');
  }

  try {
    const params = {
      servicio_id: progState.servicioId,
      mes: progState.mes,
      anio: progState.anio,
      condicion: progState.condicion
    };

    const res = await API.get('programacion/mensual.php', params);
    if (res && res.ok) {
      progState.dias = res.periodo.dias;
      progState.catalogTurnos = res.catalog_turnos;
      progState.turnosDisponibles = res.turnos_disponibles || [];
      progState.matriz = res.matriz;
      progState.cambiosPendientes = [];
      progState.versionBase = res.resiliencia?.snapshot_meta?.version || 1;

      updatePeriodoUI(res.periodo, res.resiliencia);
      updateResumenKPIs(res.resumen_servicio);
      renderMatrixHeader(res.periodo.dias);
      renderMatrixBody(res.matriz, res.periodo.dias);
      renderMatrixCoverage(res.resumen_servicio.cobertura_diaria, res.periodo.dias);
      renderPaletteModal(res.catalog_turnos);
    }
  } catch (error) {
    console.error('Error cargando matriz mensual:', error);
  }
}

/**
 * Actualiza la información del periodo y estado
 */
function updatePeriodoUI(periodo, resiliencia) {
  const label = document.getElementById('progPeriodoTitulo');
  if (label) {
    let extraRes = '';
    if (resiliencia && resiliencia.es_snapshot) {
      const fechaSnap = resiliencia.snapshot_meta?.saved_at || 'Reciente';
      extraRes = ` <span class="badge badge-neutral" style="font-size: 0.68rem; background-color: var(--primary-50); color: var(--primary-700);" title="Cargado desde el Snapshot Local de disco">• 💾 Snapshot Real (${fechaSnap}) v${progState.versionBase}</span>`;
    }
    label.innerHTML = `${escapeHtml(periodo.servicio_nombre)} • ${periodo.mes_nombre} ${periodo.anio}${extraRes}`;
  }

  const badge = document.getElementById('progEstadoBadge');
  if (badge) {
    badge.textContent = periodo.estado_rol;
  }
}

/**
 * Actualiza los contadores KPI superiores del rol
 */
function updateResumenKPIs(resumen) {
  if (!resumen) return;
  const setEl = (id, v) => {
    const el = document.getElementById(id);
    if (el) el.textContent = v;
  };

  setEl('kpiProgPersonal', resumen.total_personal);
  setEl('kpiProgHoras', `${resumen.total_horas_programadas} hrs`);
  setEl('kpiProgCumplen', resumen.personal_cumple_150h);
  setEl('kpiProgObservados', resumen.personal_observado);

  const obsCard = document.getElementById('kpiCardObservados');
  if (obsCard) {
    if (resumen.personal_observado > 0) {
      obsCard.style.borderColor = 'var(--status-late-accent)';
    } else {
      obsCard.style.borderColor = 'var(--border-subtle)';
    }
  }
}

/**
 * Renderiza los encabezados de columnas (Días del Mes)
 */
function renderMatrixHeader(dias) {
  const row = document.getElementById('matrixHeaderRow');
  if (!row) return;

  let html = `
    <th class="col-worker">Personal Asignado</th>
  `;

  dias.forEach(d => {
    const isWk = d.es_fin_de_semana;
    html += `
      <th class="${isWk ? 'col-weekend' : ''}" title="${d.fecha}">
        <div class="day-header-box">
          <span class="day-header-num">${d.dia}</span>
          <span class="day-header-name" style="${isWk ? 'color: var(--primary-600); font-weight: 700;' : ''}">${d.dia_semana}</span>
        </div>
      </th>
    `;
  });

  html += `
    <th class="col-hours">Cómputo Total</th>
  `;

  row.innerHTML = html;
}

/**
 * Renderiza las filas de los trabajadores y celdas diarias
 */
function renderMatrixBody(matriz, dias) {
  const tbody = document.getElementById('matrixTableBody');
  if (!tbody) return;

  if (!matriz || matriz.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="35" style="text-align: center; padding: 2.5rem; color: var(--text-secondary);">
          No hay trabajadores asignados a este servicio en el periodo seleccionado.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = matriz.map(w => {
    const isAsist = (w.regimen_laboral === 'ASISTENCIAL');
    const badgeHours = getHoursBadgeHtml(w.total_horas, isAsist);

    let cellsHtml = '';
    dias.forEach(d => {
      const prog = w.programacion[d.dia] || { codigo: 'LIB', color: '#94a3b8', horas: 0 };
      const isLib = (prog.codigo === 'LIB');
      const isWk = d.es_fin_de_semana;

      cellsHtml += `
        <td class="matrix-cell ${isWk ? 'col-weekend' : ''}" 
            data-personal-id="${w.personal_id}" 
            data-dia="${d.dia}" 
            onclick="abrirPaletaTurnos(this, ${w.personal_id}, ${d.dia}, '${prog.codigo}')">
          <span class="shift-cell-badge ${isLib ? 'badge-lib' : ''}" 
                style="--cell-color: ${prog.color};">
            ${prog.codigo}
          </span>
        </td>
      `;
    });

    return `
      <tr id="row-worker-${w.personal_id}">
        <!-- Columna Fija: Trabajador -->
        <td class="col-worker">
          <div style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem; line-height: 1.2;">
            ${escapeHtml(w.nombre_completo)}
          </div>
          <div style="font-size: 0.74rem; color: var(--text-secondary); margin-top: 0.15rem;">
            DNI: ${escapeHtml(w.dni)} • ${escapeHtml(w.cargo_nombre)}
          </div>
          <div style="display: flex; gap: 0.35rem; margin-top: 0.35rem; align-items: center; flex-wrap: wrap;">
            <span class="badge ${w.condicion_laboral === 'NOMBRADO' ? 'badge-nombrado' : 'badge-cas'}" style="font-size: 0.65rem; padding: 0.1rem 0.4rem;">
              ${escapeHtml(w.condicion_laboral)}
            </span>
            ${w.aplica_seti == 1 ? '<span class="badge badge-asistencial" style="font-size: 0.65rem; padding: 0.1rem 0.4rem;">SETI 150h</span>' : '<span class="badge badge-neutral" style="font-size: 0.65rem;">ADMIN</span>'}
            <button type="button" class="btn btn-outline btn-xs" onclick="editarHorarioTrabajador(${w.personal_id})" title="Editar horario semanal o pauta del mes" style="font-size: 0.68rem; padding: 0.12rem 0.45rem; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 0.25rem;">
              <span>✏</span>
              <span>Editar Horario</span>
            </button>
          </div>
        </td>

        <!-- Celdas Diarias -->
        ${cellsHtml}

        <!-- Columna Fija: Total Horas -->
        <td class="col-hours" id="hours-worker-${w.personal_id}">
          ${badgeHours}
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Renderiza la fila de cobertura diaria inferior
 */
function renderMatrixCoverage(cobertura, dias) {
  const row = document.getElementById('matrixCoverageRow');
  if (!row) return;

  let html = `
    <td class="col-worker" style="font-weight: 700;">
      <div style="font-weight: 800; font-size: 0.82rem; color: var(--text-primary);">
        Personal Activo en Turno
      </div>
      <div style="font-size: 0.7rem; color: var(--text-tertiary);">
        Cobertura diaria por guardia
      </div>
    </td>
  `;

  dias.forEach(d => {
    const cob = cobertura[d.dia] || { total: 0 };
    const isWk = d.es_fin_de_semana;

    html += `
      <td class="${isWk ? 'col-weekend' : ''}" id="cov-day-${d.dia}">
        <div class="coverage-box">
          <span class="coverage-num" style="${cob.total < 3 ? 'color: var(--status-late-accent);' : ''}">${cob.total}</span>
          <span class="coverage-lbl">pers.</span>
        </div>
      </td>
    `;
  });

  html += `
    <td class="col-hours" style="font-weight: 800; color: var(--primary-700);">
      TOTAL
    </td>
  `;

  row.innerHTML = html;
}

/**
 * Retorna el badge estilizado de cómputo de horas mensuales
 */
function getHoursBadgeHtml(totalHoras, isAsistencial) {
  if (!isAsistencial) {
    return `<span class="badge badge-neutral" style="font-size: 0.8rem; font-weight: 700;">${totalHoras.toFixed(1)} hrs</span>`;
  }

  const dif = totalHoras - 150.0;
  if (Math.abs(dif) < 0.1) {
    return `<span class="badge-150h-ok" title="Cumple exactamente las 150 horas del régimen asistencial">150.0h ✓ Cumple</span>`;
  } else if (dif < 0) {
    return `<span class="badge-150h-deficit" title="Déficit de horas respecto a las 150h normativas">${totalHoras.toFixed(1)}h ⚠ (${dif.toFixed(1)}h)</span>`;
  } else {
    return `<span class="badge-150h-excess" title="Sobretiempo respecto a las 150h normativas">${totalHoras.toFixed(1)}h + (+${dif.toFixed(1)}h)</span>`;
  }
}

/**
 * Prepara la paleta de selección rápida de turnos con números y letras
 */
function renderPaletteModal(catalog) {
  const container = document.getElementById('quickShiftPaletteList');
  if (!container) return;

  // Lista ordenada y curada de turnos principales (evitar redundancias en vista de botones)
  const displayCodes = ['M', 'T', 'N', '1/2', '2/3', 'G24', 'GD', 'GN', 'ADM', 'VAC', 'DESC', 'LIB'];

  container.innerHTML = displayCodes.map(cod => {
    const t = catalog[cod] || { nombre: cod, color: '#94a3b8', horas: 0, tecla: '' };
    const keyHint = t.tecla ? `<kbd style="background: var(--bg-surface-muted); padding: 2px 5px; border-radius: 4px; font-size: 0.72rem; border: 1px solid var(--border-default);">${t.tecla}</kbd>` : '';
    return `
      <div class="palette-item" onclick="seleccionarTurnoDePaleta('${cod}')" title="Asignar ${t.nombre}">
        <div class="palette-item-code" style="background-color: ${t.color};">
          ${cod}
        </div>
        <div class="palette-item-info" style="flex: 1;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="palette-item-name" style="font-weight: 700;">${t.nombre}</span>
            ${keyHint}
          </div>
          <span class="palette-item-hrs">${t.horas} horas</span>
        </div>
      </div>
    `;
  }).join('');
}

/**
 * Cambia de pestaña en el modal de paleta de turnos
 */
function switchPaletteTab(tab) {
  const btnFast = document.getElementById('tabBtnPaletteFast');
  const btnDetail = document.getElementById('tabBtnPaletteDetail');
  const viewFast = document.getElementById('paletteTabFast');
  const viewDetail = document.getElementById('paletteTabDetail');

  if (!btnFast || !btnDetail || !viewFast || !viewDetail) return;

  if (tab === 'fast') {
    btnFast.classList.add('active');
    btnFast.style.color = 'var(--primary-600)';
    btnFast.style.borderBottom = '2px solid var(--primary-600)';
    btnDetail.classList.remove('active');
    btnDetail.style.color = 'var(--text-secondary)';
    btnDetail.style.borderBottom = 'none';
    viewFast.style.display = 'block';
    viewDetail.style.display = 'none';
  } else {
    btnDetail.classList.add('active');
    btnDetail.style.color = 'var(--primary-600)';
    btnDetail.style.borderBottom = '2px solid var(--primary-600)';
    btnFast.classList.remove('active');
    btnFast.style.color = 'var(--text-secondary)';
    btnFast.style.borderBottom = 'none';
    viewFast.style.display = 'none';
    viewDetail.style.display = 'block';
    actualizarValoresTurnoDetallado();
  }
}

/**
 * Actualiza los campos numéricos de horas y horarios en la pestaña detallada
 */
function actualizarValoresTurnoDetallado() {
  const selCod = document.getElementById('customTurnoCodigo');
  const inpHoras = document.getElementById('customTurnoHoras');
  const inpInicio = document.getElementById('customTurnoInicio');
  const inpFin = document.getElementById('customTurnoFin');

  if (!selCod || !inpHoras) return;
  const cod = selCod.value;

  const defaultHours = {
    '1': 6.0, 'M': 6.0,
    '2': 6.0, 'T': 6.0,
    '3': 12.0, 'N': 12.0,
    '1/2': 12.0,
    '2/3': 18.0,
    'G24': 24.0,
    'GD': 12.0,
    'GN': 12.0,
    'ADM': 8.0,
    'VAC': 6.0,
    'DESC': 6.0,
    'LIB': 0.0
  };

  const defaultTimes = {
    '1': ['07:00', '13:00'], 'M': ['07:00', '13:00'],
    '2': ['13:00', '19:00'], 'T': ['13:00', '19:00'],
    '3': ['19:00', '07:00'], 'N': ['19:00', '07:00'],
    '1/2': ['07:00', '19:00'],
    '2/3': ['13:00', '07:00'],
    'G24': ['07:00', '07:00'],
    'GD': ['07:00', '19:00'],
    'GN': ['19:00', '07:00'],
    'ADM': ['08:00', '16:45'],
    'VAC': ['08:00', '14:00'],
    'DESC': ['08:00', '14:00'],
    'LIB': ['00:00', '00:00']
  };

  inpHoras.value = defaultHours[cod] !== undefined ? defaultHours[cod] : 6.0;
  if (inpInicio && inpFin && defaultTimes[cod]) {
    inpInicio.value = defaultTimes[cod][0];
    inpFin.value = defaultTimes[cod][1];
  }
}

/**
 * Aplica el turno detallado / de emergencia a la celda seleccionada
 */
function aplicarTurnoDetallado() {
  if (!progState.selectedCell) return;

  const selCod = document.getElementById('customTurnoCodigo');
  const inpHoras = document.getElementById('customTurnoHoras');
  const checkEmergencia = document.getElementById('checkEmergenciaVac');

  let codigo = selCod ? selCod.value : 'M';
  const horas = inpHoras ? (parseFloat(inpHoras.value) || 0) : 6.0;
  const esEmergencia = checkEmergencia ? checkEmergencia.checked : false;

  // Manejo de turno trabajado durante vacaciones programadas
  if (esEmergencia) {
    if (codigo === '1' || codigo === 'M') {
      codigo = 'VAC-1';
    } else if (codigo === '2' || codigo === 'T') {
      codigo = 'VAC-2';
    } else if (codigo === '3' || codigo === 'N') {
      codigo = 'VAC-3';
    } else {
      codigo = `VAC-${codigo}`;
    }
  }

  // Si tiene horas personalizadas, usar color del catálogo o color rojo de emergencia
  let customColor = '#0284c7';
  if (esEmergencia) {
    customColor = '#e11d48';
  } else if (progState.catalogTurnos[codigo]) {
    customColor = progState.catalogTurnos[codigo].color;
  }

  seleccionarTurnoDePaleta(codigo, horas, customColor);

  if (esEmergencia) {
    showToast(`🚨 Turno de Emergencia asignado durante vacaciones. El día trabajado se repondrá al saldo vacacional.`, 'warning');
  }
}

/**
 * Abre la paleta flotante para la celda seleccionada y enfoca la interacción
 */
function abrirPaletaTurnos(cellEl, personalId, dia, currentCode) {
  // Limpiar selección previa en celdas
  document.querySelectorAll('.matrix-cell.selected-cell').forEach(c => c.classList.remove('selected-cell'));
  
  if (cellEl) {
    cellEl.classList.add('selected-cell');
  }

  progState.selectedCell = { personalId: String(personalId), dia: intval(dia), cellEl };

  const worker = progState.matriz.find(w => String(w.personal_id) === String(personalId));
  const title = document.getElementById('paletteModalTitle');
  if (title && worker) {
    title.innerHTML = `Asignar / Editar Turno: <strong>${escapeHtml(worker.nombre_completo)}</strong> (Día ${dia})`;
  }

  // Iniciar en pestaña rápida
  switchPaletteTab('fast');

  // Pre-cargar valores en formulario detallado
  const selCod = document.getElementById('customTurnoCodigo');
  if (selCod) {
    if (currentCode === 'M') selCod.value = '1';
    else if (currentCode === 'T') selCod.value = '2';
    else if (currentCode === 'N') selCod.value = '3';
    else if (selCod.querySelector(`option[value="${currentCode}"]`)) {
      selCod.value = currentCode;
    }
  }

  const checkVac = document.getElementById('checkEmergenciaVac');
  if (checkVac) {
    checkVac.checked = (currentCode && currentCode.startsWith('VAC-'));
  }

  openModal('modalPaletteTurnos');
}

/**
 * Aplica el turno seleccionado a la celda y recalcula en tiempo real
 */
function seleccionarTurnoDePaleta(nuevoCodigo, customHoras = null, customColor = null) {
  if (!progState.selectedCell) return;

  const { personalId, dia, cellEl } = progState.selectedCell;
  const worker = progState.matriz.find(w => String(w.personal_id) === String(personalId));
  if (!worker) return;

  const infoCatalogo = progState.catalogTurnos[nuevoCodigo] || { horas: 0, color: '#94a3b8' };
  const horasTurno = (customHoras !== null) ? customHoras : infoCatalogo.horas;
  const colorTurno = (customColor !== null) ? customColor : infoCatalogo.color;

  // Actualizar objeto en memoria
  worker.programacion[dia] = {
    codigo: nuevoCodigo,
    horas: horasTurno,
    color: colorTurno
  };

  // Recalcular horas del trabajador
  let nuevasHoras = 0.0;
  Object.keys(worker.programacion).forEach(d => {
    nuevasHoras += (worker.programacion[d].horas || 0.0);
  });
  worker.total_horas = round(nuevasHoras, 1);
  const isAsist = (worker.regimen_laboral === 'ASISTENCIAL');
  worker.cumple_150h = isAsist ? (Math.abs(nuevasHoras - 150.0) < 0.1) : true;

  // Actualizar celda visual en el DOM
  const isLib = (nuevoCodigo === 'LIB');
  cellEl.innerHTML = `
    <span class="shift-cell-badge ${isLib ? 'badge-lib' : ''}" style="--cell-color: ${colorTurno};">
      ${nuevoCodigo}
    </span>
  `;

  // Actualizar columna de horas del trabajador en el DOM
  const hoursCell = document.getElementById(`hours-worker-${personalId}`);
  if (hoursCell) {
    hoursCell.innerHTML = getHoursBadgeHtml(worker.total_horas, isAsist);
  }

  // Actualizar cobertura diaria en el DOM
  recalcularCoberturaDia(dia);

  // Actualizar contadores KPI del servicio
  recalcularKpisServicio();

  // Registrar en cambios pendientes
  progState.cambiosPendientes.push({
    personal_id: personalId,
    dia: dia,
    codigo_turno: nuevoCodigo,
    horas: horasTurno
  });

  const paletteModal = document.getElementById('modalPaletteTurnos');
  if (paletteModal && paletteModal.classList.contains('open')) {
    closeModal('modalPaletteTurnos');
  }
  showToast(`Día ${dia}: Turno actualizado a ${nuevoCodigo} (${turnoInfo.horas}h)`, 'info');
}

/**
 * Recalcula en cliente la cobertura de un día específico
 */
function recalcularCoberturaDia(dia) {
  let count = 0;
  progState.matriz.forEach(w => {
    const cod = w.programacion[dia]?.codigo || 'LIB';
    if (cod !== 'LIB') count++;
  });

  const covCell = document.getElementById(`cov-day-${dia}`);
  if (covCell) {
    covCell.innerHTML = `
      <div class="coverage-box">
        <span class="coverage-num" style="${count < 3 ? 'color: var(--status-late-accent);' : ''}">${count}</span>
        <span class="coverage-lbl">pers.</span>
      </div>
    `;
  }
}

/**
 * Recalcula los KPIs globales del servicio en tiempo real
 */
function recalcularKpisServicio() {
  let totalHoras = 0;
  let cumplen = 0;
  let observados = 0;

  progState.matriz.forEach(w => {
    totalHoras += w.total_horas;
    if (w.regimen_laboral === 'ASISTENCIAL') {
      if (Math.abs(w.total_horas - 150.0) < 0.1) {
        cumplen++;
      } else {
        observados++;
      }
    }
  });

  updateResumenKPIs({
    total_personal: progState.matriz.length,
    total_horas_programadas: round(totalHoras, 1),
    personal_cumple_150h: cumplen,
    personal_observado: observados
  });
}

/**
 * Ejecuta el validador oficial de 150 horas
 */
async function ejecutarValidacion150h() {
  try {
    const res = await API.post('programacion/validar.php', {
      matriz: progState.matriz,
      servicio_nombre: document.getElementById('progPeriodoTitulo')?.textContent || 'Emergencia',
      mes_nombre: `Setiembre ${progState.anio}`
    });

    if (res && res.ok) {
      renderModalValidacion(res);
      openModal('modalValidacion150h');
    }
  } catch (error) {
    showToast('Error al ejecutar la validación de horas.', 'danger');
  }
}

/**
 * Renderiza el reporte de validación en el modal
 */
function renderModalValidacion(report) {
  const alertBox = document.getElementById('valReportAlert');
  const obsList = document.getElementById('valReportObsList');
  const okList = document.getElementById('valReportOkList');
  const btnAprobarModal = document.getElementById('btnAprobarModal');

  if (alertBox) {
    if (report.puede_enviar) {
      alertBox.className = 'toast toast-success';
      alertBox.style.margin = '0 0 1rem 0';
      alertBox.innerHTML = `
        <div style="font-size: 1.35rem; margin-right: 0.5rem;">✓</div>
        <div>
          <strong style="font-size: 0.95rem;">Rol Aprobado - Cumplimiento del 100%</strong>
          <p style="font-size: 0.8rem; margin-top: 0.2rem;">${report.dictamen}</p>
        </div>
      `;
      if (btnAprobarModal) btnAprobarModal.disabled = false;
    } else {
      alertBox.className = 'toast toast-warning';
      alertBox.style.margin = '0 0 1rem 0';
      alertBox.innerHTML = `
        <div style="font-size: 1.35rem; margin-right: 0.5rem;">⚠</div>
        <div>
          <strong style="font-size: 0.95rem;">Observaciones Pendientes de Carga Horaria</strong>
          <p style="font-size: 0.8rem; margin-top: 0.2rem;">${report.dictamen}</p>
        </div>
      `;
      if (btnAprobarModal) btnAprobarModal.disabled = true;
    }
  }

  // Lista de observados
  if (obsList) {
    if (report.observados.length === 0) {
      obsList.innerHTML = `<div style="color: var(--text-tertiary); font-size: 0.82rem;">No hay observaciones pendientes.</div>`;
    } else {
      obsList.innerHTML = report.observados.map(o => `
        <div class="search-result-item" style="border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
          <div>
            <div style="font-weight: 700; color: var(--text-primary);">${o.trabajador}</div>
            <div style="font-size: 0.76rem; color: var(--text-secondary);">${o.mensaje}</div>
          </div>
          <span class="badge ${o.tipo === 'DEFICIT' ? 'badge-late' : 'badge-absent'}">
            ${o.tipo === 'DEFICIT' ? `${o.horas}h (-${Math.abs(o.diferencia)}h)` : `${o.horas}h (+${o.diferencia}h)`}
          </span>
        </div>
      `).join('');
    }
  }

  // Lista de cumplidores
  if (okList) {
    okList.innerHTML = report.cumplen.map(c => `
      <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; font-size: 0.82rem; border-bottom: 1px dashed var(--border-subtle);">
        <span>${c.trabajador} (DNI: ${c.dni})</span>
        <span style="font-weight: 700; color: var(--status-present-accent);">150.0h ✓</span>
      </div>
    `).join('');
  }
}

/**
 * Guarda los cambios pendientes en el backend
 */
async function guardarCambiosRol(forzar = false) {
  if (!progState.cambiosPendientes || progState.cambiosPendientes.length === 0) {
    showToast('No hay cambios pendientes por guardar.', 'info');
    return;
  }

  try {
    const res = await API.post('programacion/guardar.php', {
      servicio_id: progState.servicioId,
      mes: progState.mes,
      anio: progState.anio,
      version_base: progState.versionBase,
      forzar: forzar,
      cambios: progState.cambiosPendientes
    });

    if (res && res.conflicto) {
      // Conflicto de concurrencia detectado (Estrategia C)
      const deseaForzar = confirm(
        `⚠ CONFLICTO DE CONCURRENCIA:\n\n${res.mensaje}\n\n` +
        `• Su versión en pantalla: v${res.version_cliente}\n` +
        `• Versión más reciente en el servidor: v${res.version_servidor} (${res.ultima_modificacion})\n\n` +
        `¿Desea FORZAR el guardado para aplicar sus cambios sobre la última versión?\n\n` +
        `[Aceptar]: Forzar guardado e integrar sus cambios.\n` +
        `[Cancelar]: Recargar la versión del servidor.`
      );
      if (deseaForzar) {
        await guardarCambiosRol(true);
      } else {
        await loadMonthlyMatrix();
      }
      return;
    }

    if (res && res.ok) {
      progState.cambiosPendientes = [];
      if (res.nueva_version) {
        progState.versionBase = res.nueva_version;
      }
      showToast(`Programación guardada y asegurada en disco (v${progState.versionBase}).`, 'success');
      // Actualizar badge de resiliencia si existe
      const badgeRes = document.querySelector('.prog-meta-pill');
      if (badgeRes) {
        badgeRes.innerHTML = `💾 Snapshot Real v${progState.versionBase}`;
      }
    }
  } catch (error) {
    showToast('Error al guardar la programación mensual.', 'danger');
  }
}

/**
 * Enviar el rol a RR.HH. previa validación
 */
function enviarRolRRHH() {
  const observados = progState.matriz.filter(w => w.regimen_laboral === 'ASISTENCIAL' && !w.cumple_150h);
  if (observados.length > 0) {
    showToast(`No se puede enviar a RR.HH.: Existen ${observados.length} trabajadores asistenciales que no cumplen con 150 horas exactas.`, 'danger');
    ejecutarValidacion150h();
    return;
  }

  const badge = document.getElementById('progEstadoBadge');
  if (badge) {
    badge.textContent = 'ENVIADO';
    badge.className = 'badge badge-late';
  }
  showToast('El rol mensual ha sido validado y enviado a Recursos Humanos con éxito.', 'success');
}

function round(val, dec = 1) {
  const p = Math.pow(10, dec);
  return Math.round(val * p) / p;
}

function intval(v) {
  return parseInt(v, 10) || 0;
}

function escapeHtml(t) {
  if (!t) return '';
  return String(t)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Exporta / Imprime exclusivamente la Ficha Oficial del Rol Mensual
 */
function imprimirFichaRolProg() {
  const nombresMeses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SETIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
  const mesNombre = nombresMeses[progState.mes] || 'SETIEMBRE';
  const anio = progState.anio;
  const dias = progState.dias || [];
  const totalDias = dias.length;
  const letrasDias = { 'Dom': 'D', 'Lun': 'L', 'Mar': 'M', 'Mié': 'X', 'Jue': 'J', 'Vie': 'V', 'Sáb': 'S' };

  const selectServicio = document.getElementById('progServiceSelect');
  const areaNombre = selectServicio ? selectServicio.options[selectServicio.selectedIndex].text.toUpperCase() : 'EMERGENCIA Y CUIDADOS CRÍTICOS';
  const isAsist = (progState.servicioId == 1 || progState.servicioId == 2 || progState.servicioId == 3 || progState.servicioId == 4 || progState.servicioId == 5 || progState.servicioId == 8);

  const html = `
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
        .cell-weekend { background-color: #D9D9D9; }
        .cell-feriado { background-color: #E2EFDA; color: #C00000; font-weight: bold; }
        .bottom-section { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 10px; page-break-inside: avoid; }
        .leyenda-box { border: 1px solid #000; border-collapse: collapse; font-size: 7.5pt; width: 340px; }
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
        <h4>DISTRITAL PACASMAYO - MINSA</h4>
      </div>

      <div class="area-title">${escapeHtml(areaNombre)}</div>
      <div class="period-title">${mesNombre} ${anio}</div>
      <div class="cat-title">${isAsist ? 'ASISTENCIALES' : 'ADMINISTRATIVOS'}</div>

      <table class="rol-table">
        <tr>
          <td colspan="${totalDias + 1}" class="subarea-bar">PROGRAMACIÓN MENSUAL DEL SERVICIO</td>
        </tr>
        <tr>
          <td class="th-dias">DÍAS</td>
          ${dias.map(d => {
            const letra = letrasDias[d.dia_semana] || d.dia_semana.substring(0, 1);
            return `<td class="${d.es_fin_de_semana ? 'th-weekend' : 'th-dias'}">${letra}</td>`;
          }).join('')}
        </tr>
        <tr>
          <td class="th-name">NOMBRE COMPLETO</td>
          ${dias.map(d => `<td class="${d.es_fin_de_semana ? 'th-weekend' : 'th-dias'}">${d.dia}</td>`).join('')}
        </tr>
        ${progState.matriz.map(w => `
          <tr>
            <td class="td-name">${escapeHtml(w.nombre_completo)}</td>
            ${dias.map(d => {
              const turno = w.turnos_dias && w.turnos_dias[d.dia] ? w.turnos_dias[d.dia] : '';
              const isWe = d.es_fin_de_semana;
              const isLib = (turno === 'LIB' || !turno);

              if (isWe && isLib) {
                return `<td class="cell-weekend"></td>`;
              } else if (turno === 'LIB') {
                return `<td class="cell-weekend"></td>`;
              } else {
                return `<td class="cell-shift">${escapeHtml(turno)}</td>`;
              }
            }).join('')}
          </tr>
        `).join('')}
      </table>

      <div class="bottom-section">
        <table class="leyenda-box">
          <tr style="background: #eee; font-weight: bold;"><td colspan="2">LEYENDA:</td></tr>
          <tr>
            <td style="font-weight: bold; width: 180px;">1 = 7:00 am - 15:00 pm</td>
            <td style="font-weight: bold; text-align: center;">MAÑANA</td>
          </tr>
          ${isAsist ? `
            <tr><td style="font-weight: bold;">M = 08:00 am - 14:00 pm</td><td style="text-align: center;">TURNO MAÑANA</td></tr>
            <tr><td style="font-weight: bold;">GD = 08:00 am - 20:00 pm</td><td style="text-align: center;">GUARDIA DIURNA (12h)</td></tr>
            <tr><td style="font-weight: bold;">GN = 20:00 pm - 08:00 am</td><td style="text-align: center;">GUARDIA NOCTURNA (12h)</td></tr>
          ` : ''}
          <tr>
            <td colspan="2" style="font-size: 7pt;">
              <strong>F = FERIADO</strong> &bull; <strong>V = VACACIONES</strong> &bull; <strong>C = CITT</strong> &bull; <strong>LIB = LIBRE</strong>
            </td>
          </tr>
        </table>

        <div class="signatures">
          <div class="sig-box">
            <strong>Elaborado por</strong><br>
            Jefatura de Servicio
          </div>
          <div class="sig-box">
            <strong>Revisado por</strong><br>
            Unidad de Personal
          </div>
          <div class="sig-box">
            <strong>V°B° Dirección</strong><br>
            Hospital Pacasmayo
          </div>
        </div>
      </div>
    </body>
    </html>
  `;

  // Imprimir exclusivamente en iframe aislado
  let iframe = document.getElementById('printFichaProgIframe');
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id = 'printFichaProgIframe';
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

/* =========================================================
   GESTIÓN Y EDICIÓN DE HORARIOS / PAUTAS SEMANALES (rrhh.Horario)
   ========================================================= */

/**
 * Abre el modal para asignar o editar el horario semanal del personal
 */
function abrirModalAsignarHorarioProg(personalId = null) {
  const workerSelect = document.getElementById('progEditWorkerSelect');
  if (!workerSelect) return;

  // Poblar select con el personal de la matriz
  workerSelect.innerHTML = progState.matriz.map(w => `
    <option value="${w.personal_id}" ${String(w.personal_id) === String(personalId) ? 'selected' : ''}>
      ${escapeHtml(w.dni)} - ${escapeHtml(w.nombre_completo)} (${escapeHtml(w.cargo_nombre)})
    </option>
  `).join('');

  if (personalId) {
    workerSelect.value = String(personalId);
  }

  // Poblar los 7 selects de días de la semana con catálogo de turnos
  const turnosList = progState.turnosDisponibles && progState.turnosDisponibles.length > 0 
    ? progState.turnosDisponibles 
    : [
        { turno_id: 1, nombre: 'Mañana Ordinario (6h)', horas: 6.0 },
        { turno_id: 2, nombre: 'Tarde Ordinario (6h)', horas: 6.0 },
        { turno_id: 3, nombre: 'Guardia Nocturna (12h)', horas: 12.0 },
        { turno_id: 4, nombre: 'Guardia Diurna (12h)', horas: 12.0 },
        { turno_id: 5, nombre: 'Administrativo (8h)', horas: 8.0 }
      ];

  const optionsHtml = `
    <option value="0" data-horas="0">LIBRE (0h)</option>
    ${turnosList.map(t => `
      <option value="${t.turno_id}" data-horas="${t.horas_computables || t.horas || 6.0}">
        ${escapeHtml(t.nombre)}
      </option>
    `).join('')}
  `;

  for (let d = 1; d <= 7; d++) {
    const daySelect = document.getElementById(`progDay${d}`);
    if (daySelect) {
      daySelect.innerHTML = optionsHtml;
    }
  }

  // Configurar trabajador seleccionado
  onWorkerChangeInScheduleModal();

  openModal('modalEditarHorarioProg');
}

/**
 * Shortcut para editar el horario de un trabajador específico desde su fila en la matriz
 */
function editarHorarioTrabajador(personalId) {
  abrirModalAsignarHorarioProg(personalId);
}

/**
 * Reacciona cuando cambia el trabajador seleccionado en el modal
 */
function onWorkerChangeInScheduleModal() {
  const workerSelect = document.getElementById('progEditWorkerSelect');
  if (!workerSelect) return;

  const pid = workerSelect.value;
  const worker = progState.matriz.find(w => String(w.personal_id) === String(pid));
  if (!worker) return;

  document.getElementById('progEditPersonalId').value = pid;
  document.getElementById('modalEditarHorarioProgTitle').innerHTML = `<span>🗓</span> Editar Horario: <strong>${escapeHtml(worker.nombre_completo)}</strong>`;

  const badgeRegimen = document.getElementById('progEditRegimenBadge');
  if (badgeRegimen) {
    if (worker.regimen_laboral === 'ASISTENCIAL') {
      badgeRegimen.className = 'badge badge-asistencial';
      badgeRegimen.textContent = 'ASISTENCIAL (SETI 150h)';
    } else {
      badgeRegimen.className = 'badge badge-neutral';
      badgeRegimen.textContent = 'ADMINISTRATIVO (8h diarias)';
    }
  }

  // Inferir o precargar turnos según la programación existente del trabajador
  for (let d = 1; d <= 7; d++) {
    const sel = document.getElementById(`progDay${d}`);
    if (!sel) continue;

    // Buscar el turno de este día de la semana en la programación del mes
    let turnEncontrado = '0';
    for (let mDay = 1; mDay <= Math.min(progState.dias.length, 14); mDay++) {
      const dInfo = progState.dias[mDay - 1];
      if (dInfo && (dInfo.dia_semana_num === d || (dInfo.dia_semana_num === 0 && d === 7))) {
        const pCode = worker.programacion[mDay]?.codigo;
        if (pCode === 'M' || pCode === '1') turnEncontrado = '1';
        else if (pCode === 'T' || pCode === '2') turnEncontrado = '2';
        else if (pCode === 'N' || pCode === '3') turnEncontrado = '3';
        else if (pCode === 'GD') turnEncontrado = '4';
        else if (pCode === 'ADM') turnEncontrado = '5';
        else if (pCode === 'LIB' || pCode === '0') turnEncontrado = '0';
        break;
      }
    }
    sel.value = turnEncontrado;
  }

  recalcularResumenModalHorario();
}

/**
 * Recalcula en tiempo real las horas semanales y estimación mensual en el modal
 */
function recalcularResumenModalHorario() {
  let horasSemana = 0.0;

  for (let d = 1; d <= 7; d++) {
    const sel = document.getElementById(`progDay${d}`);
    if (sel && sel.selectedIndex >= 0) {
      const opt = sel.options[sel.selectedIndex];
      const h = parseFloat(opt.getAttribute('data-horas') || '0');
      horasSemana += h;
    }
  }

  const hoursEl = document.getElementById('progEditWeeklyHours');
  if (hoursEl) {
    hoursEl.textContent = `${horasSemana.toFixed(1)} hrs / semana`;
  }

  // Proyección mensual estimada (4.3 semanas aprox en 30 días)
  const totalDiasMes = progState.dias ? progState.dias.length : 30;
  const horasMesEstimadas = (horasSemana / 7.0) * totalDiasMes;
  const mesEl = document.getElementById('progEditMonthlyEstimate');
  if (mesEl) {
    const dif = horasMesEstimadas - 150.0;
    let difText = '';
    if (Math.abs(dif) < 1.0) {
      mesEl.style.color = 'var(--status-present-accent)';
      difText = ' (✓ Cumple 150h exactas)';
    } else if (dif < 0) {
      mesEl.style.color = 'var(--status-late-accent)';
      difText = ` (⚠ ${dif.toFixed(1)}h déficit)`;
    } else {
      mesEl.style.color = 'var(--primary-600)';
      difText = ` (+${dif.toFixed(1)}h sobretiempo)`;
    }
    mesEl.textContent = `${horasMesEstimadas.toFixed(1)} hrs${difText}`;
  }
}

/**
 * Plantilla rápida Lunes a Viernes Mañanas (30h/semana)
 */
function aplicarPlantillaRapida() {
  for (let d = 1; d <= 5; d++) {
    const sel = document.getElementById(`progDay${d}`);
    if (sel) sel.value = '1'; // Mañana
  }
  const sel6 = document.getElementById('progDay6');
  if (sel6) sel6.value = '0'; // Sábado Libre
  const sel7 = document.getElementById('progDay7');
  if (sel7) sel7.value = '0'; // Domingo Libre
  recalcularResumenModalHorario();
  showToast('Plantilla L-V aplicada en el formulario.', 'info');
}

/**
 * Aplica la pauta semanal configurada a todo el mes del trabajador en la matriz
 */
function aplicarPautaAlMesActual() {
  const workerSelect = document.getElementById('progEditWorkerSelect');
  if (!workerSelect) return;
  const pid = workerSelect.value;
  const worker = progState.matriz.find(w => String(w.personal_id) === String(pid));
  if (!worker) return;

  // Mapa de códigos según turno_id
  const turnoIdToCode = {
    '0': 'LIB',
    '1': 'M',
    '2': 'T',
    '3': 'N',
    '4': 'GD',
    '5': 'ADM'
  };

  // Recorrer los días del mes y asignar según el día de la semana
  progState.dias.forEach(d => {
    const diaNum = (d.dia_semana_num === 0) ? 7 : d.dia_semana_num;
    const sel = document.getElementById(`progDay${diaNum}`);
    const tid = sel ? sel.value : '0';
    const cod = turnoIdToCode[tid] || 'LIB';
    const turnoInfo = progState.catalogTurnos[cod] || { horas: 0, color: '#94a3b8' };

    worker.programacion[d.dia] = {
      codigo: cod,
      horas: turnoInfo.horas,
      color: turnoInfo.color
    };

    // Actualizar celda en el DOM
    const cellEl = document.querySelector(`.matrix-cell[data-personal-id="${pid}"][data-dia="${d.dia}"]`);
    if (cellEl) {
      const isLib = (cod === 'LIB');
      cellEl.innerHTML = `
        <span class="shift-cell-badge ${isLib ? 'badge-lib' : ''}" style="--cell-color: ${turnoInfo.color};">
          ${cod}
        </span>
      `;
    }

    progState.cambiosPendientes.push({
      personal_id: pid,
      dia: d.dia,
      codigo_turno: cod,
      horas: turnoInfo.horas
    });
  });

  // Recalcular total de horas del trabajador
  let totalHoras = 0.0;
  Object.keys(worker.programacion).forEach(d => {
    totalHoras += (worker.programacion[d].horas || 0.0);
  });
  worker.total_horas = round(totalHoras, 1);
  const isAsist = (worker.regimen_laboral === 'ASISTENCIAL');
  worker.cumple_150h = isAsist ? (Math.abs(totalHoras - 150.0) < 0.1) : true;

  const hoursCell = document.getElementById(`hours-worker-${pid}`);
  if (hoursCell) {
    hoursCell.innerHTML = getHoursBadgeHtml(worker.total_horas, isAsist);
  }

  // Recalcular coberturas de todos los días y KPIs
  progState.dias.forEach(d => recalcularCoberturaDia(d.dia));
  recalcularKpisServicio();

  closeModal('modalEditarHorarioProg');
  showToast(`Pauta semanal aplicada a los ${progState.dias.length} días de ${worker.nombre_completo} (${worker.total_horas}h).`, 'success');
}

/**
 * Guarda la pauta semanal en la base de datos (rrhh.Horario)
 */
async function guardarHorarioTrabajadorDB() {
  const workerSelect = document.getElementById('progEditWorkerSelect');
  const fechaInicioInput = document.getElementById('progEditFechaInicio');
  if (!workerSelect || !fechaInicioInput) return;

  const pid = intval(workerSelect.value);
  const fechaInicio = fechaInicioInput.value;

  const dias = {};
  for (let d = 1; d <= 7; d++) {
    const sel = document.getElementById(`progDay${d}`);
    dias[d] = sel ? intval(sel.value) : 0;
  }

  try {
    const res = await API.post('horarios/asignar.php', {
      personal_id: pid,
      fecha_inicio: fechaInicio,
      dias: dias
    });

    if (res && res.ok) {
      showToast('Pauta de horario guardada exitosamente en la base de datos (rrhh.Horario).', 'success');
      // Aplicar al mes actual
      aplicarPautaAlMesActual();
      // Auto-guardar inmediatamente en el snapshot mensual
      await guardarCambiosRol(true);
      if (typeof closeModal === 'function') {
        closeModal('modalEditarHorarioProg');
      }
    } else {
      showToast(res.message || 'No se pudo guardar la pauta semanal.', 'danger');
    }
  } catch (err) {
    console.error('Error guardando horario:', err);
    showToast('Error de conexión guardando el horario en el servidor.', 'danger');
  }
}

/**
 * Asigna un rango de días (ej. Vacaciones día 1 al 15) a un trabajador
 */
function aplicarRangoEspecialTrabajador() {
  const workerSelect = document.getElementById('progEditWorkerSelect');
  const startInp = document.getElementById('progRangeStart');
  const endInp = document.getElementById('progRangeEnd');
  const typeSel = document.getElementById('progRangeType');

  if (!workerSelect || !startInp || !endInp || !typeSel) return;

  const pid = workerSelect.value;
  const worker = progState.matriz.find(w => String(w.personal_id) === String(pid));
  if (!worker) return;

  const startDay = Math.max(1, intval(startInp.value));
  const endDay = Math.min(progState.dias.length, intval(endInp.value));
  const cod = typeSel.value;
  const turnoInfo = progState.catalogTurnos[cod] || { horas: (cod === 'LIB' ? 0 : 6), color: '#0891b2' };

  if (startDay > endDay) {
    showToast('El día de inicio no puede ser mayor que el día de fin.', 'warning');
    return;
  }

  for (let d = startDay; d <= endDay; d++) {
    worker.programacion[d] = {
      codigo: cod,
      horas: turnoInfo.horas,
      color: turnoInfo.color
    };

    const cellEl = document.querySelector(`.matrix-cell[data-personal-id="${pid}"][data-dia="${d}"]`);
    if (cellEl) {
      const isLib = (cod === 'LIB');
      cellEl.innerHTML = `
        <span class="shift-cell-badge ${isLib ? 'badge-lib' : ''}" style="--cell-color: ${turnoInfo.color};">
          ${cod}
        </span>
      `;
    }

    progState.cambiosPendientes.push({
      personal_id: pid,
      dia: d,
      codigo_turno: cod,
      horas: turnoInfo.horas
    });
  }

  // Recalcular horas del trabajador
  let totalHoras = 0.0;
  Object.keys(worker.programacion).forEach(d => {
    totalHoras += (worker.programacion[d].horas || 0.0);
  });
  worker.total_horas = round(totalHoras, 1);
  const isAsist = (worker.regimen_laboral === 'ASISTENCIAL');
  worker.cumple_150h = isAsist ? (Math.abs(totalHoras - 150.0) < 0.1) : true;

  const hoursCell = document.getElementById(`hours-worker-${pid}`);
  if (hoursCell) {
    hoursCell.innerHTML = getHoursBadgeHtml(worker.total_horas, isAsist);
  }

  for (let d = startDay; d <= endDay; d++) {
    recalcularCoberturaDia(d);
  }
  recalcularKpisServicio();

  closeModal('modalEditarHorarioProg');
  showToast(`Rango del día ${startDay} al ${endDay} asignado como ${cod} (${turnoInfo.horas}h/día).`, 'info');
}
