<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';
apiGuard('dashboard', ['GET']);
require_once __DIR__ . '/../includes/DataRevision.php';
$pdo = Database::getConnection();
$revision = null;
if ($pdo) {
    try { $revision = DataRevision::read($pdo); }
    catch (Throwable $e) { error_log('Revisión de datos: '.$e->getMessage()); }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true, 'conexion_sql'=>$pdo !== null, 'revision'=>$revision,'cache_disponible'=>$revision!==null,
    'scope'=>hash('sha256', Database::sourceId() . '|' . SystemContext::code() . '|' . session_id() . '|' . Auth::getCurrentUser()['rol'] . '|' . (string)Auth::getAllowedServiceId())]);
