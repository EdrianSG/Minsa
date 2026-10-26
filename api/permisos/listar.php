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
                per.NumeroDocumento AS DNI,
                per.Nombres,
                per.ApellidoPaterno,
                per.ApellidoMaterno,
                ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                s.ServicioID,
                ISNULL(s.Nombre, 'Servicio General') AS NombreServicio,
                ISNULL(tc.Nombre, 'CAS') AS CondicionLaboral,
                tpl.TipoPermisoLaboralID,
                tpl.Nombre AS TipoPermisoNombre,
                pl.FechaInicio,
                pl.FechaFin,
                pl.Motivo,
                pl.Estado
            FROM rrhh.PermisoLaboral pl
            INNER JOIN rrhh.Personal p ON pl.PersonalID = p.PersonalID
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            INNER JOIN rrhh.TipoPermisoLaboral tpl ON pl.TipoPermisoLaboralID = tpl.TipoPermisoLaboralID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
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
                'servicio_id'         => intval($r['ServicioID'] ?? 1),
                'servicio_nombre'     => $r['NombreServicio'] ?? 'General',
                'condicion_laboral'   => $r['CondicionLaboral'] ?? 'NOMBRADO',
                'tipo_id'             => intval($r['TipoPermisoLaboralID']),
                'tipo_nombre'         => $r['TipoPermisoNombre'],
                'fecha_inicio'        => $r['FechaInicio'],
                'fecha_fin'           => $r['FechaFin'],
                'dias_solicitados'    => $dias,
                'motivo'              => $r['Motivo'] ?? '',
                'documento_sustento'  => 'Papeleta N° ' . str_pad($r['PermisoLaboralID'], 4, '0', STR_PAD_LEFT),
                'goce_haber'          => 1,
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
            strpos(mb_strtolower($item['documento_sustento'], 'UTF-8'), $term) !== false ||
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


