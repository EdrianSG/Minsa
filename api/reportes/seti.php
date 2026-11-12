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

require_once __DIR__.'/../../includes/ReportAreaFilter.php';
$areaFiltro = ReportAreaFilter::id();

$mes        = isset($_GET['mes']) ? intval($_GET['mes']) : (int)date('n');
$anio       = isset($_GET['anio']) ? intval($_GET['anio']) : (int)date('Y');
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
        $whereSql = "WHERE p.Activo = 1 AND c.Nombre NOT LIKE '%Admin%' AND c.Nombre NOT LIKE '%Inform%'";
        $params = [];
        if ($areaFiltro !== null) {
            $whereSql .= ' AND ap.AreaID = :areaId';
            $params[':areaId'] = $areaFiltro;
        }
        if ($servicioId !== null && $servicioId > 0) {
            $whereSql .= " AND s.ServicioID = :servId";
            $params[':servId'] = $servicioId;
        }

        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                p.NumeroDocumento AS dni,
                COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, ''), ' ', p.Nombres))), ''), p.NombreCompletoOriginal, p.CodigoEmpleado) AS nombre_completo,
                ISNULL(c.Nombre, 'Personal Asistencial') AS cargo,
                s.ServicioID AS servicio_id,
                ISNULL(s.Nombre, 'Servicio Asistencial') AS servicio_nombre,
                p.CondicionLaboral AS condicion_laboral
            FROM rrhh.Personal p
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            {$whereSql}
            ORDER BY 
                CASE WHEN c.Nombre LIKE '%Médico%' THEN 1 WHEN c.Nombre LIKE '%Enferm%' THEN 2 ELSE 3 END,
                p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres
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

            $duraciones=array_map(static fn($shift)=>$shift['horas'],TurnoCatalog::programming(TurnoCatalog::load($pdo)));
            foreach ($rows as $r) {
                $pid = intval($r['personal_id']);
                $patron = $patronPorPersonal[$pid] ?? [];
                $cond = $r['condicion_laboral'];

                $gd = 0;
                $gn = 0;
                $ord = 0;
                $horasEfectivas = 0.0;
                $inasistencias = 0;

                $workerAsist = $asistMap[$pid] ?? [];
                $incompleto = !$workerAsist || !$patron;

                for ($d = 1; $d <= $diasEnMes; $d++) {
                    $codTurno = $patron[$d - 1] ?? 'LIB';
                    $as = $workerAsist[$d] ?? null;

                    if ($as) {
                        $st = strtoupper(trim((string)$as['Estado']));
                        if (in_array($st, ['PUNTUAL', 'TARDANZA', 'ASISTIO'])) {
                            $duration=$duraciones[$codTurno]??null;
                            if ($duration===null) $incompleto=true; else $horasEfectivas+=$duration;
                            // Contabilizar según el turno efectivamente cubierto
                            if ($codTurno === 'GD') {
                                $gd++;
                            } elseif (in_array($codTurno, ['GN', 'N', '3'])) {
                                $gn++;
                            } elseif (in_array($codTurno, ['M', 'T', '1', '2'])) {
                                $ord++;
                            } elseif ($codTurno === '1/2') {
                                $ord += 2;
                            } elseif (in_array($codTurno, ['G24', '1/2/3'])) {
                                $gd++; $gn++;
                            } elseif ($codTurno === '2/3') {
                                $ord++; $gn++;
                            } else {
                                $incompleto = true;
                            }
                        } elseif (strpos($st, 'FALTA') !== false) {
                            $inasistencias++;
                        } elseif (strpos($st, 'PERMISO') !== false || strpos($st, 'LICENCIA') !== false) {
                            // El esquema no define créditos horarios ni goce para el permiso.
                            $incompleto = true;
                        }
                    }
                }

                $citt = $cittMap[$pid] ?? 0;
                if ($citt > 0) $incompleto = true;

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
                    $citt,
                    $incompleto
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
$sinCondicion = [];
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
    $incompleto = $item[13];
    $cumple = $incompleto ? null : ($horas >= 150.0);
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
        'estado_seti'            => $incompleto ? 'PENDIENTE' : ($cumple ? 'CUMPLE_NORMA' : 'OBSERVADO')
    ];

    if ($item[6] === 'NOMBRADO') {
        $nombrados[] = $registro;
    } elseif ($item[6] === 'CAS') {
        $cas[] = $registro;
    } else {
        $sinCondicion[] = $registro;
    }
}

$totalEvaluados = count($nombrados) + count($cas) + count($sinCondicion);
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
    'sin_condicion_confirmada' => $sinCondicion,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
