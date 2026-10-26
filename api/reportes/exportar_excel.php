<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('reportes', ['GET'], []);

/**
 * API Endpoint: /api/reportes/exportar_excel.php
 * Hospital Distrital de Pacasmayo - ETAPA 7
 * 
 * Genera y descarga el archivo Excel (.xls) con el formato oficial institucional MINSA:
 * 1. ROL MENSUAL DE TURNOS (Formato idéntico al rol oficial del Hospital Pacasmayo):
 *    - Membrete Oficial MINSA: Gobierno Regional La Libertad / Gerencia General de Salud / Red Integrada de Salud / Distrital Pacasmayo - MINSA
 *    - Título del Área/Servicio y Periodo (Mes y Año)
 *    - Indicador de Categoría (Administrativos / Asistenciales)
 *    - Bloques por Sub-área (Jefatura de Información, Informática, Call Center, etc.)
 *    - Cabecera doble: DÍAS con letras (L, M, X, J, V, S, D) y NOMBRE COMPLETO con números de día (1..30/31)
 *    - Fines de semana (Sábado y Domingo) sombreados en gris oscuro #BFBFBF
 *    - Celdas de turnos en verde suave #E2EFDA (código 1, GD, M, etc.)
 *    - Destacado de turnos especiales en naranja #FFC000
 *    - Feriados nacionales con F en rojo #C00000
 *    - Cuadro oficial de Leyenda al pie
 * 
 * 2. FICHA INDIVIDUAL DE ASISTENCIA:
 *    - Membrete, Datos del servidor, Resumen de horas/tardanzas/faltas, Detalle diario 1..30 y firmas
 * 
 * 3. CONSOLIDADO PARA PLANILLAS:
 *    - Membrete, Nómina de trabajadores con tardanzas, faltas, permisos, CITT y horas netas
 * 
 * 4. REPORTE OFICIAL SETI:
 *    - Membrete, Nombrados 276 y CAS 1057 con cómputo de 150h y firmas
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/SnapshotStore.php';

$tipo       = $_GET['tipo'] ?? 'rol'; // 'rol', 'individual', 'consolidado', 'seti'
$mes        = max(1, min(12, intval($_GET['mes'] ?? 9)));
$anio       = max(2020, min(2035, intval($_GET['anio'] ?? 2026)));
$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : 0;
$allowedServiceId = Auth::getAllowedServiceId();
if ($allowedServiceId !== null) {
    $servicioId = $allowedServiceId;
    $_GET['servicio_id'] = $servicioId;
} elseif ($servicioId > 0) {
    apiRequireServiceAccess($servicioId);
}
$regimen    = trim($_GET['regimen'] ?? '');
$personalId = intval($_GET['personal_id'] ?? 1);

if ($tipo === 'individual' && $personalId > 0) {
    apiRequirePersonalAccess($personalId);
}

$nombresMeses = [
    1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
    5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
    9 => 'SETIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
];
$nombreMes = $nombresMeses[$mes] ?? 'JUNIO';
$totalDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

// Letras de días en español abreviado (Miércoles = 'X' según norma MINSA)
$letrasDiasSemana = [
    0 => 'D', 1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S'
];

/**
 * Normaliza turnos al estándar MINSA oficial del hospital (1, 2, 3, 1/2...)
 */
function normalizarTurnoAMatrizExcel($cod) {
    if (empty($cod) || $cod === '0' || $cod === 'LIB' || $cod === 'DESC') return 'LIB';
    $c = strtoupper(trim($cod));
    if ($c === 'M' || $c === 'ADM' || $c === '1') return '1';
    if ($c === 'T' || $c === '2') return '2';
    if ($c === 'N' || $c === 'GN' || $c === '3') return '3';
    if ($c === 'GD' || $c === '1/2') return '1/2';
    if ($c === '2/3') return '2/3';
    if ($c === 'G24' || $c === '1/2/3') return '1/2/3';
    if ($c === 'VAC') return 'V';
    if ($c === 'VAC-1') return 'V1';
    if ($c === 'F') return 'F';
    if ($c === 'C' || $c === 'CITT') return 'C';
    if ($c === 'P' || $c === 'PER') return 'P';
    return $c;
}

// Feriados nacionales peruanos reconocidos
$feriadosPeruanos = [
    '01-01' => 'Año Nuevo',
    '05-01' => 'Día del Trabajo',
    '06-07' => 'Día de la Bandera',
    '06-29' => 'San Pedro y San Pablo',
    '07-23' => 'Día FAP',
    '07-28' => 'Fiestas Patrias',
    '07-29' => 'Fiestas Patrias',
    '08-06' => 'Batalla de Junín',
    '08-30' => 'Santa Rosa de Lima',
    '10-08' => 'Combate de Angamos',
    '11-01' => 'Todos los Santos',
    '12-08' => 'Inmaculada Concepción',
    '12-09' => 'Batalla de Ayacucho',
    '12-25' => 'Navidad'
];

