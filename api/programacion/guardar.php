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
$directory = SnapshotStore::directory();
if (!is_dir($directory)) @mkdir($directory, 0775, true);
$periodLock = @fopen($directory . "/programacion_{$anio}_{$mes}.lock", 'c');
if (!$periodLock || !flock($periodLock, LOCK_EX)) apiError(503, 'No se pudo bloquear el rol para guardarlo.');
register_shutdown_function(static function () use ($periodLock) { flock($periodLock, LOCK_UN); fclose($periodLock); });

$snapshotKey = "programacion_serv_{$servicioId}_{$anio}_{$mes}";
$snap = SnapshotStore::get($snapshotKey);
$versionActual = $snap['_meta']['version'] ?? 1;

// DETECCIÓN DE CONFLICTO (Estrategia C):
// Si el cliente especificó con qué versión empezó a editar, y la versión actual es mayor,
// y no solicitó forzar el guardado, advertir el conflicto.
if (!$forzar && $versionCliente !== null && $versionActual !== $versionCliente) {
    http_response_code(409); // Conflict
    echo json_encode([
        'ok'               => false,
        'conflicto'        => true,
        'mensaje'          => "Conflicto de concurrencia: El rol de turnos de este servicio fue modificado mientras usted operaba.",
        'version_cliente'  => $versionCliente,
        'version_servidor' => $versionActual,
        'ultima_modificacion' => $snap['_meta']['saved_at'] ?? 'Reciente',
        'servicio_id'      => $servicioId
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);

// Si el snapshot no existe en disco, obtener los datos base de la BD o inicializarlos
$personalList = [];
if ($snap && isset($snap['data']['personal_servicio'])) {
    $personalList = $snap['data']['personal_servicio'];
} else {
    // Si no existía snapshot previo, intentar traerlo de la BD
    if ($pdo) {
        $whereServ = ($servicioId > 0) ? "WHERE s.ServicioID = :servId" : "WHERE 1=1";
        $sqlP = "
            SELECT 
                p.PersonalID AS personal_id,
                p.CodigoEmpleado AS codigo_empleado,
                per.NumeroDocumento AS dni,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
                ISNULL(c.Nombre, 'Personal') AS cargo_nombre,
                ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre,
                s.ServicioID AS servicio_id,
                ISNULL(tc.Nombre, 'CAS') AS condicion_laboral,
                CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 'ADMINISTRATIVO' ELSE 'ASISTENCIAL' END AS regimen_laboral,
                CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 0 ELSE 1 END AS aplica_seti
            FROM organizacion.Servicio s
            INNER JOIN rrhh.AsignacionPersonal ap ON s.ServicioID = ap.ServicioID AND ap.EsActual = 1 AND ap.Estado = 1
            INNER JOIN rrhh.Personal p ON ap.PersonalID = p.PersonalID AND p.Estado = 1
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            {$whereServ}
        ";
        $stmtP = $pdo->prepare($sqlP);
        if ($servicioId > 0) $stmtP->execute([':servId' => $servicioId]);
        else $stmtP->execute();
        $rows = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $r['patron'] = array_fill(0, 31, 'LIB');
            $personalList[] = $r;
        }
    }
}

$knownIds = array_map(static fn($p) => (int)$p['personal_id'], $personalList);
foreach ($cambios as $change) {
    if (!in_array((int)$change['personal_id'], $knownIds, true)) apiError(422, 'El trabajador no pertenece a este rol.');
}

// Aplicar los cambios sobre personalList
$trabajadoresAfectados = [];
foreach ($cambios as $c) {
    $pid = intval($c['personal_id'] ?? 0);
    $dia = intval($c['dia'] ?? 0);
    $cod = $c['codigo_turno'] ?? 'LIB';

    foreach ($personalList as &$pItem) {
        if (intval($pItem['personal_id']) === $pid) {
            if (!isset($pItem['patron']) || !is_array($pItem['patron'])) {
                $pItem['patron'] = array_fill(0, 31, 'LIB');
            }
            $idx = $dia - 1;
            $pItem['patron'][$idx] = $cod;
            $trabajadoresAfectados[$pid] = $pItem;
        }
    }
    unset($pItem);
}

// Guardar el Snapshot en disco
if (!SnapshotStore::set($snapshotKey, [
    'personal_servicio' => $personalList,
    'servicio_id'       => $servicioId,
    'mes'               => $mes,
    'anio'              => $anio
], [
    'servicio_id'     => $servicioId,
    'ultimo_autor'    => $usuario,
    'total_cambios'   => count($cambios),
    'persistencia' => 'LOCAL'
])) apiError(500, 'No se pudo guardar el rol en disco. Se conservó la versión anterior.');

$versionActual = SnapshotStore::getVersion($snapshotKey);

// Sincronización cruzada:
// 1. Si guardamos en un servicio específico (ej: 7), sincronizar también en el snapshot global (servicio 0) si existe
if ($servicioId > 0) {
    $globalKey = "programacion_serv_0_{$anio}_{$mes}";
    if (SnapshotStore::has($globalKey)) {
        $snapGlobal = SnapshotStore::get($globalKey);
        if ($snapGlobal && isset($snapGlobal['data']['personal_servicio'])) {
            $gList = $snapGlobal['data']['personal_servicio'];
            foreach ($cambios as $c) {
                $pid = intval($c['personal_id'] ?? 0);
                $dia = intval($c['dia'] ?? 0);
                $cod = $c['codigo_turno'] ?? 'LIB';
                foreach ($gList as &$gp) {
                    if (intval($gp['personal_id']) === $pid) {
                        if (!isset($gp['patron']) || !is_array($gp['patron'])) {
                            $gp['patron'] = array_fill(0, 31, 'LIB');
                        }
                        $gp['patron'][$dia - 1] = $cod;
                    }
                }
                unset($gp);
            }
            SnapshotStore::set($globalKey, [
                'personal_servicio' => $gList,
                'servicio_id'       => 0,
                'mes'               => $mes,
                'anio'              => $anio
            ], ['servicio_id' => 0, 'ultimo_autor' => $usuario, 'sincronizado_desde' => $servicioId]);
        }
    }
} else {
    // 2. Si guardamos en Todos los Servicios (servicio 0), sincronizar cada trabajador con el snapshot de su respectivo servicio si existe
    foreach ($trabajadoresAfectados as $pid => $aff) {
        $wServId = intval($aff['servicio_id'] ?? 0);
        if ($wServId > 0) {
            $wKey = "programacion_serv_{$wServId}_{$anio}_{$mes}";
            if (SnapshotStore::has($wKey)) {
                $wSnap = SnapshotStore::get($wKey);
                if ($wSnap && isset($wSnap['data']['personal_servicio'])) {
                    $wList = $wSnap['data']['personal_servicio'];
                    foreach ($wList as &$wp) {
                        if (intval($wp['personal_id']) === $pid) {
                            $wp['patron'] = $aff['patron'];
                        }
                    }
                    unset($wp);
                    SnapshotStore::set($wKey, [
                        'personal_servicio' => $wList,
                        'servicio_id'       => $wServId,
                        'mes'               => $mes,
                        'anio'              => $anio
                    ], ['servicio_id' => $wServId, 'ultimo_autor' => $usuario, 'sincronizado_desde' => 0]);
                }
            }
        }
    }
}

echo json_encode([
    'ok'              => true,
    'conflicto'       => false,
    'message'         => 'Programación mensual guardada y persistida correctamente en disco.',
    'total_cambios'   => count($cambios),
    'nueva_version'   => $versionActual,
    'servicio_id'     => $servicioId,
    'mes'             => $mes,
    'anio'            => $anio,
    'fecha_guardado'  => date('Y-m-d H:i:s'),
    'conexion_sql'    => $dbConnected,
    'persistencia' => 'LOCAL',
    'sincronizado_sql' => false
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
