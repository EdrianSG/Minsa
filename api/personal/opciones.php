<?php
require_once __DIR__.'/../../includes/api_bootstrap.php';
$context = (string)($_GET['contexto'] ?? 'personal');
if (!in_array($context, ['personal','reportes','horarios','ausencias','administracion'], true)) {
    apiError(400, 'Contexto de consulta inválido.');
}
apiGuard($context, ['GET']);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../includes/SnapshotStore.php';

$allowed = Auth::getAllowedServiceId();
$state = trim((string)($_GET['estado'] ?? ''));
if ($state !== '' && !in_array($state,['0','1'],true)) apiError(400, 'Estado inválido.');
$key = 'personal_opciones_' . hash('sha256', json_encode([$context,$allowed,$state]));
$pdo = Database::getConnection();
if (!$pdo) {
    $snapshot = SnapshotStore::get($key);
    if (!$snapshot) apiError(503, 'SQL Server no está disponible y no existe un respaldo del selector.');
    echo json_encode(['ok'=>true,'data'=>$snapshot['data'],'conexion_sql'=>false], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    $sql = "SELECT p.PersonalID AS personal_id,p.CodigoEmpleado AS codigo_empleado,
        p.NumeroDocumento AS dni,p.Activo AS estado,
        LTRIM(RTRIM(CONCAT(p.ApellidoPaterno,' ',ISNULL(p.ApellidoMaterno,''),', ',p.Nombres))) AS nombre_completo,
        ap.AreaID AS area_id,ap.ServicioID AS servicio_id,
        c.Nombre AS cargo_nombre,s.Nombre AS servicio_nombre
        FROM rrhh.Personal p
        LEFT JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID=p.PersonalID AND ap.EsActual=1 AND ap.Activo=1
        LEFT JOIN rrhh.Cargo c ON c.CargoID=ap.CargoID
        LEFT JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
        WHERE 1=1";
    $params = [];
    if ($allowed !== null) { $sql .= ' AND ap.ServicioID=?'; $params[]=$allowed; }
    if ($state !== '') { $sql .= ' AND p.Activo=?'; $params[]=(int)$state; }
    $sql .= ' ORDER BY p.ApellidoPaterno,p.ApellidoMaterno,p.Nombres,p.PersonalID';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    SnapshotStore::set($key, $rows, ['source'=>'SQL_SERVER_LIVE']);
    echo json_encode(['ok'=>true,'data'=>$rows,'conexion_sql'=>true], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    error_log('Opciones de personal: '.$e->getMessage());
    apiError(503, 'No se pudo consultar el selector de personal.');
}
