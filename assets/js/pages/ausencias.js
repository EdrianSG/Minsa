/**
 * Lógica del Módulo: Ausencias y Justificaciones
 * Hospital Distrital de Pacasmayo - ETAPA 6
 */

let ausState = {
  estado: 'TODOS',
  tipoId: '',
  servicioId: '',
  buscar: '',
  datos: [],
  resumen: null,
  tiposCatalogo: [],
  personalCatalogo: [],
  permisoEnDictamen: null
};

document.addEventListener('DOMContentLoaded', () => {
  initAusenciasControls();
  cargarCatalogoTipos();
  cargarPermisos();
  cargarPersonalParaSelect();
});

/**
 * Inicializa controles de interfaz y filtros
 */
function initAusenciasControls() {
  const searchInput = document.getElementById('ausSearchInput');
  const selectEstado = document.getElementById('ausEstadoSelect');
  const selectServicio = document.getElementById('ausServicioSelect');
  const selectTipo = document.getElementById('ausTipoSelect');

  const btnNueva = document.getElementById('btnNuevaPapeleta');
  const btnCatalogo = document.getElementById('btnVerCatalogo');
  const btnImprimir = document.getElementById('btnImprimirAusencias');

  if (searchInput) {
    searchInput.addEventListener('input', debounce((e) => {
      ausState.buscar = e.target.value.trim();
      cargarPermisos();
    }, 280));
  }

  if (selectEstado) {
    selectEstado.addEventListener('change', (e) => {
      ausState.estado = e.target.value;
      cargarPermisos();
    });
  }

  if (selectServicio) {
    selectServicio.addEventListener('change', (e) => {
      ausState.servicioId = e.target.value;
      cargarPermisos();
    });
  }

  if (selectTipo) {
    selectTipo.addEventListener('change', (e) => {
      ausState.tipoId = e.target.value;
      cargarPermisos();
    });
  }

  if (btnNueva) {
    btnNueva.addEventListener('click', () => {
      abrirModalNuevaPapeleta();
    });
  }

  if (btnCatalogo) {
    btnCatalogo.addEventListener('click', () => {
      abrirModalCatalogo();
    });
  }

  if (btnImprimir) {
    btnImprimir.addEventListener('click', () => {
      window.print();
    });
  }

  // Interacción en KPI cards como filtros rápidos
  const kpiCards = document.querySelectorAll('.aus-kpi-box');
  kpiCards.forEach(card => {
    card.addEventListener('click', () => {
      const filtro = card.getAttribute('data-filtro');
      if (filtro) {
        ausState.estado = filtro;
        if (selectEstado) selectEstado.value = filtro;
        kpiCards.forEach(c => c.classList.remove('active-filter'));
        card.classList.add('active-filter');
        cargarPermisos();
      }
    });
  });

  // Calculadora de días en el modal de nueva papeleta
  const fIniInput = document.getElementById('solFechaInicio');
  const fFinInput = document.getElementById('solFechaFin');
  const tipoSelect = document.getElementById('solTipoPermiso');

  if (fIniInput && fFinInput) {
    [fIniInput, fFinInput].forEach(inp => {
      inp.addEventListener('change', recalcularDiasSolicitud);
    });
  }

  if (tipoSelect) {
    tipoSelect.addEventListener('change', (e) => {
      const isCitt = (e.target.value === '1');
      const docGroup = document.getElementById('groupDocSustento');
      const docLabel = document.getElementById('labelDocSustento');
      const docInput = document.getElementById('solDocumentoSustento');

      if (docLabel && docInput) {
        if (isCitt) {
          docLabel.innerHTML = 'N° de CITT / Certificado Médico * <span style="color: var(--status-absent-accent);">(Obligatorio por norma)</span>';
          docInput.placeholder = 'Ej: CITT EsSalud N° 2026-0045812';
          docInput.required = true;
        } else {
          docLabel.innerHTML = 'Documento de Sustento';
          docInput.placeholder = 'Ej: Papeleta N° / Memo N° 045-2026';
          docInput.required = false;
        }
      }
    });
  }

  // Form submit nueva papeleta
  const formSolicitud = document.getElementById('formNuevaPapeleta');
  if (formSolicitud) {
    formSolicitud.addEventListener('submit', async (e) => {
      e.preventDefault();
      await enviarNuevaPapeleta();
    });
  }
}

