<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('dashboard', ['GET'], []);

/**
 * API Endpoint: /api/dashboard/resumen.php
 * Hospital Distrital de Pacasmayo
 * 
 * Provee datos consolidados para el Dashboard de Recursos Humanos:
 * - KPIs de Asistencia (Programados, Presentes, Tardanzas, Faltas, Justificados)
 * - Distribución porcentual
 * - Casos pendientes de revisión
 * - Estado de integración biométrica en tiempo real
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../includes/WorkloadPolicy.php';
$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida.');
$workloadSql=WorkloadPolicy::sql('p.PersonalID',$fecha);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/SnapshotStore.php';
require_once __DIR__ . '/../../includes/attendance_evaluator.php';
require_once __DIR__ . '/../../includes/AttendanceReconciler.php';

$diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesNombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$ts = strtotime($fecha);
$diaNom = $diasSemana[intval(date('w', $ts))];
$mesNom = $mesesNombres[intval(date('n', $ts))];
$fechaFormateada = sprintf('%s, %d de %s de %04d', $diaNom, intval(date('j', $ts)), $mesNom, intval(date('Y', $ts)));

$snapAsist = null;
$pdo = Database::getConnection();

// 1. Intentar cargar del SnapshotStore
if (!$pdo && SnapshotStore::has("asistencia_diaria_{$fecha}")) {
    $snap = SnapshotStore::get("asistencia_diaria_{$fecha}");
    $snapAsist = $snap['data'] ?? null;
}

// 2. Si no hay snapshot, consultar directamente SQL Server
if (empty($snapAsist) && $pdo !== null) {
    try {
        // Consultar personal y asistencia
        $sql = "
            SELECT 
                p.PersonalID,
                p.NumeroDocumento AS DNI,
                p.Nombres,
                p.ApellidoPaterno,
                p.ApellidoMaterno,
                p.Telefono,
                p.Correo AS CorreoElectronico,
                ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                ISNULL(prof.Nombre, 'General') AS NombreProfesion,
                s.ServicioID,
                ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                {$workloadSql} AS RegimenLaboral,
                p.CondicionLaboral AS CondicionLaboral,
                NULL AS TurnoID,
                NULL AS NombreTurno,
                NULL AS HoraProgInicio,
                NULL AS HoraProgFin,
                a.AsistenciaID,
                CONVERT(VARCHAR(8), a.HoraEntrada, 108) AS HoraEntrada,
                CONVERT(VARCHAR(8), a.HoraSalida, 108) AS HoraSalida,
                a.Estado AS EstadoAsistencia,
                a.Observacion
            FROM rrhh.Personal p
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Profesion prof ON p.ProfesionID = prof.ProfesionID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
            WHERE p.Activo = 1
            ORDER BY p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':fechaAsist'   => $fecha
        ]);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $asistenciasCalculadas = [];
        foreach ($rawRecords as $row) {
            $nombreCompleto = trim("{$row['ApellidoPaterno']} {$row['ApellidoMaterno']} {$row['Nombres']}");
            $asistenciasCalculadas[] = procesarFilaAsistencia($row, $nombreCompleto, $fecha);
        }

        if (is_array($asistenciasCalculadas)) {
            $snapAsist = $asistenciasCalculadas;
            SnapshotStore::set("asistencia_diaria_{$fecha}", $snapAsist, ['source' => 'SQL_SERVER_LIVE', 'fecha' => $fecha]);
        }
    } catch (Throwable $e) {
        error_log("Error al consultar asistencia para dashboard: " . $e->getMessage());
        apiError(503, 'No se pudo consultar el resumen en SQL Server.');
    }
}

// Estadísticas biométricas reales
$bioStats = [
    'estado'                => 'SIN_MARCACIONES',
    'terminal_id'           => null,
    'ultima_sincronizacion' => null,
    'total_marcaciones_hoy' => 0,
    'dispositivos_activos'  => 0
];

if ($pdo !== null) {
    try {
        $stmtBioStats = $pdo->prepare("
            SELECT 
                COUNT(*) as TotalMarcaciones,
                COUNT(DISTINCT DispositivoID) as DispositivosActivos,
                MAX(FechaHora) as UltimaMarcacion
            FROM rrhh.MarcacionBiometrica
            WHERE CAST(FechaHora AS DATE) = ?
        ");
        $stmtBioStats->execute([$fecha]);
        $rowStats = $stmtBioStats->fetch(PDO::FETCH_ASSOC);
        if ($rowStats && $rowStats['TotalMarcaciones'] > 0) {
            $bioStats['estado'] = 'CON_MARCACIONES';
            $bioStats['total_marcaciones_hoy'] = (int)$rowStats['TotalMarcaciones'];
            $bioStats['dispositivos_activos'] = max(1, (int)$rowStats['DispositivosActivos']);
            if (!empty($rowStats['UltimaMarcacion'])) {
                $bioStats['ultima_sincronizacion'] = date('H:i:s', strtotime($rowStats['UltimaMarcacion']));
            }
        }
    } catch (Throwable $e) {
        // Fallback silencioso
    }
}

// Si aún no hay datos de asistencia (ej: fecha futura o sin registros)
if (empty($snapAsist)) {
    if (!$pdo) apiError(503, 'No hay datos para la fecha solicitada y SQL Server no está disponible.');
    $data = [
        'ok'               => true,
        'fecha'            => $fecha,
        'fecha_formateada' => $fechaFormateada,
        'is_mock'          => false,
        'is_empty'         => true,
        'kpis' => [
            'programados'  => 0,
            'presentes'    => 0,
            'tardanzas'    => 0,
            'faltas'       => 0,
            'justificados' => 0,
            'vacaciones'   => 0,
            'efectividad_porcentaje' => null
        ],
        'distribucion' => [
            ['estado' => 'Presentes', 'clave' => 'presentes', 'cantidad' => 0, 'porcentaje' => 0, 'color' => 'var(--status-present-accent)', 'descripcion' => 'Marcación puntual'],
            ['estado' => 'Tardanzas', 'clave' => 'tardanzas', 'cantidad' => 0, 'porcentaje' => 0, 'color' => 'var(--status-late-accent)', 'descripcion' => 'Ingreso con tardanza'],
            ['estado' => 'Faltas', 'clave' => 'faltas', 'cantidad' => 0, 'porcentaje' => 0, 'color' => 'var(--status-absent-accent)', 'descripcion' => 'Sin marcación']
        ],
        'casos_pendientes' => [],
        'alertas_notificaciones' => [],
        'biometrico' => $bioStats
    ];

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Restricción por servicio para jefaturas y supervisores
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    $snapAsist = array_values(array_filter($snapAsist, function($item) use ($userAllowedServ) {
        return (int)($item['servicio_id'] ?? 0) === $userAllowedServ;
    }));
}

// Procesar KPIs y distribución a partir de los datos consolidados
$totProg = count($snapAsist);
$presentes = 0;
$tardanzas = 0;
$faltas = 0;
$justificados = 0;
$vacaciones = 0;
$casosPendientes = [];

foreach ($snapAsist as $idx => $item) {
    $st = strtoupper($item['estado_evaluado'] ?? 'FALTA');
    if ($st === 'PUNTUAL' || $st === 'ASISTIO') {
        $presentes++;
    } elseif ($st === 'TARDANZA') {
        $tardanzas++;
        $minTard = intval($item['minutos_tardanza'] ?? 0);
        $casosPendientes[] = [
            'id'          => $idx + 1,
            'dni'         => $item['dni'],
            'trabajador'  => $item['nombre_completo'],
            'cargo'       => $item['cargo'],
            'servicio'    => $item['servicio_nombre'],
            'turno'       => $item['turno_nombre'] . " (" . ($item['hora_prog_inicio'] ?? '07:00') . " - " . ($item['hora_prog_fin'] ?? '13:00') . ")",
            'tipo_caso'   => 'Tardanza',
            'severidad'   => 'warning',
            'detalle'     => "Marcación {$item['hora_entrada']} (+{$minTard} min). Requiere validar justificación.",
            'hora_ingreso'=> $item['hora_entrada'],
            'minutos_tardanza' => $minTard
        ];
    } elseif (in_array($st, ['FALTA', 'FALTA_ADMINISTRATIVA'])) {
        $faltas++;
        if (count($casosPendientes) < 8) {
            $isFaltaAdmin = ($st === 'FALTA_ADMINISTRATIVA');
            $casosPendientes[] = [
                'id'          => $idx + 1,
                'dni'         => $item['dni'],
                'trabajador'  => $item['nombre_completo'],
                'cargo'       => $item['cargo'],
                'servicio'    => $item['servicio_nombre'],
                'turno'       => $item['turno_nombre'] . " (" . ($item['hora_prog_inicio'] ?? '07:00') . " - " . ($item['hora_prog_fin'] ?? '13:00') . ")",
                'tipo_caso'   => $isFaltaAdmin ? 'Falta Administrativa' : 'Falta',
                'severidad'   => 'danger',
                'detalle'     => $isFaltaAdmin 
                    ? "Marcación tardía a las {$item['hora_entrada']} (+{$item['minutos_tardanza']} min). Falta administrativa."
                    : 'Sin marcación de ingreso en el biométrico ni justificación previa.',
                'hora_ingreso'=> $item['hora_entrada'],
                'minutos_tardanza' => $item['minutos_tardanza'] ?? 0
            ];
        }
    } elseif ($st === 'JUSTIFICADO' || $st === 'PERMISO' || $st === 'LICENCIA') {
        $justificados++;
    } elseif ($st === 'VACACIONES' || $st === 'VAC') {
        $vacaciones++;
    }
}

$efectividad = $totProg > 0 ? round(($presentes / $totProg) * 100, 2) : null;

$data = [
    'ok'               => true,
    'fecha'            => $fecha,
    'fecha_formateada' => $fechaFormateada,
    'is_mock'          => false,
    'kpis' => [
        'programados'  => $totProg,
        'presentes'    => $presentes,
        'tardanzas'    => $tardanzas,
        'faltas'       => $faltas,
        'justificados' => $justificados,
        'vacaciones'   => $vacaciones,
        'efectividad_porcentaje' => $efectividad
    ],
    'distribucion' => [
        [
            'estado'     => 'Presentes',
            'clave'      => 'presentes',
            'cantidad'   => $presentes,
            'porcentaje' => $totProg > 0 ? round(($presentes / $totProg) * 100, 1) : 0,
            'color'      => 'var(--status-present-accent)',
            'descripcion'=> 'Marcación registrada dentro de la tolerancia de 5 minutos'
        ],
        [
            'estado'     => 'Tardanzas',
            'clave'      => 'tardanzas',
            'cantidad'   => $tardanzas,
            'porcentaje' => $totProg > 0 ? round(($tardanzas / $totProg) * 100, 1) : 0,
            'color'      => 'var(--status-late-accent)',
            'descripcion'=> 'Ingreso posterior a los 5 minutos y hasta los 30 minutos'
        ],
        [
            'estado'     => 'Faltas',
            'clave'      => 'faltas',
            'cantidad'   => $faltas,
            'porcentaje' => $totProg > 0 ? round(($faltas / $totProg) * 100, 1) : 0,
            'color'      => 'var(--status-absent-accent)',
            'descripcion'=> 'Sin registro de marcación o falta administrativa por retraso'
        ],
        [
            'estado'     => 'Justificados',
            'clave'      => 'justificados',
            'cantidad'   => $justificados,
            'porcentaje' => $totProg > 0 ? round(($justificados / $totProg) * 100, 1) : 0,
            'color'      => 'var(--status-justified-accent)',
            'descripcion'=> 'Papeletas de permiso, comisiones o licencias autorizadas'
        ],
        [
            'estado'     => 'Vacaciones',
            'clave'      => 'vacaciones',
            'cantidad'   => $vacaciones,
            'porcentaje' => $totProg > 0 ? round(($vacaciones / $totProg) * 100, 1) : 0,
            'color'      => 'var(--status-vacation-accent)',
            'descripcion'=> 'Personal con descanso vacacional según rol programado'
        ]
    ],
    'casos_pendientes' => $casosPendientes,
    'biometrico'       => $bioStats
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
