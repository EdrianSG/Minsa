<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('horarios', ['GET'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/turnos/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 3
 * 
 * Entrega el catálogo parametrizado de turnos oficiales del hospital
 * Reutiliza y extiende: rrhh.Turno
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$turnos = [];

require_once __DIR__ . '/../../includes/SnapshotStore.php';
require_once __DIR__ . '/../../includes/TurnoCatalog.php';
if ($dbConnected) {
    try {
        $turnos = TurnoCatalog::load($pdo);
        SnapshotStore::set('turnos_catalogo_oficial', $turnos, ['source'=>'SQL_SERVER_LIVE']);
    } catch (PDOException $e) { $dbConnected = false; }
}
if (!$dbConnected) {
    $snap = SnapshotStore::get('turnos_catalogo_oficial');
    if (!$snap) apiError(503, 'SQL Server no está disponible y no existe un respaldo del catálogo.');
    $turnos = $snap['data'];
}

// Estadísticas de parametrización
$stats = [
    'total_turnos'     => count($turnos),
    'turnos_6h'        => count(array_filter($turnos, fn($t) => floatval($t['horas_computables']) === 6.00)),
    'guardias_12h'     => count(array_filter($turnos, fn($t) => floatval($t['horas_computables']) === 12.00 || intval($t['es_guardia']) === 1)),
    'cruza_medianoche' => count(array_filter($turnos, fn($t) => intval($t['cruza_medianoche']) === 1)),
    'activos'          => count(array_filter($turnos, fn($t) => intval($t['estado']) === 1))
];

echo json_encode([
    'ok'           => true,
    'estadisticas' => $stats,
    'data'         => $turnos,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
