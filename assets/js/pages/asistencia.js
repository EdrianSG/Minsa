/**
 * Lógica del Módulo: Control Diario de Asistencia y ¿Quién faltó hoy?
 * Hospital Distrital de Pacasmayo - ETAPA 5
 */

let asistState = {
  fecha: getTodayIsoString(),
  servicioId: '',
  estado: 'TODOS',
  buscar: '',
  datos: [],
  resumen: null,
  ausentes: []
};

document.addEventListener('DOMContentLoaded', () => {
  initAsistenciaControls();
  initBiostarImportControls();
  cargarAsistenciaDiaria();
  verificarAusenciasUrgentes();
});

/**
 * Retorna fecha en formato YYYY-MM-DD
 */
function getTodayIsoString() {
  const d = new Date();
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Inicializa los controles de fecha, búsqueda y modales
 */
function initAsistenciaControls() {
  const dateInput = document.getElementById('asistFechaInput');
  const btnHoy = document.getElementById('btnFechaHoy');
  const btnAyer = document.getElementById('btnFechaAyer');
  const btnPrev = document.getElementById('btnFechaPrev');
  const btnNext = document.getElementById('btnFechaNext');

  const selectServicio = document.getElementById('asistServicioSelect');
  const selectEstado = document.getElementById('asistEstadoSelect');
  const searchInput = document.getElementById('asistSearchInput');

  const btnAusentes = document.getElementById('btnQuienFaltoHoy');
  const btnBannerAusentes = document.getElementById('btnBannerVerAusentes');
  const btnRegularizar = document.getElementById('btnNuevaRegularizacion');
  const btnImprimir = document.getElementById('btnImprimirParte');

  if (dateInput) {
    dateInput.value = asistState.fecha;
    dateInput.addEventListener('change', (e) => {
      if (e.target.value) {
        asistState.fecha = e.target.value;
        [btnHoy, btnAyer].forEach(b => b?.classList.remove('active'));
        if (asistState.fecha === getTodayIsoString()) btnHoy?.classList.add('active');
        cargarAsistenciaDiaria();
        verificarAusenciasUrgentes();
      }
    });
  }

  if (btnHoy) {
    btnHoy.addEventListener('click', () => {
      asistState.fecha = getTodayIsoString();
      if (dateInput) dateInput.value = asistState.fecha;
      [btnHoy, btnAyer].forEach(b => b?.classList.remove('active'));
      btnHoy.classList.add('active');
      cargarAsistenciaDiaria();
      verificarAusenciasUrgentes();
    });
  }

  if (btnAyer) {
    btnAyer.addEventListener('click', () => {
      [btnHoy, btnAyer].forEach(b => b?.classList.remove('active'));
      btnAyer.classList.add('active');
      cambiarDiaRelativo(-1);
    });
  }

  if (btnPrev) {
    btnPrev.addEventListener('click', () => {
      cambiarDiaRelativo(-1);
    });
  }

  if (btnNext) {
    btnNext.addEventListener('click', () => {
      cambiarDiaRelativo(1);
    });
  }

  if (selectServicio) {
    selectServicio.addEventListener('change', (e) => {
      asistState.servicioId = e.target.value;
      cargarAsistenciaDiaria();
    });
  }

  if (selectEstado) {
    selectEstado.addEventListener('change', (e) => {
      asistState.estado = e.target.value;
      cargarAsistenciaDiaria();
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', debounce((e) => {
      asistState.buscar = e.target.value.trim();
      cargarAsistenciaDiaria();
    }, 280));
  }

  if (btnAusentes) {
    btnAusentes.addEventListener('click', () => {
      abrirModalAusenciasUrgentes();
    });
  }

  if (btnBannerAusentes) {
    btnBannerAusentes.addEventListener('click', () => {
      abrirModalAusenciasUrgentes();
    });
  }

  if (btnRegularizar) {
    btnRegularizar.addEventListener('click', () => {
      abrirModalRegularizar();
    });
  }

  if (btnImprimir) {
    btnImprimir.addEventListener('click', () => {
      prepararEImprimirParte();
    });
  }

  const btnReconciliar = document.getElementById('btnReconciliarBiometrico');
  if (btnReconciliar) {
    btnReconciliar.addEventListener('click', async () => {
      btnReconciliar.disabled = true;
      btnReconciliar.innerHTML = '<span>⏳</span> Reconciliando...';
      try {
        const res = await API.post('asistencia/biometrico/conciliar.php', { fecha: asistState.fecha });
        if (res && res.ok) {
          if (typeof Toast !== 'undefined' && Toast.success) {
            Toast.success(res.mensaje || 'Conciliación biométrica completada.');
          }
          await cargarAsistenciaDiaria();
        } else {
          if (typeof Toast !== 'undefined' && Toast.error) {
            Toast.error(res?.mensaje || 'Error al reconciliar.');
          }
        }
      } catch (err) {
        console.error('Error al reconciliar:', err);
        if (typeof Toast !== 'undefined' && Toast.error) {
          Toast.error('No se pudo reconciliar con el servidor.');
        }
      } finally {
        btnReconciliar.disabled = false;
        btnReconciliar.innerHTML = '<span>🔄</span> Reconciliar';
      }
    });
  }

  // Interacción en KPI cards como filtros rápidos
  const kpiCards = document.querySelectorAll('.asist-kpi-box');
  kpiCards.forEach(card => {
    card.addEventListener('click', () => {
      const filtro = card.getAttribute('data-filtro');
      if (filtro) {
        asistState.estado = filtro;
        if (selectEstado) selectEstado.value = filtro;
        kpiCards.forEach(c => c.classList.remove('active-filter'));
        card.classList.add('active-filter');
        cargarAsistenciaDiaria();
      }
    });
  });

  // Modal regularización form submit
  const formReg = document.getElementById('formRegularizar');
  if (formReg) {
    formReg.addEventListener('submit', async (e) => {
      e.preventDefault();
      await enviarRegularizacion();
    });
  }
}