if (!function_exists('generarPermisosInstitucionales')) {
function generarPermisosInstitucionales() {
    return [
        [
            'permiso_id'          => 1,
            'personal_id'         => 7,
            'dni'                 => '48963214',
            'nombre_completo'     => 'Tec. Rosa María Vásquez León',
            'cargo'               => 'Técnica en Enfermería',
            'servicio_id'         => 1,
            'servicio_nombre'     => 'Emergencia y Cuidados Críticos',
            'condicion_laboral'   => 'CAS',
            'tipo_id'             => 2,
            'tipo_nombre'         => 'Comisión Oficial de Servicio',
            'fecha_inicio'        => '2026-09-21',
            'fecha_fin'           => '2026-09-21',
            'dias_solicitados'    => 1,
            'motivo'              => 'Traslado de emergencia de paciente crítico al Hospital Regional Docente de Trujillo en ambulancia.',
            'documento_sustento'  => 'Memo N° 088-2026-DEP-HDP',
            'goce_haber'          => 1,
            'estado'              => 'APROBADO',
            'fecha_solicitud'     => '2026-09-20',
            'es_citt'             => false
        ],
        [
            'permiso_id'          => 2,
            'personal_id'         => 4,
            'dni'                 => '46985214',
            'nombre_completo'     => 'Lic. Patricia Noelia Ríos Mendoza',
            'cargo'               => 'Enfermera Asistencial de Emergencia',
            'servicio_id'         => 1,
            'servicio_nombre'     => 'Emergencia y Cuidados Críticos',
            'condicion_laboral'   => 'CAS',
            'tipo_id'             => 1,
            'tipo_nombre'         => 'Descanso Médico (CITT / EsSalud)',
            'fecha_inicio'        => '2026-09-21',
            'fecha_fin'           => '2026-09-23',
            'dias_solicitados'    => 3,
            'motivo'              => 'Incapacidad temporal por síndrome febril agudo en evaluación.',
            'documento_sustento'  => 'CITT EsSalud N° 2026-0045812',
            'goce_haber'          => 1,
            'estado'              => 'PENDIENTE',
            'fecha_solicitud'     => '2026-09-21',
            'es_citt'             => true
        ],
        [
            'permiso_id'          => 3,
            'personal_id'         => 10,
            'dni'                 => '46321789',
            'nombre_completo'     => 'Lic. Claudia Milagros Cruzado Vera',
            'cargo'               => 'Enfermera Coordinadora de Sala',
            'servicio_id'         => 2,
            'servicio_nombre'     => 'Hospitalización Medicina y Cirugía',
            'condicion_laboral'   => 'NOMBRADO',
            'tipo_id'             => 6,
            'tipo_nombre'         => 'Compensación de Horas / Retén',
            'fecha_inicio'        => '2026-09-22',
            'fecha_fin'           => '2026-09-22',
            'dias_solicitados'    => 1,
            'motivo'              => 'Compensación por 12 horas extraordinarias laboradas en retén el domingo anterior.',
            'documento_sustento'  => 'Papeleta N° 045-2026',
            'goce_haber'          => 1,
            'estado'              => 'PENDIENTE',
            'fecha_solicitud'     => '2026-09-21',
            'es_citt'             => false
        ],
        [
            'permiso_id'          => 4,
            'personal_id'         => 9,
            'dni'                 => '42589631',
            'nombre_completo'     => 'Dra. Ana Isabel Gutiérrez Torres',
            'cargo'               => 'Médico Internista',
            'servicio_id'         => 2,
            'servicio_nombre'     => 'Hospitalización Medicina y Cirugía',
            'condicion_laboral'   => 'NOMBRADO',
            'tipo_id'             => 3,
            'tipo_nombre'         => 'Capacitación Oficializada MINSA',
            'fecha_inicio'        => '2026-09-24',
            'fecha_fin'           => '2026-09-25',
            'dias_solicitados'    => 2,
            'motivo'              => 'Curso Nacional de Actualización en Medicina Interna y Manejo de Dengue Grave.',
            'documento_sustento'  => 'Resolución Directoral N° 032-2026',
            'goce_haber'          => 1,
            'estado'              => 'APROBADO',
            'fecha_solicitud'     => '2026-09-18',
            'es_citt'             => false
        ],
        [
            'permiso_id'          => 5,
            'personal_id'         => 14,
            'dni'                 => '47852369',
            'nombre_completo'     => 'Obs. Jessica Paola Chinchay Vega',
            'cargo'               => 'Obstetra Asistencial',
            'servicio_id'         => 3,
            'servicio_nombre'     => 'Gineco-Obstetricia y Centro Obstétrico',
            'condicion_laboral'   => 'CAS',
            'tipo_id'             => 5,
            'tipo_nombre'         => 'Permiso por Onomástico',
            'fecha_inicio'        => '2026-09-28',
            'fecha_fin'           => '2026-09-28',
            'dias_solicitados'    => 1,
            'motivo'              => 'Descanso por onomástico de la trabajadora (Fecha de nacimiento: 28 de Setiembre).',
            'documento_sustento'  => 'DNI / Récord de Personal N° 14',
            'goce_haber'          => 1,
            'estado'              => 'APROBADO',
            'fecha_solicitud'     => '2026-09-15',
            'es_citt'             => false
        ],
        [
            'permiso_id'          => 6,
            'personal_id'         => 12,
            'dni'                 => '48521479',
            'nombre_completo'     => 'Tec. Walter Andrés Gil Valdivia',
            'cargo'               => 'Técnico en Enfermería',
            'servicio_id'         => 2,
            'servicio_nombre'     => 'Hospitalización Medicina y Cirugía',
            'condicion_laboral'   => 'CAS',
            'tipo_id'             => 7,
            'tipo_nombre'         => 'Permiso Particular (Sin Goce)',
            'fecha_inicio'        => '2026-09-21',
            'fecha_fin'           => '2026-09-21',
            'dias_solicitados'    => 1,
            'motivo'              => 'Diligencia notarial y bancaria personal impostergable en Chepén.',
            'documento_sustento'  => 'Solicitud Expresa de Parte',
            'goce_haber'          => 0,
            'estado'              => 'PENDIENTE',
            'fecha_solicitud'     => '2026-09-21',
            'es_citt'             => false
        ],
        [
            'permiso_id'          => 7,
            'personal_id'         => 22,
            'dni'                 => '48965214',
            'nombre_completo'     => 'Tec. Nelson David Ortiz Mendoza',
            'cargo'               => 'Técnico de Farmacia',
            'servicio_id'         => 7,
            'servicio_nombre'     => 'Farmacia Hospitalaria',
            'condicion_laboral'   => 'CAS',
            'tipo_id'             => 7,
            'tipo_nombre'         => 'Permiso Particular (Sin Goce)',
            'fecha_inicio'        => '2026-09-15',
            'fecha_fin'           => '2026-09-15',
            'dias_solicitados'    => 1,
            'motivo'              => 'Falta sin sustento documental extemporánea.',
            'documento_sustento'  => 'Sin Documento',
            'goce_haber'          => 0,
            'estado'              => 'RECHAZADO',
            'fecha_solicitud'     => '2026-09-16',
            'es_citt'             => false
        ]
    ];
}
}

