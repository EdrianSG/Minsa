<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('reportes', ['GET'], []);

/**
 * API Endpoint: /api/reportes/individual.php
 * Hospital Distrital de Pacasmayo - ETAPA 7
 * 
 * Récord de Asistencia y Turnos Individual Mensual por Trabajador
 * Detalle día a día (1 al 30/31) con cómputo de tardanzas y horas
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/SnapshotStore.php';
require_once __DIR__ . '/../../includes/AttendanceSchedule.php';
require_once __DIR__ . '/../../includes/AttendanceRules.php';

$personalId = isset($_GET['personal_id']) ? intval($_GET['personal_id']) : 6;
$mes        = isset($_GET['mes']) ? intval($_GET['mes']) : 9;
$anio       = isset($_GET['anio']) ? intval($_GET['anio']) : 2026;

apiRequirePersonalAccess($personalId);
if ($personalId <= 0 || $mes < 1 || $mes > 12 || $anio < 2020 || $anio > 2100) apiError(400, 'Trabajador o periodo inválido.');
$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$totalDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
$diasSemanaAbrev = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

$trabajador = [];

if ($dbConnected) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.PersonalID, per.NumeroDocumento AS DNI,
                per.Nombres, per.ApellidoPaterno, per.ApellidoMaterno,
                ISNULL(c.Nombre, 'Personal Hospitalario') AS NombreCargo,
                s.ServicioID,
                ISNULL(s.Nombre, 'Emergencia y Cuidados Críticos') AS NombreServicio,
                CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 'ADMINISTRATIVO' ELSE 'ASISTENCIAL' END AS RegimenLaboral,
                ISNULL(tc.Nombre, 'CAS') AS CondicionLaboral
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            WHERE p.PersonalID = :pid
        ");
        $stmt->execute([':pid' => $personalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) apiError(404, 'Trabajador no encontrado.');

        if ($row) {
            $personalId                      = intval($row['PersonalID']);
            $trabajador['personal_id']       = $personalId;
            $trabajador['dni']               = $row['DNI'];
            $trabajador['nombre_completo']   = trim("{$row['ApellidoPaterno']} " . ($row['ApellidoMaterno'] ? "{$row['ApellidoMaterno']} " : "") . "{$row['Nombres']}");
            $trabajador['cargo']             = $row['NombreCargo'] ?? 'Personal Hospitalario';
            $trabajador['servicio_id']       = intval($row['ServicioID'] ?? 8);
            $trabajador['servicio_nombre']   = $row['NombreServicio'] ?? 'Servicio Hospitalario';
            $trabajador['regimen_laboral']   = $row['RegimenLaboral'] ?? 'ASISTENCIAL';
            $trabajador['condicion_laboral'] = (stripos($row['CondicionLaboral'], 'Nombrad') !== false ? 'NOMBRADO' : 'CAS');
            $trabajador['norma_horas']       = ($trabajador['regimen_laboral'] === 'ADMINISTRATIVO' ? 176.0 : 150.0);

            // Validar acceso estricto por servicio para el usuario actual
            apiRequirePersonalAccess($personalId);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        apiError(503, 'No se pudo consultar el trabajador.');
    }
}

// Si no está en el snapshot, consultar la programación base de rrhh.Horario
$horarioSemanalDb = [];
if ($dbConnected) {
    try {
        $stmtH = $pdo->prepare("
            SELECT h.DiaSemana, h.FechaInicio, h.FechaFin, t.TurnoID, t.Nombre AS TurnoNombre,
                   CONVERT(VARCHAR(8), t.HoraInicio, 108) AS HoraInicio, CONVERT(VARCHAR(8), t.HoraFin, 108) AS HoraFin
            FROM rrhh.Horario h
            INNER JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            WHERE h.PersonalID = :pid AND h.Estado = 1
        ");
        $stmtH->execute([':pid' => $personalId]);
        while ($hRow = $stmtH->fetch(PDO::FETCH_ASSOC)) {
            $horarioSemanalDb[intval($hRow['DiaSemana'])][] = $hRow;
        }
    } catch (Exception $e) { error_log($e->getMessage()); apiError(503, 'No se pudo consultar la información del reporte.'); }
}

// Consultar registros efectivos de asistencia del trabajador en el mes desde rrhh.Asistencia
$asistenciaEfectiva = [];
if ($dbConnected) {
    try {
        $stmtAsistWorker = $pdo->prepare("
            SELECT 
                CAST(a.Fecha AS DATE) AS Fecha,
                DAY(a.Fecha) AS Dia,
                a.HoraEntrada,
                a.HoraSalida,
                a.Estado,
                a.Observacion
            FROM rrhh.Asistencia a
            WHERE a.PersonalID = :pid AND MONTH(a.Fecha) = :mes AND YEAR(a.Fecha) = :anio
        ");
        $stmtAsistWorker->execute([':pid' => $personalId, ':mes' => $mes, ':anio' => $anio]);
        while ($ar = $stmtAsistWorker->fetch(PDO::FETCH_ASSOC)) {
            $asistenciaEfectiva[intval($ar['Dia'])] = $ar;
        }
    } catch (Exception $e) { error_log($e->getMessage()); apiError(503, 'No se pudo consultar la información del reporte.'); }
}

// Consultar permisos y licencias del trabajador en el mes desde rrhh.PermisoLaboral
$permisosWorker = [];
if ($dbConnected) {
    try {
        $stmtPerm = $pdo->prepare("
            SELECT 
                pl.FechaInicio,
                pl.FechaFin,
                tpl.Nombre AS TipoNombre,
                pl.Motivo
            FROM rrhh.PermisoLaboral pl
            INNER JOIN rrhh.TipoPermisoLaboral tpl ON pl.TipoPermisoLaboralID = tpl.TipoPermisoLaboralID
            WHERE pl.PersonalID = :pid AND pl.Estado = 'APROBADO'
              AND pl.FechaInicio < :finExclusivo AND pl.FechaFin >= :inicioMes
        ");
        $stmtPerm->execute([':pid' => $personalId, ':inicioMes' => sprintf('%04d-%02d-01', $anio, $mes), ':finExclusivo' => date('Y-m-d', strtotime(sprintf('%04d-%02d-01 +1 month', $anio, $mes)))]);
        $rowsPerm = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsPerm as $pm) {
            $fIni = max(new DateTime($pm['FechaInicio']), new DateTime(sprintf('%04d-%02d-01', $anio, $mes)));
            $fFin = min(new DateTime($pm['FechaFin']), new DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $totalDias)));
            $curr = clone $fIni;
            while ($curr <= $fFin) {
                if (intval($curr->format('n')) === $mes && intval($curr->format('Y')) === $anio) {
                    $dNum = intval($curr->format('j'));
                    $permisosWorker[$dNum] = $pm;
                }
                $curr->modify('+1 day');
            }
        }
    } catch (Exception $e) { error_log($e->getMessage()); apiError(503, 'No se pudo consultar la información del reporte.'); }
}

// Generación día a día del mes
$dias = [];
$totalHorasProgramadas = 0;
$totalHorasAsistidas = 0;
$totalMinutosTardanza = 0;
$totalDiasLaborados = 0;
$totalFaltas = 0;
$totalPermisos = 0;

for ($d = 1; $d <= $totalDias; $d++) {
    $fechaStr = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
    $timestamp = strtotime($fechaStr);
    $diaSemanaNum = intval(date('w', $timestamp));
    $diaSemanaNombre = $diasSemanaAbrev[$diaSemanaNum];

    $diaIso = ($diaSemanaNum === 0) ? 7 : $diaSemanaNum;
    $schedule = ['PersonalID'=>$personalId, 'ServicioID'=>$trabajador['servicio_id'], 'TurnoID'=>null];
    foreach ($horarioSemanalDb[$diaIso] ?? [] as $weekly) {
        if (substr($weekly['FechaInicio'], 0, 10) <= $fechaStr
            && (empty($weekly['FechaFin']) || substr($weekly['FechaFin'], 0, 10) >= $fechaStr)) {
            $schedule = array_replace($schedule, ['TurnoID'=>$weekly['TurnoID'], 'NombreTurno'=>$weekly['TurnoNombre'],
                'HoraProgInicio'=>$weekly['HoraInicio'], 'HoraProgFin'=>$weekly['HoraFin']]);
        }
    }
    try { $schedule = AttendanceSchedule::apply($schedule, $fechaStr); }
    catch (RuntimeException $e) { apiError(503, $e->getMessage()); }
    $esLaborable = !empty($schedule['TurnoID']);
    $turnoCodigo = $schedule['TurnoCodigo'] ?? ($esLaborable ? 'TURNO' : 'LIB');
    $tInfo = ['nombre'=>$schedule['NombreTurno'] ?? 'Descanso', 'inicio'=>$schedule['HoraProgInicio'] ?? '',
        'fin'=>$schedule['HoraProgFin'] ?? '', 'horas'=>0.0];
    if ($esLaborable) {
        [$start, $end] = AttendanceRules::interval($fechaStr, $tInfo['inicio'], $tInfo['fin']);
        $tInfo['horas'] = ($end - $start) / 3600;
    }
    $horasProg = $tInfo['horas'];
    $horaProgInicio = $tInfo['inicio'];
    $horaProgFin = $tInfo['fin'];

    $horaEntrada = null;
    $horaSalida = null;
    $minutosTardanza = 0;
    $estado = 'DESCANSO';
    $obs = 'Día de descanso programado';

    if ($esLaborable) $totalHorasProgramadas += $horasProg;

    if (isset($permisosWorker[$d])) {
        $pInfo = $permisosWorker[$d];
        $estado = (stripos($pInfo['TipoNombre'], 'CITT') !== false || stripos($pInfo['TipoNombre'], 'Médico') !== false) ? 'CITT' : 'PERMISO';
        $obs = $pInfo['TipoNombre'] . (!empty($pInfo['Motivo']) ? ': ' . $pInfo['Motivo'] : '');
        $totalPermisos++;
        $totalHorasAsistidas += $horasProg;
    } elseif (isset($asistenciaEfectiva[$d])) {
        $asRec = $asistenciaEfectiva[$d];
        $stUpper = strtoupper(trim((string)$asRec['Estado']));
        $horaEntrada = !empty($asRec['HoraEntrada']) ? substr($asRec['HoraEntrada'], 0, 5) : null;
        $horaSalida = !empty($asRec['HoraSalida']) ? substr($asRec['HoraSalida'], 0, 5) : null;
        $obs = !empty($asRec['Observacion']) ? $asRec['Observacion'] : 'Conforme';

        // Extraer minutos de tardanza si existen
        if (preg_match('/Tardanza de (\d+) min/i', $obs, $m)) {
            $minutosTardanza = intval($m[1]);
        }

        if (in_array($stUpper, ['PUNTUAL', 'ASISTIO'])) {
            $estado = 'ASISTIO';
            $totalDiasLaborados++;
            $totalHorasAsistidas += $horasProg;
        } elseif ($stUpper === 'TARDANZA') {
            $estado = 'TARDANZA';
            $totalDiasLaborados++;
            $totalMinutosTardanza += $minutosTardanza;
            $totalHorasAsistidas += $horasProg;
        } elseif (strpos($stUpper, 'FALTA') !== false) {
            $estado = 'FALTA';
            $totalFaltas++;
        } elseif (in_array($stUpper, ['PERMISO', 'LICENCIA'])) {
            $estado = 'PERMISO';
            $totalPermisos++;
            $totalHorasAsistidas += $horasProg;
        } else {
            $estado = $asRec['Estado'] ?: 'SIN_DATOS';
        }
    } elseif ($esLaborable) {
        // La ausencia de un registro no demuestra una falta: puede faltar importar o conciliar.
        $estado = AttendanceRules::missingState($fechaStr, $horaProgInicio, $horaProgFin);
        $obs = $estado === 'SIN_DATOS' ? 'Sin asistencia conciliada; pendiente de verificación' : 'Jornada pendiente de cierre';
    }


    $dias[] = [
        'dia'               => $d,
        'fecha'             => $fechaStr,
        'dia_semana'        => $diaSemanaNombre,
        'turno_codigo'      => $turnoCodigo,
        'turno_nombre'      => $tInfo['nombre'],
        'hora_prog_inicio'  => $horaProgInicio,
        'hora_prog_fin'     => $horaProgFin,
        'horas_programadas' => $horasProg,
        'hora_entrada'      => $horaEntrada,
        'hora_salida'       => $horaSalida,
        'minutos_tardanza'  => $minutosTardanza,
        'estado'            => $estado,
        'observacion'       => $obs,
        'observaciones'     => $obs,
        'es_fin_de_semana'  => ($diaSemanaNum === 0 || $diaSemanaNum === 6)
    ];
}

$horasFaltantes = max(0, $trabajador['norma_horas'] - $totalHorasAsistidas);
$cumpleNorma = ($totalHorasAsistidas >= $trabajador['norma_horas']);

$totales = [
    'horas_programadas' => $totalHorasProgramadas,
    'horas_asistidas'   => $totalHorasAsistidas,
    'minutos_tardanza'  => $totalMinutosTardanza,
    'dias_laborados'    => $totalDiasLaborados,
    'total_faltas'      => $totalFaltas,
    'total_permisos'    => $totalPermisos,
    'horas_faltantes'   => $horasFaltantes,
    'cumple_150h'       => $cumpleNorma
];

echo json_encode([
    'ok'                 => true,
    'periodo'            => [
        'mes'        => $mes,
        'mes_nombre' => $nombresMeses[$mes],
        'anio'       => $anio,
        'total_dias' => $totalDias
    ],
    'trabajador'         => $trabajador,
    'totales'            => $totales,
    'resumen_mensual'    => [
        'total_horas_programadas' => $totalHorasProgramadas,
        'total_horas_asistidas'   => $totalHorasAsistidas,
        'total_minutos_tardanza'  => $totalMinutosTardanza,
        'total_dias_laborados'    => $totalDiasLaborados,
        'total_faltas'            => $totalFaltas,
        'total_permisos'          => $totalPermisos,
        'horas_faltantes'         => $horasFaltantes,
        'cumple_norma'            => $cumpleNorma
    ],
    'dias'               => $dias,
    'conexion_sql'       => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
