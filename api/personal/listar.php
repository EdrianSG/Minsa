<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('personal', ['GET'], ['JEFE_RRHH', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/personal/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 2
 * 
 * Reutiliza el modelo de datos institucional:
 * core.Persona, rrhh.Personal, rrhh.AsignacionPersonal,
 * rrhh.Cargo, rrhh.Profesion, rrhh.Especialidad,
 * organizacion.Area, organizacion.Servicio, rrhh.Contrato, rrhh.TipoContrato
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$q         = trim($_GET['q'] ?? '');
$servicio  = trim($_GET['servicio'] ?? '');
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

if ($dbConnected) {
    try {
        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                p.PersonaID AS persona_id,
                p.CodigoEmpleado AS codigo_empleado,
                p.FechaIngreso AS fecha_ingreso,
                p.FechaCese AS fecha_cese,
                p.Estado AS estado,
                
                per.NumeroDocumento AS dni,
                per.Nombres AS nombres,
                per.ApellidoPaterno AS apellido_paterno,
                per.ApellidoMaterno AS apellido_materno,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
                per.Celular AS celular,
                per.Correo AS correo,
                per.Telefono AS telefono,
                
                c.CargoID AS cargo_id,
                ISNULL(c.Nombre, 'Sin Cargo') AS cargo_nombre,
                
                prof.ProfesionID AS profesion_id,
                ISNULL(prof.Nombre, 'General') AS profesion_nombre,
                
                esp.EspecialidadID AS especialidad_id,
                esp.Nombre AS especialidad_nombre,
                
                a.AreaID AS area_id,
                ISNULL(a.Nombre, 'General') AS area_nombre,
                
                s.ServicioID AS servicio_id,
                ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre,
                
                tc.TipoContratoID AS tipo_contrato_id,
                ISNULL(tc.Nombre, 'CAS') AS condicion_laboral,
                con.NumeroContrato AS numero_contrato
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Profesion prof ON ap.ProfesionID = prof.ProfesionID
            LEFT JOIN rrhh.Especialidad esp ON ap.EspecialidadID = esp.EspecialidadID
            LEFT JOIN organizacion.Area a ON ap.AreaID = a.AreaID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            WHERE 1=1
        ";

        $params = [];

        if ($estado !== null) {
            $sql .= " AND p.Estado = :estado";
            $params[':estado'] = $estado;
        }

        if ($condicion !== '') {
            $sql .= " AND tc.Nombre = :condicion";
            $params[':condicion'] = $condicion;
        }

        if ($servicio !== '') {
            if (is_numeric($servicio)) {
                $sql .= " AND s.ServicioID = :servId";
                $params[':servId'] = intval($servicio);
            } else {
                $sql .= " AND s.Nombre LIKE :servNombre";
                $params[':servNombre'] = "%$servicio%";
            }
        }

        if ($q !== '') {
            $sql .= " AND (per.Nombres LIKE :q1 OR per.ApellidoPaterno LIKE :q2 OR per.ApellidoMaterno LIKE :q3 OR per.NumeroDocumento LIKE :q4 OR p.CodigoEmpleado LIKE :q5)";
            foreach (range(1, 5) as $index) $params[':q' . $index] = "%$q%";
        }

        $sql .= " ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $personalList = $rows;
        $totalRecords = count($personalList);
        if ($q === '' && $condicion === '' && $regimen === '' && $estado === null) {
            require_once __DIR__ . '/../../includes/SnapshotStore.php';
            if ($servicio === '' || $userAllowedServ !== null) {
                SnapshotStore::set('personal_catalogo_completo' . ($userAllowedServ ? '_serv_' . $userAllowedServ : ''), $personalList, ['source'=>'SQL_SERVER_LIVE']);
            }
        }
    } catch (PDOException $e) {
        $dbConnected = false;
    }
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
    if (!isset($item['nombre_completo'])) {
        $item['nombre_completo'] = trim($item['apellido_paterno'] . ' ' . ($item['apellido_materno'] ?? '') . ', ' . $item['nombres']);
    }
    $profAsistenciales = ['Medicina Humana', 'Enfermería', 'Obstetricia', 'Técnico de Salud', 'Tecnología Médica', 'Farmacia Técnica'];
    $esAsistencial = in_array($item['profesion_nombre'] ?? '', $profAsistenciales) || (isset($item['aplica_seti']) && $item['aplica_seti'] == 1);
    $item['regimen_laboral'] = $esAsistencial ? 'ASISTENCIAL' : 'ADMINISTRATIVO';
    $item['aplica_seti'] = $esAsistencial ? 1 : 0;
}
unset($item);

// Filtrado en memoria si operamos con dataset
$filtered = array_filter($personalList, function($p) use ($q, $servicio, $condicion, $regimen, $estado) {
    if ($q !== '') {
        $matchQ = (
            stripos($p['nombre_completo'], $q) !== false ||
            stripos($p['dni'], $q) !== false ||
            stripos($p['codigo_empleado'], $q) !== false ||
            stripos($p['cargo_nombre'], $q) !== false ||
            stripos($p['profesion_nombre'], $q) !== false
        );
        if (!$matchQ) return false;
    }

    if ($servicio !== '') {
        if (is_numeric($servicio) ? (int)($p['servicio_id'] ?? 0) !== (int)$servicio : stripos($p['servicio_nombre'], $servicio) === false) return false;
    }

    if ($condicion !== '' && strtoupper($p['condicion_laboral']) !== strtoupper($condicion)) {
        return false;
    }

    if ($regimen !== '' && strtoupper($p['regimen_laboral']) !== strtoupper($regimen)) {
        return false;
    }

    if ($estado !== null && intval($p['estado']) !== $estado) {
        return false;
    }

    return true;
});

// Estadísticas globales sobre el total
$totales = [
    'total'          => count($personalList),
    'asistenciales'  => count(array_filter($personalList, fn($p) => strtoupper($p['regimen_laboral'] ?? '') === 'ASISTENCIAL')),
    'administrativos'=> count(array_filter($personalList, fn($p) => strtoupper($p['regimen_laboral'] ?? '') === 'ADMINISTRATIVO')),
    'nombrados'      => count(array_filter($personalList, fn($p) => strtoupper($p['condicion_laboral'] ?? '') === 'NOMBRADO')),
    'cas'            => count(array_filter($personalList, fn($p) => strtoupper($p['condicion_laboral'] ?? '') === 'CAS')),
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
