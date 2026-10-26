<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('reportes', ['GET'], []);

/**
 * API Endpoint: /api/reportes/consolidado.php
 * Hospital Distrital de Pacasmayo - ETAPA 7
 * 
 * Reporte Consolidado Mensual de Asistencia y Tardanzas para la Unidad de Planillas
 * Insumo para el cálculo de haberes, descuentos por tardanza y faltas injustificadas.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/SnapshotStore.php';
require_once __DIR__ . '/../../includes/AttendancePeriod.php';
require_once __DIR__ . '/../../includes/ProgramacionStore.php';

$mes            = isset($_GET['mes']) ? intval($_GET['mes']) : 9;
$anio           = isset($_GET['anio']) ? intval($_GET['anio']) : 2026;
$servicioId     = (isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' && intval($_GET['servicio_id']) > 0) ? intval($_GET['servicio_id']) : null;

if ($mes < 1 || $mes > 12 || $anio < 2020 || $anio > 2100) apiError(400, 'Periodo inválido.');

// Restringir a jefaturas de servicio
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    if ($servicioId === null) {
        $servicioId = $userAllowedServ;
    } elseif ($servicioId !== $userAllowedServ) {
        apiError(403, 'No tiene autorización para ver reportes de otro servicio.');
    }
}

$condicionFiltro= isset($_GET['condicion']) && $_GET['condicion'] !== '' ? strtoupper(trim($_GET['condicion'])) : null;
$regimenFiltro  = isset($_GET['regimen']) && $_GET['regimen'] !== '' ? strtoupper(trim($_GET['regimen'])) : null;

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);

$personalConsolidado = [];

// Intentar consultar personal real desde SQL Server
if ($dbConnected) {
    try {
        $whereSql = "WHERE p.Estado = 1";
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
                ISNULL(c.Nombre, 'Personal Hospitalario') AS cargo,
                ISNULL(s.ServicioID, 7) AS servicio_id,
                ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre,
                CASE 
                    WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 'ADMINISTRATIVO' 
                    ELSE 'ASISTENCIAL' 
                END AS regimen_laboral,
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
            // Catálogo de duraciones oficiales de turnos
            $duracionTurnos = [
                '1' => 6.0, 'M' => 6.0, '2' => 6.0, 'T' => 6.0,
                '3' => 12.0, 'N' => 12.0, 'GN' => 12.0, 'GD' => 12.0,
                '1/2' => 12.0, '2/3' => 18.0, 'G24' => 24.0, '1/2/3' => 24.0,
                'ADM' => 8.0, 'VAC' => 6.0, 'VAC-1' => 6.0, 'VAC-2' => 6.0, 'VAC-3' => 12.0,
                'DESC' => 6.0, 'CITT' => 6.0, 'LIB' => 0.0, '0' => 0.0
            ];

            // Leer snapshot para obtener horas programadas reales de la matriz
            $snapGlobal = ['data'=>ProgramacionStore::read($pdo, $anio, $mes, $servicioId ?? 0)];
            $servSnap = null;

            $patronPorPersonal = [];
            $horasProgPorPersonal = [];
            $diasProgPorPersonal = [];
            $listaSnap = $snapGlobal['data']['personal_servicio'] ?? ($servSnap['data']['personal_servicio'] ?? []);
            foreach ($listaSnap as $sp) {
                $pid = intval($sp['personal_id']);
                $patronPorPersonal[$pid] = $sp['patron'] ?? [];
                $totH = 0.0;
                $totD = 0;
                if (!empty($sp['patron']) && is_array($sp['patron'])) {
                    foreach ($sp['patron'] as $cod) {
                        $hCod = $duracionTurnos[$cod] ?? ($cod !== 'LIB' && $cod !== '0' ? 6.0 : 0.0);
                        if ($hCod > 0) {
                            $totH += $hCod;
                            $totD++;
                        }
                    }
                }
                $horasProgPorPersonal[$pid] = $totH;
                $diasProgPorPersonal[$pid] = $totD;
            }

            // Consultar datos de asistencia efectiva en rrhh.Asistencia para este mes y año
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

            // Consultar descansos médicos CITT desde rrhh.PermisoLaboral (parámetros distintos para evitar error ODBC)
            $cittMap = AttendancePeriod::loadMedicalDays($pdo, $anio, $mes);

            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

            foreach ($rows as $r) {
                $pid = intval($r['personal_id']);
                $reg = $r['regimen_laboral'];
                $patron = $patronPorPersonal[$pid] ?? null;

                $diasProg = $diasProgPorPersonal[$pid] ?? 0;
                $horasProg = $horasProgPorPersonal[$pid] ?? ($reg === 'ADMINISTRATIVO' ? 176.0 : 150.0);

                $diasLab = 0;
                $minTard = 0;
                $faltas = 0;
                $permisos = 0;
                $horasEfectivas = 0.0;

                $workerAsist = $asistMap[$pid] ?? [];
                $incompleto = empty($workerAsist) || $patron === null;
                foreach ($workerAsist as $registro) {
                    if (in_array(strtoupper(trim((string)$registro['Estado'])), ['SIN_DATOS', 'EN_CURSO', 'PROGRAMADO'], true)) $incompleto = true;
                }
                foreach ($patron ?? [] as $dia => $codigo) {
                    if (($duracionTurnos[$codigo] ?? 0) > 0 && !isset($workerAsist[$dia + 1])) $incompleto = true;
                }

                if (!empty($workerAsist)) {
                    for ($d = 1; $d <= $diasEnMes; $d++) {
                        $turno = ($patron !== null && isset($patron[$d - 1])) ? $patron[$d - 1] : 'LIB';
                        $hTurno = $duracionTurnos[$turno] ?? 0.0;
                        if ($hTurno <= 0 && $turno !== 'LIB' && $turno !== '0') {
                            $hTurno = ($reg === 'ADMINISTRATIVO' ? 8.0 : 6.0);
                        }

                        $as = $workerAsist[$d] ?? null;
                        if ($as) {
                            $st = strtoupper(trim((string)$as['Estado']));
                            $obs = (string)($as['Observacion'] ?? '');

                            if (in_array($st, ['PUNTUAL', 'ASISTIO'])) {
                                $diasLab++;
                                $horasEfectivas += ($hTurno > 0 ? $hTurno : ($reg === 'ADMINISTRATIVO' ? 8.0 : 6.0));
                            } elseif ($st === 'TARDANZA') {
                                $diasLab++;
                                $horasEfectivas += ($hTurno > 0 ? $hTurno : ($reg === 'ADMINISTRATIVO' ? 8.0 : 6.0));
                                if (preg_match('/Tardanza de (\d+) min/i', $obs, $m)) {
                                    $minTard += intval($m[1]);
                                }
                            } elseif (strpos($st, 'FALTA') !== false) {
                                $faltas++;
                            } elseif (strpos($st, 'PERMISO') !== false || strpos($st, 'LICENCIA') !== false) {
                                $permisos++;
                                $horasEfectivas += ($hTurno > 0 ? $hTurno : ($reg === 'ADMINISTRATIVO' ? 8.0 : 6.0));
                            }
                        }
                    }
                }

                $citt = $cittMap[$pid] ?? 0;

                $personalConsolidado[] = [
                    $pid,
                    $r['dni'],
                    $r['nombre_completo'],
                    $r['cargo'],
                    intval($r['servicio_id']),
                    $r['servicio_nombre'],
                    $reg,
                    (stripos($r['condicion_laboral'], 'Nombrad') !== false ? 'NOMBRADO' : 'CAS'),
                    $diasProg,
                    $diasLab,
                    $minTard,
                    $faltas,
                    $permisos,
                    $citt,
                    round($horasEfectivas, 1),
                    empty($workerAsist) ? 'SIN_DATOS' : ($incompleto ? 'PENDIENTE' : null)
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error en api/reportes/consolidado.php: " . $e->getMessage());
        apiError(503, 'No se pudo consultar la información del reporte.');
    }
}

$filas = [];
$totalTardanzasMinutos = 0;
$totalDiasDescontar = 0;
$totalDescansosCitt = 0;
$totalPermisosGoce = 0;

foreach ($personalConsolidado as $p) {
    // Filtros
    if ($servicioId !== null && $p[4] !== $servicioId) continue;
    if ($condicionFiltro !== null && $condicionFiltro !== 'TODOS' && $p[7] !== $condicionFiltro) continue;
    if ($regimenFiltro !== null && $regimenFiltro !== 'TODOS' && $p[6] !== $regimenFiltro) continue;

    $totalTardanzasMinutos += $p[10];
    $totalDiasDescontar += $p[11];
    $totalPermisosGoce += $p[12];
    $totalDescansosCitt += $p[13];

    $filas[] = [
        'personal_id'           => $p[0],
        'dni'                   => $p[1],
        'nombre_completo'       => $p[2],
        'cargo'                 => $p[3],
        'servicio_id'           => $p[4],
        'servicio_nombre'       => $p[5],
        'regimen_laboral'       => $p[6],
        'condicion_laboral'     => $p[7],
        'dias_programados'      => $p[8],
        'dias_laborados'        => $p[9],
        'minutos_tardanza_total'=> $p[10],
        'faltas_injustificadas' => $p[11],
        'permisos_con_goce'     => $p[12],
        'descansos_citt'        => $p[13],
        'horas_netas'           => $p[14],
        'estado_planilla'       => $p[15] ?? (($p[11] > 0 || $p[10] > 60) ? 'CON_DESCUENTO' : 'CONFORME')
    ];
}

echo json_encode([
    'ok'           => true,
    'periodo'      => [
        'mes'        => $mes,
        'mes_nombre' => $nombresMeses[$mes],
        'anio'       => $anio
    ],
    'resumen'      => [
        'total_personal'          => count($filas),
        'total_tardanzas_minutos' => $totalTardanzasMinutos,
        'total_dias_descontar'    => $totalDiasDescontar,
        'total_descansos_citt'    => $totalDescansosCitt,
        'total_permisos_goce'     => $totalPermisosGoce
    ],
    'filas'        => $filas,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
