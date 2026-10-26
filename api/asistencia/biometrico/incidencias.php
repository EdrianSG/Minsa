<?php
/**
 * API Endpoint: /api/asistencia/biometrico/incidencias.php
 * Hospital Distrital de Pacasmayo - Fase 2
 * 
 * Consulta las incidencias técnicas registradas para una importación específica.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';

$importacionId = isset($_GET['importacion_id']) ? intval($_GET['importacion_id']) : 0;
if ($importacionId <= 0) {
    apiError(400, "Identificador de importación requerido.");
}

$pdo = Database::getConnection();
if ($pdo === null) {
    apiError(503, "SQL Server no disponible.");
}

try {
    $stmt = $pdo->prepare("
        SELECT TOP 100
            IncidenciaID,
            ImportacionID,
            MarcacionID,
            BiostarUserID,
            TipoIncidencia,
            Nivel,
            Detalle,
            CONVERT(VARCHAR(19), FechaHoraEvento, 120) AS FechaHoraEvento,
            CONVERT(VARCHAR(19), FechaHoraRegistro, 120) AS FechaHoraRegistro
        FROM rrhh.IncidenciaBiometrica
        WHERE ImportacionID = :id
        ORDER BY IncidenciaID ASC
    ");
    $stmt->execute([':id' => $importacionId]);
    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'          => true,
        'importacion_id' => $importacionId,
        'total'       => count($incidencias),
        'incidencias' => $incidencias
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("Error al consultar incidencias: " . $e->getMessage());
    apiError(500, "Error al consultar incidencias: " . $e->getMessage());
}
