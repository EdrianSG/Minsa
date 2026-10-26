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

$personalId = isset($_GET['personal_id']) ? intval($_GET['personal_id']) : 6;
$mes        = isset($_GET['mes']) ? intval($_GET['mes']) : 9;
$anio       = isset($_GET['anio']) ? intval($_GET['anio']) : 2026;

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$totalDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
$diasSemanaAbrev = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

// Datos del trabajador por defecto (Dr. Luis Alberto Díaz Rojas - UCI)
$trabajador = [
    'personal_id'       => 6,
    'dni'               => '45892106',
    'nombre_completo'   => 'Díaz Rojas, Luis Alberto',
    'cargo'             => 'Médico Especialista',
    'servicio_id'       => 8,
    'servicio_nombre'   => 'Unidad de Cuidados Intensivos (UCI)',
    'regimen_laboral'   => 'ASISTENCIAL',
    'condicion_laboral' => 'NOMBRADO',
    'norma_horas'       => 150.0
];

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

        // Si no se encontró por ese ID, tomar el primer trabajador real de la BD
        if (!$row) {
            $stmtFirst = $pdo->query("
                SELECT TOP 1
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
                WHERE p.Estado = 1
                ORDER BY CASE WHEN c.Nombre LIKE '%Médico%' THEN 1 ELSE 2 END, p.PersonalID
            ");
            $row = $stmtFirst->fetch(PDO::FETCH_ASSOC);
        }

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
        $dbConnected = false;
    }
}

// Intentar leer el patrón del trabajador desde el snapshot del mes o desde rrhh.Horario
$patronWorker = null;
$snapGlob = SnapshotStore::get("programacion_serv_0_{$anio}_{$mes}");
$snapServ = SnapshotStore::get("programacion_serv_{$trabajador['servicio_id']}_{$anio}_{$mes}");
$pList = $snapGlob['data']['personal_servicio'] ?? ($snapServ['data']['personal_servicio'] ?? []);
foreach ($pList as $pItem) {
    if (intval($pItem['personal_id']) === $personalId) {
        $patronWorker = $pItem['patron'] ?? null;
        break;
    }
}

