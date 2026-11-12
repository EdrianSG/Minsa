/**
 * Lógica del Módulo: Administración, Seguridad y Auditoría
 * Hospital Distrital de Pacasmayo - ETAPA 8
 */

let adminState = {
  tabActiva: 'usuarios', // 'usuarios', 'roles', 'auditoria', 'parametros'
  usuarios: [],
  roles: [],
  auditoria: [],
  parametros: {},
  personalDisponible: [],
  cargandoPersonal: true,
  cargandoRoles: true,
  errorPersonal: '',
  errorRoles: '',
  creandoUsuario: false
};

document.addEventListener('DOMContentLoaded', () => {
  initAdminTabs();
  initAdminControls();
  cargarUsuarios();
  cargarRoles();
  cargarAuditoria();
  cargarParametros();
  cargarPersonalParaCreacion();
});

/**
 * Control de navegación por pestañas
 */
function initAdminTabs() {
  const tabBtns = document.querySelectorAll('.admin-tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.getAttribute('data-tab');
      if (tab) {
        adminState.tabActiva = tab;
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        const targetPane = document.getElementById(`pane-${tab}`);
        if (targetPane) targetPane.classList.add('active');

        // Refrescar según pestaña
        if (tab === 'usuarios') cargarUsuarios();
        if (tab === 'roles') cargarRoles();
        if (tab === 'auditoria') cargarAuditoria();
        if (tab === 'parametros') cargarParametros();
      }
    });
  });
}

/**
 * Eventos y controles generales
 */
function initAdminControls() {
  // Filtros de usuarios
  const inputSearchUser = document.getElementById('searchUserInput');
  const selectRolUser = document.getElementById('filterUserRol');
  const selectEstadoUser = document.getElementById('filterUserEstado');

  if (inputSearchUser) {
    inputSearchUser.addEventListener('input', debounce(() => cargarUsuarios(), 300));
  }
  if (selectRolUser) {
    selectRolUser.addEventListener('change', () => cargarUsuarios());
  }
  if (selectEstadoUser) {
    selectEstadoUser.addEventListener('change', () => cargarUsuarios());
  }

  // Filtros de auditoría
  const selectModAudit = document.getElementById('filterAuditModulo');
  const selectAccAudit = document.getElementById('filterAuditAccion');
  const inputSearchAudit = document.getElementById('searchAuditInput');

  if (selectModAudit) {
    selectModAudit.addEventListener('change', () => cargarAuditoria());
  }
  if (selectAccAudit) {
    selectAccAudit.addEventListener('change', () => cargarAuditoria());
  }
  if (inputSearchAudit) {
    inputSearchAudit.addEventListener('input', debounce(() => cargarAuditoria(), 300));
  }

  // Botón Nuevo Usuario
  const btnNuevoUser = document.getElementById('btnNuevoUsuario');
  if (btnNuevoUser) {
    btnNuevoUser.addEventListener('click', () => {
      openModal('modalCrearUsuario');
      cargarPersonalParaCreacion();
      cargarRoles();
    });
  }

  // Selector de Personal en modal Crear Usuario
  const selectPersonalModal = document.getElementById('modalUserPersonalSelect');
  if (selectPersonalModal) {
    selectPersonalModal.addEventListener('change', (e) => {
      const pId = parseInt(e.target.value, 10);
      const persona = adminState.personalDisponible.find(p => Number(p.personal_id) === pId);
      for (const id of ['modalUserDni', 'modalUserNombre', 'modalUserServicio', 'modalUserCargo']) document.getElementById(id).value = '';
      if (persona) {
        document.getElementById('modalUserDni').value = persona.dni || '';
        document.getElementById('modalUserNombre').value = persona.nombre_completo || '';
        document.getElementById('modalUserServicio').value = persona.servicio_nombre || '';
        document.getElementById('modalUserCargo').value = persona.cargo_nombre || '';

        // Sugerir nombre de usuario basado en inicial + apellido
        const partes = (persona.nombre_completo || '').split(' ');
        if (partes.length >= 2) {
          const sug = (partes[0].charAt(0) + partes[partes.length - 2]).toLowerCase().replace(/[^a-z]/g, '');
          document.getElementById('modalUserUsername').value = sug;
        }
      }
    });
  }

  // Formulario Crear Usuario
  const formCrearUser = document.getElementById('formCrearUsuario');
  if (formCrearUser) {
    formCrearUser.addEventListener('submit', async (e) => {
      e.preventDefault();
      await guardarNuevoUsuario();
    });
  }

  // Formulario Guardar Parámetros
  const formParams = document.getElementById('formParametrosSistema');
  if (formParams) {
    formParams.addEventListener('submit', async (e) => {
      e.preventDefault();
      await guardarParametrosSistema();
    });
  }
}