/**
 * Recalcula la duración en días de la solicitud
 */
function recalcularDiasSolicitud() {
  const fIni = document.getElementById('solFechaInicio')?.value;
  const fFin = document.getElementById('solFechaFin')?.value;
  const boxCalc = document.getElementById('calcDiasResultado');
  const btnSubmit = document.getElementById('btnGuardarPapeleta');

  if (!fIni || !fFin) return;

  const t1 = new Date(fIni);
  const t2 = new Date(fFin);

  if (t2 < t1) {
    if (boxCalc) {
      boxCalc.innerHTML = `<span style="color: var(--status-absent-accent); font-weight: 700;">⚠ Error: La fecha fin no puede ser anterior a la fecha de inicio.</span>`;
    }
    if (btnSubmit) btnSubmit.disabled = true;
    return;
  }

  const diffDays = Math.round((t2 - t1) / (1000 * 60 * 60 * 24)) + 1;
  if (boxCalc) {
    boxCalc.innerHTML = `Duración: <strong style="color: var(--primary-600); font-size: 1.05rem;">${diffDays} día(s) calendario</strong> programados`;
  }
  if (btnSubmit) btnSubmit.disabled = false;
}

/**
 * Carga el catálogo de tipos de permiso
 */
async function cargarCatalogoTipos() {
  try {
    const res = await API.get('permisos/tipos.php');
    if (res && res.ok) {
      ausState.tiposCatalogo = res.tipos || [];
      poblarSelectTipos(res.tipos);
      renderModalCatalogo(res.tipos);
    }
  } catch (e) {
    console.error('Error al cargar catálogo de tipos:', e);
  }
}

/**
 * Llena los selects de tipos de permiso en la barra y en el modal
 */
function poblarSelectTipos(tipos) {
  const selectBar = document.getElementById('ausTipoSelect');
  const selectModal = document.getElementById('solTipoPermiso');

  if (selectBar) {
    selectBar.innerHTML = '<option value="">-- Todos los Tipos de Permiso --</option>' + 
      tipos.map(t => `<option value="${t.TipoPermisoLaboralID}">${escapeHtml(t.Nombre)}</option>`).join('');
  }

  if (selectModal) {
    selectModal.innerHTML = '<option value="">-- Seleccione Tipo de Permiso --</option>' + 
      tipos.map(t => `<option value="${t.TipoPermisoLaboralID}">${escapeHtml(t.Nombre)} ${t.GoceHaber ? '(Con Goce)' : '(Sin Goce)'}</option>`).join('');
  }
}

/**
 * Carga la lista de trabajadores para el select del modal
 */
async function cargarPersonalParaSelect() {
  try {
    const res = await API.get('personal/listar.php', { limit: 100 });
    if (res && res.ok) {
      ausState.personalCatalogo = res.personal || [];
      const select = document.getElementById('solPersonalId');
      if (select) {
        select.innerHTML = '<option value="">-- Seleccione Trabajador --</option>' +
          res.personal.map(p => `
            <option value="${p.personal_id}">${escapeHtml(p.nombre_completo)} (DNI: ${p.dni} - ${escapeHtml(p.cargo)})</option>
          `).join('');
      }
    }
  } catch (e) {
    // Si falla, se carga dinámicamente
  }
}

/**
 * Consulta la lista de permisos desde la API
 */
