<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('programacion', ['POST'], ['JEFE_RRHH', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/programacion/guardar.php
 * Hospital Distrital de Pacasmayo - ETAPA 4
 * 
 * Persiste las celdas diarias de la matriz mensual de programación
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/SnapshotStore.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$servicioId      = intval($data['servicio_id'] ?? 7);
$mes             = intval($data['mes'] ?? 9);
$anio            = intval($data['anio'] ?? 2026);
$cambios         = $data['cambios'] ?? []; // Array de { personal_id, dia, codigo_turno, horas }
$versionCliente  = isset($data['version_base']) ? intval($data['version_base']) : null;
$forzar          = !empty($data['forzar']);
$usuario         = Auth::getCurrentUser()['username'];

if ($servicioId < 0 || $mes < 1 || $mes > 12 || $anio < 2020 || $anio > 2100)
    apiError(400, 'Servicio o periodo inválido.');
apiRequireServiceAccess($servicioId);
if (!is_array($cambios) || !$cambios) apiError(400, 'Debe enviar cambios válidos.');
if ($versionCliente === null || $versionCliente < 1) apiError(400, 'La versión base es obligatoria. Recargue el rol.');
if ($forzar && !Auth::hasRole('ADMIN', 'ADMIN_RRHH', 'JEFE_RRHH')) apiError(403, 'Solo RR.HH. puede forzar el guardado.');
$codes = ['M','1','T','2','N','3','GD','GN','1/2','2/3','1/2/3','G24','ADM','VAC','V',
    'VAC-1','VAC-2','VAC-3','V1','V2','V3','DESC','C','CITT','F','P','PER','LIB'];
foreach ($cambios as $change) {
    if (!is_array($change) || !isset($change['personal_id'], $change['dia'], $change['codigo_turno'])
        || !is_int($change['dia']) || $change['dia'] < 1 || $change['dia'] > cal_days_in_month(CAL_GREGORIAN, $mes, $anio)
        || !in_array($change['codigo_turno'], $codes, true)) apiError(400, 'Día, trabajador o código de turno inválido.');
}
require_once __DIR__ . '/../../includes/ProgramacionStore.php';
$pdo = apiRequireDatabase();
try {
    $version = ProgramacionStore::save($pdo, $anio, $mes, $servicioId, $versionCliente, $forzar, $cambios,
        (int)Auth::getCurrentUser()['usuario_id']);
} catch (ProgramacionConflict $e) {
    http_response_code(409);
    echo json_encode(['ok'=>false, 'conflicto'=>true, 'mensaje'=>$e->getMessage(),
        'version_cliente'=>$versionCliente, 'version_servidor'=>$e->version, 'servicio_id'=>$servicioId]);
    exit;
} catch (InvalidArgumentException $e) {
    apiError(422, $e->getMessage());
} catch (Throwable $e) {
    error_log('Programación SQL: ' . $e->getMessage());
    apiError(503, 'No se guardó la programación. Verifique la conexión y la migración de programación mensual en SQL Server.');
}
// El commit SQL determina el éxito. Un fallo de respaldo no revierte ni oculta ese commit.
$respaldo = true;
try { $respaldo = ProgramacionStore::backup($pdo, $anio, $mes); }
catch (Throwable $e) { $respaldo = false; error_log('Respaldo de programación: ' . $e->getMessage()); }
foreach (glob(SnapshotStore::directory() . sprintf('/asistencia_diaria_%04d-%02d-*.json', $anio, $mes)) ?: [] as $file) {
    SnapshotStore::delete(basename($file, '.json'));
}
echo json_encode(['ok'=>true, 'conflicto'=>false, 'message'=>'Programación guardada en SQL Server.',
    'total_cambios'=>count($cambios), 'nueva_version'=>$version, 'servicio_id'=>$servicioId,
    'mes'=>$mes, 'anio'=>$anio, 'fecha_guardado'=>date('Y-m-d H:i:s'), 'conexion_sql'=>true,
    'persistencia'=>'SQL_SERVER', 'sincronizado_sql'=>true, 'respaldo_local'=>$respaldo], JSON_UNESCAPED_UNICODE);
