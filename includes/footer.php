<?php
/**
 * Pie de Página Institucional y Modales Globales
 * Hospital Distrital de Pacasmayo
 */
?>
  <!-- Footer Institucional -->
  <footer class="app-footer">
    <div>
      <strong>Hospital Distrital de Pacasmayo</strong> • Sistema de Gestión de Horarios y Asistencia (SGH-RRHH)
    </div>
    <div class="footer-links">
      <span>Versión 1.0 - Sistema Oficial MINSA</span>
      <span>•</span>
      <span>Unidad de Informática y Estadística</span>
    </div>
  </footer>

  <!-- Contenedor de Alertas Toast -->
  <div id="toastContainer" class="toast-container"></div>

  <!-- =========================================================
       MODAL: REGISTRAR NUEVA JUSTIFICACIÓN
       ========================================================= -->
  <div class="modal-backdrop" id="modalJustificacion">
    <div class="modal-dialog">
      <div class="modal-header">
        <div class="modal-title">
          <span>📄</span> Registrar Justificación de Asistencia
        </div>
        <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
      </div>
      <form id="formJustificacion">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label" for="justifWorker">Trabajador *</label>
            <select class="form-select" id="justifWorker" required>
              <option value="">Seleccione personal...</option>
              <option value="45892104" selected>45892104 - Ana María Torres Benites (Enfermera)</option>
              <option value="70445708">70445708 - Lic. Luis Alberto Díaz Rojas (Médico)</option>
              <option value="18942301">18942301 - Carlos Eduardo Vega Morales (Técnico)</option>
              <option value="43920194">43920194 - Elena Beatriz Ramos Quispe (Obstetra)</option>
              <option value="41209845">41209845 - Dra. Rosa Patricia Mendoza Campos (Médico)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="justifType">Tipo de Incidencia / Justificación *</label>
            <select class="form-select" id="justifType" required>
              <option value="">Seleccione tipo de permiso...</option>
              <option value="descanso_medico">Descanso Médico (CITT / Certificado)</option>
              <option value="comision_servicio">Comisión de Servicio Oficial</option>
              <option value="permiso_personal">Permiso Particular / Personal</option>
              <option value="licencia_capacitacion">Licencia por Capacitación</option>
              <option value="omision_marcacion">Omisión de Marcación (Falla Técnica)</option>
              <option value="tolerancia_tardanza">Tardanza Justificada por Urgencia</option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label" for="justifDateStart">Fecha Inicio *</label>
              <input type="date" class="form-control" id="justifDateStart" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="justifDateEnd">Fecha Fin</label>
              <input type="date" class="form-control" id="justifDateEnd" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="justifReason">Motivo o Sustento Administrativo *</label>
            <textarea class="form-control" id="justifReason" placeholder="Describa el motivo o justificación documentada..." required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="justifFile">Documento de Sustento (Opcional - PDF / JPG)</label>
            <input type="file" class="form-control" id="justifFile" accept=".pdf,.jpg,.jpeg,.png">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar Justificación</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       MODAL: BANDEJA COMPLETA DE CASOS PENDIENTES
       ========================================================= -->
  <div class="modal-backdrop" id="modalVerTodos">
    <div class="modal-dialog" style="max-width: 720px;">
      <div class="modal-header">
        <div class="modal-title">
          <span>📋</span> Bandeja de Casos Pendientes de Asistencia
        </div>
        <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
      </div>
      <div class="modal-body">
        <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem;">
          <input type="text" class="form-control" placeholder="Filtrar por nombre, DNI o servicio..." style="flex: 1;">
          <select class="form-select" style="width: 170px;">
            <option value="">Todos los casos</option>
            <option value="tardanza">Tardanzas</option>
            <option value="falta">Faltas sin marcar</option>
            <option value="justificacion">Permisos pendientes</option>
          </select>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          <div class="case-card urgent">
            <div class="case-person-details">
              <div class="case-avatar">AT</div>
              <div class="case-info">
                <h4>Ana María Torres Benites <span class="badge badge-late">Tardanza +24m</span></h4>
                <p>Enfermera • Emergencia • Ingreso 08:24 (Turno 08:00 - 14:00)</p>
              </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="closeModal('modalVerTodos'); openModal('modalJustificacion');">
              Evaluar
            </button>
          </div>

          <div class="case-card info">
            <div class="case-person-details">
              <div class="case-avatar">LD</div>
              <div class="case-info">
                <h4>Lic. Luis Alberto Díaz Rojas <span class="badge badge-justified">Descanso Médico</span></h4>
                <p>Médico • UCI • Solicitud 48h con certificado médico adjunto</p>
              </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="showToast('Expediente de descanso médico abierto para revisión.', 'info');">
              Ver Certificado
            </button>
          </div>

          <div class="case-card danger">
            <div class="case-person-details">
              <div class="case-avatar">CV</div>
              <div class="case-info">
                <h4>Carlos Eduardo Vega Morales <span class="badge badge-absent">Sin Salida</span></h4>
                <p>Técnico • Hospitalización • Marcó ingreso 20:01, sin egreso a las 08:00</p>
              </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="showToast('Abriendo corrector de marcación para Carlos Vega.', 'info');">
              Corregir
            </button>
          </div>

          <div class="case-card info">
            <div class="case-person-details">
              <div class="case-avatar">ER</div>
              <div class="case-info">
                <h4>Elena Beatriz Ramos Quispe <span class="badge badge-justified">Comisión</span></h4>
                <p>Obstetra • Centro Obstétrico • Capacitación Red Chepén</p>
              </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="showToast('Comisión aprobada para Elena Ramos.', 'success');">
              Aprobar
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       MODAL: DETALLE DE MÉTRICA / KPI
       ========================================================= -->
  <div class="modal-backdrop" id="modalKpiDetail">
    <div class="modal-dialog">
      <div class="modal-header">
        <div class="modal-title" id="kpiModalTitle">
          <span>📊</span> Detalle de la Métrica
        </div>
        <button type="button" class="modal-close" aria-label="Cerrar modal">&times;</button>
      </div>
      <div class="modal-body" id="kpiModalBody">
        <!-- Contenido dinámico inyectado por JS -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- Scripts Globales -->
  <script src="assets/js/api.js?v=<?= filemtime(__DIR__ . '/../assets/js/api.js') ?>"></script>
  <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>

  <!-- Script Específico de Página si aplica -->
  <?php if (isset($pageScript) && file_exists(__DIR__ . '/../assets/js/pages/' . $pageScript)): ?>
    <script src="assets/js/pages/<?= htmlspecialchars($pageScript) ?>?v=<?= filemtime(__DIR__ . '/../assets/js/pages/' . $pageScript) ?>"></script>
  <?php endif; ?>
</body>
</html>