// Si no está en el snapshot, consultar la programación base de rrhh.Horario
$horarioSemanalDb = [];
if ($dbConnected) {
    try {
        $stmtH = $pdo->prepare("
            SELECT h.DiaSemana, t.TurnoID, t.Nombre AS TurnoNombre, t.HoraInicio, t.HoraFin
            FROM rrhh.Horario h
            INNER JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            WHERE h.PersonalID = :pid AND h.Estado = 1
        ");
        $stmtH->execute([':pid' => $personalId]);
        while ($hRow = $stmtH->fetch(PDO::FETCH_ASSOC)) {
            $horarioSemanalDb[intval($hRow['DiaSemana'])] = $hRow;
        }
    } catch (Exception $e) {}
}

// Catálogo de turnos y equivalencias
$catalogTurnos = [
    'M'     => ['nombre' => 'Mañana Ordinario (6h)',     'inicio' => '07:00', 'fin' => '13:00', 'horas' => 6.0],
    '1'     => ['nombre' => 'Turno 1 - Mañana (6h)',     'inicio' => '07:00', 'fin' => '13:00', 'horas' => 6.0],
    'T'     => ['nombre' => 'Tarde Ordinario (6h)',      'inicio' => '13:00', 'fin' => '19:00', 'horas' => 6.0],
    '2'     => ['nombre' => 'Turno 2 - Tarde (6h)',      'inicio' => '13:00', 'fin' => '19:00', 'horas' => 6.0],
    'N'     => ['nombre' => 'Guardia Nocturna (12h)',    'inicio' => '19:00', 'fin' => '07:00', 'horas' => 12.0],
    '3'     => ['nombre' => 'Turno 3 - Noche (12h)',     'inicio' => '19:00', 'fin' => '07:00', 'horas' => 12.0],
    'GD'    => ['nombre' => 'Guardia Diurna (12h)',      'inicio' => '07:00', 'fin' => '19:00', 'horas' => 12.0],
    'GN'    => ['nombre' => 'Guardia Nocturna (12h)',    'inicio' => '19:00', 'fin' => '07:00', 'horas' => 12.0],
    '1/2'   => ['nombre' => 'Doble Turno M+T (12h)',     'inicio' => '07:00', 'fin' => '19:00', 'horas' => 12.0],
    '2/3'   => ['nombre' => 'Turno Tarde+Noche (18h)',   'inicio' => '13:00', 'fin' => '07:00', 'horas' => 18.0],
    'G24'   => ['nombre' => 'Guardia 24h (1/2/3)',       'inicio' => '07:00', 'fin' => '07:00', 'horas' => 24.0],
    '1/2/3' => ['nombre' => 'Guardia 24h (1/2/3)',       'inicio' => '07:00', 'fin' => '07:00', 'horas' => 24.0],
    'ADM'   => ['nombre' => 'Jornada Administrativa',    'inicio' => '08:00', 'fin' => '16:45', 'horas' => 8.0],
    'VAC'   => ['nombre' => 'Vacaciones Programadas',    'inicio' => '08:00', 'fin' => '14:00', 'horas' => 6.0],
    'VAC-1' => ['nombre' => 'Emergencia en Vacaciones (M)','inicio' => '07:00', 'fin' => '13:00', 'horas' => 6.0],
    'VAC-2' => ['nombre' => 'Emergencia en Vacaciones (T)','inicio' => '13:00', 'fin' => '19:00', 'horas' => 6.0],
    'VAC-3' => ['nombre' => 'Emergencia en Vacaciones (N)','inicio' => '19:00', 'fin' => '07:00', 'horas' => 12.0],
    'DESC'  => ['nombre' => 'Descanso Médico CITT',      'inicio' => '08:00', 'fin' => '14:00', 'horas' => 6.0],
    'LIB'   => ['nombre' => 'Descanso / Libre',          'inicio' => '',      'fin' => '',      'horas' => 0.0]
];

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
    } catch (Exception $e) {}
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
              AND ((MONTH(pl.FechaInicio) = :m1 AND YEAR(pl.FechaInicio) = :a1)
                   OR (MONTH(pl.FechaFin) = :m2 AND YEAR(pl.FechaFin) = :a2))
        ");
        $stmtPerm->execute([':pid' => $personalId, ':m1' => $mes, ':a1' => $anio, ':m2' => $mes, ':a2' => $anio]);
        $rowsPerm = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsPerm as $pm) {
            $fIni = new DateTime($pm['FechaInicio']);
            $fFin = new DateTime($pm['FechaFin']);
            $curr = clone $fIni;
            while ($curr <= $fFin) {
                if (intval($curr->format('n')) === $mes && intval($curr->format('Y')) === $anio) {
                    $dNum = intval($curr->format('j'));
                    $permisosWorker[$dNum] = $pm;
                }
                $curr->modify('+1 day');
            }
        }
    } catch (Exception $e) {}
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

    // Obtener turno del patrón del trabajador o del horario semanal en BD
    $diaIso = ($diaSemanaNum === 0) ? 7 : $diaSemanaNum; // 1=Lunes..7=Domingo
    if ($patronWorker !== null && isset($patronWorker[$d - 1])) {
        $turnoCodigo = $patronWorker[$d - 1];
    } elseif (isset($horarioSemanalDb[$diaIso])) {
        $tDb = $horarioSemanalDb[$diaIso];
        $tId = intval($tDb['TurnoID']);
        if ($tId === 1) $turnoCodigo = '1';
        elseif ($tId === 2) $turnoCodigo = '2';
        elseif ($tId === 3) $turnoCodigo = '3';
        elseif ($tId === 4) $turnoCodigo = 'GD';
        elseif ($tId === 5) $turnoCodigo = 'GN';
        else $turnoCodigo = 'ADM';
    } else {
        if ($trabajador['regimen_laboral'] === 'ADMINISTRATIVO') {
            $turnoCodigo = ($diaSemanaNum === 0 || $diaSemanaNum === 6) ? 'LIB' : 'ADM';
        } else {
            $turnoCodigo = 'LIB';
        }
    }

    $tInfo = $catalogTurnos[$turnoCodigo] ?? $catalogTurnos['LIB'];
    $esLaborable = ($turnoCodigo !== 'LIB' && $turnoCodigo !== '0');
    // Si no figuraba como laborable en el rol pero tiene asistencia registrada en BD, es una guardia o turno efectivamente registrado
    if (!$esLaborable && isset($asistenciaEfectiva[$d]) && !empty($asistenciaEfectiva[$d]['HoraEntrada'])) {
        $esLaborable = true;
        $turnoCodigo = '3'; // Turno asignado
        $tInfo = $catalogTurnos['3'];
    }
    $horasProg = $tInfo['horas'];
    $horaProgInicio = $tInfo['inicio'];
    $horaProgFin = $tInfo['fin'];

    $horaEntrada = null;
    $horaSalida = null;
    $minutosTardanza = 0;
    $estado = 'DESCANSO';
    $obs = 'Día de descanso programado';

    if ($esLaborable) {
        $totalHorasProgramadas += $horasProg;

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
                $estado = $asRec['Estado'] ?: 'ASISTIO';
                $totalDiasLaborados++;
                $totalHorasAsistidas += $horasProg;
            }
        } else {
            // Día laborable sin registro de marcación
            $isFuturo = (strtotime($fechaStr) > strtotime(date('Y-m-d')));
            if ($isFuturo) {
                $estado = 'PROGRAMADO';
                $obs = 'Turno programado pendiente de marcación';
            } else {
                $estado = 'FALTA';
                $obs = 'Inasistencia sin marcación en biométrico';
                $totalFaltas++;
            }
        }
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
