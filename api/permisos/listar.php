<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('ausencias', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/permisos/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 6
 *
 * Consulta y gestión de permisos, descansos médicos (CITT), licencias y justificaciones
 * basados en rrhh.PermisoLaboral y rrhh.TipoPermisoLaboral.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

function calcularDias($fIni, $fFin) {
    $t1 = strtotime($fIni);
    $t2 = strtotime($fFin);
    if ($t2 < $t1) return 1;
    return round(($t2 - $t1) / 86400) + 1;
}

$estadoFiltro  = isset($_GET['estado']) && $_GET['estado'] !== '' ? strtoupper(trim($_GET['estado'])) : 'TODOS';
$tipoIdFiltro  = isset($_GET['tipo_id']) && $_GET['tipo_id'] !== '' ? intval($_GET['tipo_id']) : null;
$servicioFiltro= isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : null;

// Restricción por servicio para jefaturas y supervisores
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    if ($servicioFiltro === null) {
        $servicioFiltro = $userAllowedServ;
    } elseif ($servicioFiltro !== $userAllowedServ) {
        apiError(403, 'No tiene autorización para ver permisos de otro servicio.');
    }
}

$busqueda      = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$permisos = [];

if ($dbConnected) {
    try {
        $sql = "
            SELECT
                pl.PermisoLaboralID,
                pl.PersonalID,
                p.NumeroDocumento AS DNI,
                p.Nombres,
                p.ApellidoPaterno,
                p.ApellidoMaterno,
                ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                s.ServicioID,
                ISNULL(s.Nombre, 'Servicio General') AS NombreServicio,
                p.CondicionLaboral AS CondicionLaboral,
                tpl.TipoPermisoLaboralID,
                tpl.Nombre AS TipoPermisoNombre,
                pl.FechaInicio,
                pl.FechaFin,
                pl.Motivo,
                pl.Estado
            FROM rrhh.PermisoLaboral pl
            INNER JOIN rrhh.Personal p ON pl.PersonalID = p.PersonalID
            INNER JOIN rrhh.TipoPermisoLaboral tpl ON pl.TipoPermisoLaboralID = tpl.TipoPermisoLaboralID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            WHERE p.Activo = 1
            ORDER BY pl.PermisoLaboralID DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $nombreCompleto = trim("{$r['ApellidoPaterno']} {$r['ApellidoMaterno']} {$r['Nombres']}");
            $dias = calcularDias($r['FechaInicio'], $r['FechaFin']);

            $permisos[] = [
                'permiso_id'          => intval($r['PermisoLaboralID']),
                'personal_id'         => intval($r['PersonalID']),
                'dni'                 => $r['DNI'],
                'nombre_completo'     => $nombreCompleto,
                'cargo'               => $r['NombreCargo'] ?? 'Personal Hospitalario',
                'servicio_id'         => isset($r['ServicioID']) ? intval($r['ServicioID']) : null,
                'servicio_nombre'     => $r['NombreServicio'] ?? 'General',
                'condicion_laboral'   => $r['CondicionLaboral'] ?? null,
                'tipo_id'             => intval($r['TipoPermisoLaboralID']),
                'tipo_nombre'         => $r['TipoPermisoNombre'],
                'fecha_inicio'        => $r['FechaInicio'],
                'fecha_fin'           => $r['FechaFin'],
                'dias_solicitados'    => $dias,
                'motivo'              => $r['Motivo'] ?? '',
                'documento_sustento'  => preg_match('/^Sustento: (.*?)\. Motivo:/us', (string)$r['Motivo'], $sustento) ? ($sustento[1] ?: null) : null,
                'goce_haber'          => null,
                'estado'              => $r['Estado'],
                'fecha_solicitud'     => $r['FechaInicio'],
                'es_citt'             => (strpos(strtoupper($r['TipoPermisoNombre']), 'CITT') !== false || strpos(strtoupper($r['TipoPermisoNombre']), 'MÉDICO') !== false)
            ];
        }

        if (!empty($permisos)) {
            require_once __DIR__ . '/../../includes/SnapshotStore.php';
            SnapshotStore::set('permisos_lista_completa' . ($userAllowedServ ? '_serv_' . $userAllowedServ : ''), $permisos, ['source' => 'SQL_SERVER_LIVE']);
        }
        require_once __DIR__ . '/../../includes/SnapshotStore.php';
        SnapshotStore::set('permisos_lista_completa' . ($userAllowedServ ? '_serv_' . $userAllowedServ : ''), $permisos, ['source'=>'SQL_SERVER_LIVE']);
    } catch (Exception $e) {
        $dbConnected = false;
    }
}

if (!$dbConnected) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    $snap = $userAllowedServ ? SnapshotStore::get('permisos_lista_completa_serv_' . $userAllowedServ) : null;
    $snap = $snap ?? SnapshotStore::get('permisos_lista_completa');
    if (!$snap) apiError(503, 'SQL Server no está disponible y no existe un respaldo de permisos.');
    $permisos = $snap['data'];
}
if ($userAllowedServ !== null) $permisos = array_values(array_filter($permisos, static fn($p)=>(int)($p['servicio_id'] ?? 0) === $userAllowedServ));

// Resumen y KPIs globales calculados sobre el universo completo de la BD
$totalSolicitudes = count($permisos);
$pendientes = count(array_filter($permisos, function($p) { return strtoupper($p['estado']) === 'PENDIENTE'; }));
$aprobadas  = count(array_filter($permisos, function($p) { return strtoupper($p['estado']) === 'APROBADO'; }));
$rechazadas = count(array_filter($permisos, function($p) { return in_array(strtoupper($p['estado']), ['RECHAZADO', 'ANULADO']); }));
$cittActivos= count(array_filter($permisos, function($p) { return $p['es_citt'] && strtoupper($p['estado']) === 'APROBADO'; }));

// Si venimos de BD sin filtros en el SQL (o para filtrar en memoria de forma uniforme):
$filtrados = array_values(array_filter($permisos, function($item) use ($estadoFiltro, $tipoIdFiltro, $servicioFiltro, $busqueda) {
    if ($estadoFiltro !== 'TODOS' && strtoupper($item['estado']) !== $estadoFiltro) {
        return false;
    }
    if ($tipoIdFiltro !== null && intval($item['tipo_id']) !== $tipoIdFiltro) {
        return false;
    }
    if ($servicioFiltro !== null && intval($item['servicio_id']) !== $servicioFiltro) {
        return false;
    }
    if ($busqueda !== '') {
        $term = mb_strtolower($busqueda, 'UTF-8');
        $encontrado = (
            strpos(mb_strtolower($item['nombre_completo'], 'UTF-8'), $term) !== false ||
            strpos($item['dni'], $term) !== false ||
            strpos(mb_strtolower((string)$item['documento_sustento'], 'UTF-8'), $term) !== false ||
            strpos(mb_strtolower($item['servicio_nombre'], 'UTF-8'), $term) !== false ||
            strpos(mb_strtolower($item['tipo_nombre'], 'UTF-8'), $term) !== false
        );
        if (!$encontrado) return false;
    }
    return true;
}));

echo json_encode([
    'ok'              => true,
    'conexion_sql'    => $dbConnected,
    'resumen'         => [
        'total_solicitudes' => $totalSolicitudes,
        'pendientes'        => $pendientes,
        'aprobadas'         => $aprobadas,
        'rechazadas'        => $rechazadas,
        'citt_activos'      => $cittActivos
    ],
    'total_registros' => count($filtrados),
    'permisos'        => $filtrados
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
