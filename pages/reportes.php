<?php
/**
 * Módulo: Reportes Oficiales de Recursos Humanos (ETAPA 7)
 * Hospital Distrital de Pacasmayo
 */
$pageTitle = "Reportes y Estadísticas - Hospital Pacasmayo";
$pageScript = "reportes.js";
?>

<!-- Encabezado Oficial Institucional para Impresión -->
<div class="print-official-header">
  <div style="font-size: 10pt; color: #555;">GOBIERNO REGIONAL LA LIBERTAD &bull; RED DE SALUD PACASMAYO</div>
  <h1>HOSPITAL DISTRITAL DE PACASMAYO</h1>
  <h2>SISTEMA DE GESTIÓN DE RECURSOS HUMANOS - REPORTES OFICIALES</h2>
  <div style="font-size: 8.5pt; color: #666; margin-top: 4px;">
    Control de Asistencia, Rol Mensual y Cumplimiento Normativo MINSA (Regla 150h)
  </div>
</div>

<!-- Barra de Pestañas de Navegación de Reportes -->
<div class="report-tabs-nav">
  <button type="button" class="report-tab-btn active" data-tab="individual">
    <span>👤</span> Récord Individual Mensual
  </button>
  <button type="button" class="report-tab-btn" data-tab="consolidado">
    <span>📋</span> Consolidado para Planillas
  </button>
  <button type="button" class="report-tab-btn" data-tab="seti">
    <span>🏥</span> Reporte Oficial SETI (150h Asistencial)
  </button>
  <button type="button" class="report-tab-btn" data-tab="rol">
    <span>📅</span> Rol Mensual Oficial (Matriz MINSA)
  </button>
</div>

<!-- Barra de Parámetros y Filtros Comunes -->
<div class="report-toolbar">
  <div class="report-filters-group">
    <!-- Selector de Mes -->
    <div style="display: flex; align-items: center; gap: 0.35rem;">
      <label for="repMesSelect" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Mes:</label>
      <select id="repMesSelect" class="input" style="width: auto;">
        <option value="1">Enero</option>
        <option value="2">Febrero</option>
        <option value="3">Marzo</option>
        <option value="4">Abril</option>
        <option value="5">Mayo</option>
        <option value="6">Junio</option>
        <option value="7">Julio</option>
        <option value="8">Agosto</option>
        <option value="9" selected>Setiembre</option>
        <option value="10">Octubre</option>
        <option value="11">Noviembre</option>
        <option value="12">Diciembre</option>
      </select>
    </div>

    <!-- Selector de Año -->
    <div style="display: flex; align-items: center; gap: 0.35rem;">
      <label for="repAnioSelect" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Año:</label>
      <select id="repAnioSelect" class="input" style="width: auto;">
        <option value="2025">2025</option>
        <option value="2026" selected>2026</option>
      </select>
    </div>

    <!-- Selector de Servicio -->
    <div style="display: flex; align-items: center; gap: 0.35rem;">
      <label for="repServicioSelect" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Servicio:</label>
      <select id="repServicioSelect" class="input" style="width: auto; min-width: 220px;">
        <option value="" selected>Todos los Servicios (Plantel Completo - Médicos y Asistenciales)</option>
        <option value="7">Emergencia y Cuidados Críticos</option>
        <option value="8">Unidad de Cuidados Intensivos (UCI)</option>
        <option value="9">Hospitalización Medicina</option>
        <option value="10">Consulta Externa</option>
        <option value="11">Pediatría y Neonatología</option>
        <option value="12">Centro Obstétrico</option>
        <option value="13">Centro Quirúrgico</option>
        <option value="14">Laboratorio Clínico</option>
        <option value="15">Farmacia y Dosis Unitaria</option>
        <option value="16">Unidad de Personal / RR.HH.</option>
        <option value="17">Unidad de Informática y Estadística</option>
      </select>
    </div>

    <!-- Selector de Régimen / Tipo de Personal -->
    <div id="groupFiltroRegimen" style="display: flex; align-items: center; gap: 0.35rem;">
      <label for="repRegimenSelect" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Régimen / Tipo:</label>
      <select id="repRegimenSelect" class="input" style="width: auto;">
        <option value="" selected>Todos (Médicos, Asistenciales y Administrativos)</option>
        <option value="ASISTENCIAL">Personal Asistencial (Médicos, Enfermería, Técnicos)</option>
        <option value="ADMINISTRATIVO">Personal Administrativo (Oficina y Gestión)</option>
      </select>
    </div>

    <!-- Selector de Condición (solo en consolidado) -->
    <div id="groupFiltroCondicion" style="display: none; align-items: center; gap: 0.35rem;">
      <label for="repCondicionSelect" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Condición:</label>
      <select id="repCondicionSelect" class="input" style="width: auto;">
        <option value="">Todas</option>
        <option value="NOMBRADO">Nombrado (DL 276)</option>
        <option value="CAS">CAS (DL 1057)</option>
      </select>
    </div>

    <!-- Selector de Trabajador (para récord individual) -->
    <div id="groupFiltroPersonal" style="display: flex; align-items: center; gap: 0.35rem;">
      <label for="repPersonalSelect" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Trabajador:</label>
      <select id="repPersonalSelect" class="input" style="width: auto; min-width: 260px;">
        <option value="">Cargando personal institucional...</option>
      </select>
    </div>
  </div>

  <!-- Botones de Acción -->
  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <button type="button" class="btn btn-secondary" id="btnGenerarReporte">
      <span>🔍</span> Actualizar
    </button>
    <button type="button" class="btn btn-primary" id="btnExportarExcel" title="Descargar en formato Excel oficial de MINSA">
      <span>📥</span> Exportar a Excel (Formato MINSA)
    </button>
    <button type="button" class="btn btn-primary" id="btnExportarPdfFicha" style="background-color: #dc2626; border-color: #dc2626;" title="Exportar o Guardar como PDF la Ficha Oficial">
      <span>📄</span> Exportar Ficha a PDF
    </button>
    <button type="button" class="btn btn-secondary" id="btnImprimirReporte" title="Imprimir la Ficha Oficial">
      <span>🖨</span> Imprimir Ficha
    </button>
  </div>