/**
 * Avanza o retrocede días en el calendario
 */
function cambiarDiaRelativo(deltaDias) {
  const parts = asistState.fecha.split('-');
  const curr = new Date(parts[0], parts[1] - 1, parts[2]);
  curr.setDate(curr.getDate() + deltaDias);

  const y = curr.getFullYear();
  const m = String(curr.getMonth() + 1).padStart(2, '0');
  const d = String(curr.getDate()).padStart(2, '0');
  asistState.fecha = `${y}-${m}-${d}`;

  const dateInput = document.getElementById('asistFechaInput');
  if (dateInput) dateInput.value = asistState.fecha;

  cargarAsistenciaDiaria();
  verificarAusenciasUrgentes();
}

/**
 * Consulta la asistencia del día desde la API
 */
async function cargarAsistenciaDiaria() {
  const tbody = document.getElementById('asistTableBody');
  if (tbody) {
    tbody.innerHTML = Array(6).fill(0).map(() => `
      <tr class="skeleton-table-row">
        <td>
          <div class="skeleton skeleton-text w-80" style="height: 0.95rem;"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.75rem;"></div>
        </td>
        <td>
          <div class="skeleton skeleton-text w-60"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
        </td>
        <td><div class="skeleton skeleton-pill" style="width: 80px;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-text" style="width: 45px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-text" style="width: 45px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-text" style="width: 40px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-pill" style="width: 75px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-btn" style="width: 70px; height: 1.8rem; margin: 0 auto;"></div></td>
      </tr>
    `).join('');
  }

  try {
    const params = {
      fecha: asistState.fecha
    };
    if (asistState.servicioId !== '') params.servicio_id = asistState.servicioId;
    if (asistState.estado !== 'TODOS') params.estado = asistState.estado;
    if (asistState.buscar !== '') params.buscar = asistState.buscar;

    const res = await API.get('asistencia/diaria.php', params);

    if (res && res.ok) {
      asistState.datos = res.asistencias || [];
      asistState.resumen = res.resumen || null;
      renderKPIs(res.resumen);
      renderTablaAsistencia(res.asistencias);
    }
  } catch (error) {
    console.error('Error al cargar asistencia:', error);
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--status-absent-accent);">
            No se pudo cargar la asistencia del día. Verifique la conexión con el servidor.
          </td>
        </tr>
      `;
    }
  }
}

/**
 * Actualiza las tarjetas de métricas del día
 */
function renderKPIs(resumen) {
  if (!resumen) return;

  const elProg = document.getElementById('kpiTotalProg');
  const elPuntual = document.getElementById('kpiAsistPuntual');
  const elTard = document.getElementById('kpiTardanzas');
  const elFaltas = document.getElementById('kpiFaltas');
  const elPermisos = document.getElementById('kpiPermisos');
  const elDescansos = document.getElementById('kpiDescansos');

  if (elProg) elProg.textContent = resumen.total_programados;
  if (elPuntual) elPuntual.textContent = resumen.asistio_puntual;
  if (elTard) {
    elTard.innerHTML = `${resumen.tardanzas} <span style="font-size: 0.85rem; font-weight: 500; color: var(--status-late-accent);">(${resumen.minutos_tardanza_total} min)</span>`;
  }
  if (elFaltas) elFaltas.textContent = resumen.faltas;
  if (elPermisos) elPermisos.textContent = resumen.permisos;
  if (elDescansos) elDescansos.textContent = resumen.descansos;

  // Actualizar contador discreto en el botón "¿Quién faltó hoy?"
  const badgeCount = document.getElementById('badgeQuienFaltoCount');
  if (badgeCount) {
    badgeCount.textContent = resumen.faltas;
    badgeCount.style.display = (resumen.faltas > 0) ? 'inline-block' : 'none';
  }
}

/**
 * Renderiza la tabla del Parte Diario de Asistencia
 */
function renderTablaAsistencia(items) {
  const tbody = document.getElementById('asistTableBody');
  const countEl = document.getElementById('asistRegistrosCount');
  if (!tbody) return;

  if (countEl) countEl.textContent = `${items.length} trabajadores listados`;

  if (items.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
          No se encontraron registros de asistencia con los filtros seleccionados.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = items.map(row => {
    const initials = getInitials(row.nombre_completo);
    const badgeEstado = getBadgeEstado(row.estado_evaluado, row.minutos_tardanza);
    const chipEntrada = getChipEntrada(row.hora_entrada, row.hora_prog_inicio, row.estado_evaluado, row.minutos_tardanza);
    const chipSalida = getChipSalida(row.hora_salida, row.hora_prog_fin, row.turno_codigo);

    return `
      <tr>
        <!-- Trabajador -->
        <td>
          <div style="display: flex; flex-direction: column;">
            <span style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem;">
              ${escapeHtml(row.nombre_completo)}
            </span>
            <span style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
              DNI ${row.dni} &bull; ${escapeHtml(row.cargo)}
            </span>
          </div>
        </td>

        <!-- Servicio -->
        <td>
          <div style="font-size: 0.82rem; color: var(--text-primary); font-weight: 500;">
            ${escapeHtml(row.servicio_nombre)}
          </div>
          <div style="font-size: 0.72rem; color: var(--text-tertiary); margin-top: 1px;">
            ${row.condicion_laboral} &bull; ${row.regimen_laboral}
          </div>
        </td>

        <!-- Turno Programado -->
        <td>
          <div style="font-weight: 600; font-size: 0.82rem; color: var(--text-primary);">
            ${row.turno_nombre}
          </div>
          <div style="font-size: 0.74rem; font-family: monospace; color: var(--text-tertiary);">
            ${row.hora_prog_inicio} - ${row.hora_prog_fin}
          </div>
        </td>

        <!-- Marcación Entrada -->
        <td>${chipEntrada}</td>

        <!-- Marcación Salida -->
        <td>${chipSalida}</td>

        <!-- Tardanza -->
        <td style="text-align: center;">
          ${row.minutos_tardanza > 0 ? `
            <span style="font-weight: 700; color: var(--status-late-accent); font-family: monospace; font-size: 0.85rem;">
              +${row.minutos_tardanza}m
            </span>
          ` : `
            <span style="color: var(--text-tertiary); font-size: 0.8rem;">--</span>
          `}
        </td>

        <!-- Estado Oficial -->
        <td>
          <div style="display: flex; flex-direction: column; gap: 3px;">
            ${badgeEstado}
            ${(row.observacion && row.observacion.includes('Regularizado')) ? `
              <span class="badge" style="font-size: 0.68rem; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); width: fit-content;" title="${escapeHtml(row.observacion)}">
                📝 Regularizado
              </span>
            ` : ''}
          </div>
        </td>

        <!-- Acciones -->
        <td style="text-align: right;">
          <button 
            type="button" 
            class="btn btn-secondary btn-sm" 
            style="padding: 0.25rem 0.6rem; font-size: 0.76rem;"
            title="Regularizar o Justificar Marcación"
            onclick="prepararRegularizacionFila(${row.personal_id}, '${escapeHtml(row.nombre_completo)}', '${row.hora_prog_inicio}', '${row.hora_prog_fin}')"
          >
            ✎ Regularizar
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Chip para la entrada del reloj biométrico
 */
function getChipEntrada(horaEntrada, horaProg, estado, minTardanza) {
  if (!horaEntrada) {
    if (['SIN_DATOS', 'EN_CURSO', 'PROGRAMADO'].includes(estado)) {
      return '<span class="punch-chip">Pendiente de verificación</span>';
    }
    if (estado === 'DESCANSO') {
      return `<span class="punch-chip" style="color: var(--text-tertiary); border: 1px dashed var(--border-subtle);">--:--</span>`;
    }
    if (estado === 'PERMISO' || estado === 'LICENCIA') {
      return `<span class="punch-chip" style="background: var(--status-leave-subtle); color: var(--status-leave-accent); border: 1px solid rgba(139, 92, 246, 0.2);">Permiso Concedido</span>`;
    }
    return `<span class="punch-chip punch-none">Sin Marcación ✖</span>`;
  }

  if (minTardanza > 0) {
    return `
      <div class="table-time-cell">
        <span class="punch-chip punch-late">${horaEntrada}</span>
        <span class="time-sub" style="color: var(--status-late-accent);">+${minTardanza}m tardanza</span>
      </div>
    `;
  }

  return `
    <div class="table-time-cell">
      <span class="punch-chip punch-ok">${horaEntrada} ✓</span>
      <span class="time-sub">Puntual (Tolerancia)</span>
    </div>
  `;
}

/**
 * Chip para la salida del reloj biométrico
 */
function getChipSalida(horaSalida, horaProgFin, turnoCodigo) {
  if (turnoCodigo === 'LIB' || turnoCodigo === 'DESC') {
    return `<span class="punch-chip" style="color: var(--text-tertiary); border: 1px dashed var(--border-subtle);">--:--</span>`;
  }

  if (!horaSalida) {
    return `<span class="punch-chip" style="background: var(--bg-surface-soft); color: var(--text-tertiary); border: 1px solid var(--border-subtle);">En Turno / Pendiente</span>`;
  }

  return `
    <div class="table-time-cell">
      <span class="punch-chip punch-ok">${horaSalida} ✓</span>
      <span class="time-sub">Marcación registrada</span>
    </div>
  `;
}

/**
 * Badge de Estado Oficial MINSA
 */
function getBadgeEstado(estado, tardanza) {
  switch (estado) {
    case 'SIN_DATOS': return '<span class="badge badge-neutral">Sin datos</span>';
    case 'EN_CURSO': return '<span class="badge badge-neutral">Jornada en curso</span>';
    case 'PROGRAMADO': return '<span class="badge badge-neutral">Programado</span>';
    case 'ASISTIO':
      return `<span class="badge badge-present">Asistió Puntual</span>`;
    case 'TARDANZA':
      return `<span class="badge badge-late">Tardanza (${tardanza}m)</span>`;
    case 'FALTA':
      return `<span class="badge badge-absent">Inasistencia / Falta</span>`;
    case 'FALTA_ADMINISTRATIVA':
      return `<span class="badge badge-absent">Falta (>30 min)</span>`;
    case 'PERMISO':
      return `<span class="badge badge-leave">Permiso Laboral</span>`;
    case 'LICENCIA':
      return `<span class="badge badge-leave">Licencia Médica</span>`;
    case 'DESCANSO':
      return `<span class="badge badge-neutral">Descanso Programado</span>`;
    default:
      return `<span class="badge badge-neutral">${escapeHtml(estado)}</span>`;
  }
}

/**
 * Badge de estilo según el turno
 */
function getBadgeTurno(codigo) {
  switch (codigo) {
    case 'M': return 'badge-present';
    case 'T': return 'badge-late';
    case 'N': return 'badge-absent';
    case 'GD': return 'badge-present';
    case 'GN': return 'badge-late';
    case 'ADM': return 'badge-neutral';
    default: return 'badge-neutral';
  }
}

/**
 * Consulta de ausencias urgentes en background
 */
async function verificarAusenciasUrgentes() {
  try {
    const res = await API.get('asistencia/ausentes.php', { fecha: asistState.fecha });
    if (res && res.ok) {
      asistState.ausentes = res.ausentes || [];
      const badgeUrg = document.getElementById('badgeQuienFaltoCount');
      if (badgeUrg) {
        badgeUrg.textContent = res.total_ausentes;
        badgeUrg.style.display = (res.total_ausentes > 0) ? 'inline-block' : 'none';
      }
    }
  } catch (e) {
    // Ignorar si falla
  }
}

/**
 * Abre el modal prioritario de "¿Quién faltó hoy?"
 */
async function abrirModalAusenciasUrgentes() {
  const modal = document.getElementById('modalAusenciasUrgentes');
  const container = document.getElementById('listaAusentesUrgentes');
  const countBadge = document.getElementById('modalAusentesTotal');

  if (!modal || !container) return;

  container.innerHTML = `
    <div style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
      Consultando ausencias críticas del turno actual...
    </div>
  `;
  modal.classList.add('active');

  try {
    const res = await API.get('asistencia/ausentes.php', { fecha: asistState.fecha });
    if (res && res.ok) {
      const lista = res.ausentes || [];
      if (countBadge) countBadge.textContent = lista.length;

      if (lista.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 2.5rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
            <h4 style="font-size: 1.1rem; color: var(--status-present-accent);">Sin Ausencias en el Turno</h4>
            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.25rem;">
              Todo el personal asistencial y administrativo programado ha marcado su ingreso conforme a norma.
            </p>
          </div>
        `;
        return;
      }

      container.innerHTML = lista.map(item => `
        <div class="urgent-worker-card ${item.criticidad === 'ALTA_URGENTE' ? 'priority-high' : ''}">
          <div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <strong style="color: var(--text-primary); font-size: 0.95rem;">${escapeHtml(item.nombre_completo)}</strong>
              ${item.criticidad === 'ALTA_URGENTE' ? `
                <span class="badge badge-absent" style="font-size: 0.68rem;">🚨 URGENTE: Reemplazo en Guardia</span>
              ` : `
                <span class="badge badge-late" style="font-size: 0.68rem;">Falta Administrativa</span>
              `}
            </div>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.2rem;">
              <strong>${escapeHtml(item.cargo)}</strong> &bull; ${escapeHtml(item.servicio_nombre)}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-tertiary); margin-top: 0.25rem;">
              Turno: <strong>${escapeHtml(item.turno_nombre)}</strong> &bull; Motivo: ${escapeHtml(item.motivo)}
            </div>
          </div>
          <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem;">
            <a href="tel:${item.telefono}" class="urgent-contact-btn" title="Llamar directamente">
              📞 Llamar: ${item.telefono}
            </a>
            <button 
              type="button" 
              class="btn btn-secondary btn-sm"
              onclick="prepararRegularizacionFila(${item.personal_id}, '${escapeHtml(item.nombre_completo)}', '${item.hora_prog_inicio}', '${item.hora_prog_fin}')"
            >
              Subsanar / Justificar
            </button>
          </div>
        </div>
      `).join('');
    }
  } catch (error) {
    container.innerHTML = `
      <div style="text-align: center; padding: 2rem; color: var(--status-absent-accent);">
        Error al recuperar el reporte de ausentes. Intente nuevamente.
      </div>
    `;
  }
}

/**
 * Prepara el modal de regularización con los datos de un trabajador
 */
function prepararRegularizacionFila(personalId, nombreCompleto, horaProgInicio, horaProgFin) {
  // Cerrar modal de ausentes si estuviera abierto
  const modalAusentes = document.getElementById('modalAusenciasUrgentes');
  if (modalAusentes) modalAusentes.classList.remove('active');

  abrirModalRegularizar();

  const selectTrabajador = document.getElementById('regPersonalId');
  const inputFecha = document.getElementById('regFecha');
  const inputEntrada = document.getElementById('regHoraEntrada');
  const inputSalida = document.getElementById('regHoraSalida');

  if (inputFecha) inputFecha.value = asistState.fecha;
  if (inputEntrada) inputEntrada.value = horaProgInicio || '08:00';
  if (inputSalida) inputSalida.value = horaProgFin || '14:00';

  if (selectTrabajador) {
    selectTrabajador.value = personalId;
  }
}

/**
 * Abre el modal de regularización
 */
function abrirModalRegularizar() {
  const modal = document.getElementById('modalRegularizarMarcacion');
  if (modal) {
    // Poblar select de trabajadores si no tiene opciones
    poblarSelectTrabajadores();
    modal.classList.add('active');
  }
}

/**
 * Llena las opciones del select de personal en el modal
 */
function poblarSelectTrabajadores() {
  const select = document.getElementById('regPersonalId');
  if (!select) return;

  if (select.options.length <= 1 && asistState.datos.length > 0) {
    select.innerHTML = '<option value="">-- Seleccione Trabajador --</option>' + 
      asistState.datos.map(p => `
        <option value="${p.personal_id}">${escapeHtml(p.nombre_completo)} (DNI: ${p.dni} - ${escapeHtml(p.cargo)})</option>
      `).join('');
  }
}

/**
 * Envía la regularización al endpoint de persistencia
 */
async function enviarRegularizacion() {
  const selectTrabajador = document.getElementById('regPersonalId');
  const inputFecha = document.getElementById('regFecha');
  const inputEntrada = document.getElementById('regHoraEntrada');
  const inputSalida = document.getElementById('regHoraSalida');
  const selectMotivo = document.getElementById('regMotivo');
  const inputSustento = document.getElementById('regSustento');
  const inputObs = document.getElementById('regObservacion');

  const personalId = parseInt(selectTrabajador?.value, 10);
  if (!personalId) {
    showToast('Debe seleccionar al trabajador a regularizar.', 'danger');
    return;
  }

  const payload = {
    personal_id: personalId,
    fecha: inputFecha?.value || asistState.fecha,
    hora_entrada: inputEntrada?.value || null,
    hora_salida: inputSalida?.value || null,
    motivo: selectMotivo?.value || 'REGULARIZACION_RRHH',
    documento_sustento: inputSustento?.value || '',
    observacion: inputObs?.value || ''
  };

  try {
    const res = await API.post('asistencia/regularizar.php', payload);
    if (res && res.ok) {
      showToast('Marcación regularizada satisfactoriamente.', 'success');
      const modal = document.getElementById('modalRegularizarMarcacion');
      if (modal) modal.classList.remove('active');
      cargarAsistenciaDiaria();
      verificarAusenciasUrgentes();
    } else {
      showToast(res.message || 'Error al regularizar marcación.', 'danger');
    }
  } catch (e) {
    showToast('Error de comunicación con el servidor.', 'danger');
  }
}

/**
 * Modal simple de contacto rápido
 */
function contactarTrabajador(nombre, telefono, cargo) {
  showToast(`Contacto de ${nombre}: Teléfono ${telefono} (${cargo})`, 'info');
}

/**
 * Prepara vista e invoca impresión oficial del Parte Diario
 */
function prepararEImprimirParte() {
  const printFecha = document.getElementById('printHeaderFecha');
  if (printFecha) {
    printFecha.textContent = `PARTE DIARIO DE ASISTENCIA - FECHA: ${asistState.fecha}`;
  }
  window.print();
}

/**
 * Cierra modales
 */
function cerrarModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove('active');
}

function getInitials(name) {
  if (!name) return 'HP';
  const parts = name.replace(/^(Dr\.|Dra\.|Lic\.|Tec\.|Obs\.|Q\.F\.|Abog\.|Bach\.)\s*/i, '').trim().split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return parts[0].substring(0, 2).toUpperCase();
}

function escapeHtml(t) {
  if (!t) return '';
  return String(t)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function debounce(fn, wait) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), wait);
  };
}

/* ==========================================================================
   CONTROLADOR DE IMPORTACIÓN BIOMÉTRICA BIOSTAR 2 (FASE 3)
   ========================================================================== */

let biostarState = {
  selectedFile: null,
  previewData: null,
  isAnalyzing: false,
  isImporting: false
};

/**
 * Alterna entre la vista de Control Diario y la Importación Biométrica
 */
function switchAsistTab(tab) {
  const tabDiario = document.getElementById('tabNavDiario');
  const tabBio = document.getElementById('tabNavBiometrico');
  const vistaDiario = document.getElementById('vistaControlDiario');
  const vistaBio = document.getElementById('vistaImportacionBiometrica');

  if (tab === 'biometrico') {
    tabDiario?.classList.remove('active');
    tabBio?.classList.add('active');
    if (vistaDiario) vistaDiario.style.display = 'none';
    if (vistaBio) vistaBio.style.display = 'block';
    cargarHistorialImportaciones();
  } else {
    tabBio?.classList.remove('active');
    tabDiario?.classList.add('active');
    if (vistaBio) vistaBio.style.display = 'none';
    if (vistaDiario) vistaDiario.style.display = 'block';
    cargarAsistenciaDiaria();
  }
}

/**
 * Inicializa los eventos de Drag & Drop y selección de archivo
 */
function initBiostarImportControls() {
  const dropzone = document.getElementById('biostarDropzone');
  const fileInput = document.getElementById('inputBiostarCsv');

  if (dropzone && fileInput) {
    // Click en la zona abre el selector nativo
    dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.remove('dragover');
    });

    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.remove('dragover');

      if (e.dataTransfer?.files?.length > 0) {
        handleBiostarFileSelect(e.dataTransfer.files[0]);
      }
    });

    fileInput.addEventListener('change', (e) => {
      if (e.target.files?.length > 0) {
        handleBiostarFileSelect(e.target.files[0]);
      }
    });
  }
}

/**
 * Valida y registra el archivo seleccionado en el cliente
 */
function handleBiostarFileSelect(file) {
  if (!file) return;

  const ext = file.name.split('.').pop().toLowerCase();
  if (ext !== 'csv') {
    alert('Por favor seleccione un archivo con formato .csv');
    return;
  }

  biostarState.selectedFile = file;

  const fileNameEl = document.getElementById('biostarSelectedFileName');
  const fileSizeEl = document.getElementById('biostarSelectedFileSize');
  const fileBar = document.getElementById('biostarFileSelectedBar');
  const dropzone = document.getElementById('biostarDropzone');

  if (fileNameEl) fileNameEl.textContent = file.name;
  if (fileSizeEl) fileSizeEl.textContent = `${formatBytes(file.size)} • Listo para analizar`;
  if (fileBar) fileBar.style.display = 'flex';
  if (dropzone) dropzone.style.display = 'none';

  // Ocultar previsualización anterior si existía
  const previewCard = document.getElementById('cardBiostarPreview');
  if (previewCard) previewCard.style.display = 'none';
}

/**
 * Limpia la selección actual del archivo
 */
function limpiarArchivoCsv() {
  biostarState.selectedFile = null;
  biostarState.previewData = null;

  const fileInput = document.getElementById('inputBiostarCsv');
  const fileBar = document.getElementById('biostarFileSelectedBar');
  const dropzone = document.getElementById('biostarDropzone');
  const previewCard = document.getElementById('cardBiostarPreview');

  if (fileInput) fileInput.value = '';
  if (fileBar) fileBar.style.display = 'none';
  if (dropzone) dropzone.style.display = 'block';
  if (previewCard) previewCard.style.display = 'none';
}

/**
 * Envía el CSV al endpoint de previsualización
 */
async function ejecutarPrevisualizacion() {
  if (!biostarState.selectedFile) {
    alert('Por favor seleccione un archivo CSV primero.');
    return;
  }

  const spinner = document.getElementById('biostarLoadingSpinner');
  const fileBar = document.getElementById('biostarFileSelectedBar');
  const previewCard = document.getElementById('cardBiostarPreview');

  if (spinner) spinner.style.display = 'block';
  if (fileBar) fileBar.style.opacity = '0.5';
  if (previewCard) previewCard.style.display = 'none';

  const formData = new FormData();
  formData.append('archivo_csv', biostarState.selectedFile);

  try {
    const res = await fetch('api/asistencia/biometrico/previsualizar.php', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.message || data.mensaje || 'Error al procesar el archivo CSV.');
    }

    biostarState.previewData = data;
    renderPrevisualizacion(data);

  } catch (err) {
    console.error('Error en previsualización BioStar:', err);
    alert(`Error de análisis: ${err.message}`);
  } finally {
    if (spinner) spinner.style.display = 'none';
    if (fileBar) fileBar.style.opacity = '1';
  }
}

/**
 * Renderiza los KPIs, muestra de marcaciones, omitidos y dispositivos
 */
function renderPrevisualizacion(resumen) {
  const card = document.getElementById('cardBiostarPreview');
  if (!card) return;

  // Banner de Idempotencia
  const banner = document.getElementById('biostarIdempotenciaBanner');
  if (banner) {
    if (resumen.ya_importado) {
      banner.style.background = 'rgba(245, 158, 11, 0.12)';
      banner.style.border = '1px solid rgba(245, 158, 11, 0.35)';
      banner.style.color = '#fbbf24';
      banner.innerHTML = `
        <strong>⚠️ Archivo ya importado con anterioridad:</strong><br>
        Este archivo idéntico (SHA-256: <code>${resumen.hash_sha256.substring(0, 16)}...</code>) ya fue procesado el <strong>${resumen.importacion_previa.FechaHoraCarga}</strong> (Carga #${resumen.importacion_previa.ImportacionID}).<br>
        <span style="font-size: 0.8rem; color: var(--text-secondary);">
          Si decide confirmar la importación, el sistema aplicará deduplicación atómica a nivel de registro: las marcaciones ya existentes se omitirán y solo se agregarán registros inéditos.
        </span>
      `;
    } else {
      banner.style.background = 'rgba(34, 197, 94, 0.12)';
      banner.style.border = '1px solid rgba(34, 197, 94, 0.35)';
      banner.style.color = '#4ade80';
      banner.innerHTML = `
        <strong>✓ Archivo Verificado:</strong> Estructura oficial de BioStar 2 reconocida. Se detectaron <strong>${formatNumber(resumen.marcaciones_validas)} marcaciones válidas de huella</strong> listas para ser importadas al sistema.
      `;
    }
  }

  // KPIs
  setText('prevKpiTotalFilas', formatNumber(resumen.total_filas));
  setText('prevKpiValidas', formatNumber(resumen.marcaciones_validas));
  setText('prevKpiOmitidas', formatNumber(resumen.marcaciones_omitidas));
  setText('prevKpiConsecutivas', formatNumber(resumen.marcaciones_consecutivas));
  setText('prevKpiUsuarios', formatNumber(resumen.total_usuarios_unicos));
  setText('prevKpiUsuariosSub', `${resumen.usuarios_vinculados} vinculados / ${resumen.usuarios_pendientes} pendientes`);

  setText('badgeRangoFechasPreview', `Periodo: ${resumen.fecha_inicio || '--'} al ${resumen.fecha_fin || '--'}`);

  // Conteo de pestañas
  setText('countMuestraMarcaciones', resumen.muestra_marcaciones?.length || 0);
  setText('countMuestraOmitidos', resumen.muestra_omitidos?.length || 0);
  setText('countDispositivos', resumen.dispositivos?.length || 0);

  // Renderizar tabla de muestra de marcaciones
  const tbodyMarc = document.getElementById('tbodyMuestraMarcaciones');
  if (tbodyMarc && resumen.muestra_marcaciones) {
    if (resumen.muestra_marcaciones.length === 0) {
      tbodyMarc.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1.5rem;">No se encontraron marcaciones de huella válidas.</td></tr>';
    } else {
      tbodyMarc.innerHTML = resumen.muestra_marcaciones.map(m => {
        const badgeVinc = (m.estado_vinculacion === 'VINCULADO')
          ? '<span class="badge-vinculado">✓ Vinculado</span>'
          : '<span class="badge-pendiente">⏳ Pendiente</span>';

        const badgeCons = m.es_consecutiva
          ? `<span class="badge-consecutiva">Ráfaga (${m.segundos_dif}s)</span>`
          : '<span style="color:var(--text-tertiary); font-size:0.75rem;">Normal</span>';

        return `
          <tr>
            <td style="color:var(--text-tertiary);">${m.fila}</td>
            <td><strong>${escapeHtml(m.fecha_hora)}</strong></td>
            <td style="font-size:0.8rem; color:var(--text-secondary);">${escapeHtml(m.dispositivo_nombre || m.dispositivo_id)}</td>
            <td>${escapeHtml(m.biostar_user_name)}</td>
            <td><code>${escapeHtml(m.biostar_user_id)}</code></td>
            <td>${badgeVinc}</td>
            <td>${badgeCons}</td>
          </tr>
        `;
      }).join('');
    }
  }

  // Renderizar tabla de muestra de omitidos
  const tbodyOmi = document.getElementById('tbodyMuestraOmitidos');
  if (tbodyOmi && resumen.muestra_omitidos) {
    if (resumen.muestra_omitidos.length === 0) {
      tbodyOmi.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1.5rem;">No hay eventos omitidos en este archivo.</td></tr>';
    } else {
      tbodyOmi.innerHTML = resumen.muestra_omitidos.map(o => `
        <tr>
          <td style="color:var(--text-tertiary);">${o.fila}</td>
          <td>${escapeHtml(o.fecha)}</td>
          <td style="font-size:0.8rem;">${escapeHtml(o.dispositivo)}</td>
          <td><strong style="color:var(--status-late-accent, #f59e0b);">${escapeHtml(o.evento)}</strong></td>
          <td>${escapeHtml(o.usuario_raw || '(Sistema / Equipo)')}</td>
          <td style="font-size:0.78rem; color:var(--text-secondary);">${escapeHtml(o.motivo_omision)}</td>
        </tr>
      `).join('');
    }
  }

  // Renderizar dispositivos
  const listDisp = document.getElementById('listaDispositivosDetectados');
  if (listDisp && resumen.dispositivos) {
    listDisp.innerHTML = resumen.dispositivos.map(d => `
      <div style="background:var(--bg-surface-muted, #1e293b); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1rem;">
        <div style="font-size:0.75rem; color:var(--text-secondary); text-transform:uppercase; font-weight:700;">Dispositivo BioStation A2</div>
        <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0.25rem 0;">${escapeHtml(d.nombre)}</div>
        <div style="display:flex; justify-content:space-between; margin-top:0.5rem; font-size:0.85rem;">
          <span style="color:var(--text-secondary);">ID Terminal: <code>${escapeHtml(d.id)}</code></span>
          <span style="font-weight:700; color:var(--primary-accent, #0284c7);">${formatNumber(d.total)} eventos</span>
        </div>
      </div>
    `).join('');
  }

  card.style.display = 'block';
  switchPreviewSubTab('marcaciones');
  card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Alterna entre subpestañas de la previsualización
 */
function switchPreviewSubTab(subtab) {
  const btnMarc = document.getElementById('btnSubTabMarcaciones');
  const btnOmi = document.getElementById('btnSubTabOmitidos');
  const btnDisp = document.getElementById('btnSubTabDispositivos');

  const secMarc = document.getElementById('subseccionMarcaciones');
  const secOmi = document.getElementById('subseccionOmitidos');
  const secDisp = document.getElementById('subseccionDispositivos');

  [btnMarc, btnOmi, btnDisp].forEach(b => b?.classList.remove('active'));
  [secMarc, secOmi, secDisp].forEach(s => { if (s) s.style.display = 'none'; });

  if (subtab === 'omitidos') {
    btnOmi?.classList.add('active');
    if (secOmi) secOmi.style.display = 'block';
  } else if (subtab === 'dispositivos') {
    btnDisp?.classList.add('active');
    if (secDisp) secDisp.style.display = 'block';
  } else {
    btnMarc?.classList.add('active');
    if (secMarc) secMarc.style.display = 'block';
  }
}

/**
 * Confirma e inicia la importación definitiva en SQL Server
 */
async function ejecutarImportacionDefinitiva() {
  if (!biostarState.selectedFile) {
    alert('No hay ningún archivo seleccionado para importar.');
    return;
  }

  const resumen = biostarState.previewData;
  const totalValidas = resumen ? resumen.marcaciones_validas : 'las';

  const confirmMsg = `¿Confirma la importación de ${formatNumber(totalValidas)} marcaciones de huella a SQL Server?\n\n` +
    `- Se aplicará deduplicación estricta para omitir registros repetidos.\n` +
    `- No se modificarán las asistencias ni se generarán descuentos automáticos.\n` +
    `- Las marcaciones se almacenarán de forma auditable en rrhh.MarcacionBiometrica.`;

  if (!confirm(confirmMsg)) {
    return;
  }

  const btnImportar = document.getElementById('btnEjecutarImportacion');
  const obsInput = document.getElementById('biostarObservacionesInput');

  if (btnImportar) {
    btnImportar.disabled = true;
    btnImportar.innerHTML = '<span>⏳</span> Importando a SQL Server... Espere por favor';
  }

  const formData = new FormData();
  formData.append('archivo_csv', biostarState.selectedFile);
  if (obsInput && obsInput.value.trim() !== '') {
    formData.append('observaciones', obsInput.value.trim());
  }

  try {
    const res = await fetch('api/asistencia/biometrico/importar.php', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.message || data.mensaje || 'Error al ejecutar la importación en la base de datos.');
    }

    alert(
      `¡Importación completada con éxito!\n\n` +
      `• Total filas analizadas: ${formatNumber(data.total_filas)}\n` +
      `• Nuevas marcaciones insertadas: ${formatNumber(data.marcaciones_insertadas)}\n` +
      `• Marcaciones duplicadas omitidas: ${formatNumber(data.marcaciones_duplicadas)}\n` +
      `• Marcaciones en ráfaga detectadas: ${formatNumber(data.marcaciones_consecutivas)}\n` +
      `• Pendientes de vincular a padrón: ${formatNumber(data.pendientes_vinculacion)}\n\n` +
      `Registro de auditoría guardado como Carga #${data.importacion_id}.`
    );

    // Limpiar formulario y actualizar historial
    limpiarArchivoCsv();
    cargarHistorialImportaciones();

  } catch (err) {
    console.error('Error al importar BioStar:', err);
    alert(`Error en la importación: ${err.message}`);
  } finally {
    if (btnImportar) {
      btnImportar.disabled = false;
      btnImportar.innerHTML = '<span>💾</span> Confirmar e Importar a Base de Datos';
    }
  }
}

/**
 * Consulta y renderiza el historial de importaciones
 */
async function cargarHistorialImportaciones() {
  const tbody = document.getElementById('tbodyHistorialImportaciones');
  const lblTotal = document.getElementById('labelTotalHistorial');
  if (!tbody) return;

  try {
    const res = await fetch('api/asistencia/biometrico/historial.php?limit=25');
    const data = await res.json();

    if (!res.ok || !data.ok) {
      throw new Error(data.message || 'Error al obtener historial.');
    }

    const items = data.historial || [];
    if (lblTotal) lblTotal.textContent = `${items.length} cargas registradas`;

    if (items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:2rem; color:var(--text-tertiary);">Aún no se han registrado importaciones biométricas.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(h => {
      const isOk = (h.Estado === 'COMPLETADO');
      const badgeEstado = isOk
        ? '<span class="badge-vinculado">COMPLETADO</span>'
        : '<span class="badge badge-absent">FALLIDO</span>';

      return `
        <tr>
          <td><strong>#${h.ImportacionID}</strong></td>
          <td>${escapeHtml(h.FechaHoraCarga)}</td>
          <td><strong style="color:var(--text-primary);">${escapeHtml(h.NombreArchivo)}</strong></td>
          <td>${escapeHtml(h.UsuarioNombre)}</td>
          <td style="text-align:right;">${formatNumber(h.TotalRegistros)}</td>
          <td style="text-align:right; color:#22c55e; font-weight:700;">${formatNumber(h.MarcacionesValidas)}</td>
          <td style="text-align:right; color:var(--text-secondary);">${formatNumber(h.MarcacionesDuplicadas)}</td>
          <td style="text-align:right; color:#a855f7;">${formatNumber(h.MarcacionesConsecutivas)}</td>
          <td style="text-align:right; color:#f59e0b;">${formatNumber(h.MarcacionesOmitidas)}</td>
          <td style="text-align:center;">${badgeEstado}</td>
          <td style="text-align:right;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="verIncidenciasImportacion(${h.ImportacionID})" title="Ver detalles e incidencias">
              <span>⚠️</span> Incidencias
            </button>
          </td>
        </tr>
      `;
    }).join('');

  } catch (err) {
    console.error('Error cargando historial BioStar:', err);
    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:1.5rem; color:#ef4444;">Error al cargar historial: ${escapeHtml(err.message)}</td></tr>`;
  }
}

/**
 * Consulta y muestra el modal con las incidencias de una importación
 */
async function verIncidenciasImportacion(importacionId) {
  const modal = document.getElementById('modalIncidenciasImportacion');
  const spanId = document.getElementById('modalIncidenciasId');
  const tbody = document.getElementById('tbodyDetalleIncidencias');

  if (spanId) spanId.textContent = importacionId;
  if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem;">Cargando incidencias...</td></tr>';
  if (modal) modal.classList.add('active');

  try {
    const res = await fetch(`api/asistencia/biometrico/incidencias.php?importacion_id=${importacionId}`);
    const data = await res.json();

    if (!res.ok || !data.ok) {
      throw new Error(data.message || 'Error al obtener incidencias.');
    }

    const items = data.incidencias || [];
    if (items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-tertiary);">No se registraron incidencias en esta importación. Carga 100% limpia.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map((inc, i) => {
      let badgeNivel = '<span class="badge badge-info">INFO</span>';
      if (inc.Nivel === 'ADVERTENCIA') badgeNivel = '<span class="badge badge-warning" style="background:#f59e0b; color:#fff;">AVISO</span>';
      if (inc.Nivel === 'ERROR') badgeNivel = '<span class="badge badge-absent">ERROR</span>';

      return `
        <tr>
          <td style="color:var(--text-tertiary);">${i + 1}</td>
          <td><code>${escapeHtml(inc.TipoIncidencia)}</code></td>
          <td>${badgeNivel}</td>
          <td>${escapeHtml(inc.Detalle)}</td>
          <td style="font-size:0.8rem; color:var(--text-secondary);">${escapeHtml(inc.FechaHoraEvento || '--')}</td>
        </tr>
      `;
    }).join('');

  } catch (err) {
    console.error('Error cargando incidencias:', err);
    if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:1.5rem; color:#ef4444;">Error: ${escapeHtml(err.message)}</td></tr>`;
  }
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function formatNumber(num) {
  if (num === null || num === undefined) return '0';
  return Number(num).toLocaleString('es-PE');
}

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

