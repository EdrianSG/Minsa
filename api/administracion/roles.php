<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET'], []);

/**
 * API Endpoint: /api/administracion/roles.php
 * Hospital Distrital de Pacasmayo - ETAPA 8
 * 
 * Catálogo de Roles Institucionales y Matriz de Permisos por Módulo:
 * Mapeado a seguridad.Rol, seguridad.Permiso y seguridad.RolPermiso
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$roles = [
    [
        'rol_id'      => 1,
        'codigo'      => 'ADMIN_RRHH',
        'nombre'      => 'Administrador General de RR.HH.',
        'descripcion' => 'Acceso total a todos los módulos: gestión de personal, programación, asistencia, licencias, reportes oficiales y administración del sistema.',
        'color'       => '#2563eb',
        'usuarios_total' => 1,
        'permisos'    => [
            'dashboard'      => ['ver' => true, 'exportar' => true],
            'personal'       => ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true],
            'horarios'       => ['ver' => true, 'crear' => true, 'editar' => true],
            'programacion'   => ['ver' => true, 'editar' => true, 'validar_150h' => true, 'aprobar' => true],
            'asistencia'     => ['ver' => true, 'regularizar' => true, 'reemplazo_urgencia' => true],
            'ausencias'      => ['ver' => true, 'solicitar' => true, 'dictaminar' => true],
            'reportes'       => ['ver' => true, 'exportar_excel' => true, 'exportar_pdf' => true],
            'administracion' => ['ver' => true, 'usuarios' => true, 'roles' => true, 'auditoria' => true, 'parametros' => true]
        ]
    ],
    [
        'rol_id'      => 2,
        'codigo'      => 'JEFE_SERVICIO',
        'nombre'      => 'Jefatura de Servicio / UPSS',
        'descripcion' => 'Gestión operativa del servicio: propuesta y balance del rol mensual, validación de 150h y consulta del récord de asistencia del personal a cargo.',
        'color'       => '#059669',
        'usuarios_total' => 1,
        'permisos'    => [
            'dashboard'      => ['ver' => true, 'exportar' => false],
            'personal'       => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'horarios'       => ['ver' => true, 'crear' => false, 'editar' => false],
            'programacion'   => ['ver' => true, 'editar' => true, 'validar_150h' => true, 'aprobar' => false],
            'asistencia'     => ['ver' => true, 'regularizar' => false, 'reemplazo_urgencia' => true],
            'ausencias'      => ['ver' => true, 'solicitar' => true, 'dictaminar' => false],
            'reportes'       => ['ver' => true, 'exportar_excel' => true, 'exportar_pdf' => true],
            'administracion' => ['ver' => false, 'usuarios' => false, 'roles' => false, 'auditoria' => false, 'parametros' => false]
        ]
    ],
    [
        'rol_id'      => 3,
        'codigo'      => 'OPERADOR_ASISTENCIA',
        'nombre'      => 'Operador de Control de Asistencia',
        'descripcion' => 'Seguimiento diario de asistencia, regularizaciones de marcación omitida por justificación y registro de descansos médicos con CITT.',
        'color'       => '#d97706',
        'usuarios_total' => 2,
        'permisos'    => [
            'dashboard'      => ['ver' => true, 'exportar' => false],
            'personal'       => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'horarios'       => ['ver' => true, 'crear' => false, 'editar' => false],
            'programacion'   => ['ver' => true, 'editar' => false, 'validar_150h' => false, 'aprobar' => false],
            'asistencia'     => ['ver' => true, 'regularizar' => true, 'reemplazo_urgencia' => false],
            'ausencias'      => ['ver' => true, 'solicitar' => true, 'dictaminar' => true],
            'reportes'       => ['ver' => true, 'exportar_excel' => true, 'exportar_pdf' => true],
            'administracion' => ['ver' => false, 'usuarios' => false, 'roles' => false, 'auditoria' => false, 'parametros' => false]
        ]
    ],
    [
        'rol_id'      => 4,
        'codigo'      => 'AUDITOR_PLANILLAS',
        'nombre'      => 'Auditor de Planillas y Remuneraciones',
        'descripcion' => 'Consulta y auditoría de asistencia consolidada, tardanzas acumuladas, faltas sujetas a descuento y reporte SETI para cálculo de planillas.',
        'color'       => '#7c3aed',
        'usuarios_total' => 1,
        'permisos'    => [
            'dashboard'      => ['ver' => true, 'exportar' => true],
            'personal'       => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'horarios'       => ['ver' => true, 'crear' => false, 'editar' => false],
            'programacion'   => ['ver' => true, 'editar' => false, 'validar_150h' => true, 'aprobar' => false],
            'asistencia'     => ['ver' => true, 'regularizar' => false, 'reemplazo_urgencia' => false],
            'ausencias'      => ['ver' => true, 'solicitar' => false, 'dictaminar' => false],
            'reportes'       => ['ver' => true, 'exportar_excel' => true, 'exportar_pdf' => true],
            'administracion' => ['ver' => false, 'usuarios' => false, 'roles' => false, 'auditoria' => true, 'parametros' => false]
        ]
    ]
];

echo json_encode([
    'ok'    => true,
    'total' => count($roles),
    'roles' => $roles
], JSON_UNESCAPED_UNICODE);
