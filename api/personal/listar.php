<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('personal', ['GET'], ['JEFE_RRHH', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/personal/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 2
 * 
 * Reutiliza el modelo de datos institucional:
 * rrhh.Personal, rrhh.AsignacionPersonal,
 * rrhh.Cargo, rrhh.Profesion, rrhh.Especialidad,
 * organizacion.Area, organizacion.Servicio, rrhh.Contrato, rrhh.TipoContrato
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__.'/../../includes/PersonalDirectory.php';
require_once __DIR__.'/../../includes/SnapshotStore.php';

$q         = trim($_GET['q'] ?? '');
$servicio  = trim($_GET['servicio'] ?? '');
$areaFilter = trim((string)($_GET['area'] ?? ''));
if ($areaFilter !== '' && (!ctype_digit($areaFilter) || (int)$areaFilter < 1)) {
    apiError(400, 'El área debe ser un identificador válido.');
}
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    $servicio = (string)$userAllowedServ;
}
$condicion = trim($_GET['condicion'] ?? '');
$regimen   = trim($_GET['regimen'] ?? '');
$estado    = isset($_GET['estado']) && $_GET['estado'] !== '' ? intval($_GET['estado']) : null;
$page      = max(1, intval($_GET['page'] ?? 1));
$limit     = max(5, min(50, intval($_GET['limit'] ?? 10)));

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$personalList = [];
$totalRecords = 0;

$filters = compact('q','servicio','areaFilter','condicion','regimen','estado','page','limit');
$pageKey = 'personal_pagina_' . hash('sha256', json_encode([$userAllowedServ,$filters]));
if ($dbConnected) {
    try {
        $result = PersonalDirectory::page($pdo, $filters);
        SnapshotStore::set($pageKey, $result, ['source'=>'SQL_SERVER_LIVE']);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    } catch (PDOException $e) {
        error_log('Listado paginado de personal: '.$e->getMessage());
        $dbConnected = false;
    }
}
$cachedPage = SnapshotStore::get($pageKey);
if ($cachedPage) {
    $result = $cachedPage['data'];
    $result['conexion_sql'] = false;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!$dbConnected) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    $snap = $userAllowedServ ? SnapshotStore::get('personal_catalogo_completo_serv_' . $userAllowedServ) : null;
    $snap = $snap ?? SnapshotStore::get('personal_catalogo_completo');
    if (!$snap) apiError(503, 'SQL Server no está disponible y no existe un respaldo del personal.');
    $personalList = $snap['data'];
}
if ($userAllowedServ !== null) {
    $personalList = array_values(array_filter($personalList, static fn($p)=>(int)($p['servicio_id'] ?? 0) === $userAllowedServ));
}

// Asegurar campo nombre_completo y régimen asistencial vs administrativo
foreach ($personalList as &$item) {
    if (trim($item['nombre_completo'] ?? '', ' ,') === '') {
        $item['nombre_completo'] = trim(implode(' ', array_filter([$item['apellido_paterno'] ?? null, $item['apellido_materno'] ?? null, $item['nombres'] ?? null]))) ?: ($item['nombre_completo_original'] ?? null);
    }
    // Normalizar tipo_personal según grupo_ocupacional, regimen_laboral, cargo o área
    $grupo = strtoupper(trim((string)($item['grupo_ocupacional'] ?? '')));
    $reg = strtoupper(trim((string)($item['regimen_laboral'] ?? $item['tipo_personal'] ?? '')));
    $area = strtoupper(trim((string)($item['area_nombre'] ?? '')));
    $cargo = strtoupper(trim((string)($item['cargo_nombre'] ?? '')));

    if ($grupo === 'ADMINISTRATIVO' || str_contains($reg, 'ADMIN') || str_contains($area, 'ADMINISTRATIV') || str_contains($cargo, 'ADMINISTRATIV') || str_contains($cargo, 'CONTADOR') || str_contains($cargo, 'SECRETARIA') || str_contains($cargo, 'SISTEMA') || str_contains($area, 'RECURSOS HUMANOS') || str_contains($area, 'SIS') || str_contains($area, 'ALMACEN')) {
        $item['tipo_personal'] = 'ADMINISTRATIVO';
    } elseif ($reg === 'DL 1153' || str_contains($reg, 'ASIST') || str_contains($cargo, 'MEDIC') || str_contains($cargo, 'ENFERM') || str_contains($cargo, 'OBSTETR') || str_contains($cargo, 'CIRUG') || str_contains($area, 'EMERGENCIA') || str_contains($area, 'CIRUGIA')) {
        $item['tipo_personal'] = 'ASISTENCIAL';
    } elseif ($reg !== '') {
        $item['tipo_personal'] = $reg;
    } else {
        $item['tipo_personal'] = null;
    }
    $item['aplica_seti'] = ($item['tipo_personal'] === 'ASISTENCIAL') ? 1 : null;
}
unset($item);

