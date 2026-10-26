<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('ausencias', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/permisos/tipos.php
 * Hospital Distrital de Pacasmayo - ETAPA 6
 * 
 * Catálogo Oficial de Tipos de Permisos Laborales (rrhh.TipoPermisoLaboral)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$tipos = [];

if ($dbConnected) {
    try {
        $stmt = $pdo->query("SELECT TipoPermisoLaboralID, Nombre, Descripcion, Estado FROM rrhh.TipoPermisoLaboral WHERE Estado = 1 ORDER BY TipoPermisoLaboralID");
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $dbConnected = false;
    }
}

require_once __DIR__ . '/../../includes/SnapshotStore.php';
if ($dbConnected) SnapshotStore::set('tipos_permiso_catalogo', $tipos, ['source'=>'SQL_SERVER_LIVE']);
else {
    $snapshot = SnapshotStore::get('tipos_permiso_catalogo');
    if (!$snapshot) apiError(503, 'SQL Server no está disponible y no existe un respaldo del catálogo.');
    $tipos = $snapshot['data'];
}

echo json_encode([
    'ok'           => true,
    'total'        => count($tipos),
    'tipos'        => $tipos,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