$serviciosNombres = [
    0  => 'PLANTEL COMPLETO DE SERVICIOS HOSPITALARIOS',
    7  => 'EMERGENCIA Y CUIDADOS CRÍTICOS',
    8  => 'UNIDAD DE CUIDADOS INTENSIVOS (UCI)',
    9  => 'HOSPITALIZACIÓN MEDICINA',
    10 => 'CONSULTA EXTERNA',
    11 => 'PEDIATRÍA Y NEONATOLOGÍA',
    12 => 'CENTRO OBSTÉTRICO',
    13 => 'CENTRO QUIRÚRGICO',
    14 => 'LABORATORIO CLÍNICO',
    15 => 'FARMACIA Y DOSIS UNITARIA',
    16 => 'UNIDAD DE PERSONAL / RR.HH.',
    17 => 'UNIDAD DE INFORMÁTICA Y ESTADÍSTICA'
];
$areaNombre = $serviciosNombres[$servicioId] ?? 'SERVICIOS HOSPITALARIOS';

if ($regimen !== '') {
    $isAsistencial = (strtoupper($regimen) === 'ASISTENCIAL');
    $categoriaNombre = $isAsistencial ? 'PERSONAL ASISTENCIAL (MÉDICOS, ENFERMERÍA, TÉCNICOS)' : 'PERSONAL ADMINISTRATIVO (OFICINA Y GESTIÓN)';
} else {
    $isAsistencial = ($servicioId === 0 || in_array($servicioId, [7, 8, 9, 10, 11, 12, 13, 14, 15]));
    $categoriaNombre = ($servicioId === 0) ? 'PLANTEL COMPLETO (ASISTENCIALES Y ADMINISTRATIVOS)' : ($isAsistencial ? 'PERSONAL ASISTENCIAL' : 'PERSONAL ADMINISTRATIVO');
}

// Definir nombre de archivo
$filename = "rol_programacion_{$mes}_{$anio}.xls";
if ($tipo === 'individual') {
    $filename = "ficha_individual_{$personalId}_{$mes}_{$anio}.xls";
} elseif ($tipo === 'consolidado') {
    $filename = "consolidado_planillas_{$mes}_{$anio}.xls";
} elseif ($tipo === 'seti') {
    $filename = "reporte_seti_{$mes}_{$anio}.xls";
}

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name><?= ($tipo === 'rol' ? "Rol $nombreMes $anio" : "Reporte MINSA") ?></x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
     <x:Print>
       <x:ValidPrinterInfo/>
       <x:Orientation><?= ($tipo === 'individual' ? 'Portrait' : 'Landscape') ?></x:Orientation>
     </x:Print>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  body, table {
    font-family: Arial, Calibri, sans-serif;
    font-size: 10pt;
  }
  .title-header {
    text-align: center;
    font-size: 11pt;
    font-weight: bold;
    color: #1a202c;
  }
  .area-title {
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
    text-decoration: underline;
  }
  .period-title {
    text-align: center;
    font-size: 12pt;
    font-weight: bold;
  }
  .category-title {
    font-size: 11pt;
    font-weight: bold;
    text-align: left;
    padding-bottom: 6px;
  }
  .subarea-header {
    background-color: #D9E1F2;
    color: #000000;
    font-weight: bold;
    text-align: center;
    border: 1px solid #000000;
    font-size: 10pt;
    padding: 4px;
  }
  .th-dias {
    background-color: #D9D9D9;
    color: #000000;
    font-weight: bold;
    text-align: center;
    border: 1px solid #000000;
    font-size: 9.5pt;
  }
  .th-weekend {
    background-color: #BFBFBF;
    color: #000000;
    font-weight: bold;
    text-align: center;
    border: 1px solid #000000;
    font-size: 9.5pt;
  }
  .th-nombre {
    background-color: #D9D9D9;
    color: #000000;
    font-weight: bold;
    text-align: center;
    border: 1px solid #000000;
    width: 250px;
    font-size: 9.5pt;
  }
  .td-worker-name {
    background-color: #E2EFDA;
    color: #000000;
    font-weight: bold;
    text-align: left;
    border: 1px solid #000000;
    padding-left: 6px;
    white-space: nowrap;
    font-size: 9.5pt;
  }
  .td-shift-cell {
    text-align: center;
    font-weight: bold;
    border: 1px solid #000000;
    background-color: #E2EFDA;
    color: #000000;
    mso-number-format: "\@";
  }
  .td-shift-special {
    text-align: center;
    font-weight: bold;
    border: 1px solid #000000;
    background-color: #FFC000;
    color: #000000;
    mso-number-format: "\@";
  }
  .td-shift-weekend {
    text-align: center;
    border: 1px solid #000000;
    background-color: #D9D9D9;
    color: #555555;
    mso-number-format: "\@";
  }
  .td-feriado {
    text-align: center;
    font-weight: bold;
    border: 1px solid #000000;
    background-color: #E2EFDA;
    color: #C00000;
    mso-number-format: "\@";
  }
  .leyenda-table {
    border-collapse: collapse;
    margin-top: 15px;
    border: 1px solid #000000;
  }
  .leyenda-header {
    background-color: #F2F2F2;
    font-weight: bold;
    border: 1px solid #000000;
    padding: 3px 6px;
  }
  .leyenda-cell {
    border: 1px solid #000000;
    padding: 3px 6px;
    font-size: 9pt;
  }
  /* Estilos para Fichas y Tablas Generales */
  .table-clean {
    width: 100%;
    border-collapse: collapse;
  }
  .table-clean th {
    background-color: #D9E1F2;
    border: 1px solid #000000;
    font-weight: bold;
    padding: 5px;
    text-align: center;
    font-size: 9.5pt;
  }
  .table-clean td {
    border: 1px solid #000000;
    padding: 4px 6px;
    font-size: 9pt;
  }
  .info-box td {
    border: 1px solid #cccccc;
    padding: 4px 8px;
    font-size: 9.5pt;
  }
  .info-lbl {
    background-color: #F2F2F2;
    font-weight: bold;
    width: 20%;
  }
  .sig-row td {
    border-top: 1px solid #000000;
    text-align: center;
    font-size: 9pt;
    padding-top: 5px;
  }
