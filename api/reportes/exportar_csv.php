<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('reportes', ['GET'], []);

/**
 * API Endpoint: /api/reportes/exportar_csv.php
 * Hospital Distrital de Pacasmayo - ETAPA 7
 * 
 * Exportador oficial a formato CSV compatible con Microsoft Excel
 * Incluye UTF-8 BOM (\xEF\xBB\xBF) para correcta codificación de tildes y caracteres especiales.
 */

$tipo       = $_GET['tipo'] ?? 'consolidado'; // 'consolidado', 'individual', 'seti'
$mes        = intval($_GET['mes'] ?? 9);
$anio       = intval($_GET['anio'] ?? 2026);
$personalId = intval($_GET['personal_id'] ?? 1);
$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : 0;
$allowedServiceId = Auth::getAllowedServiceId();
if ($allowedServiceId !== null) {
    $_GET['servicio_id'] = $allowedServiceId;
} elseif ($servicioId > 0) {
    apiRequireServiceAccess($servicioId);
}

if ($tipo === 'individual' && $personalId > 0) {
    apiRequirePersonalAccess($personalId);
}

$filename = "reporte_{$tipo}_{$mes}_{$anio}.csv";

// Consumir datos en buffer antes de emitir encabezados
$data = null;
if ($tipo === 'individual') {
    $_GET['personal_id'] = $personalId;
    $_GET['mes'] = $mes;
    $_GET['anio'] = $anio;
    ob_start();
    require __DIR__ . '/individual.php';
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
} elseif ($tipo === 'seti') {
    $_GET['mes'] = $mes;
    $_GET['anio'] = $anio;
    ob_start();
    require __DIR__ . '/seti.php';
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
} else {
    $_GET['mes'] = $mes;
    $_GET['anio'] = $anio;
    ob_start();
    require __DIR__ . '/consolidado.php';
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
}

// Ahora emitimos los encabezados finales definitivos para descarga CSV
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Escribir BOM UTF-8 para compatibilidad absoluta con Microsoft Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($tipo === 'individual') {
    // Encabezado institucional
    fputcsv($output, ['HOSPITAL DISTRITAL DE PACASMAYO - RECURSOS HUMANOS']);
    fputcsv($output, ["RÉCORD INDIVIDUAL DE ASISTENCIA Y TURNOS - MES: $mes / AÑO: $anio"]);
    fputcsv($output, []);

    // Columnas
    fputcsv($output, [
        'Día', 'Fecha', 'Día Semana', 'Turno Código', 'Turno Nombre',
        'Hora Prog. Inicio', 'Hora Prog. Fin', 'Horas Prog.',
        'Hora Entrada Real', 'Hora Salida Real', 'Minutos Tardanza',
        'Estado Asistencia', 'Observaciones / Sustento'
    ]);

    if ($data && !empty($data['dias'])) {
        foreach ($data['dias'] as $d) {
            fputcsv($output, [
                $d['dia'],
                $d['fecha'],
                $d['dia_semana'],
                $d['turno_codigo'],
                $d['turno_nombre'],
                $d['hora_prog_inicio'],
                $d['hora_prog_fin'],
                $d['horas_programadas'],
                $d['hora_entrada'] ?? '--',
                $d['hora_salida'] ?? '--',
                $d['minutos_tardanza'],
                $d['estado'],
                $d['observacion']
            ]);
        }
    }
} elseif ($tipo === 'seti') {
    fputcsv($output, ['HOSPITAL DISTRITAL DE PACASMAYO - RED DE SALUD PACASMAYO']);
    fputcsv($output, ["REPORTE OFICIAL SETI - SISTEMA DE INFORMACIÓN DE TURNOS ASISTENCIALES"]);
    fputcsv($output, ["PERIODO: Mes $mes / Año $anio - NORMA MINSA 150 HORAS"]);
    fputcsv($output, []);

    fputcsv($output, [
        'Condición', 'DNI', 'Nombre Completo', 'Cargo', 'Servicio Hospitalario',
        'Guardias Diurnas (12h)', 'Guardias Nocturnas (12h)', 'Turnos Ordinarios (6h)',
        'Total Horas Computadas', 'Cumple 150h', 'Diferencia Horas', 'Faltas', 'CITT EsSalud'
    ]);

    if ($data) {
        foreach ($data['nombrados'] as $r) {
            fputcsv($output, [
                'NOMBRADO (DL 276)', $r['dni'], $r['nombre_completo'], $r['cargo'], $r['servicio_nombre'],
                $r['guardias_diurnas_12h'], $r['guardias_nocturnas_12h'], $r['turnos_ordinarios_6h'],
                $r['total_horas'], $r['cumple_150h'] ? 'SÍ (150h)' : 'NO', $r['diferencia_horas'],
                $r['total_inasistencias'], $r['total_licencias_citt']
            ]);
        }
        foreach ($data['cas'] as $r) {
            fputcsv($output, [
                'CAS (DL 1057)', $r['dni'], $r['nombre_completo'], $r['cargo'], $r['servicio_nombre'],
                $r['guardias_diurnas_12h'], $r['guardias_nocturnas_12h'], $r['turnos_ordinarios_6h'],
                $r['total_horas'], $r['cumple_150h'] ? 'SÍ (150h)' : 'NO', $r['diferencia_horas'],
                $r['total_inasistencias'], $r['total_licencias_citt']
            ]);
        }
    }
} else {
    // Consolidado para planillas
    fputcsv($output, ['HOSPITAL DISTRITAL DE PACASMAYO - UNIDAD DE RECURSOS HUMANOS']);
    fputcsv($output, ["CONSOLIDADO MENSUAL DE ASISTENCIA Y TARDANZAS PARA PLANILLAS - $mes / $anio"]);
    fputcsv($output, []);

    fputcsv($output, [
        'DNI', 'Apellidos y Nombres', 'Cargo', 'Servicio', 'Condición', 'Régimen',
        'Días Programados', 'Días Laborados', 'Minutos Tardanza Acumulados',
        'Faltas Injustificadas (Descuento)', 'Permisos con Goce', 'Descansos CITT EsSalud',
        'Horas Netas Computables', 'Estado Planilla'
    ]);

    if ($data && !empty($data['filas'])) {
        foreach ($data['filas'] as $f) {
            fputcsv($output, [
                $f['dni'],
                $f['nombre_completo'],
                $f['cargo'],
                $f['servicio_nombre'],
                $f['condicion_laboral'],
                $f['regimen_laboral'],
                $f['dias_programados'],
                $f['dias_laborados'],
                $f['minutos_tardanza_total'],
                $f['faltas_injustificadas'],
                $f['permisos_con_goce'],
                $f['descansos_citt'],
                $f['horas_netas'],
                $f['estado_planilla']
            ]);
        }
    }
}

fclose($output);
exit;