/**
 * Carga usuarios del sistema
 */
async function cargarUsuarios() {
  const container = document.getElementById('usuariosTableBody');
  if (!container) return;

  const q = document.getElementById('searchUserInput')?.value || '';
  const rol = document.getElementById('filterUserRol')?.value || '';
  const estado = document.getElementById('filterUserEstado')?.value || '';

  try {
    const res = await API.get('administracion/usuarios.php', { q, rol, estado });
    if (res && res.ok) {
      adminState.usuarios = res.usuarios || [];
      actualizarKpisUsuarios(adminState.usuarios);
      renderUsuarios(adminState.usuarios);
    }
  } catch (e) {
    container.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--status-absent-text);">Error al cargar usuarios.</td></tr>`;
  }
}

function actualizarKpisUsuarios(usuarios) {
  const kpiTotal = document.getElementById('kpiTotalUsuarios');
  const kpiActivos = document.getElementById('kpiUsuariosActivos');
  const kpiBloqueados = document.getElementById('kpiUsuariosBloqueados');

  if (kpiTotal) kpiTotal.textContent = usuarios.length;
  if (kpiActivos) kpiActivos.textContent = usuarios.filter(u => u.estado && !u.bloqueado).length;
  if (kpiBloqueados) kpiBloqueados.textContent = usuarios.filter(u => u.bloqueado).length;
}

