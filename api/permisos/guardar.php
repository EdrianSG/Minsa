<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('ausencias', ['POST'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/permisos/guardar.php
 * Hospital Distrital de Pacasmayo - ETAPA 6
 * 
 * Registra una nueva papeleta, descanso médico CITT o solicitud de permiso en rrhh.PermisoLaboral
 * con validación de fechas (FechaFin >= FechaInicio) y documento de sustento.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$personalId        = intval($data['personal_id'] ?? 0);
$tipoPermisoId     = intval($data['tipo_permiso_id'] ?? 1);
$fechaInicio       = trim($data['fecha_inicio'] ?? '');
$fechaFin          = trim($data['fecha_fin'] ?? '');
$documentoSustento = trim($data['documento_sustento'] ?? '');
$motivo            = trim($data['motivo'] ?? '');
$estadoInicial     = 'PENDIENTE';

if (!apiValidDate($fechaInicio) || !apiValidDate($fechaFin)) apiError(400, 'Fechas inválidas. Use YYYY-MM-DD.');
if ($tipoPermisoId <= 0 || $motivo === '') apiError(400, 'Tipo de permiso y motivo son obligatorios.');

// Validaciones
if ($personalId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Debe seleccionar un trabajador válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

apiRequirePersonalAccess($personalId);

if (empty($fechaInicio) || empty($fechaFin)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Debe especificar el rango completo de fechas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtotime($fechaFin) < strtotime($fechaInicio)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Validación normativa: La fecha fin no puede ser anterior a la fecha de inicio.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Descansos médicos requieren CITT obligatorio
if ($tipoPermisoId === 1 && empty($documentoSustento)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Para descanso médico es obligatorio ingresar el número de CITT / Certificado médico.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dias = round((strtotime($fechaFin) - strtotime($fechaInicio)) / 86400) + 1;
$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);
$nuevoId = time();

if ($dbConnected) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rrhh.PermisoLaboral (PersonalID, TipoPermisoLaboralID, FechaInicio, FechaFin, Motivo, Estado)
            VALUES (:pid, :tid, :fini, :ffin, :mot, :est)
        ");
        $stmt->execute([
            ':pid'  => $personalId,
            ':tid'  => $tipoPermisoId,
            ':fini' => $fechaInicio,
            ':ffin' => $fechaFin,
            ':mot'  => "Sustento: $documentoSustento. Motivo: $motivo",
            ':est'  => $estadoInicial
        ]);
        $nuevoId = $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log('Guardar permiso: ' . $e->getMessage());
        apiError(500, 'No se pudo registrar la solicitud.');
    }
}

echo json_encode([
    'ok'                 => true,
    'message'            => 'Papeleta de justificación registrada satisfactoriamente.',
    'permiso_id'         => $nuevoId,
    'personal_id'        => $personalId,
    'dias_solicitados'   => $dias,
    'fecha_inicio'       => $fechaInicio,
    'fecha_fin'          => $fechaFin,
    'documento_sustento' => $documentoSustento,
    'estado'             => $estadoInicial,
    'conexion_sql'       => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
