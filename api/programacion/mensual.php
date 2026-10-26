<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('programacion', ['GET'], ['JEFE_RRHH', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/programacion/mensual.php
 * Hospital Distrital de Pacasmayo - ETAPA 4
 * 
 * Genera la matriz mensual de programación de turnos (Trabajador x Días del Mes)
 * con cálculo automático de horas y validación de las 150h del régimen asistencial
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/SnapshotStore.php';

$mes        = max(1, min(12, intval($_GET['mes'] ?? date('n')))); // Setiembre por defecto
$anio       = max(2020, min(2030, intval($_GET['anio'] ?? date('Y'))));
$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : 0;

apiRequireServiceAccess($servicioId);

$condicion  = trim($_GET['condicion'] ?? '');
$regimen    = trim($_GET['regimen'] ?? '');
$snapshotKey = "programacion_serv_{$servicioId}_{$anio}_{$mes}";

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$diasSemanaAbrev = [
    0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'
];

// Calcular número de días del mes calendario
$diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

// Construir array de días
$diasHeader = [];
for ($d = 1; $d <= $diasEnMes; $d++) {
    $fechaStr = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
    $diaSemanaNum = intval(date('w', strtotime($fechaStr))); // 0=Dom, 6=Sab
    $diasHeader[] = [
        'dia'             => $d,
        'fecha'           => $fechaStr,
        'dia_semana'      => $diasSemanaAbrev[$diaSemanaNum],
        'dia_semana_num'  => $diaSemanaNum,
        'es_fin_de_semana'=> ($diaSemanaNum === 0 || $diaSemanaNum === 6)
    ];
}

// Catálogo de turnos y equivalencia de horas computables (Soporta modelo tradicional 1, 2, 3 y MINSA M, T, N)
$catalogTurnos = [
    'M'     => ['codigo' => 'M',     'tecla' => '1',     'nombre' => 'Mañana (Turno 1)',           'horas' => 6.0,  'color' => '#0284c7'],
    '1'     => ['codigo' => '1',     'tecla' => '1',     'nombre' => 'Turno 1 (Mañana 6h)',        'horas' => 6.0,  'color' => '#0284c7'],
    'T'     => ['codigo' => 'T',     'tecla' => '2',     'nombre' => 'Tarde (Turno 2)',            'horas' => 6.0,  'color' => '#d97706'],
    '2'     => ['codigo' => '2',     'tecla' => '2',     'nombre' => 'Turno 2 (Tarde 6h)',         'horas' => 6.0,  'color' => '#d97706'],
    'N'     => ['codigo' => 'N',     'tecla' => '3',     'nombre' => 'Noche / Guardia (Turno 3)',  'horas' => 12.0, 'color' => '#7c3aed'],
    '3'     => ['codigo' => '3',     'tecla' => '3',     'nombre' => 'Turno 3 (Noche / Guardia)',  'horas' => 12.0, 'color' => '#7c3aed'],
    '1/2'   => ['codigo' => '1/2',   'tecla' => '1/2',   'nombre' => 'Doble Turno (Mañana + Tarde)','horas' => 12.0, 'color' => '#0d9488'],
    '2/3'   => ['codigo' => '2/3',   'tecla' => '2/3',   'nombre' => 'Tarde + Noche',              'horas' => 18.0, 'color' => '#4f46e5'],
    'G24'   => ['codigo' => 'G24',   'tecla' => '4',     'nombre' => 'Guardia 24 Horas (1/2/3)',   'horas' => 24.0, 'color' => '#b91c1c'],
    '1/2/3' => ['codigo' => '1/2/3', 'tecla' => '4',     'nombre' => 'Guardia Completa 24h (1/2/3)','horas' => 24.0, 'color' => '#b91c1c'],
    'GD'    => ['codigo' => 'GD',    'tecla' => 'GD',    'nombre' => 'Guardia Diurna (12h)',       'horas' => 12.0, 'color' => '#0d9488'],
    'GN'    => ['codigo' => 'GN',    'tecla' => 'GN',    'nombre' => 'Guardia Nocturna (12h)',     'horas' => 12.0, 'color' => '#4f46e5'],
    'ADM'   => ['codigo' => 'ADM',   'tecla' => 'A',     'nombre' => 'Administrativo (8h)',        'horas' => 8.0,  'color' => '#475569'],
    'VAC'   => ['codigo' => 'VAC',   'tecla' => 'V',     'nombre' => 'Vacaciones Programadas',     'horas' => 6.0,  'color' => '#0891b2'],
    'VAC-1' => ['codigo' => 'VAC-1', 'tecla' => 'V1',    'nombre' => 'Emergencia en Vacaciones (M)','horas' => 6.0, 'color' => '#e11d48'],
    'VAC-2' => ['codigo' => 'VAC-2', 'tecla' => 'V2',    'nombre' => 'Emergencia en Vacaciones (T)','horas' => 6.0, 'color' => '#e11d48'],
    'VAC-3' => ['codigo' => 'VAC-3', 'tecla' => 'V3',    'nombre' => 'Emergencia en Vacaciones (N)','horas' => 12.0,'color' => '#e11d48'],
    'DESC'  => ['codigo' => 'DESC',  'tecla' => 'D',     'nombre' => 'Descanso Médico / CITT',     'horas' => 6.0,  'color' => '#dc2626'],
    'LIB'   => ['codigo' => 'LIB',   'tecla' => '0',     'nombre' => 'Libre / Descanso',           'horas' => 0.0,  'color' => '#94a3b8']
];

// Nombres de Servicios según ID oficial de la BD
$serviciosHospital = [
    0  => 'Todos los Servicios (Plantel Completo)',
    7  => 'Emergencia y Cuidados Críticos',
    8  => 'Unidad de Cuidados Intensivos (UCI)',
    9  => 'Hospitalización Medicina',
    10 => 'Consulta Externa',
    11 => 'Pediatría y Neonatología',
    12 => 'Centro Obstétrico',
    13 => 'Centro Quirúrgico',
    14 => 'Laboratorio Clínico',
    15 => 'Farmacia y Dosis Unitaria',
    16 => 'Unidad de Personal / RR.HH.',
    17 => 'Unidad de Informática y Estadística'
];
$servicioNombre = $serviciosHospital[$servicioId] ?? 'Servicio Hospitalario';

// Lista del personal para la matriz
$personalServicio = [];
$esSnapshotLocal = false;
$snapshotMeta = null;
$pdo = Database::getConnection();

// 1. Si ya existe un Snapshot guardado para este servicio y mes, evaluar su consistencia
if (SnapshotStore::has($snapshotKey)) {
    $snap = SnapshotStore::get($snapshotKey);
    if ($snap && isset($snap['data']['personal_servicio']) && !empty($snap['data']['personal_servicio'])) {
        $candidatePersonal = $snap['data']['personal_servicio'];
        // Si hay conexión con SQL Server y el snapshot proviene de una versión desactualizada con conteo mínimo (ej. 13 vs 283)
        $needRefresh = false;
        if ($pdo && $servicioId === 0 && count($candidatePersonal) < 50) {
            $needRefresh = true;
        }
        if (!$needRefresh) {
            $personalServicio = $candidatePersonal;
            $esSnapshotLocal = true;
            $snapshotMeta = $snap['_meta'] ?? null;
        }
    }
}

// 2. Si no hay snapshot válido o requiere refresco, consultar a SQL Server y crear/actualizar snapshot base
if (empty($personalServicio) && $pdo) {
    try {
        $whereServicio = ($servicioId > 0) ? "WHERE s.ServicioID = :servId" : "WHERE 1=1";
        $sqlPersonal = "
            SELECT 
                p.PersonalID AS personal_id,
                p.CodigoEmpleado AS codigo_empleado,
                per.NumeroDocumento AS dni,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
                ISNULL(c.Nombre, 'Personal') AS cargo_nombre,
                ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre,
                s.ServicioID AS servicio_id,
                ISNULL(tc.Nombre, 'CAS') AS condicion_laboral,
                CASE 
                    WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 'ADMINISTRATIVO'
                    ELSE 'ASISTENCIAL'
                END AS regimen_laboral,
                CASE 
                    WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 0
                    ELSE 1
                END AS aplica_seti
            FROM organizacion.Servicio s
            INNER JOIN rrhh.AsignacionPersonal ap ON s.ServicioID = ap.ServicioID AND ap.EsActual = 1 AND ap.Estado = 1
            INNER JOIN rrhh.Personal p ON ap.PersonalID = p.PersonalID AND p.Estado = 1
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            {$whereServicio}
            ORDER BY 
                CASE WHEN c.Nombre LIKE '%Médico%' THEN 1 WHEN c.Nombre LIKE '%Enferm%' THEN 2 ELSE 3 END,
                per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
        ";
        $stmtP = $pdo->prepare($sqlPersonal);
        if ($servicioId > 0) {
            $stmtP->execute([':servId' => $servicioId]);
        } else {
            $stmtP->execute();
        }
        $rowsP = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rowsP)) {
            // Consultar turnos asignados en rrhh.Horario para cada trabajador
            $stmtH = $pdo->prepare("
                SELECT h.DiaSemana, t.Nombre AS TurnoNombre
                FROM rrhh.Horario h
                INNER JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
                WHERE h.PersonalID = :pid AND h.Estado = 1
            ");

            foreach ($rowsP as $rp) {
                $pid = intval($rp['personal_id']);
                $stmtH->execute([':pid' => $pid]);
                $pautaDias = [];
                while ($h = $stmtH->fetch(PDO::FETCH_ASSOC)) {
                    $dSem = intval($h['DiaSemana']);
                    $tName = $h['TurnoNombre'];
                    if (stripos($tName, 'Mañana') !== false) {
                        $cod = 'M';
                    } elseif (stripos($tName, 'Tarde') !== false) {
                        $cod = 'T';
                    } elseif (stripos($tName, 'Nocturna') !== false || stripos($tName, 'Noche') !== false) {
                        $cod = 'N';
                    } elseif (stripos($tName, 'Diurna') !== false) {
                        $cod = 'GD';
                    } elseif (stripos($tName, 'Admin') !== false) {
                        $cod = 'ADM';
                    } else {
                        $cod = 'M';
                    }
                    $pautaDias[$dSem] = $cod;
                }

                // Armar el array de los días del mes calendario según la pauta asignada
                $diasPatron = [];
                for ($d = 1; $d <= $diasEnMes; $d++) {
                    $fechaStr = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
                    $phpW = intval(date('w', strtotime($fechaStr))); // 0=Dom, 1=Lun..6=Sab
                    $diaSemanaNum = ($phpW === 0) ? 7 : $phpW; // 1=Lun..7=Dom

                    if (!empty($pautaDias)) {
                        $diasPatron[] = $pautaDias[$diaSemanaNum] ?? 'LIB';
                    } else {
                        if ($rp['regimen_laboral'] === 'ADMINISTRATIVO') {
                            $diasPatron[] = ($diaSemanaNum >= 6) ? 'LIB' : 'ADM';
                        } else {
                            $diasPatron[] = ($diaSemanaNum % 2 === 0) ? 'M' : 'T';
                        }
                    }
                }

                $rp['patron'] = $diasPatron;
                $personalServicio[] = $rp;
            }

            // Persistir como Snapshot inicial en disco (solo una vez)
            if (!empty($personalServicio)) {
                SnapshotStore::set($snapshotKey, [
                    'personal_servicio' => $personalServicio,
                    'servicio_id'       => $servicioId,
                    'mes'               => $mes,
                    'anio'              => $anio
                ], ['servicio_id' => $servicioId, 'ultimo_autor' => 'Sistema (Carga Base SQL)']);
                
                $snapInit = SnapshotStore::get($snapshotKey);
                $snapshotMeta = $snapInit['_meta'] ?? null;
                $esSnapshotLocal = true;
            }
        }
    } catch (Exception $e) {
        $personalServicio = [];
    }
}

// Si la base de datos no respondió o no trajo personal, consultar si existe un Snapshot real en disco
if (empty($personalServicio) && SnapshotStore::has($snapshotKey)) {
    $snap = SnapshotStore::get($snapshotKey);
    if ($snap && isset($snap['data']['personal_servicio'])) {
        $personalServicio = $snap['data']['personal_servicio'];
        $esSnapshotLocal = true;
        $snapshotMeta = $snap['_meta'] ?? null;
    }
}

if (empty($personalServicio)) apiError(503, 'No hay programación disponible para este servicio y periodo.');

// Filtrar por condición si aplica
if ($condicion !== '') {
    $personalServicio = array_values(array_filter($personalServicio, fn($p) => strtoupper($p['condicion_laboral']) === strtoupper($condicion)));
}

// Procesar matriz y calcular horas por trabajador
$matriz = [];
$coberturaDiaria = [];
for ($d = 1; $d <= $diasEnMes; $d++) {
    $coberturaDiaria[$d] = [
        'total'  => 0,
        'manana' => 0,
        'tarde'  => 0,
        'noche'  => 0,
        'guardia'=> 0
    ];
}

$totalPersonalServicio = count($personalServicio);
$totalHorasServicio = 0;
$personalCumple150h = 0;
$personalObservado = 0;

foreach ($personalServicio as $p) {
    $programacionDias = [];
    $horasAcumuladas = 0.0;

    for ($d = 1; $d <= $diasEnMes; $d++) {
        $idx = $d - 1;
        // Si el día es sábado o domingo y es administrativo, asigna LIB
        $fechaStr = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
        $w = intval(date('w', strtotime($fechaStr)));
        $esFDS = ($w === 0 || $w === 6);

        if (isset($p['patron'][$idx])) {
            $codTurno = $p['patron'][$idx];
        } elseif ($p['regimen_laboral'] === 'ADMINISTRATIVO' && $esFDS) {
            $codTurno = 'LIB';
        } else {
            $codTurno = 'LIB';
        }

        $horasTurno = $catalogTurnos[$codTurno]['horas'] ?? 0.0;
        $horasAcumuladas += $horasTurno;

        $programacionDias[$d] = [
            'codigo' => $codTurno,
            'horas'  => $horasTurno,
            'color'  => $catalogTurnos[$codTurno]['color'] ?? '#94a3b8'
        ];

        // Sumar a cobertura diaria
        if ($codTurno !== 'LIB') {
            $coberturaDiaria[$d]['total']++;
            if ($codTurno === 'M') $coberturaDiaria[$d]['manana']++;
            if ($codTurno === 'T') $coberturaDiaria[$d]['tarde']++;
            if ($codTurno === 'N') $coberturaDiaria[$d]['noche']++;
            if (in_array($codTurno, ['GD', 'GN'])) $coberturaDiaria[$d]['guardia']++;
        }
    }

    $totalHorasServicio += $horasAcumuladas;
    $isAsist = ($p['regimen_laboral'] === 'ASISTENCIAL');
    $cumple = $isAsist ? (abs($horasAcumuladas - 150.0) < 0.1) : true;
    $dif = $isAsist ? ($horasAcumuladas - 150.0) : 0.0;

    if ($isAsist) {
        if ($cumple) {
            $personalCumple150h++;
        } else {
            $personalObservado++;
        }
    }

    $matriz[] = [
        'personal_id'       => $p['personal_id'],
        'dni'               => $p['dni'],
        'codigo_empleado'   => $p['codigo_empleado'],
        'nombre_completo'   => $p['nombre_completo'],
        'cargo_nombre'      => $p['cargo_nombre'],
        'condicion_laboral' => $p['condicion_laboral'],
        'regimen_laboral'   => $p['regimen_laboral'],
        'aplica_seti'       => $p['aplica_seti'],
        'programacion'      => $programacionDias,
        'total_horas'       => round($horasAcumuladas, 1),
        'cumple_150h'       => $cumple,
        'diferencia_horas'  => round($dif, 1)
    ];
}

$turnosDisponibles = [];
if ($pdo) {
    try {
        $stmtT = $pdo->query("SELECT TurnoID AS turno_id, Nombre AS nombre, HoraInicio AS hora_inicio, HoraFin AS hora_fin FROM rrhh.Turno WHERE Estado = 1 ORDER BY TurnoID");
        $turnosDisponibles = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
if (empty($turnosDisponibles)) {
    $turnosDisponibles = [
        ['turno_id' => 1, 'nombre' => 'Mañana Ordinario (07:00 - 13:00)', 'codigo' => 'M', 'horas' => 6.0],
        ['turno_id' => 2, 'nombre' => 'Tarde Ordinario (13:00 - 19:00)', 'codigo' => 'T', 'horas' => 6.0],
        ['turno_id' => 3, 'nombre' => 'Guardia Nocturna (19:00 - 07:00)', 'codigo' => 'N', 'horas' => 12.0],
        ['turno_id' => 4, 'nombre' => 'Guardia Diurna (07:00 - 19:00)', 'codigo' => 'GD', 'horas' => 12.0],
        ['turno_id' => 5, 'nombre' => 'Jornada Administrativa (08:00 - 16:45)', 'codigo' => 'ADM', 'horas' => 8.0]
    ];
}

echo json_encode([
    'ok'       => true,
    'periodo'  => [
        'mes'            => $mes,
        'mes_nombre'     => $nombresMeses[$mes],
        'anio'           => $anio,
        'total_dias'     => $diasEnMes,
        'servicio_id'    => $servicioId,
        'servicio_nombre'=> $servicioNombre,
        'estado_rol'     => 'BORRADOR',
        'dias'           => $diasHeader
    ],
    'resumen_servicio' => [
        'total_personal'          => $totalPersonalServicio,
        'total_horas_programadas' => round($totalHorasServicio, 1),
        'personal_cumple_150h'    => $personalCumple150h,
        'personal_observado'      => $personalObservado,
        'cobertura_diaria'        => $coberturaDiaria
    ],
    'catalog_turnos'     => $catalogTurnos,
    'turnos_disponibles' => $turnosDisponibles,
    'matriz'             => $matriz,
    'resiliencia'        => [
        'es_snapshot'   => $esSnapshotLocal,
        'snapshot_meta' => $snapshotMeta,
        'conexion_sql'  => ($pdo !== null && !$esSnapshotLocal)
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
