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

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida.');

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
if (SnapshotStore::has("asistencia_diaria_{$fecha}")) {
    $snap = SnapshotStore::get("asistencia_diaria_{$fecha}");
    $snapAsist = $snap['data'] ?? null;
}

// 2. Si no hay snapshot, consultar directamente SQL Server
if (empty($snapAsist) && $pdo !== null) {
    try {
        // Verificar si rrhh.Asistencia tiene registros para esta fecha
        $stmtChk = $pdo->prepare("SELECT COUNT(*) as cnt FROM rrhh.Asistencia WHERE Fecha = ?");
        $stmtChk->execute([$fecha]);
        $cntAsist = (int)($stmtChk->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        // Si no hay registros en rrhh.Asistencia, verificar si hay marcaciones biométricas para auto-conciliar
        if ($cntAsist === 0) {
            $stmtBio = $pdo->prepare("SELECT COUNT(*) as cnt FROM rrhh.MarcacionBiometrica WHERE CAST(FechaHora AS DATE) = ?");
            $stmtBio->execute([$fecha]);
            $cntBio = (int)($stmtBio->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

            if ($cntBio > 0) {
                // Auto-conciliación en vivo
                AttendanceReconciler::conciliarFecha($fecha, $pdo);
            }
        }

        // Consultar personal y asistencia
        $sql = "
            SELECT 
                p.PersonalID,
                per.NumeroDocumento AS DNI,
                per.Nombres,
                per.ApellidoPaterno,
                per.ApellidoMaterno,
                per.Telefono,
                per.Correo AS CorreoElectronico,
                ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                ISNULL(prof.Nombre, 'General') AS NombreProfesion,
                s.ServicioID,
                ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                'ASISTENCIAL' AS RegimenLaboral,
                ISNULL(tc.Nombre, 'CAS') AS CondicionLaboral,
                t.TurnoID,
                ISNULL(t.Nombre, 'Mañana Ordinario') AS NombreTurno,
                ISNULL(CONVERT(VARCHAR(8), t.HoraInicio, 108), '07:00:00') AS HoraProgInicio,
                ISNULL(CONVERT(VARCHAR(8), t.HoraFin, 108), '13:00:00') AS HoraProgFin,
                a.AsistenciaID,
                CONVERT(VARCHAR(8), a.HoraEntrada, 108) AS HoraEntrada,
                CONVERT(VARCHAR(8), a.HoraSalida, 108) AS HoraSalida,
                a.Estado AS EstadoAsistencia,
                a.Observacion
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Profesion prof ON ap.ProfesionID = prof.ProfesionID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            LEFT JOIN rrhh.Horario h ON p.PersonalID = h.PersonalID AND h.DiaSemana = :diaSemana AND h.Estado = 1 AND h.FechaInicio <= :fechaInicio AND (h.FechaFin IS NULL OR h.FechaFin >= :fechaFin)
            LEFT JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
            WHERE p.Estado = 1
            ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
        ";

        $diaSemana = (int)date('N', strtotime($fecha));
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':diaSemana'    => $diaSemana,
            ':fechaInicio'  => $fecha,
            ':fechaFin'     => $fecha,
            ':fechaAsist'   => $fecha
        ]);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $asistenciasCalculadas = [];
        foreach ($rawRecords as $row) {
            $nombreCompleto = trim("{$row['ApellidoPaterno']} {$row['ApellidoMaterno']} {$row['Nombres']}");
            $asistenciasCalculadas[] = procesarFilaAsistencia($row, $nombreCompleto, $fecha);
        }

        if (!empty($asistenciasCalculadas)) {
            $snapAsist = $asistenciasCalculadas;
            SnapshotStore::set("asistencia_diaria_{$fecha}", $snapAsist, ['source' => 'SQL_SERVER_LIVE', 'fecha' => $fecha]);
        }
    } catch (Throwable $e) {
        error_log("Error al consultar asistencia para dashboard: " . $e->getMessage());
    }
}

// Estadísticas biométricas reales
$bioStats = [
    'estado'                => 'ONLINE',
    'terminal_id'           => 'BIO-PUERTA-PRINCIPAL-01',
    'ultima_sincronizacion' => date('H:i:s'),
    'total_marcaciones_hoy' => 0,
    'dispositivos_activos'  => 4
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
            'efectividad_porcentaje' => 100.0
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
        $minTard = intval($item['minutos_tardanza'] ?? 10);
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
    } else {
        $presentes++;
    }
}

$efectividad = $totProg > 0 ? round(($presentes / $totProg) * 100, 2) : 100;

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
