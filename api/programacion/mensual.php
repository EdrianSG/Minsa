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

require_once __DIR__ . '/../../includes/ProgramacionStore.php';
if ($pdo) {
    try {
        $sqlData = ProgramacionStore::read($pdo, $anio, $mes, $servicioId);
        $personalServicio = $sqlData['personal_servicio'];
        ProgramacionStore::snapshot($sqlData);
        $snapshotMeta = ['version'=>$sqlData['version'], 'source'=>'SQL_SERVER'];
    } catch (Throwable $e) {
        error_log('Consulta de programación SQL: ' . $e->getMessage());
        apiError(503, 'No se pudo consultar la programación SQL. Verifique la migración de programación mensual.');
    }
} else {
    $snap = SnapshotStore::get($snapshotKey);
    if (!$snap) apiError(503, 'SQL Server no está disponible y no existe un respaldo de este periodo.');
    $personalServicio = $snap['data']['personal_servicio'] ?? [];
    $esSnapshotLocal = true;
    $snapshotMeta = $snap['_meta'] ?? [];
    $snapshotMeta['version'] = $snapshotMeta['sql_version'] ?? $snapshotMeta['version'] ?? 1;
}

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

$turnosDisponibles = $pdo ? TurnoCatalog::load($pdo) : (SnapshotStore::get('turnos_catalogo_oficial')['data'] ?? []);
if ($pdo) SnapshotStore::set('turnos_catalogo_oficial', $turnosDisponibles, ['source'=>'SQL_SERVER_LIVE']);

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
        'conexion_sql'  => ($pdo !== null),
        'origen' => $esSnapshotLocal ? 'RESPALDO_EMERGENCIA' : 'SQL_SERVER',
        'solo_lectura' => $esSnapshotLocal
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
