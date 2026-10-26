<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('ausencias', ['POST'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

/**
 * API Endpoint: /api/permisos/dictaminar.php
 * Hospital Distrital de Pacasmayo - ETAPA 6
 * 
 * Flujo de aprobación oficial por Recursos Humanos:
 * - Aprueba o Rechaza solicitudes en rrhh.PermisoLaboral.
 * - Al Aprobar un permiso, sincroniza automáticamente las fechas con rrhh.Asistencia,
 *   regularizando las inasistencias a estado PERMISO/LICENCIA con goce para evitar descuentos indebidos.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auditoria.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$permisoId   = intval($data['permiso_id'] ?? 0);
$accion      = strtoupper(trim($data['accion'] ?? 'APROBAR')); // APROBAR, RECHAZAR, ANULAR
$observacion = trim($data['observacion_dictamen'] ?? 'Aprobado conforme a directiva institucional de RR.HH.');

if ($permisoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Identificador de permiso inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($accion, ['APROBAR', 'RECHAZAR', 'ANULAR'], true)) apiError(400, 'Acción de dictamen inválida.');

$nuevoEstado = ($accion === 'RECHAZAR') ? 'RECHAZADO' : (($accion === 'ANULAR') ? 'ANULADO' : 'APROBADO');

$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);
$diasAfectados = 0;

if ($dbConnected) {
    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT PersonalID, Estado FROM rrhh.PermisoLaboral WITH (UPDLOCK, HOLDLOCK) WHERE PermisoLaboralID = ?');
        $lock->execute([$permisoId]);
        $permRow = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$permRow) { $pdo->rollBack(); apiError(404, 'Permiso inexistente.'); }

        $previous = $permRow['Estado'];
        $personalIdPermiso = (int)$permRow['PersonalID'];

        // Validar autorización por servicio
        if (!Auth::checkPersonalAccess($personalIdPermiso)) {
            $pdo->rollBack();
            apiError(403, 'No tiene autorización para dictaminar permisos de personal de otro servicio.');
        }

        // La reversión de un permiso aprobado necesita conservar el estado anterior de asistencia.
        if ($previous !== 'PENDIENTE') { $pdo->rollBack(); apiError(409, 'Solo se puede dictaminar una solicitud pendiente. La reversión de aprobaciones requiere revisión de RR.HH.'); }
        // Actualizar el estado en rrhh.PermisoLaboral
        $updPerm = $pdo->prepare("
            UPDATE rrhh.PermisoLaboral
            SET Estado = :est, Motivo = LEFT(COALESCE(Motivo, '') + ' | Dictamen RRHH: ' + :obs, 500)
            WHERE PermisoLaboralID = :pid
        ");
        $updPerm->execute([
            ':est' => $nuevoEstado,
            ':obs' => $observacion,
            ':pid' => $permisoId
        ]);

        // Si fue aprobado, obtener los datos del permiso para sincronizar con rrhh.Asistencia
        if ($nuevoEstado === 'APROBADO') {
            $selPerm = $pdo->prepare("
                SELECT pl.PersonalID, pl.FechaInicio, pl.FechaFin, tpl.Nombre AS TipoNombre
                FROM rrhh.PermisoLaboral pl
                INNER JOIN rrhh.TipoPermisoLaboral tpl ON pl.TipoPermisoLaboralID = tpl.TipoPermisoLaboralID
                WHERE pl.PermisoLaboralID = :pid
            ");
            $selPerm->execute([':pid' => $permisoId]);
            $permiso = $selPerm->fetch(PDO::FETCH_ASSOC);

            if ($permiso) {
                $pid = $permiso['PersonalID'];
                $fIni = new DateTime($permiso['FechaInicio']);
                $fFin = new DateTime($permiso['FechaFin']);
                $interval = new DateInterval('P1D');
                $daterange = new DatePeriod($fIni, $interval, $fFin->modify('+1 day'));

                $estadoAsist = (strpos(mb_strtoupper($permiso['TipoNombre'], 'UTF-8'), 'MÉDICO') !== false || strpos(mb_strtoupper($permiso['TipoNombre'], 'UTF-8'), 'MATERNIDAD') !== false)
                    ? 'LICENCIA'
                    : 'PERMISO';

                $obsAsist = "Justificado con {$permiso['TipoNombre']}. Dictamen RRHH: $observacion";

                foreach ($daterange as $dateObj) {
                    $fechaStr = $dateObj->format('Y-m-d');
                    // Upsert en rrhh.Asistencia
                    $check = $pdo->prepare("SELECT AsistenciaID FROM rrhh.Asistencia WHERE PersonalID = :pid AND Fecha = :fec");
                    $check->execute([':pid' => $pid, ':fec' => $fechaStr]);
                    $asistExist = $check->fetch(PDO::FETCH_ASSOC);

                    if ($asistExist) {
                        $updAsist = $pdo->prepare("
                            UPDATE rrhh.Asistencia
                            SET Estado = :est, Observacion = :obs
                            WHERE AsistenciaID = :aid
                        ");
                        $updAsist->execute([
                            ':est' => $estadoAsist,
                            ':obs' => $obsAsist,
                            ':aid' => $asistExist['AsistenciaID']
                        ]);
                    } else {
                        $insAsist = $pdo->prepare("
                            INSERT INTO rrhh.Asistencia (PersonalID, Fecha, HoraEntrada, HoraSalida, Estado, Observacion)
                            VALUES (:pid, :fec, NULL, NULL, :est, :obs)
                        ");
                        $insAsist->execute([
                            ':pid' => $pid,
                            ':fec' => $fechaStr,
                            ':est' => $estadoAsist,
                            ':obs' => $obsAsist
                        ]);
                    }
                    $diasAfectados++;
                }
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Dictamen: ' . $e->getMessage());
        apiError(500, 'No se pudo guardar el dictamen. Se revirtieron los cambios.');
    }
}

$currentUser = Auth::getCurrentUser();
Auditoria::registrar(
    $currentUser['usuario_id'] ?? 1,
    'AUSENCIAS',
    $accion,
    'rrhh.PermisoLaboral',
    (string)$permisoId,
    "Dictamen de solicitud de permiso ID $permisoId: $nuevoEstado. Obs: $observacion"
);

$mensaje = ($nuevoEstado === 'APROBADO')
    ? "Permiso APROBADO por Recursos Humanos. Se sincronizó el registro de asistencia institucional."
    : "Solicitud de justificación {$nuevoEstado} por la Jefatura de Recursos Humanos.";

echo json_encode([
    'ok'                   => true,
    'message'              => $mensaje,
    'permiso_id'           => $permisoId,
    'nuevo_estado'         => $nuevoEstado,
    'accion_ejecutada'     => $accion,
    'observacion_dictamen' => $observacion,
    'dias_sincronizados'   => $diasAfectados,
    'fecha_dictamen'       => date('Y-m-d H:i:s'),
    'conexion_sql'         => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
