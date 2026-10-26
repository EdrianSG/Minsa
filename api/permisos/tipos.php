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

if (!$dbConnected || empty($tipos)) {
    $tipos = [
        [
            'TipoPermisoLaboralID' => 1,
            'Nombre'               => 'Descanso Médico (CITT / EsSalud)',
            'Descripcion'          => 'Incapacidad temporal para el trabajo con goce de haber sustentado en CITT de EsSalud o certificado médico visado.',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 1,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 2,
            'Nombre'               => 'Comisión Oficial de Servicio',
            'Descripcion'          => 'Comisión asistencial o administrativa en representación oficial del Hospital Distrital de Pacasmayo.',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 0,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 3,
            'Nombre'               => 'Capacitación Oficializada MINSA',
            'Descripcion'          => 'Actividades formativas, congresos o talleres de salud autorizados por la Dirección Ejecutiva.',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 0,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 4,
            'Nombre'               => 'Licencia por Maternidad / Paternidad',
            'Descripcion'          => 'Licencia legal por maternidad prenatal y postnatal (98 días) o paternidad (10 días calendario).',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 1,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 5,
            'Nombre'               => 'Permiso por Onomástico',
            'Descripcion'          => 'Derecho de 1 día de descanso con goce de haber durante el mes de natalicio del trabajador.',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 0,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 6,
            'Nombre'               => 'Compensación de Horas / Retén',
            'Descripcion'          => 'Canje compensatorio de turnos extraordinarios o guardias de retén cumplidas efectivamente.',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 0,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 7,
            'Nombre'               => 'Permiso Particular (Sin Goce)',
            'Descripcion'          => 'Permiso por motivos personales particulares. Sujeto a deducción en la planilla mensual de haberes.',
            'GoceHaber'            => 0,
            'RequiereCITT'         => 0,
            'Estado'               => 1
        ],
        [
            'TipoPermisoLaboralID' => 8,
            'Nombre'               => 'Licencia por Duelo Familiar',
            'Descripcion'          => 'Licencia por fallecimiento de cónyuge, padres, hijos o hermanos (5 a 8 días según distancia).',
            'GoceHaber'            => 1,
            'RequiereCITT'         => 0,
            'Estado'               => 1
        ]
    ];
}

echo json_encode([
    'ok'           => true,
    'total'        => count($tipos),
    'tipos'        => $tipos,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