</style>
</head>
<body>

<?php
// =========================================================================
// CASO 1: ROL MENSUAL OFICIAL (FORMATO EXACTO DEL HOSPITAL / MINSA)
// =========================================================================
if ($tipo === 'rol'):
    $colspanTotal = $totalDias + 1;

    // Cargar personal desde el snapshot oficial de programación
    $personalServicio = [];
    $snapshotKey = "programacion_serv_{$servicioId}_{$anio}_{$mes}";
    if (SnapshotStore::has($snapshotKey)) {
        $snap = SnapshotStore::get($snapshotKey);
        if (!empty($snap['data']['personal_servicio'])) {
            $personalServicio = $snap['data']['personal_servicio'];
        }
    }

    if (empty($personalServicio) && SnapshotStore::has("programacion_serv_0_{$anio}_{$mes}")) {
        $snap = SnapshotStore::get("programacion_serv_0_{$anio}_{$mes}");
        if (!empty($snap['data']['personal_servicio'])) {
            $allStaff = $snap['data']['personal_servicio'];
            if ($servicioId > 0) {
                $personalServicio = array_values(array_filter($allStaff, function($p) use ($servicioId) {
                    return intval($p['servicio_id'] ?? 0) === $servicioId;
                }));
            } else {
                $personalServicio = $allStaff;
            }
        }
    }

    // Filtrar por régimen si se solicitó
    if ($regimen !== '') {
        $personalServicio = array_values(array_filter($personalServicio, function($p) use ($regimen) {
            return strtoupper($p['regimen_laboral'] ?? '') === strtoupper($regimen);
        }));
    }

    // Agrupar en secciones organizadas
    $secciones = [];
    if (!empty($personalServicio)) {
        $grupos = [];
        $esTodos = ($servicioId === 0);
        foreach ($personalServicio as $p) {
            $grupoTitulo = '';
            if ($esTodos && empty($regimen)) {
                $grupoTitulo = strtoupper($p['servicio_nombre'] ?? 'SERVICIO GENERAL');
            } else {
                $cargo = strtolower($p['cargo_nombre'] ?? '');
                $reg = strtoupper($p['regimen_laboral'] ?? '');
                if (str_contains($cargo, 'médic') || str_contains($cargo, 'ciruj') || str_contains($cargo, 'pediatr') || str_contains($cargo, 'intensiv')) {
                    $grupoTitulo = 'CUERPO MÉDICO Y ESPECIALISTAS';
                } elseif (str_contains($cargo, 'enferm') || str_contains($cargo, 'obstetr') || str_contains($cargo, 'tecnólog')) {
                    $grupoTitulo = 'PROFESIONALES DE ENFERMERÍA Y OBSTETRICIA';
                } elseif (str_contains($cargo, 'técnic') || str_contains($cargo, 'farmac')) {
                    $grupoTitulo = 'PERSONAL TÉCNICO Y ASISTENCIAL';
                } elseif ($reg === 'ADMINISTRATIVO' || str_contains($cargo, 'admin') || str_contains($cargo, 'informát') || str_contains($cargo, 'jefe')) {
                    $grupoTitulo = 'PERSONAL ADMINISTRATIVO Y GESTIÓN';
                } else {
                    $grupoTitulo = 'PERSONAL ASISTENCIAL DE APOYO';
                }
            }

            if (!isset($grupos[$grupoTitulo])) {
                $grupos[$grupoTitulo] = [];
            }
            $grupos[$grupoTitulo][] = [
                'nombre'    => strtoupper($p['nombre_completo'] ?? ''),
                'condicion' => (str_contains($p['condicion_laboral'] ?? '', '276') || str_contains($p['condicion_laboral'] ?? '', 'NOMBRADO')) ? 'NOMBRADO' : 'CAS',
                'patron'    => $p['patron'] ?? [],
                'turno'     => !empty($p['patron'][0]) ? $p['patron'][0] : 'M'
            ];
        }

        foreach ($grupos as $gNom => $pList) {
            $secciones[] = [
                'subarea'  => $gNom,
                'personal' => $pList
            ];
        }
    }

    if (empty($secciones)) {
        $secciones = [
            [
                'subarea' => 'PERSONAL PROGRAMADO',
                'personal' => []
            ]
        ];
    }
