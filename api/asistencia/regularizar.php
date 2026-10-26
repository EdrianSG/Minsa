<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('asistencia', ['POST'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

/**
 * API Endpoint: /api/asistencia/regularizar.php
 * Hospital Distrital de Pacasmayo - ETAPA 5
 * 
 * Permite a Recursos Humanos registrar o subsanar marcaciones de asistencia
 * ante contingencias del reloj biométrico, comisiones de servicio o papeletas autorizadas,
 * manteniendo trazabilidad y auditoría.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auditoria.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$personalId   = intval($data['personal_id'] ?? 0);
$fecha        = $data['fecha'] ?? date('Y-m-d');
$horaEntrada  = !empty($data['hora_entrada']) ? trim($data['hora_entrada']) : null;
$horaSalida   = !empty($data['hora_salida']) ? trim($data['hora_salida']) : null;
$motivo       = trim($data['motivo'] ?? 'REGULARIZACION_RRHH');
$sustento     = trim($data['documento_sustento'] ?? '');
$observacion  = trim($data['observacion'] ?? '');

if ($personalId <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'message' => 'Identificador de personal inválido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

apiRequirePersonalAccess($personalId);

if (!apiValidDate($fecha) || ($horaEntrada !== null && !apiValidTime($horaEntrada))
    || ($horaSalida !== null && !apiValidTime($horaSalida))
    || !apiValidTime($data['hora_prog_inicio'] ?? '08:00')) {
    apiError(400, 'Fecha u hora inválida.');
}
if ($horaEntrada === null && $horaSalida === null) apiError(400, 'Ingrese al menos una marcación.');
if ($sustento === '' || $motivo === '') apiError(400, 'El motivo y documento de sustento son obligatorios.');

require_once __DIR__ . '/../../includes/AttendanceRules.php';
$horaProgInicio = $data['hora_prog_inicio'] ?? '08:00';

$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);

if ($dbConnected) {
    try {
        // Verificar si ya existe registro de asistencia para esa fecha
        $checkStmt = $pdo->prepare("SELECT AsistenciaID, HoraEntrada, HoraSalida FROM rrhh.Asistencia WHERE PersonalID = :pid AND Fecha = :fec");
        $checkStmt->execute([':pid' => $personalId, ':fec' => $fecha]);
        $existente = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $horaEntrada = $horaEntrada ?? $existente['HoraEntrada'];
            $horaSalida = $horaSalida ?? $existente['HoraSalida'];
        }
        $evaluation = AttendanceRules::evaluate($horaEntrada, $horaProgInicio);
        $estadoEvaluado = $evaluation['estado'];
        $minutosTardanza = $evaluation['minutos'];
        $obsCompleta = "Regularizado: $motivo. Sustento: $sustento. Obs: $observacion";

        if ($existente) {
            $upd = $pdo->prepare("
                UPDATE rrhh.Asistencia
                SET HoraEntrada = :he, HoraSalida = :hs, Estado = :est, Observacion = :obs
                WHERE AsistenciaID = :aid
            ");
            $upd->execute([
                ':he'  => $horaEntrada,
                ':hs'  => $horaSalida,
                ':est' => $estadoEvaluado,
                ':obs' => $obsCompleta,
                ':aid' => $existente['AsistenciaID']
            ]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO rrhh.Asistencia (PersonalID, Fecha, HoraEntrada, HoraSalida, Estado, Observacion)
                VALUES (:pid, :fec, :he, :hs, :est, :obs)
            ");
            $ins->execute([
                ':pid' => $personalId,
                ':fec' => $fecha,
                ':he'  => $horaEntrada,
                ':hs'  => $horaSalida,
                ':est' => $estadoEvaluado,
                ':obs' => $obsCompleta
            ]);
        }
    } catch (Exception $e) {
        error_log('Regularización: ' . $e->getMessage());
        apiError(500, 'No se pudo guardar la regularización.');
    }
}

$currentUser = Auth::getCurrentUser();
Auditoria::registrar(
    $currentUser['usuario_id'] ?? 1,
    'ASISTENCIA',
    'REGULARIZAR',
    'rrhh.Asistencia',
    (string)$personalId,
    "Regularización de marcación para el personal ID $personalId el día $fecha ($estadoEvaluado). Motivo: $motivo. Sustento: $sustento"
);

echo json_encode([
    'ok'                 => true,
    'message'            => 'Marcación regularizada satisfactoriamente en el registro institucional.',
    'personal_id'        => $personalId,
    'fecha'              => $fecha,
    'hora_entrada'       => $horaEntrada,
    'hora_salida'        => $horaSalida,
    'nuevo_estado'       => $estadoEvaluado,
    'minutos_tardanza'   => $minutosTardanza,
    'motivo'             => $motivo,
    'documento_sustento' => $sustento,
    'fecha_registro'     => date('Y-m-d H:i:s'),
    'conexion_sql'       => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
