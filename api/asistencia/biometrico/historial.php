<?php
/**
 * API Endpoint: /api/asistencia/biometrico/historial.php
 * Hospital Distrital de Pacasmayo - Fase 2
 * 
 * Lista el historial de importaciones biométricas ejecutadas en el hospital.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';

$pdo = Database::getConnection();
if ($pdo === null) {
    apiError(503, "SQL Server no disponible.");
}

$limit = max(1, min(100, intval($_GET['limit'] ?? 20)));

try {
    $sql = "
        SELECT TOP ($limit)
            ib.ImportacionID,
            ib.NombreArchivo,
            ib.HashSHA256,
            CONVERT(VARCHAR(19), ib.FechaHoraCarga, 120) AS FechaHoraCarga,
            ib.UsuarioID,
            ISNULL(u.NombreUsuario, 'Sistema / Administrador') AS UsuarioNombre,
            ib.TotalRegistros,
            ib.MarcacionesValidas,
            ib.MarcacionesOmitidas,
            ib.MarcacionesDuplicadas,
            ib.PendientesVinculacion,
            ib.MarcacionesConsecutivas,
            ib.VinculadosDirectos,
            ib.Estado,
            ib.MensajeError,
            ib.Observaciones
        FROM rrhh.ImportacionBiometrica ib
        LEFT JOIN seguridad.Usuario u ON ib.UsuarioID = u.UsuarioID
        ORDER BY ib.ImportacionID DESC
    ";

    $stmt = $pdo->query($sql);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'        => true,
        'total'     => count($historial),
        'historial' => $historial
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("Error al consultar historial de importaciones: " . $e->getMessage());
    apiError(500, "Error al consultar historial: " . $e->getMessage());
}