?>
<table border="0" cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
  <!-- Membrete Institucional Oficial -->
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="title-header">GOBIERNO REGIONAL LA LIBERTAD</td>
  </tr>
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="title-header">GERENCIA GENERAL DE SALUD</td>
  </tr>
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="title-header">RED INTEGRADA DE SALUD</td>
  </tr>
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="title-header">DISTRITAL PACASMAYO - MINSA</td>
  </tr>
  <tr><td colspan="<?= $colspanTotal ?>" style="height: 12px;"></td></tr>

  <!-- Título del Área y Mes -->
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="area-title"><?= htmlspecialchars($areaNombre) ?></td>
  </tr>
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="period-title"><?= $nombreMes ?> <?= $anio ?></td>
  </tr>
  <tr><td colspan="<?= $colspanTotal ?>" style="height: 12px;"></td></tr>

  <!-- Indicador de Categoría -->
  <tr>
    <td colspan="<?= $colspanTotal ?>" class="category-title">
      <?= $categoriaNombre ?>
    </td>
  </tr>

  <?php foreach ($secciones as $sec): ?>
    <!-- Barra de Sub-área -->
    <tr><td colspan="<?= $colspanTotal ?>" style="height: 8px;"></td></tr>
    <tr>
      <td colspan="<?= $colspanTotal ?>" class="subarea-header">
        <?= htmlspecialchars($sec['subarea']) ?>
      </td>
    </tr>

    <!-- Fila 1: DÍAS con letras (L, M, X, J, V, S, D) -->
    <tr>
      <td class="th-dias">DÍAS</td>
      <?php for ($d = 1; $d <= $totalDias; $d++): 
        $timestamp = strtotime(sprintf('%04d-%02d-%02d', $anio, $mes, $d));
        $diaSemNum = intval(date('w', $timestamp));
        $letra = $letrasDiasSemana[$diaSemNum];
        $isWeekend = ($diaSemNum === 0 || $diaSemNum === 6);
      ?>
        <td class="<?= $isWeekend ? 'th-weekend' : 'th-dias' ?>" style="width: 28px;">
          <?= $letra ?>
        </td>
      <?php endfor; ?>
    </tr>

    <!-- Fila 2: NOMBRE COMPLETO con números de día (1 al 30/31) -->
    <tr>
      <td class="th-nombre">NOMBRE COMPLETO</td>
      <?php for ($d = 1; $d <= $totalDias; $d++): 
        $timestamp = strtotime(sprintf('%04d-%02d-%02d', $anio, $mes, $d));
        $diaSemNum = intval(date('w', $timestamp));
        $isWeekend = ($diaSemNum === 0 || $diaSemNum === 6);
      ?>
        <td class="<?= $isWeekend ? 'th-weekend' : 'th-dias' ?>">
          <?= $d ?>
        </td>
      <?php endfor; ?>
    </tr>

    <!-- Filas de Personal -->
    <?php foreach ($sec['personal'] as $pIdx => $p): 
      $pName = $p['nombre'];
      $turnoBase = $p['turno'];
    ?>
      <tr>
        <td class="td-worker-name"><?= htmlspecialchars($pName) ?></td>
        <?php for ($d = 1; $d <= $totalDias; $d++): 
          $fechaStr = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
          $timestamp = strtotime($fechaStr);
          $diaSemNum = intval(date('w', $timestamp));
          $isWeekend = ($diaSemNum === 0 || $diaSemNum === 6);
          $mesDiaKey = sprintf('%02d-%02d', $mes, $d);
          $isFeriado = isset($feriadosPeruanos[$mesDiaKey]);
          $rawTurno = !empty($p['patron'][$d - 1]) ? $p['patron'][$d - 1] : ($isWeekend ? 'LIB' : $turnoBase);
          $turnoDia = normalizarTurnoAMatrizExcel($rawTurno);

          if ($isFeriado) {
            echo '<td class="td-feriado">F</td>';
          } elseif ($turnoDia === 'LIB' || $turnoDia === '0') {
            echo '<td class="td-shift-weekend"></td>';
          } else {
            $isSpecial = in_array($turnoDia, ['1/2', '2/3', '1/2/3', 'V1']);
            $cellClass = $isSpecial ? 'td-shift-special' : 'td-shift-cell';
            echo "<td class=\"$cellClass\">" . htmlspecialchars($turnoDia) . "</td>";
          }
        endfor; ?>
      </tr>
    <?php endforeach; ?>

  <?php endforeach; ?>