</div>

<!-- ========================================================================= -->
<!-- PANELES DE CONTENIDO POR PESTAÑA                                          -->
<!-- ========================================================================= -->

<!-- PANEL 1: RÉCORD INDIVIDUAL -->
<div class="tab-pane active" id="pane-individual">
  <div id="recordIndividualContainer">
    <!-- Inyectado por JS -->
  </div>
</div>

<!-- PANEL 2: CONSOLIDADO DE PLANILLAS -->
<div class="tab-pane" id="pane-consolidado">
  <div id="consolidadoPlanillasContainer">
    <!-- Inyectado por JS -->
  </div>
</div>

<!-- PANEL 3: REPORTE OFICIAL SETI -->
<div class="tab-pane" id="pane-seti">
  <div id="reporteSetiContainer">
    <!-- Inyectado por JS -->
  </div>
</div>

<!-- PANEL 4: ROL MENSUAL OFICIAL (FORMATO MINSA) -->
<div class="tab-pane" id="pane-rol">
  <div id="rolMensualContainer">
    <!-- Inyectado por JS -->
  </div>
</div>

<!-- Firmas Oficiales de Auditoría para Impresión -->
<div class="print-signatures-row">
  <div class="signature-box">
    <strong>Elaborado por</strong><br>
    Control de Asistencia y Turnos<br>
    Hospital Distrital de Pacasmayo
  </div>
  <div class="signature-box">
    <strong>Revisado por</strong><br>
    Jefatura de Recursos Humanos<br>
    Hospital Distrital de Pacasmayo
  </div>
  <div class="signature-box">
    <strong>V°B° Dirección Ejecutiva</strong><br>
    Hospital Distrital de Pacasmayo<br>
    Red de Salud Pacasmayo
  </div>
</div>
