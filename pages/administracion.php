<?php
/**
 * Módulo: Administración, Seguridad y Auditoría (ETAPA 8)
 * Hospital Distrital de Pacasmayo
 */

$pageTitle = "Administración y Seguridad - Hospital Pacasmayo";
$pageScript = "administracion.js";
?>

<div class="admin-container">
  <!-- Encabezado del Módulo -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.65rem;">
        <span>⚙</span> Administración, Seguridad y Auditoría
      </h1>
      <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">
        Control de acceso basado en roles (RBAC), cuentas de usuario, bitácora de eventos y políticas normativas hospitalarias.
      </p>
    </div>
  </div>

  <!-- Barra de Métricas Rápidas -->
  <div class="mini-kpis-bar admin-kpis-grid">
    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--primary-50); color: var(--primary-600);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiTotalUsuarios">5</span>
        <span class="mini-kpi-lbl">Total Usuarios</span>
      </div>
    </div>

    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-present-bg); color: var(--status-present-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiUsuariosActivos" style="color: var(--status-present-text);">4</span>
        <span class="mini-kpi-lbl">Cuentas Activas</span>
      </div>
    </div>

    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-absent-bg); color: var(--status-absent-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiUsuariosBloqueados" style="color: var(--status-absent-text);">1</span>
        <span class="mini-kpi-lbl">Bloqueados Preventivos</span>
      </div>
    </div>

    <div class="mini-kpi-card">
      <div class="mini-kpi-icon" style="background-color: var(--status-late-bg); color: var(--status-late-accent);">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
      </div>
      <div class="mini-kpi-data">
        <span class="mini-kpi-val" id="kpiTotalEventos">7</span>
        <span class="mini-kpi-lbl">Eventos en Bitácora</span>
      </div>
    </div>
  </div>

  <!-- Pestañas Principales de Administración -->
  <div class="admin-tabs-nav">
    <button type="button" class="admin-tab-btn active" data-tab="usuarios">
      <span>👥</span> Usuarios del Sistema
    </button>
    <button type="button" class="admin-tab-btn" data-tab="roles">
      <span>🛡</span> Roles y Permisos (RBAC)
    </button>
    <button type="button" class="admin-tab-btn" data-tab="auditoria">
      <span>📋</span> Bitácora de Auditoría
    </button>
    <button type="button" class="admin-tab-btn" data-tab="parametros">
      <span>⚙</span> Parámetros y Políticas
    </button>
  </div>

  <!-- ========================================================================= -->
  <!-- PANEL 1: USUARIOS DEL SISTEMA                                             -->
  <!-- ========================================================================= -->
  <div class="tab-pane active" id="pane-usuarios">
    <div class="admin-toolbar">
      <div class="admin-filters-group">
        <!-- Buscador -->
        <div style="position: relative; width: 280px;">
          <input type="text" id="searchUserInput" class="input" placeholder="Buscar por usuario, DNI o nombre..." style="padding-left: 2rem;">
          <span style="position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);">🔍</span>
        </div>

        <!-- Filtro por Rol -->
        <select id="filterUserRol" class="input" style="width: auto;">
          <option value="">Todos los Roles</option>
          <option value="ADMIN_RRHH">Administrador General</option>
          <option value="JEFE_SERVICIO">Jefe de Servicio</option>
          <option value="OPERADOR_ASISTENCIA">Operador de Asistencia</option>
          <option value="AUDITOR_PLANILLAS">Auditor de Planillas</option>
        </select>

        <!-- Filtro por Estado -->
        <select id="filterUserEstado" class="input" style="width: auto;">
          <option value="">Todos los Estados</option>
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>
      </div>

      <div>
        <button type="button" class="btn btn-primary" id="btnNuevoUsuario">
          <span>+</span>
          <span>Nuevo Usuario</span>
        </button>
      </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="admin-card">
      <div class="admin-card-header">
        <span>Directorio de Cuentas de Acceso Institucionales</span>
        <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-secondary);">Mapeado a <code>seguridad.Usuario</code> y <code>seguridad.UsuarioRol</code></span>
      </div>
      <div style="overflow-x: auto;">
        <table class="table" style="margin: 0;">
          <thead>
            <tr>
              <th>Usuario / Identificador</th>
              <th>Servidor Vinculado (Persona)</th>
              <th>Servicio / UPSS</th>
              <th>Rol Institucional</th>
              <th>Estado</th>
              <th>Último Acceso</th>
              <th style="text-align: right;">Acciones</th>
            </tr>
          </thead>
          <tbody id="usuariosTableBody">
            <!-- Inyectado por JS -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- PANEL 2: ROLES Y PERMISOS (RBAC)                                          -->
  <!-- ========================================================================= -->
  <div class="tab-pane" id="pane-roles">
    <!-- Tarjetas de Roles -->
    <div class="roles-grid" id="rolesGridContainer">
      <!-- Inyectado por JS -->
    </div>

    <!-- Matriz de Permisos por Módulo -->
    <div class="admin-card">
      <div class="admin-card-header">
        <span>Matriz de Capacidades y Permisos por Módulo del Sistema</span>
        <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-secondary);">Mapeado a <code>seguridad.RolPermiso</code></span>
      </div>
      <div style="overflow-x: auto;">
        <table class="table" style="margin: 0; font-size: 0.85rem;">
          <thead>
            <tr>
              <th>Módulo Funcional</th>
              <th style="text-align: center;">ADMIN_RRHH</th>
              <th style="text-align: center;">JEFE_SERVICIO</th>
              <th style="text-align: center;">OPERADOR_ASISTENCIA</th>
              <th style="text-align: center;">AUDITOR_PLANILLAS</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>1. Dashboard / Resumen</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Acceso Total</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Ver Resumen</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Ver Resumen</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Ver Resumen</span></td>
            </tr>
            <tr>
              <td><strong>2. Directorio de Personal</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Crear / Editar / Bajas</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Solo Lectura</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Solo Lectura</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Solo Lectura</span></td>
            </tr>
            <tr>
              <td><strong>3. Horarios y Turnos</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Parametrizar Turnos</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Consultar Catálogo</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Consultar Catálogo</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Consultar Catálogo</span></td>
            </tr>
            <tr>
              <td><strong>4. Programación Mensual (150h)</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Aprobar y Modificar</span></td>
              <td style="text-align: center;"><span class="badge badge-late">Elaborar y Validar</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Consultar Rol</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Auditar Horas</span></td>
            </tr>
            <tr>
              <td><strong>5. Control de Asistencia</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Supervisar y Ajustar</span></td>
              <td style="text-align: center;"><span class="badge badge-late">Reemplazos Urgencia</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Regularizar Diaria</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Solo Lectura</span></td>
            </tr>
            <tr>
              <td><strong>6. Ausencias y CITT</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Dictamen Final</span></td>
              <td style="text-align: center;"><span class="badge badge-late">Solicitar / Avalar</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Registrar CITT</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Consultar Descuentos</span></td>
            </tr>
            <tr>
              <td><strong>7. Reportes Oficiales MINSA</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Exportar Excel / PDF</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Exportar Excel / PDF</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Exportar Excel / PDF</span></td>
              <td style="text-align: center;"><span class="badge badge-present">Exportar Planillas</span></td>
            </tr>
            <tr>
              <td><strong>8. Administración y Seguridad</strong></td>
              <td style="text-align: center;"><span class="badge badge-present">Control Total</span></td>
              <td style="text-align: center;"><span class="badge badge-absent">Sin Acceso</span></td>
              <td style="text-align: center;"><span class="badge badge-absent">Sin Acceso</span></td>
              <td style="text-align: center;"><span class="badge badge-neutral">Ver Auditoría</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- PANEL 3: BITÁCORA DE AUDITORÍA                                            -->
  <!-- ========================================================================= -->
  <div class="tab-pane" id="pane-auditoria">
    <div class="admin-toolbar">
      <div class="admin-filters-group">
        <!-- Buscador de auditoría -->
        <div style="position: relative; width: 260px;">
          <input type="text" id="searchAuditInput" class="input" placeholder="Buscar en bitácora..." style="padding-left: 2rem;">
          <span style="position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%); color: var(--text-tertiary);">🔍</span>
        </div>

        <!-- Filtro por Módulo -->
        <select id="filterAuditModulo" class="input" style="width: auto;">
          <option value="">Todos los Módulos</option>
          <option value="SEGURIDAD">Seguridad y Cuentas</option>
          <option value="PROGRAMACION">Programación Mensual</option>
          <option value="ASISTENCIA">Control de Asistencia</option>
          <option value="AUSENCIAS">Ausencias y CITT</option>
          <option value="HORARIOS">Horarios y Turnos</option>
          <option value="REPORTES">Reportes Oficiales</option>
          <option value="ADMINISTRACION">Administración General</option>
        </select>

        <!-- Filtro por Acción -->
        <select id="filterAuditAccion" class="input" style="width: auto;">
          <option value="">Todas las Acciones</option>
          <option value="LOGIN">Inicios de Sesión</option>
          <option value="INSERT">Creaciones (INSERT)</option>
          <option value="UPDATE">Modificaciones (UPDATE)</option>
          <option value="APROBAR">Aprobaciones / Dictamen</option>
          <option value="REGULARIZAR">Regularizaciones</option>
          <option value="BLOQUEO">Bloqueos de Acceso</option>
        </select>
      </div>

      <div>
        <button type="button" class="btn btn-secondary" onclick="cargarAuditoria()">
          <span>🔄</span>
          <span>Actualizar Bitácora</span>
        </button>
      </div>
    </div>

    <!-- Tabla de Auditoría -->
    <div class="admin-card">
      <div class="admin-card-header">
        <span>Historial Cronológico de Trazabilidad y Eventos Críticos</span>
        <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-secondary);">Mapeado a <code>auditoria.EventoAuditoria</code></span>
      </div>
      <div style="overflow-x: auto;">
        <table class="table" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 140px;">Fecha y Hora</th>
              <th>Usuario</th>
              <th>Módulo</th>
              <th>Acción</th>
              <th>Tabla Afectada</th>
              <th>Dirección IP</th>
              <th>Descripción del Evento</th>
            </tr>
          </thead>
          <tbody id="auditoriaTableBody">
            <!-- Inyectado por JS -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- PANEL 4: PARÁMETROS Y POLÍTICAS                                           -->
  <!-- ========================================================================= -->
  <div class="tab-pane" id="pane-parametros">
    <form id="formParametrosSistema">
      <div class="params-section">
        <!-- Bloque 1: Políticas de Entrada y Tolerancia -->
        <div class="param-group-box">
          <div class="param-group-title">
            <span>⏱</span> Políticas de Marcación y Tolerancias
          </div>
          <div class="param-group-desc">
            Configuración de los márgenes de tiempo oficiales aplicados por el motor de evaluación de asistencia.
          </div>

          <div class="param-item">
            <label for="paramTolerancia">Tolerancia de Entrada (Minutos):</label>
            <input type="number" id="paramTolerancia" class="input" min="0" max="30" value="5" required>
            <small>Hasta este minuto la marcación se considera <strong>Puntual</strong>.</small>
          </div>

          <div class="param-item">
            <label for="paramTardanzaMax">Límite de Tardanza Leve (Minutos):</label>
            <input type="number" id="paramTardanzaMax" class="input" min="10" max="60" value="30" required>
            <small>Pasado este umbral se cataloga automáticamente como <strong>Inasistencia Injustificada</strong>.</small>
          </div>

          <div class="param-item">
            <label for="paramDiasReg">Días Máximos para Solicitar Justificación:</label>
            <input type="number" id="paramDiasReg" class="input" min="1" max="15" value="3" required>
            <small>Plazo hábil que tiene el servidor para regularizar omisión de marcación.</small>
          </div>
        </div>

        <!-- Bloque 2: Normativa Asistencial y CITT -->
        <div class="param-group-box">
          <div class="param-group-title">
            <span>🏥</span> Normativa Asistencial MINSA (150 Horas)
          </div>
          <div class="param-group-desc">
            Cómputo mensual de guardias diurnas, nocturnas y turnos de 6 horas para personal de salud.
          </div>

          <div class="param-item">
            <label for="paramReglaHoras">Carga Horaria Mensual Obligatoria (Horas):</label>
            <input type="number" id="paramReglaHoras" class="input" min="120" max="180" value="150" required>
            <small>Norma estándar de 150 horas para médicos, enfermeras y técnicos en rol mensual.</small>
          </div>

          <div class="param-item" style="margin-top: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
              <input type="checkbox" id="paramCittObligatorio" checked style="width: 18px; height: 18px;">
              <span style="font-weight: 700;">Exigir CITT en Licencias Médicas</span>
            </label>
            <small style="margin-left: 1.6rem;">Impide aprobar solicitudes de descanso médico si no registran el código oficial emitido por EsSalud.</small>
          </div>
        </div>

        <!-- Bloque 3: Arquitectura Biométrico e Institución -->
        <div class="param-group-box" style="grid-column: 1 / -1;">
          <div class="param-group-title">
            <span>🔌</span> Integración con Reloj Biométrico Institucional
          </div>
          <div class="param-group-desc">
            El Hospital Distrital de Pacasmayo dispone de un biométrico independiente. El sistema procesa horarios e importación desacoplada.
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
            <div class="param-item">
              <label for="paramBiometricoModo">Modalidad de Integración:</label>
              <select id="paramBiometricoModo" class="input">
                <option value="IMPORTACION_INDEPENDIENTE" selected>Importación por Lotes / Lector Independiente</option>
                <option value="SYNC_PROGRAMADA">Sincronización Programada por Cron</option>
                <option value="API_BIOMETRICO">Conexión Directa por Socket/API</option>
              </select>
              <small>Arquitectura diseñada para carga masiva sin alterar el reloj físico existente.</small>
            </div>

            <div class="param-item">
              <label>Entorno de Base de Datos:</label>
              <div style="padding: 0.5rem 0.75rem; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); font-size: 0.84rem;">
                <strong>Servidor SQL Server:</strong> <code><?= htmlspecialchars(Database::getConfig()['host'] . ':' . Database::getConfig()['port']) ?></code> &bull; <code><?= htmlspecialchars(Database::getConfig()['database']) ?></code>
              </div>
              <small>Capa de desacoplamiento institucional con fallback activo.</small>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; border-top: 1px solid var(--border-subtle); padding-top: 1rem; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.78rem; color: var(--text-secondary);" id="paramUltimaActualizacion">
              <!-- Inyectado por JS -->
            </div>
            <button type="submit" class="btn btn-primary">
              <span>💾</span>
              <span>Guardar Políticas y Parámetros</span>
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     MODAL: CREAR NUEVO USUARIO
     ========================================================= -->
