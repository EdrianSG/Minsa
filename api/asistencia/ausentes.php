<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/asistencia/ausentes.php
 * Hospital Distrital de Pacasmayo
 * 
 * Endpoint para la consulta "¿Quién faltó hoy?":
 * - Identifica al personal que no ha registrado ingreso en el biométrico o incurrió en falta administrativa (>30m) desde rrhh.Asistencia.
 * - Clasifica la criticidad de la ausencia según el servicio asistencial (Emergencia, UCI, Centro Obstétrico, etc.).
 * - Entrega los datos directos de contacto para coordinación de reemplazo.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida. Formato YYYY-MM-DD requerido.');

$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : null;

// Restricción por servicio según el rol del usuario
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    if ($servicioId === null) {
        $servicioId = $userAllowedServ;
    } elseif ($servicioId !== $userAllowedServ) {
        apiError(403, 'No tiene autorización para consultar ausencias de otro servicio.');
    }
}

// Servicios críticos que demandan reemplazo asistencial urgente
$serviciosCriticos = [
    'EMERGENCIA Y CUIDADOS CRÍTICOS',
    'UNIDAD DE CUIDADOS INTENSIVOS',
    'UNIDAD DE CUIDADOS INTENSIVOS (UCI)',
    'HOSPITALIZACIÓN MEDICINA Y CIRUGÍA',
    'HOSPITALIZACIÓN MEDICINA',
    'CENTRO QUIRÚRGICO',
    'CENTRO OBSTÉTRICO',
    'PEDIATRÍA Y NEONATOLOGÍA'
];

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$ausentes = [];
require_once __DIR__ . '/../../includes/SnapshotStore.php';
require_once __DIR__ . '/../../includes/AttendanceSchedule.php';
$backupKey = 'ausentes_' . $fecha . '_serv_' . ($servicioId ?? 0);

if ($dbConnected) {
    try {
        $whereSql = "WHERE CAST(a.Fecha AS DATE) = :fecha AND a.Estado LIKE '%Falta%' AND p.Activo = 1";
        $params = [
            ':fecha' => $fecha
        ];

        if ($servicioId !== null && $servicioId > 0) {
            $whereSql .= " AND s.ServicioID = :servId";
            $params[':servId'] = $servicioId;
        }

        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                p.PersonalID,
                p.NumeroDocumento AS dni,
                COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, ''), ' ', p.Nombres))), ''), p.NombreCompletoOriginal, p.CodigoEmpleado) AS nombre_completo,
                p.Telefono AS telefono,
                p.Correo AS correo,
                ISNULL(c.Nombre, 'Personal Hospitalario') AS cargo,
                ISNULL(s.ServicioID, 7) AS servicio_id,
                s.ServicioID,
                ISNULL(s.Nombre, 'Servicio Hospitalario') AS servicio_nombre,
                p.CondicionLaboral AS condicion_laboral,
                'Turno Asignado' AS turno_nombre,
                '08:00' AS hora_prog_inicio,
                '14:00' AS hora_prog_fin,
                a.Estado AS tipo_ausencia,
                CONVERT(VARCHAR(5), a.HoraEntrada, 108) AS hora_marcada,
                'SIN_JUSTIFICAR' AS justificacion_estado,
                a.Observacion AS motivo
            FROM rrhh.Asistencia a
            INNER JOIN rrhh.Personal p ON a.PersonalID = p.PersonalID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            {$whereSql}
            ORDER BY p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $r = AttendanceSchedule::apply($r, $fecha);
            $servUpper = strtoupper(trim((string)$r['servicio_nombre']));
            $esCritico = false;
            foreach ($serviciosCriticos as $crit) {
                if (strpos($servUpper, $crit) !== false) {
                    $esCritico = true;
                    break;
                }
            }

            $stUpper = strtoupper(trim((string)$r['tipo_ausencia']));
            $esFaltaAdmin = (strpos($stUpper, 'ADMINISTRATIVA') !== false);

            $ausentes[] = [
                'personal_id'          => intval($r['personal_id']),
                'dni'                  => $r['dni'],
                'nombre_completo'      => $r['nombre_completo'],
                'telefono'             => $r['telefono'] ?: 'No registrado',
                'correo'               => $r['correo'] ?: 'No registrado',
                'cargo'                => $r['cargo'],
                'servicio_id'          => intval($r['servicio_id']),
                'servicio_nombre'      => $r['servicio_nombre'],
                'condicion_laboral'    => (stripos($r['condicion_laboral'], 'Nombrad') !== false ? 'NOMBRADO' : 'CAS'),
                'turno_nombre'         => $r['NombreTurno'] ?? $r['turno_nombre'],
                'hora_prog_inicio'     => !empty($r['HoraProgInicio']) ? substr($r['HoraProgInicio'], 0, 5) : ($r['hora_prog_inicio'] ?: '08:00'),
                'hora_prog_fin'        => !empty($r['HoraProgFin']) ? substr($r['HoraProgFin'], 0, 5) : ($r['hora_prog_fin'] ?: '14:00'),
                'tipo_ausencia'        => $esFaltaAdmin ? 'FALTA_ADMINISTRATIVA' : 'FALTA_TOTAL',
                'hora_marcada'         => $r['hora_marcada'],
                'justificacion_estado' => 'SIN_JUSTIFICAR',
                'motivo'               => $r['motivo'] ?: 'Inasistencia sin marcación en biométrico.',
                'criticidad'           => $esCritico ? 'ALTA_URGENTE' : ($esFaltaAdmin ? 'MEDIA' : 'BAJA'),
                'requiere_reemplazo'   => $esCritico
            ];
        }
        SnapshotStore::set($backupKey, $ausentes, ['source'=>'SQL_SERVER_LIVE']);
    } catch (Throwable $e) {
        error_log("Error en api/asistencia/ausentes.php: " . $e->getMessage());
        apiError(503, 'No se pudo consultar las ausencias en SQL Server. No se muestran respaldos anteriores.');
    }
}

if (!$dbConnected) {
    $backup = SnapshotStore::get($backupKey);
    if (!$backup) apiError(503, 'SQL Server no está disponible y no existe un respaldo de ausencias para esta fecha.');
    $ausentes = $backup['data'];
}

$totalAltaCriticidad = count(array_filter($ausentes, function($a) { return $a['criticidad'] === 'ALTA_URGENTE'; }));
$totalRequierenReemplazo = count(array_filter($ausentes, function($a) { return $a['requiere_reemplazo']; }));

echo json_encode([
    'ok'                      => true,
    'fecha'                   => $fecha,
    'conexion_sql'            => $dbConnected,
    'total_ausentes'          => count($ausentes),
    'ausencias_criticas'      => $totalAltaCriticidad,
    'requieren_reemplazo_urg' => $totalRequierenReemplazo,
    'ausentes'                => $ausentes
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