</table>

<!-- Tabla de Leyenda Oficial MINSA -->
<table class="leyenda-table" border="1" cellpadding="2" cellspacing="0" style="margin-top: 20px;">
  <tr>
    <td class="leyenda-header" colspan="2">LEYENDA OFICIAL HOSPITAL PACASMAYO (FORMATO 1, 2, 3):</td>
  </tr>
  <tr>
    <td class="leyenda-cell" style="font-weight: bold; width: 180px;">1</td>
    <td class="leyenda-cell" style="font-weight: bold;">TURNO DÍA / MAÑANA (07:00 / 08:00 - 14:00, 6h/8h)</td>
  </tr>
  <tr>
    <td class="leyenda-cell" style="font-weight: bold;">2</td>
    <td class="leyenda-cell" style="font-weight: bold;">TURNO TARDE (14:00 - 20:00, 6h)</td>
  </tr>
  <tr>
    <td class="leyenda-cell" style="font-weight: bold;">3</td>
    <td class="leyenda-cell" style="font-weight: bold;">TURNO NOCHE / GUARDIA NOCTURNA (20:00 - 08:00, 12h)</td>
  </tr>
  <tr>
    <td class="leyenda-cell" style="font-weight: bold;">1/2</td>
    <td class="leyenda-cell" style="font-weight: bold;">DOBLE TURNO M+T / GUARDIA DIURNA (08:00 - 20:00, 12h)</td>
  </tr>
  <tr>
    <td class="leyenda-cell" style="font-weight: bold;">2/3</td>
    <td class="leyenda-cell" style="font-weight: bold;">TURNO TARDE + NOCHE (14:00 - 08:00, 18h)</td>
  </tr>
  <tr>
    <td class="leyenda-cell" style="font-weight: bold;">1/2/3</td>
    <td class="leyenda-cell" style="font-weight: bold;">GUARDIA COMPLETA 24 HORAS (08:00 - 08:00)</td>
  </tr>
  <tr>
    <td class="leyenda-cell" colspan="2" style="font-size: 8.5pt;">
      <strong>F = FERIADO</strong> &bull; <strong>V = VACACIONES</strong> &bull; <strong>C = CITT / MÉDICO</strong> &bull; <strong>LIB = LIBRE / DESCANSO</strong>
    </td>
  </tr>
</table>

<?php
// =========================================================================
// CASO 2: FICHA INDIVIDUAL DE ASISTENCIA (EXCEL)
// =========================================================================
elseif ($tipo === 'individual'):
    $_GET['personal_id'] = $personalId;
    $_GET['mes'] = $mes;
    $_GET['anio'] = $anio;
    ob_start();
    require __DIR__ . '/individual.php';
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
    $t = $data['trabajador'] ?? [];
    $tot = $data['totales'] ?? [];
    $dias = $data['dias'] ?? [];
?>
<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
  <tr><td colspan="9" class="title-header">GOBIERNO REGIONAL LA LIBERTAD &bull; RED INTEGRADA DE SALUD PACASMAYO</td></tr>
  <tr><td colspan="9" class="title-header">HOSPITAL DISTRITAL DE PACASMAYO</td></tr>
  <tr><td colspan="9" style="text-align: center; font-size: 11pt; font-weight: bold;">UNIDAD DE RECURSOS HUMANOS - CONTROL DE ASISTENCIA</td></tr>
  <tr><td colspan="9" style="height: 10px;"></td></tr>
  <tr><td colspan="9" class="area-title">FICHA INDIVIDUAL DE ASISTENCIA Y CONTROL HORARIO</td></tr>
  <tr><td colspan="9" class="period-title"><?= $nombreMes ?> <?= $anio ?></td></tr>
  <tr><td colspan="9" style="height: 12px;"></td></tr>