<div class="modal-backdrop" id="modalCrearUsuario">
  <div class="modal-dialog" style="max-width: 580px;">
    <div class="modal-header">
      <div class="modal-title">
        <span>👤</span> Crear Nueva Cuenta de Acceso
      </div>
      <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
    </div>
    <form id="formCrearUsuario">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom: 1rem;">
          <label for="modalUserPersonalSelect">Vincular a Servidor Registrado (core.Persona): *</label>
          <select id="modalUserPersonalSelect" class="input" required>
            <option value="">Cargando lista de servidores...</option>
          </select>
          <small style="color: var(--text-tertiary);">Seleccione el trabajador que utilizará esta cuenta de acceso.</small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1rem;">
          <div class="form-group">
            <label for="modalUserUsername">Nombre de Usuario (Login): *</label>
            <input type="text" id="modalUserUsername" class="input" placeholder="ej. cbenites" required>
          </div>

          <div class="form-group">
            <label for="modalUserRolSelect">Rol Institucional (seguridad.Rol): *</label>
            <select id="modalUserRolSelect" class="input" required>
              <option value="3" selected>OPERADOR_ASISTENCIA - Operador</option>
              <option value="2">JEFE_SERVICIO - Jefatura de Servicio</option>
              <option value="4">AUDITOR_PLANILLAS - Auditor Planillas</option>
              <option value="1">ADMIN_RRHH - Administrador RR.HH.</option>
            </select>
          </div>
        </div>

        <!-- Campos de solo lectura autocompletados -->
        <div style="background: var(--bg-surface-soft); padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); margin-bottom: 1rem;">
          <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.5rem;">
            Datos del Servidor Seleccionado:
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; font-size: 0.82rem;">
            <div>
              <span style="color: var(--text-tertiary);">DNI:</span>
              <input type="text" id="modalUserDni" class="input" readonly style="background: transparent; padding: 2px; height: auto; font-weight: 700;">
            </div>
            <div>
              <span style="color: var(--text-tertiary);">Nombre:</span>
              <input type="text" id="modalUserNombre" class="input" readonly style="background: transparent; padding: 2px; height: auto; font-weight: 700;">
            </div>
            <div>
              <span style="color: var(--text-tertiary);">Servicio:</span>
              <input type="text" id="modalUserServicio" class="input" readonly style="background: transparent; padding: 2px; height: auto;">
            </div>
            <div>
              <span style="color: var(--text-tertiary);">Cargo:</span>
              <input type="text" id="modalUserCargo" class="input" readonly style="background: transparent; padding: 2px; height: auto;">
            </div>
          </div>
        </div>

        <div style="font-size: 0.78rem; color: var(--text-secondary); background: rgba(37, 99, 235, 0.05); padding: 0.65rem; border-radius: var(--radius-sm); border-left: 3px solid var(--primary-600);">
          Nota: La contraseña inicial provisoria será generada automáticamente por el sistema como <code>Minsa.2026!</code>. El usuario podrá cambiarla en su panel.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear Cuenta</button>
      </div>
    </form>
  </div>
</div>