function renderUsuarios(usuarios) {
  const tbody = document.getElementById('usuariosTableBody');
  if (!tbody) return;

  if (usuarios.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
          No se encontraron usuarios registrados con los filtros seleccionados.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = usuarios.map(u => {
    const isBloq = u.bloqueado;
    return `
      <tr style="${isBloq ? 'background-color: rgba(239, 68, 68, 0.03);' : ''}">
        <td>
          <div style="display: flex; align-items: center; gap: 0.65rem;">
            <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--primary-100); color: var(--primary-700); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;">
              ${u.username.substring(0, 2).toUpperCase()}
            </div>
            <div>
              <div style="font-weight: 700; color: var(--text-primary); font-size: 0.88rem;">${escapeHtml(u.username)}</div>
              <div style="font-size: 0.72rem; color: var(--text-tertiary);">ID: #${u.usuario_id} &bull; Creado: ${u.fecha_creacion ? u.fecha_creacion.substring(0, 10) : '--'}</div>
            </div>
          </div>
        </td>
        <td>
          <div style="font-weight: 600; color: var(--text-primary); font-size: 0.86rem;">${escapeHtml(u.nombre_completo)}</div>
          <div style="font-size: 0.74rem; color: var(--text-secondary);">DNI: ${escapeHtml(u.dni)} &bull; ${escapeHtml(u.cargo_nombre || '')}</div>
        </td>
        <td style="font-size: 0.82rem; color: var(--text-secondary);">
          ${escapeHtml(u.servicio_nombre || 'Hospital Pacasmayo')}
        </td>
        <td>
          <span class="role-badge" style="background: rgba(37, 99, 235, 0.1); color: var(--primary-600); border: 1px solid rgba(37, 99, 235, 0.2);">
            ${escapeHtml(u.rol_codigo)}
          </span>
        </td>
        <td>
          ${isBloq
            ? '<span class="badge badge-absent">Bloqueado 🔒</span>'
            : '<span class="badge badge-present">Activo ✓</span>'}
        </td>
        <td style="font-size: 0.76rem; color: var(--text-secondary);">
          ${u.ultimo_acceso ? u.ultimo_acceso.substring(0, 16) : '<span style="color: var(--text-tertiary);">Nunca</span>'}
        </td>
        <td style="text-align: right;">
          <div style="display: inline-flex; gap: 0.35rem;">
            <!-- Botón Bloquear / Desbloquear -->
            <button type="button" class="btn btn-secondary btn-sm"
              onclick="toggleBloqueoUsuario(${u.usuario_id}, ${!isBloq})"
              title="${isBloq ? 'Desbloquear acceso' : 'Bloquear acceso preventivamente'}">
              ${isBloq ? '🔓 Desbloquear' : '🔒 Bloquear'}
            </button>

            <!-- Botón Resetear Clave -->
            <button type="button" class="btn btn-outline btn-sm"
              data-reset-user="${Number(u.usuario_id)}" data-username="${escapeHtml(u.username)}"
              title="Generar nueva contraseña temporal">
              🔑 Reset Clave
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Alterna el bloqueo de un usuario
 */
async function toggleBloqueoUsuario(usuarioId, nuevoEstadoBloqueo) {
  try {
    const res = await API.post('administracion/usuarios.php', {
      accion: 'cambiar_bloqueo',
      usuario_id: usuarioId,
      bloqueado: nuevoEstadoBloqueo
    });

    if (res && res.ok) {
      showToast(nuevoEstadoBloqueo ? 'Cuenta de usuario bloqueada con éxito.' : 'Cuenta de usuario desbloqueada.', 'success');
      cargarUsuarios();
      cargarAuditoria();
    } else {
      showToast(res.error || 'Error al cambiar estado de bloqueo.', 'danger');
    }
  } catch (e) {
    showToast(e.message || 'No se pudo cambiar el bloqueo.', 'danger');
  }
}

/**
 * Solicita reseteo de clave para un usuario
 */
async function solicitarResetPassword(usuarioId, username) {
  if (!confirm(`¿Está seguro de restablecer la contraseña del usuario '${username}'? Se generará una clave temporal institucional.`)) {
    return;
  }

  try {
    const res = await API.post('administracion/usuarios.php', {
      accion: 'reset_password',
      usuario_id: usuarioId
    });

    if (res && res.ok) {
      alert(`Contraseña restablecida con éxito para el usuario '${username}'.\n\nClave temporal generada: ${res.clave_temporal}\n\nEntregue esta clave al titular por un canal privado.`);
      cargarUsuarios();
      cargarAuditoria();
    } else {
      showToast(res.error || 'Error al resetear contraseña.', 'danger');
    }
  } catch (e) {
    showToast(e.message || 'No se pudo restablecer la contraseña.', 'danger');
  }
}

/**
 * Carga el selector de personal que no tiene usuario asignado aún
 */
async function cargarPersonalParaCreacion() {
  adminState.cargandoPersonal = true;
  adminState.errorPersonal = '';
  actualizarFormularioUsuario();
  try {
    const [res, accounts] = await Promise.all([
      API.get('personal/opciones.php', {contexto:'administracion', estado:1}),
      API.get('administracion/usuarios.php')
    ]);
    const staff = [...(res.data || [])];
    const used = new Set((accounts.usuarios || []).map(user => Number(user.personal_id)));
    adminState.personalDisponible = staff.filter(person => !used.has(Number(person.personal_id)));
    const select = document.getElementById('modalUserPersonalSelect');
    if (select) {
      select.replaceChildren(new Option('Cuenta técnica (sin trabajador; solo administradores)', ''));
      adminState.personalDisponible.forEach(person => select.add(new Option(`${person.nombre_completo} (DNI: ${person.dni})`, String(person.personal_id))));
    }
  } catch (e) {
    adminState.errorPersonal = e.message || 'No se pudo cargar el personal disponible.';
  } finally {
    adminState.cargandoPersonal = false;
    actualizarFormularioUsuario();
  }
}

/**
 * Guarda nuevo usuario en backend
 */
async function guardarNuevoUsuario() {
  if (adminState.creandoUsuario) return;
  const username = document.getElementById('modalUserUsername')?.value.trim();
  const personalId = parseInt(document.getElementById('modalUserPersonalSelect')?.value, 10) || null;
  const rolId = parseInt(document.getElementById('modalUserRolSelect')?.value, 10);
  const dni = document.getElementById('modalUserDni')?.value.trim();
  const nombreCompleto = document.getElementById('modalUserNombre')?.value.trim();
  const servicioNombre = document.getElementById('modalUserServicio')?.value.trim();
  const cargoNombre = document.getElementById('modalUserCargo')?.value.trim();

  if (!username || !Number.isInteger(rolId) || (personalId !== null && !adminState.personalDisponible.some(person => Number(person.personal_id) === personalId)) || !adminState.roles.some(role => Number(role.rol_id) === rolId)) {
    showToast('Complete el usuario y seleccione un trabajador y un rol disponibles.', 'warning');
    return;
  }

  adminState.creandoUsuario = true;
  actualizarFormularioUsuario();
  try {
    const res = await API.post('administracion/usuarios.php', {
      accion: 'crear',
      username,
      personal_id: personalId,
      rol_id: rolId,
      dni,
      nombre_completo: nombreCompleto,
      servicio_nombre: servicioNombre,
      cargo_nombre: cargoNombre
    });

    if (res && res.ok) {
      alert(`Usuario '${username}' creado en SQL Server.\nClave generada: ${res.clave_temporal}\nEntregue esta clave al titular por un canal privado.`);
      closeModal('modalCrearUsuario');
      document.getElementById('formCrearUsuario')?.reset();
      cargarPersonalParaCreacion();
      cargarUsuarios();
      cargarAuditoria();
    } else {
      showToast(res.error || 'No se pudo crear el usuario.', 'danger');
    }
  } catch (e) {
    showToast(e.message || 'No se pudo crear el usuario.', 'danger');
  } finally {
    adminState.creandoUsuario = false;
    actualizarFormularioUsuario();
  }
}

/**
 * Carga roles y matriz de permisos
 */
async function cargarRoles() {
  adminState.cargandoRoles = true;
  adminState.errorRoles = '';
  actualizarFormularioUsuario();
  const grid = document.getElementById('rolesGridContainer');
  try {
    const res = await API.get('administracion/roles.php');
    if (res && res.ok) {
      adminState.roles = res.roles || [];
      renderRoles(adminState.roles);
      for (const [id, blank] of [['modalUserRolSelect', '-- Seleccione un rol --'], ['filterUserRol', 'Todos los roles']]) {
        const select = document.getElementById(id);
        if (!select) continue;
        const previous = select.value;
        select.replaceChildren(new Option(blank, ''));
        adminState.roles.forEach(role => select.add(new Option(role.nombre, id === 'modalUserRolSelect' ? String(role.rol_id) : role.codigo)));
        select.value = previous;
      }
    }
  } catch (e) {
    adminState.errorRoles = e.message || 'No se pudieron cargar los roles.';
    if (grid) grid.innerHTML = `<div>Error al cargar roles.</div>`;
  } finally {
    adminState.cargandoRoles = false;
    actualizarFormularioUsuario();
  }
}

function renderRoles(roles) {
  const grid = document.getElementById('rolesGridContainer');
  if (!grid) return;

  grid.innerHTML = roles.map(r => `
    <div class="role-card" style="border-top-color: ${r.color};">
      <div class="role-header">
        <span class="role-badge" style="background: rgba(37, 99, 235, 0.1); color: ${r.color};">
          ${escapeHtml(r.codigo)}
        </span>
        <span style="font-size: 0.74rem; font-weight: 700; color: var(--text-tertiary);">
          ${r.usuarios_total} ${r.usuarios_total === 1 ? 'Usuario' : 'Usuarios'}
        </span>
      </div>
      <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-primary);">
        ${escapeHtml(r.nombre)}
      </h3>
      <p class="role-desc">${escapeHtml(r.descripcion)}</p>

      <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.35rem;">
        Capacidades Clave:
      </div>
      <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
        ${r.permisos.programacion.aprobar ? '<span class="badge badge-present" style="font-size: 0.68rem;">Aprobar Roles</span>' : ''}
        ${r.permisos.asistencia.regularizar ? '<span class="badge badge-late" style="font-size: 0.68rem;">Regularizar Asistencia</span>' : ''}
        ${r.permisos.ausencias.dictaminar ? '<span class="badge badge-leave" style="font-size: 0.68rem;">Dictaminar CITT</span>' : ''}
        ${r.permisos.administracion.ver ? '<span class="badge badge-neutral" style="font-size: 0.68rem;">Administrar Sistema</span>' : ''}
        ${r.permisos.reportes.exportar_excel ? '<span class="badge badge-neutral" style="font-size: 0.68rem;">Exportar MINSA</span>' : ''}
      </div>
    </div>
  `).join('');
}

/**
 * Carga eventos de auditoría
 */
async function cargarAuditoria() {
  const tbody = document.getElementById('auditoriaTableBody');
  if (!tbody) return;

  const modulo = document.getElementById('filterAuditModulo')?.value || '';
  const accion = document.getElementById('filterAuditAccion')?.value || '';
  const q = document.getElementById('searchAuditInput')?.value || '';

  try {
    const res = await API.get('administracion/auditoria.php', { modulo, accion, q, limit: 50 });
    if (res && res.ok) {
      adminState.auditoria = res.eventos || [];
      const kpiAudit = document.getElementById('kpiTotalEventos');
      if (kpiAudit) kpiAudit.textContent = res.totales?.total_registros || adminState.auditoria.length;
      renderAuditoria(adminState.auditoria);
    }
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--status-absent-text);">Error al consultar bitácora de auditoría.</td></tr>`;
  }
}

function renderAuditoria(eventos) {
  const tbody = document.getElementById('auditoriaTableBody');
  if (!tbody) return;

  if (eventos.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
          No se registran eventos de auditoría con los criterios especificados.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = eventos.map(e => {
    let badgeClass = 'audit-update';
    if (e.accion === 'INSERT') badgeClass = 'audit-insert';
    else if (e.accion === 'DELETE' || e.accion === 'BLOQUEO') badgeClass = 'audit-delete';
    else if (e.accion === 'LOGIN') badgeClass = 'audit-login';
    else if (e.accion === 'APROBAR' || e.accion === 'VALIDAR_150H') badgeClass = 'audit-aprobar';

    return `
      <tr>
        <td style="font-family: monospace; font-size: 0.78rem; white-space: nowrap; color: var(--text-secondary);">
          ${e.fecha_hora}
        </td>
        <td>
          <div style="font-weight: 700; color: var(--text-primary); font-size: 0.84rem;">
            ${escapeHtml(e.nombre_usuario || 'SISTEMA')}
          </div>
        </td>
        <td>
          <span style="font-weight: 700; font-size: 0.76rem; color: var(--text-secondary); text-transform: uppercase;">
            ${escapeHtml(e.modulo)}
          </span>
        </td>
        <td>
          <span class="audit-badge ${badgeClass}">${escapeHtml(e.accion)}</span>
        </td>
        <td style="font-size: 0.78rem; font-family: monospace; color: var(--text-secondary);">
          ${e.tabla_afectada ? `${escapeHtml(e.tabla_afectada)}` : '--'}
        </td>
        <td>
          <span class="ip-chip">${escapeHtml(e.direccion_ip || '127.0.0.1')}</span>
        </td>
        <td style="font-size: 0.82rem; color: var(--text-primary);">
          ${escapeHtml(e.descripcion || '')}
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Carga parámetros del sistema
 */
async function cargarParametros() {
  try {
    const res = await API.get('administracion/parametros.php');
    if (res && res.ok && res.parametros) {
      adminState.parametros = res.parametros;
      const p = res.proxima_configuracion || res.parametros;

      const inputTol = document.getElementById('paramTolerancia');
      const inputTarMax = document.getElementById('paramTardanzaMax');
      const inputRegla = document.getElementById('paramReglaHoras');
      const inputDiasReg = document.getElementById('paramDiasReg');
      const checkCitt = document.getElementById('paramCittObligatorio');
      const selectBio = document.getElementById('paramBiometricoModo');

      if (inputTol) inputTol.value = p.tolerancia_entrada_minutos;
      if (inputTarMax) inputTarMax.value = p.tardanza_maxima_minutos;
      if (inputRegla) { inputRegla.value = p.regla_asistencial_horas; inputRegla.readOnly = true; }
      if (inputDiasReg) inputDiasReg.value = p.dias_maximos_regularizar;
      if (checkCitt) checkCitt.checked = !!p.citt_obligatorio_salud;
      if (selectBio) { selectBio.value = p.biometrico_modo || 'IMPORTACION_INDEPENDIENTE'; selectBio.disabled = true; }

      if (res.editable === false) document.querySelectorAll('#formParametrosSistema input, #formParametrosSistema select, #formParametrosSistema button').forEach(el => el.disabled = true);
      const infoUpdate = document.getElementById('paramUltimaActualizacion');
      if (infoUpdate) {
        infoUpdate.textContent = res.mensaje || 'Reglas institucionales en configuración.';
      }
    }
  } catch (e) {
    // Silencioso
  }
}

/**
 * Guarda parámetros del sistema
 */
async function guardarParametrosSistema() {
  const tolerancia = parseInt(document.getElementById('paramTolerancia')?.value, 10);
  const tardanzaMax = parseInt(document.getElementById('paramTardanzaMax')?.value, 10);
  const reglaHoras = parseInt(document.getElementById('paramReglaHoras')?.value, 10);
  const diasReg = parseInt(document.getElementById('paramDiasReg')?.value, 10);
  const cittOblig = document.getElementById('paramCittObligatorio')?.checked ? 1 : 0;
  const biometricoModo = document.getElementById('paramBiometricoModo')?.value;

  try {
    const res = await API.post('administracion/parametros.php', {
      tolerancia_entrada_minutos: tolerancia,
      tardanza_maxima_minutos: tardanzaMax,
      regla_asistencial_horas: reglaHoras,
      dias_maximos_regularizar: diasReg,
      citt_obligatorio_salud: cittOblig,
      biometrico_modo: biometricoModo
    });

    if (res && res.ok) {
      showToast('Parámetros guardados en SQL Server. Vigencia desde el día siguiente.', 'success');
      cargarParametros();
      cargarAuditoria();
    } else {
      showToast(res.error || 'Error al guardar parámetros.', 'danger');
    }
  } catch (e) {
    showToast('Error de conexión al actualizar parámetros.', 'danger');
  }
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function escapeHtml(t) {
  if (!t) return '';
  return String(t)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

document.addEventListener('click', event => {
  const button = event.target.closest('[data-reset-user]');
  if (button) solicitarResetPassword(Number(button.dataset.resetUser), button.dataset.username);
});

function actualizarFormularioUsuario() {
  const button = document.getElementById('btnCrearCuenta');
  const message = document.getElementById('modalUserEstado');
  const link = document.getElementById('modalUserRegistrarPersonal');
  const loading = adminState.cargandoPersonal || adminState.cargandoRoles;
  const error = adminState.errorPersonal || adminState.errorRoles;
  const noWorkers = adminState.personalDisponible.length === 0;
  const noRoles = adminState.roles.length === 0;
  if (button) {
    button.disabled = loading || Boolean(error) || noRoles || adminState.creandoUsuario;
    button.textContent = adminState.creandoUsuario ? 'Creando cuenta...' : 'Crear Cuenta';
  }
  for (const id of ['modalUserPersonalSelect', 'modalUserRolSelect']) {
    const select = document.getElementById(id);
    if (select) select.disabled = loading || Boolean(error) || adminState.creandoUsuario;
  }
  if (link) link.hidden = loading || Boolean(error) || !noWorkers;
  if (message) message.textContent = loading ? 'Cargando trabajadores y roles...' : error ||
    (noWorkers ? 'Puede crear cuentas técnicas con rol administrativo sin trabajador.' :
     noRoles ? 'No hay roles disponibles para crear una cuenta.' : 'Seleccione el trabajador y el rol para crear su cuenta de acceso.');
}