async function cargarPermisos() {
  const tbody = document.getElementById('ausTableBody');
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
        <td><div class="skeleton skeleton-pill" style="width: 85px;"></div></td>
        <td>
          <div class="skeleton skeleton-text w-60"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
        </td>
        <td>
          <div class="skeleton skeleton-text w-80"></div>
          <div class="skeleton skeleton-text w-40" style="height: 0.7rem;"></div>
        </td>
        <td style="text-align: center;"><div class="skeleton skeleton-pill" style="width: 75px; margin: 0 auto;"></div></td>
        <td style="text-align: center;"><div class="skeleton skeleton-btn" style="width: 70px; height: 1.8rem; margin: 0 auto;"></div></td>
      </tr>
    `).join('');
  }

  try {
    const params = {};
    if (ausState.estado !== 'TODOS') params.estado = ausState.estado;
    if (ausState.tipoId !== '') params.tipo_id = ausState.tipoId;
    if (ausState.servicioId !== '') params.servicio_id = ausState.servicioId;
    if (ausState.buscar !== '') params.buscar = ausState.buscar;

    const res = await API.get('permisos/listar.php', params);

    if (res && res.ok) {
      ausState.datos = res.permisos || [];
      ausState.resumen = res.resumen || null;
      renderKPIs(res.resumen);
      renderTablaPermisos(res.permisos);
    }
  } catch (error) {
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--status-absent-accent);">
            No se pudo cargar la información de papeletas.
          </td>
        </tr>
      `;
    }
  }
}

/**
 * Renderiza los KPIs
 */
function renderKPIs(resumen) {
  if (!resumen) return;

  const elTot = document.getElementById('kpiTotalPermisos');
  const elPend = document.getElementById('kpiPendientes');
  const elAprob = document.getElementById('kpiAprobadas');
  const elCitt = document.getElementById('kpiCittActivos');
  const elRech = document.getElementById('kpiRechazadas');

  if (elTot) elTot.textContent = resumen.total_solicitudes;
  if (elPend) elPend.textContent = resumen.pendientes;
  if (elAprob) elAprob.textContent = resumen.aprobadas;
  if (elCitt) elCitt.textContent = resumen.citt_activos;
  if (elRech) elRech.textContent = resumen.rechazadas;
}

/**
 * Renderiza la tabla de permisos
 */