// Filtrado en memoria si operamos con dataset
$filtered = array_filter($personalList, function($p) use ($q, $servicio, $areaFilter, $condicion, $regimen, $estado) {
    if ($q !== '') {
        $matchQ = (
            stripos((string)($p['nombre_completo'] ?? ''), $q) !== false ||
            stripos((string)($p['dni'] ?? ''), $q) !== false ||
            stripos((string)($p['codigo_empleado'] ?? ''), $q) !== false ||
            stripos((string)($p['cargo_nombre'] ?? ''), $q) !== false ||
            stripos((string)($p['profesion_nombre'] ?? ''), $q) !== false ||
            stripos((string)($p['biostar_user_id'] ?? ''), $q) !== false
        );
        if (!$matchQ) return false;
    }

    if ($areaFilter !== '' && (int)($p['area_id'] ?? 0) !== (int)$areaFilter) return false;

    if ($servicio !== '') {
        if (is_numeric($servicio) ? (int)($p['servicio_id'] ?? 0) !== (int)$servicio : stripos((string)($p['servicio_nombre'] ?? ''), $servicio) === false) return false;
    }

    if ($condicion !== '' && strtoupper((string)($p['condicion_laboral'] ?? '')) !== strtoupper($condicion)) {
        return false;
    }

    if ($regimen !== '') {
        $targetRegimen = strtoupper(trim($regimen));
        $workerTipo = strtoupper(trim((string)($p['tipo_personal'] ?? '')));
        $workerGrupo = strtoupper(trim((string)($p['grupo_ocupacional'] ?? '')));
        $workerRegimen = strtoupper(trim((string)($p['regimen_laboral'] ?? '')));

        if ($targetRegimen === 'ADMINISTRATIVO') {
            if ($workerTipo !== 'ADMINISTRATIVO' && $workerGrupo !== 'ADMINISTRATIVO' && !str_contains($workerRegimen, 'ADMIN')) {
                return false;
            }
        } elseif ($targetRegimen === 'ASISTENCIAL') {
            if ($workerTipo !== 'ASISTENCIAL' && $workerRegimen !== 'DL 1153' && !str_contains($workerRegimen, 'ASIST')) {
                return false;
            }
        } else {
            if ($workerTipo !== $targetRegimen && !str_contains($workerRegimen, $targetRegimen)) {
                return false;
            }
        }
    }

    if ($estado !== null && intval($p['estado']) !== $estado) {
        return false;
    }

    return true;
});

// Estadísticas globales sobre el total
$totales = [
    'total'          => count($personalList),
    'asistenciales'  => count(array_filter($personalList, fn($p) => strtoupper((string)($p['tipo_personal'] ?? '')) === 'ASISTENCIAL')),
    'administrativos'=> count(array_filter($personalList, fn($p) => strtoupper((string)($p['tipo_personal'] ?? '')) === 'ADMINISTRATIVO')),
    'nombrados'      => count(array_filter($personalList, fn($p) => strtoupper((string)($p['condicion_laboral'] ?? '')) === 'NOMBRADO')),
    'cas'            => count(array_filter($personalList, fn($p) => strtoupper((string)($p['condicion_laboral'] ?? '')) === 'CAS')),
    'pendientes_validacion' => count(array_filter($personalList, fn($p) => $p['estado'] === null)),
    'activos'        => count(array_filter($personalList, fn($p) => intval($p['estado']) === 1))
];

// Paginación
$totalFiltrados = count($filtered);
$offset = ($page - 1) * $limit;
$paginados = array_slice(array_values($filtered), $offset, $limit);
$totalPages = ceil($totalFiltrados / $limit);

echo json_encode([
    'ok'              => true,
    'total_registros' => $totalFiltrados,
    'pagina_actual'   => $page,
    'total_paginas'   => max(1, $totalPages),
    'limite'          => $limit,
    'estadisticas'    => $totales,
    'data'            => $paginados,
    'conexion_sql'    => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
