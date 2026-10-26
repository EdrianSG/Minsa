<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('reportes', ['GET'], []);

/**
 * API Endpoint: /api/reportes/seti.php
 * Hospital Distrital de Pacasmayo - ETAPA 7
 * 
 * Reporte Oficial SETI (Sistema de Información de Turnos e Inasistencias de Personal Asistencial)
 * Requerimiento normativo MINSA / Red de Salud Pacasmayo
 * Segmentado por Personal Nombrado (DL 276) y CAS (DL 1057) con validación de 150 horas
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/SnapshotStore.php';
require_once __DIR__ . '/../../includes/AttendancePeriod.php';
require_once __DIR__ . '/../../includes/ProgramacionStore.php';

$mes        = isset($_GET['mes']) ? intval($_GET['mes']) : 9;
$anio       = isset($_GET['anio']) ? intval($_GET['anio']) : 2026;
if ($mes < 1 || $mes > 12 || $anio < 2020 || $anio > 2100) apiError(400, 'Periodo inválido.');
$servicioId = (isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' && intval($_GET['servicio_id']) > 0) ? intval($_GET['servicio_id']) : null;

// Restricción estricta de autorización por servicio
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    if ($servicioId === null) {
        $servicioId = $userAllowedServ;
    } elseif ($servicioId !== $userAllowedServ) {
        apiError(403, 'No tiene autorización para ver reportes de otro servicio.');
    }
}

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);

$asistenciales = [];

// Consultar personal asistencial real desde SQL Server
if ($dbConnected) {
    try {
        $whereSql = "WHERE p.Estado = 1 AND c.Nombre NOT LIKE '%Admin%' AND c.Nombre NOT LIKE '%Inform%'";
        $params = [];
        if ($servicioId !== null && $servicioId > 0) {
            $whereSql .= " AND s.ServicioID = :servId";
            $params[':servId'] = $servicioId;
        }

        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                per.NumeroDocumento AS dni,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
                ISNULL(c.Nombre, 'Personal Asistencial') AS cargo,
                ISNULL(s.ServicioID, 7) AS servicio_id,
                ISNULL(s.Nombre, 'Servicio Asistencial') AS servicio_nombre,
                ISNULL(tc.Nombre, 'CAS') AS condicion_laboral
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            {$whereSql}
            ORDER BY 
                CASE WHEN c.Nombre LIKE '%Médico%' THEN 1 WHEN c.Nombre LIKE '%Enferm%' THEN 2 ELSE 3 END,
                per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            // Leer programación de la matriz para asociar tipos de turno
            $snapGlobal = ['data'=>ProgramacionStore::read($pdo, $anio, $mes, $servicioId ?? 0)];
            $servSnap = null;
            $listaSnap = $snapGlobal['data']['personal_servicio'] ?? ($servSnap['data']['personal_servicio'] ?? []);

            $patronPorPersonal = [];
            foreach ($listaSnap as $sp) {
                $patronPorPersonal[intval($sp['personal_id'])] = $sp['patron'] ?? [];
            }

            // Consultar asistencias efectivas del mes
            $stmtAsist = $pdo->prepare("
                SELECT a.PersonalID, DAY(a.Fecha) AS Dia, a.Estado, a.Observacion
                FROM rrhh.Asistencia a
                WHERE MONTH(a.Fecha) = :mes AND YEAR(a.Fecha) = :anio
            ");
            $stmtAsist->execute([':mes' => $mes, ':anio' => $anio]);
            $asistMap = [];
            while ($arow = $stmtAsist->fetch(PDO::FETCH_ASSOC)) {
                $asistMap[intval($arow['PersonalID'])][intval($arow['Dia'])] = $arow;
            }

            // Consultar CITT desde rrhh.PermisoLaboral (parámetros distintos para evitar error de conteo ODBC)
            $cittMap = AttendancePeriod::loadMedicalDays($pdo, $anio, $mes);

            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

            foreach ($rows as $r) {
                $pid = intval($r['personal_id']);
                $patron = $patronPorPersonal[$pid] ?? [];
                $cond = (stripos($r['condicion_laboral'], 'Nombrad') !== false ? 'NOMBRADO' : 'CAS');

                $gd = 0;
                $gn = 0;
                $ord = 0;
                $horasEfectivas = 0.0;
                $inasistencias = 0;

                $workerAsist = $asistMap[$pid] ?? [];

                for ($d = 1; $d <= $diasEnMes; $d++) {
                    $codTurno = $patron[$d - 1] ?? 'LIB';
                    $as = $workerAsist[$d] ?? null;

                    if ($as) {
                        $st = strtoupper(trim((string)$as['Estado']));
                        if (in_array($st, ['PUNTUAL', 'TARDANZA', 'ASISTIO'])) {
                            // Contabilizar según el turno efectivamente cubierto
                            if ($codTurno === 'GD') {
                                $gd++;
                                $horasEfectivas += 12.0;
                            } elseif (in_array($codTurno, ['GN', 'N', '3'])) {
                                $gn++;
                                $horasEfectivas += 12.0;
                            } elseif (in_array($codTurno, ['M', 'T', '1', '2'])) {
                                $ord++;
                                $horasEfectivas += 6.0;
                            } elseif ($codTurno === '1/2') {
                                $ord += 2;
                                $horasEfectivas += 12.0;
                            } elseif (in_array($codTurno, ['G24', '1/2/3'])) {
                                $gd++; $gn++;
                                $horasEfectivas += 24.0;
                            } elseif ($codTurno === '2/3') {
                                $ord++; $gn++;
                                $horasEfectivas += 18.0;
                            } else {
                                $ord++;
                                $horasEfectivas += 6.0;
                            }
                        } elseif (strpos($st, 'FALTA') !== false) {
                            $inasistencias++;
                        } elseif (strpos($st, 'PERMISO') !== false || strpos($st, 'LICENCIA') !== false) {
                            // Turno cubierto por licencia o permiso justificado
                            $horasEfectivas += 6.0;
                        }
                    }
                }

                $citt = $cittMap[$pid] ?? 0;
                if ($citt > 0) {
                    $horasEfectivas += ($citt * 6.0);
                }

                $asistenciales[] = [
                    $pid,
                    $r['dni'],
                    $r['nombre_completo'],
                    $r['cargo'],
                    intval($r['servicio_id']),
                    $r['servicio_nombre'],
                    $cond,
                    $gd,
                    $gn,
                    $ord,
                    round($horasEfectivas, 1),
                    $inasistencias,
                    $citt
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error en api/reportes/seti.php: " . $e->getMessage());
        apiError(503, 'No se pudo consultar la información del reporte.');
    }
}

$nombrados = [];
$cas = [];
$totalGuardias12h = 0;
$totalCumplen150h = 0;

foreach ($asistenciales as $item) {
    if ($servicioId !== null && $item[4] !== $servicioId) continue;

    $gd = $item[7];
    $gn = $item[8];
    $ord = $item[9];
    $horas = $item[10];
    $faltas = $item[11];
    $citt = $item[12];
    $cumple = ($horas >= 150.0);
    $dif = $horas - 150.0;

    $totalGuardias12h += ($gd + $gn);
    if ($cumple) $totalCumplen150h++;

    $registro = [
        'personal_id'            => $item[0],
        'dni'                    => $item[1],
        'nombre_completo'        => $item[2],
        'cargo'                  => $item[3],
        'servicio_id'            => $item[4],
        'servicio_nombre'        => $item[5],
        'condicion'              => $item[6],
        'guardias_diurnas_12h'   => $gd,
        'guardias_nocturnas_12h' => $gn,
        'turnos_ordinarios_6h'   => $ord,
        'total_horas'            => $horas,
        'cumple_150h'            => $cumple,
        'diferencia_horas'       => $dif,
        'total_inasistencias'    => $faltas,
        'total_licencias_citt'   => $citt,
        'estado_seti'            => $cumple ? 'CUMPLE_NORMA' : 'OBSERVADO'
    ];

    if ($item[6] === 'NOMBRADO') {
        $nombrados[] = $registro;
    } else {
        $cas[] = $registro;
    }
}

$totalEvaluados = count($nombrados) + count($cas);
$porcentajeCumplimiento = ($totalEvaluados > 0) ? round(($totalCumplen150h / $totalEvaluados) * 100, 1) : 100.0;

echo json_encode([
    'ok'           => true,
    'periodo'      => [
        'mes'        => $mes,
        'mes_nombre' => $nombresMeses[$mes],
        'anio'       => $anio
    ],
    'resumen'      => [
        'total_asistenciales'     => $totalEvaluados,
        'nombrados_count'         => count($nombrados),
        'cas_count'               => count($cas),
        'total_guardias_12h'      => $totalGuardias12h,
        'personal_cumple_150h'    => $totalCumplen150h,
        'porcentaje_cumplimiento' => $porcentajeCumplimiento
    ],
    'nombrados'    => $nombrados,
    'cas'          => $cas,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
