<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

/**
 * API Endpoint: /api/asistencia/diaria.php
 * Hospital Distrital de Pacasmayo - ETAPA 5
 *
 * Evalúa la asistencia diaria cruzando el turno programado vs. las marcaciones biométricas
 * según las reglas institucionales de Recursos Humanos y MINSA:
 * - Tolerancia de entrada: hasta +5 minutos de la HoraInicio (Puntual).
 * - Tardanza: entre +6 y +30 minutos de la HoraInicio (Cómputo exacto de minutos).
 * - Falta Administrativa: más de +30 minutos de la HoraInicio o sin marcación.
 * - Salida anticipada: antes de la HoraFin programada.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__.'/../../includes/WorkloadPolicy.php';

require_once __DIR__ . '/../../includes/attendance_evaluator.php';
require_once __DIR__ . '/../../includes/AttendanceReconciler.php';
require_once __DIR__.'/../../includes/AttendanceSummary.php';

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida.');
$workloadSql=WorkloadPolicy::sql('p.PersonalID',$fecha);
$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' && (int)$_GET['servicio_id'] > 0 ? (int)$_GET['servicio_id'] : null;

// Restringir a jefes de servicio y supervisores a su servicio asignado
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    if ($servicioId === null || $servicioId === 0) {
        $servicioId = $userAllowedServ;
    } elseif ($servicioId !== $userAllowedServ) {
        apiError(403, 'No tiene autorización para consultar la asistencia de otro servicio.');
    }
}

$filtroEstado = isset($_GET['estado']) && $_GET['estado'] !== '' ? strtoupper(trim($_GET['estado'])) : null;
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$pdo = Database::getConnection();
$asistencias = [];
$snapshotMeta = null;
$dbConnected = ($pdo !== null);

if ($dbConnected) {
    try {
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
                ap.AreaID,
                ar.Nombre AS NombreArea,
                s.ServicioID,
                s.Nombre AS NombreServicio,
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
            LEFT JOIN organizacion.Area ar ON ar.AreaID = ap.AreaID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
            WHERE p.Activo = 1
        ";

        $params = [
            ':fechaAsist' => $fecha
        ];

        if ($servicioId !== null) {
            $sql .= " AND s.ServicioID = :servicioId";
            $params[':servicioId'] = $servicioId;
        }

        $sql .= " ORDER BY p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawRecords as $row) {
            $nombreCompleto = trim("{$row['ApellidoPaterno']} {$row['ApellidoMaterno']} {$row['Nombres']}");
            $asistencias[] = procesarFilaAsistencia($row, $nombreCompleto, $fecha);
        }
        if (is_array($asistencias)) {
            require_once __DIR__ . '/../../includes/SnapshotStore.php';
            SnapshotStore::set('asistencia_diaria_' . $fecha . ($servicioId ? '_serv_' . $servicioId : ''), $asistencias, ['source' => 'SQL_SERVER_LIVE', 'fecha' => $fecha]);
        }
    } catch (Throwable $e) {
        error_log("Error al consultar asistencia diaria: " . $e->getMessage());
        apiError(503, 'No se pudo consultar la asistencia en SQL Server. No se muestran respaldos anteriores.');
    }
}

// Resiliencia local: si la BD está offline, intentar cargar el Snapshot de asistencia de esa fecha o la más reciente
if (!$dbConnected && empty($asistencias)) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    $snap = $servicioId ? SnapshotStore::get('asistencia_diaria_' . $fecha . '_serv_' . $servicioId) : null;
    $snap = $snap ?? SnapshotStore::get('asistencia_diaria_' . $fecha);
    if ($snap) {
        if (!empty($snap['data']) && is_array($snap['data'])) {
            $asistencias = $snap['data'];
            $snapshotMeta = $snap['_meta'] ?? [];
        }
    }
}

if (empty($asistencias)) {
    if (!$dbConnected) apiError(503, 'No hay datos de asistencia para la fecha solicitada y SQL Server no está disponible.');
    echo json_encode([
        'ok'              => true,
        'fecha'           => $fecha,
        'conexion_sql'    => $dbConnected,
        'consulta'        => ['fecha_hora'=>date(DATE_ATOM), 'respaldo_guardado_en'=>$snapshotMeta['saved_at'] ?? null],
        'resumen'         => AttendanceSummary::fromRows([]),
        'total_registros' => 0,
        'asistencias'     => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($servicioId !== null) {
    $asistencias = array_values(array_filter($asistencias, static fn($item) => (int)($item['servicio_id'] ?? 0) === $servicioId));
}

// Filtros en memoria para búsqueda de texto, servicio y estado
$filtrados = array_values(array_filter($asistencias, function ($item) use ($servicioId, $filtroEstado, $busqueda) {
    if ($servicioId !== null && intval($item['servicio_id']) !== $servicioId) {
        return false;
    }

    if ($filtroEstado !== null && $filtroEstado !== 'TODOS') {
        if ($filtroEstado === 'AUSENTES' || $filtroEstado === 'FALTA') {
            if (!in_array($item['estado_evaluado'], ['FALTA', 'FALTA_ADMINISTRATIVA'])) {
                return false;
            }
        } elseif ($item['estado_evaluado'] !== $filtroEstado) {
            return false;
        }
    }

    if ($busqueda !== '') {
        $term = mb_strtolower($busqueda, 'UTF-8');
        $encontrado = (
            strpos(mb_strtolower($item['nombre_completo'], 'UTF-8'), $term) !== false ||
            strpos((string)($item['dni'] ?? ''), $term) !== false ||
            strpos(mb_strtolower((string)($item['cargo'] ?? ''), 'UTF-8'), $term) !== false ||
            strpos(mb_strtolower((string)($item['servicio_nombre'] ?? ''), 'UTF-8'), $term) !== false
        );
        if (!$encontrado) return false;
    }

    return true;
}));

$resumen = AttendanceSummary::fromRows($asistencias);

echo json_encode([
    'ok'                   => true,
    'fecha'                => $fecha,
    'conexion_sql'         => $dbConnected,
    'consulta'             => ['fecha_hora'=>date(DATE_ATOM), 'respaldo_guardado_en'=>$snapshotMeta['saved_at'] ?? null],
    'resumen'              => $resumen,
    'total_registros'      => count($filtrados),
    'asistencias'          => $filtrados
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