</table>

<table class="info-box" border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
  <tr>
    <td class="info-lbl">TRABAJADOR:</td>
    <td colspan="3" style="font-weight: bold;"><?= htmlspecialchars($t['nombre_completo'] ?? '') ?></td>
  </tr>
  <tr>
    <td class="info-lbl">D.N.I.:</td>
    <td><strong><?= htmlspecialchars($t['dni'] ?? '') ?></strong></td>
    <td class="info-lbl">CARGO:</td>
    <td><?= htmlspecialchars($t['cargo'] ?? '') ?></td>
  </tr>
  <tr>
    <td class="info-lbl">SERVICIO / ÁREA:</td>
    <td><?= htmlspecialchars($t['servicio_nombre'] ?? '') ?></td>
    <td class="info-lbl">CONDICIÓN:</td>
    <td><?= htmlspecialchars($t['condicion_laboral'] ?? '') ?> &bull; <?= htmlspecialchars($t['regimen_laboral'] ?? '') ?></td>
  </tr>
</table>

<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 15px; text-align: center;">
  <tr style="background-color: #F2F2F2; font-weight: bold;">
    <td>Horas Programadas</td>
    <td>Horas Efectivas</td>
    <td>Tardanza Total</td>
    <td>Días Laborados</td>
    <td>Inasistencias</td>
    <td>Permisos / CITT</td>
  </tr>
  <tr>
    <td style="font-weight: bold;"><?= $tot['horas_programadas'] ?? 0 ?> hrs</td>
    <td style="font-weight: bold; color: #15803d;"><?= $tot['horas_asistidas'] ?? 0 ?> hrs</td>
    <td style="font-weight: bold; color: #b45309;"><?= $tot['minutos_tardanza'] ?? 0 ?> min</td>
    <td><?= $tot['dias_laborados'] ?? 0 ?></td>
    <td style="font-weight: bold; color: #b91c1c;"><?= $tot['total_faltas'] ?? 0 ?></td>
    <td><?= $tot['total_permisos'] ?? 0 ?></td>
  </tr>
</table>