function renderTablaPermisos(items) {
  const tbody = document.getElementById('ausTableBody');
  const countEl = document.getElementById('ausRegistrosCount');
  if (!tbody) return;

  if (countEl) countEl.textContent = `${items.length} solicitudes encontradas`;

  if (items.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
          No se encontraron solicitudes de permiso o justificación con los filtros aplicados.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = items.map(p => {
    const initials = getInitials(p.nombre_completo);
    const badgeTipo = getBadgeTipo(p.tipo_nombre, p.es_citt);
    const badgeEstado = getBadgeEstado(p.estado);

    return `
      <tr>
        <!-- Trabajador -->
        <td>
          <div style="display: flex; flex-direction: column;">
            <span style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem;">
              ${escapeHtml(p.nombre_completo)}
            </span>
            <span style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
              DNI ${p.dni} &bull; ${escapeHtml(p.cargo)}
            </span>
          </div>
        </td>

        <!-- Servicio -->
        <td>
          <div style="font-size: 0.82rem; color: var(--text-primary); font-weight: 500;">
            ${escapeHtml(p.servicio_nombre)}
          </div>
          <div style="font-size: 0.72rem; color: var(--text-tertiary); margin-top: 1px;">
            ${p.condicion_laboral}
          </div>
        </td>

        <!-- Tipo de Permiso -->
        <td>${badgeTipo}</td>

        <!-- Periodo y Días -->
        <td>
          <div style="font-weight: 600; font-size: 0.82rem; color: var(--text-primary);">
            ${p.fecha_inicio} &rarr; ${p.fecha_fin}
          </div>
          <div style="font-size: 0.74rem; color: var(--text-secondary); margin-top: 1px;">
            <strong>${p.dias_solicitados} d</strong> &bull; ${p.goce_haber ? '<span style="color: var(--status-present-accent);">Con Goce</span>' : '<span style="color: var(--status-absent-accent);">Sin Goce</span>'}
          </div>
        </td>

        <!-- Documento de Sustento -->
        <td>
          ${p.es_citt ? `
            <span class="citt-badge">🩺 ${escapeHtml(p.documento_sustento)}</span>
          ` : `
            <div style="font-size: 0.82rem; font-family: monospace; font-weight: 600; color: var(--text-primary);">
              ${escapeHtml(p.documento_sustento || 'Sin documento')}
            </div>
          `}
          <div style="font-size: 0.74rem; color: var(--text-tertiary); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;" title="${escapeHtml(p.motivo)}">
            ${escapeHtml(p.motivo)}
          </div>
        </td>

        <!-- Estado del Trámite -->
        <td>${badgeEstado}</td>

        <!-- Acciones -->
        <td style="text-align: right;">
          ${p.estado === 'PENDIENTE' ? `
            <button 
              type="button" 
              class="btn btn-primary btn-sm"
              style="padding: 0.25rem 0.6rem; font-size: 0.76rem;"
              onclick="abrirModalDictamen(${p.permiso_id}, '${escapeHtml(p.nombre_completo)}', '${escapeHtml(p.tipo_nombre)}', '${p.fecha_inicio}', '${p.fecha_fin}', '${escapeHtml(p.documento_sustento)}')"
            >
              ⚖ Dictaminar
            </button>
          ` : `
            <button 
              type="button" 
              class="btn btn-secondary btn-sm"
              style="padding: 0.25rem 0.6rem; font-size: 0.76rem;"
              onclick="verFichaPermiso(${p.permiso_id})"
            >
              👁 Ver
            </button>
          `}
        </td>
      </tr>
    `;
  }).join('');
}

function getBadgeTipo(nombre, esCitt) {
  if (esCitt) {
    return `<span class="badge" style="background: rgba(6, 182, 212, 0.12); color: #0891b2; border: 1px solid rgba(6, 182, 212, 0.3);">🩺 Descanso Médico CITT</span>`;
  }
  if (nombre.includes('Comisión')) {
    return `<span class="badge badge-present">🏛 Comisión de Servicio</span>`;
  }
  if (nombre.includes('Capacitación')) {
    return `<span class="badge badge-neutral">🎓 Capacitación MINSA</span>`;
  }
  if (nombre.includes('Onomástico')) {
    return `<span class="badge badge-late">🎂 Permiso Onomástico</span>`;
  }
  if (nombre.includes('Compensación')) {
    return `<span class="badge badge-late">⏱ Compensación Horas</span>`;
  }
  if (nombre.includes('Particular')) {
    return `<span class="badge badge-absent">Permiso Sin Goce</span>`;
  }
  return `<span class="badge badge-neutral">${escapeHtml(nombre)}</span>`;
}

function getBadgeEstado(estado) {
  switch (estado) {
    case 'PENDIENTE':
      return `<span class="badge badge-late">⏳ Pendiente RR.HH.</span>`;
    case 'APROBADO':
      return `<span class="badge badge-present">✓ Aprobado</span>`;
    case 'RECHAZADO':
      return `<span class="badge badge-absent">✖ Rechazado</span>`;
    case 'ANULADO':
      return `<span class="badge badge-neutral">Anulado</span>`;
    default:
      return `<span class="badge badge-neutral">${escapeHtml(estado)}</span>`;
  }
}

/**
 * Abre modal de dictamen por RR.HH.
 */
function abrirModalDictamen(id, nombre, tipo, fIni, fFin, doc) {
  ausState.permisoEnDictamen = id;
  const modal = document.getElementById('modalDictamenRRHH');
  const detailsBox = document.getElementById('dictamenTrabajadorInfo');

  if (detailsBox) {
    detailsBox.innerHTML = `
      <div style="background: var(--bg-surface-soft); padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 1rem;">
        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">${nombre}</div>
        <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.2rem;">
          Tipo: <strong>${tipo}</strong> &bull; Periodo: <strong>${fIni} al ${fFin}</strong>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-tertiary); margin-top: 0.2rem;">
          Documento Sustento: <strong>${doc}</strong>
        </div>
      </div>
    `;
  }

  if (modal) modal.classList.add('active');
}

/**
 * Ejecuta la acción de dictamen (Aprobar o Rechazar)
 */
async function ejecutarDictamen(accion) {
  if (!ausState.permisoEnDictamen) return;

  const obs = document.getElementById('dictamenObservacion')?.value || '';

  try {
    const res = await API.post('permisos/dictaminar.php', {
      permiso_id: ausState.permisoEnDictamen,
      accion: accion,
      observacion_dictamen: obs
    });

    if (res && res.ok) {
      showToast(res.message, (accion === 'APROBAR') ? 'success' : 'warning');
      cerrarModal('modalDictamenRRHH');
      cargarPermisos();
    } else {
      showToast(res.message || 'Error al dictaminar solicitud.', 'danger');
    }
  } catch (e) {
    showToast('Error de comunicación con el servidor.', 'danger');
  }
}

/**
 * Modal de nueva papeleta
 */
function abrirModalNuevaPapeleta() {
  const modal = document.getElementById('modalNuevaPapeleta');
  if (modal) {
    const today = new Date().toISOString().split('T')[0];
    const fIni = document.getElementById('solFechaInicio');
    const fFin = document.getElementById('solFechaFin');
    if (fIni) fIni.value = today;
    if (fFin) fFin.value = today;
    recalcularDiasSolicitud();
    modal.classList.add('active');
  }
}

/**
 * Envía la nueva papeleta al backend
 */
async function enviarNuevaPapeleta() {
  const personalId = parseInt(document.getElementById('solPersonalId')?.value, 10);
  const tipoId = parseInt(document.getElementById('solTipoPermiso')?.value, 10);
  const fIni = document.getElementById('solFechaInicio')?.value;
  const fFin = document.getElementById('solFechaFin')?.value;
  const doc = document.getElementById('solDocumentoSustento')?.value;
  const mot = document.getElementById('solMotivo')?.value;

  if (!personalId || !tipoId || !fIni || !fFin) {
    showToast('Complete todos los campos obligatorios.', 'danger');
    return;
  }

  try {
    const res = await API.post('permisos/guardar.php', {
      personal_id: personalId,
      tipo_permiso_id: tipoId,
      fecha_inicio: fIni,
      fecha_fin: fFin,
      documento_sustento: doc,
      motivo: mot,
      estado: 'PENDIENTE'
    });

    if (res && res.ok) {
      showToast('Papeleta registrada satisfactoriamente.', 'success');
      cerrarModal('modalNuevaPapeleta');
      cargarPermisos();
    } else {
      showToast(res.message || 'Error al registrar solicitud.', 'danger');
    }
  } catch (e) {
    showToast('Error al procesar la solicitud.', 'danger');
  }
}

/**
 * Renderiza el catálogo en el modal
 */
function renderModalCatalogo(tipos) {
  const container = document.getElementById('catalogoPermisosLista');
  if (!container) return;

  container.innerHTML = tipos.map(t => `
    <div class="catalog-item-card">
      <div class="catalog-item-header">
        <span class="catalog-item-title">${escapeHtml(t.Nombre)}</span>
        <span class="badge ${t.GoceHaber ? 'badge-present' : 'badge-absent'}">
          ${t.GoceHaber ? 'Con Goce de Haber' : 'Sin Goce (Deducción)'}
        </span>
      </div>
      <div class="catalog-item-desc">${escapeHtml(t.Descripcion)}</div>
      ${t.RequiereCITT ? `
        <div style="font-size: 0.76rem; color: #0891b2; margin-top: 0.4rem; font-weight: 600;">
          🩺 Requiere presentación obligatoria de CITT emitido por EsSalud o Certificado Médico visado.
        </div>
      ` : ''}
    </div>
  `).join('');
}

function abrirModalCatalogo() {
  const modal = document.getElementById('modalCatalogoPermisos');
  if (modal) modal.classList.add('active');
}

function verFichaPermiso(permisoId) {
  const p = ausState.datos.find(x => x.permiso_id === permisoId);
  if (p) {
    showToast(`Papeleta N° ${p.permiso_id}: ${p.tipo_nombre} - ${p.nombre_completo} (${p.estado})`, 'info');
  }
}

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
