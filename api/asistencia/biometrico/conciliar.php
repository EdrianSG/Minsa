<?php
/**
 * API Endpoint: /api/asistencia/biometrico/conciliar.php
 * Hospital Distrital de Pacasmayo
 * 
 * Ejecuta el Motor de Conciliación Biométrica para procesar marcaciones crudas
 * y generar los registros oficiales en rrhh.Asistencia según normatividad MINSA.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/api_bootstrap.php';
apiGuard('asistencia', ['POST'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/AttendanceReconciler.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$fecha = trim((string)($input['fecha'] ?? ''));
$fechaInicio = trim((string)($input['fecha_inicio'] ?? ''));
$fechaFin = trim((string)($input['fecha_fin'] ?? ''));

try {
    $pdo = Database::getConnection();
    if (!$pdo) {
        apiError(503, 'SQL Server no está disponible para ejecutar la conciliación.');
    }

    if (!empty($fechaInicio) && !empty($fechaFin)) {
        if (!apiValidDate($fechaInicio) || !apiValidDate($fechaFin)) {
            apiError(400, 'Rango de fechas inválido. Formato esperado: YYYY-MM-DD.');
        }
        if ($fechaInicio > $fechaFin) {
            apiError(400, 'La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        $res = AttendanceReconciler::conciliarRango($fechaInicio, $fechaFin, $pdo);
        echo json_encode([
            'ok'         => true,
            'mensaje'    => "Conciliación completada exitosamente del {$fechaInicio} al {$fechaFin}.",
            'resultados' => $res
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Fecha individual
    if (empty($fecha)) {
        $fecha = date('Y-m-d');
    }

    if (!apiValidDate($fecha)) {
        apiError(400, 'Fecha inválida. Formato esperado: YYYY-MM-DD.');
    }

    $res = AttendanceReconciler::conciliarFecha($fecha, $pdo);

    echo json_encode([
        'ok'         => true,
        'mensaje'    => "Conciliación biométrica completada para la fecha {$fecha}.",
        'resultado'  => $res
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error durante la conciliación: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