<table class="table-clean" border="1" cellpadding="3" cellspacing="0">
  <thead>
    <tr>
      <th style="width: 35px;">Día</th>
      <th style="width: 75px;">Fecha</th>
      <th style="width: 50px;">Turno</th>
      <th style="width: 110px;">Horario Prog.</th>
      <th style="width: 65px;">Entrada</th>
      <th style="width: 65px;">Salida</th>
      <th style="width: 60px;">Tardanza</th>
      <th style="width: 80px;">Estado</th>
      <th>Observaciones / Sustento</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($dias as $d): ?>
      <tr style="<?= $d['es_fin_de_semana'] ? 'background-color: #f7fafc;' : '' ?>">
        <td style="text-align: center; font-weight: bold;"><?= $d['dia'] ?></td>
        <td style="text-align: center;"><?= htmlspecialchars($d['dia_semana']) ?> <?= substr($d['fecha'], 8, 2) ?></td>
        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($d['turno_codigo']) ?></td>
        <td style="text-align: center;"><?= $d['hora_prog_inicio'] ? "{$d['hora_prog_inicio']} - {$d['hora_prog_fin']}" : '--' ?></td>
        <td style="text-align: center; font-weight: bold;"><?= $d['hora_entrada'] ?? '--' ?></td>
        <td style="text-align: center;"><?= $d['hora_salida'] ?? '--' ?></td>
        <td style="text-align: center;"><?= $d['minutos_tardanza'] > 0 ? "+{$d['minutos_tardanza']}m" : '0m' ?></td>
        <td style="text-align: center;"><?= htmlspecialchars($d['estado']) ?></td>
        <td><?= htmlspecialchars($d['observacion'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table border="0" style="width: 100%; margin-top: 35px; border-collapse: collapse;">
  <tr class="sig-row">
    <td style="width: 30%;"><strong>Firma del Servidor</strong><br>DNI: <?= htmlspecialchars($t['dni'] ?? '') ?></td>
    <td style="width: 5%;"></td>
    <td style="width: 30%;"><strong>Control de Asistencia</strong><br>Recursos Humanos</td>
    <td style="width: 5%;"></td>
    <td style="width: 30%;"><strong>V°B° Jefatura de RR.HH.</strong><br>Hospital Distrital de Pacasmayo</td>
  </tr>
</table>

<?php
// =========================================================================
// CASO 3: CONSOLIDADO PARA PLANILLAS (EXCEL)
// =========================================================================
elseif ($tipo === 'consolidado'):
    $_GET['mes'] = $mes;
    $_GET['anio'] = $anio;
    ob_start();
    require __DIR__ . '/consolidado.php';
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
    $filas = $data['filas'] ?? [];
    $resumen = $data['resumen'] ?? [];
?>
<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
  <tr><td colspan="12" class="title-header">GOBIERNO REGIONAL LA LIBERTAD &bull; RED INTEGRADA DE SALUD PACASMAYO</td></tr>
  <tr><td colspan="12" class="title-header">HOSPITAL DISTRITAL DE PACASMAYO</td></tr>
  <tr><td colspan="12" style="text-align: center; font-size: 10.5pt; font-weight: bold;">UNIDAD DE RECURSOS HUMANOS - INSUMO OFICIAL PARA PLANILLAS</td></tr>
  <tr><td colspan="12" style="height: 10px;"></td></tr>
  <tr><td colspan="12" class="area-title">CONSOLIDADO MENSUAL DE ASISTENCIA Y TARDANZAS</td></tr>
  <tr><td colspan="12" class="period-title"><?= $nombreMes ?> <?= $anio ?></td></tr>
  <tr><td colspan="12" style="height: 12px;"></td></tr>
</table>

<table class="table-clean" border="1" cellpadding="3" cellspacing="0">
  <thead>
    <tr>
      <th>DNI</th>
      <th>Apellidos y Nombres</th>
      <th>Servicio</th>
      <th>Condición</th>
      <th>Días Prog.</th>
      <th>Días Lab.</th>
      <th>Tardanza Total</th>
      <th>Faltas Injust.</th>
      <th>Permisos Goce</th>
      <th>CITT EsSalud</th>
      <th>Horas Netas</th>
      <th>Estado Planilla</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($filas as $f): ?>
      <tr>
        <td style="text-align: center; font-weight: bold; mso-number-format: '\@';"><?= htmlspecialchars($f['dni']) ?></td>
        <td style="font-weight: bold;"><?= htmlspecialchars($f['nombre_completo']) ?></td>
        <td><?= htmlspecialchars($f['servicio_nombre']) ?></td>
        <td style="text-align: center;"><?= htmlspecialchars($f['condicion_laboral']) ?></td>
        <td style="text-align: center;"><?= $f['dias_programados'] ?></td>
        <td style="text-align: center;"><?= $f['dias_laborados'] ?></td>
        <td style="text-align: center;"><?= $f['minutos_tardanza_total'] > 0 ? "+{$f['minutos_tardanza_total']}m" : '0m' ?></td>
        <td style="text-align: center; <?= $f['faltas_injustificadas'] > 0 ? 'color: #c00000; font-weight: bold;' : '' ?>">
          <?= $f['faltas_injustificadas'] ?>
        </td>
        <td style="text-align: center;"><?= $f['permisos_con_goce'] ?></td>
        <td style="text-align: center;"><?= $f['descansos_citt'] ?></td>
        <td style="text-align: center; font-weight: bold;"><?= $f['horas_netas'] ?> hrs</td>
        <td style="text-align: center;"><?= htmlspecialchars($f['estado_planilla']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table border="0" style="width: 100%; margin-top: 35px; border-collapse: collapse;">
  <tr class="sig-row">
    <td style="width: 30%;"><strong>Elaborado por</strong><br>Control de Asistencia</td>
    <td style="width: 5%;"></td>
    <td style="width: 30%;"><strong>Revisado por</strong><br>Jefatura de Recursos Humanos</td>
    <td style="width: 5%;"></td>
    <td style="width: 30%;"><strong>V°B° Dirección Ejecutiva</strong><br>Hospital Distrital de Pacasmayo</td>
  </tr>
</table>

<?php
// =========================================================================
// CASO 4: REPORTE OFICIAL SETI (150 HORAS ASISTENCIALES)
// =========================================================================
elseif ($tipo === 'seti'):
    $_GET['mes'] = $mes;
    $_GET['anio'] = $anio;
    ob_start();
    require __DIR__ . '/seti.php';
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
    $nombrados = $data['nombrados'] ?? [];
    $cas = $data['cas'] ?? [];
?>
<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
  <tr><td colspan="10" class="title-header">GOBIERNO REGIONAL LA LIBERTAD &bull; RED INTEGRADA DE SALUD PACASMAYO</td></tr>
  <tr><td colspan="10" class="title-header">HOSPITAL DISTRITAL DE PACASMAYO</td></tr>
  <tr><td colspan="10" style="text-align: center; font-size: 10.5pt; font-weight: bold;">SETI - SISTEMA DE INFORMACIÓN DE TURNOS E INASISTENCIAS (MINSA)</td></tr>
  <tr><td colspan="10" style="height: 10px;"></td></tr>
  <tr><td colspan="10" class="area-title">REPORTE OFICIAL SETI ASISTENCIAL (NORMA 150 HORAS)</td></tr>
  <tr><td colspan="10" class="period-title"><?= $nombreMes ?> <?= $anio ?></td></tr>
  <tr><td colspan="10" style="height: 12px;"></td></tr>
</table>

<div style="font-weight: bold; font-size: 10.5pt; margin-bottom: 5px;">1. Personal Asistencial Nombrado (D.L. 276)</div>
<table class="table-clean" border="1" cellpadding="3" cellspacing="0" style="margin-bottom: 20px;">
  <thead>
    <tr>
      <th>DNI</th>
      <th>Apellidos y Nombres</th>
      <th>Servicio</th>
      <th>GD (12h)</th>
      <th>GN (12h)</th>
      <th>Turnos Ord. (6h)</th>
      <th>Total Horas</th>
      <th>Cumple 150h</th>
      <th>Inasistencias</th>
      <th>CITT EsSalud</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($nombrados as $s): ?>
      <tr>
        <td style="text-align: center; font-weight: bold; mso-number-format: '\@';"><?= htmlspecialchars($s['dni']) ?></td>
        <td style="font-weight: bold;"><?= htmlspecialchars($s['nombre_completo']) ?></td>
        <td><?= htmlspecialchars($s['servicio_nombre']) ?></td>
        <td style="text-align: center;"><?= $s['guardias_diurnas_12h'] ?></td>
        <td style="text-align: center;"><?= $s['guardias_nocturnas_12h'] ?></td>
        <td style="text-align: center;"><?= $s['turnos_ordinarios_6h'] ?></td>
        <td style="text-align: center; font-weight: bold;"><?= $s['total_horas'] ?> hrs</td>
        <td style="text-align: center; font-weight: bold; <?= $s['cumple_150h'] ? 'color: #15803d;' : 'color: #b45309;' ?>">
          <?= $s['cumple_150h'] ? 'CONFORME ✓' : 'OBSERVADO ⚠' ?>
        </td>
        <td style="text-align: center;"><?= $s['total_inasistencias'] ?></td>
        <td style="text-align: center;"><?= $s['total_licencias_citt'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div style="font-weight: bold; font-size: 10.5pt; margin-bottom: 5px;">2. Personal Asistencial CAS (D.L. 1057)</div>
<table class="table-clean" border="1" cellpadding="3" cellspacing="0">
  <thead>
    <tr>
      <th>DNI</th>
      <th>Apellidos y Nombres</th>
      <th>Servicio</th>
      <th>GD (12h)</th>
      <th>GN (12h)</th>
      <th>Turnos Ord. (6h)</th>
      <th>Total Horas</th>
      <th>Cumple 150h</th>
      <th>Inasistencias</th>
      <th>CITT EsSalud</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cas as $s): ?>
      <tr>
        <td style="text-align: center; font-weight: bold; mso-number-format: '\@';"><?= htmlspecialchars($s['dni']) ?></td>
        <td style="font-weight: bold;"><?= htmlspecialchars($s['nombre_completo']) ?></td>
        <td><?= htmlspecialchars($s['servicio_nombre']) ?></td>
        <td style="text-align: center;"><?= $s['guardias_diurnas_12h'] ?></td>
        <td style="text-align: center;"><?= $s['guardias_nocturnas_12h'] ?></td>
        <td style="text-align: center;"><?= $s['turnos_ordinarios_6h'] ?></td>
        <td style="text-align: center; font-weight: bold;"><?= $s['total_horas'] ?> hrs</td>
        <td style="text-align: center; font-weight: bold; <?= $s['cumple_150h'] ? 'color: #15803d;' : 'color: #b45309;' ?>">
          <?= $s['cumple_150h'] ? 'CONFORME ✓' : 'OBSERVADO ⚠' ?>
        </td>
        <td style="text-align: center;"><?= $s['total_inasistencias'] ?></td>
        <td style="text-align: center;"><?= $s['total_licencias_citt'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table border="0" style="width: 100%; margin-top: 35px; border-collapse: collapse;">
  <tr class="sig-row">
    <td style="width: 30%;"><strong>Coordinador SETI</strong><br>Recursos Humanos</td>
    <td style="width: 5%;"></td>
    <td style="width: 30%;"><strong>Jefatura de Recursos Humanos</strong><br>Hospital Distrital de Pacasmayo</td>
    <td style="width: 5%;"></td>
    <td style="width: 30%;"><strong>Dirección Ejecutiva</strong><br>Red de Salud Pacasmayo</td>
  </tr>
</table>

<?php endif; ?>

</body>
</html>
