<?php
/**
 * Script de Inicialización y Creación de Roles Institucionales en Microsoft SQL Server
 * Hospital Distrital de Pacasmayo - BD_HOSPITAL_V3 (Esquema seguridad)
 * 
 * Uso vía terminal:
 * php database/crear_roles_seguridad_v3.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "Solo ejecutable por línea de comandos (CLI).\n";
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SystemContext.php';
require_once __DIR__ . '/../includes/SnapshotStore.php';

echo "=========================================================================\n";
echo " CREACIÓN Y SINCRONIZACIÓN DE ROLES INSTITUCIONALES EN SQL SERVER (V3)\n";
echo " Hospital Distrital de Pacasmayo - Sistema de RR.HH.\n";
echo "=========================================================================\n\n";

$dbConfig = Database::getConfig();
echo "Configuración activa:\n";
echo "  - Servidor: {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "  - Base de Datos: {$dbConfig['database']}\n";
echo "  - Sistema Destino: " . SystemContext::code() . " (Recursos Humanos)\n\n";

// Conectar con SQL Server
$pdo = Database::getConnection(true);
if (!$pdo) {
    fwrite(STDERR, "AVISO: No se pudo conectar directamente a SQL Server ({$dbConfig['host']}:{$dbConfig['port']}).\n");
    fwrite(STDERR, "Detalle: " . Database::getLastError() . "\n\n");
    fwrite(STDERR, "Se ha generado también el script T-SQL para SSMS / DBeaver:\n");
    fwrite(STDERR, "  -> database/crear_roles_seguridad_v3.sql\n\n");
    
    // Guardar roles en SnapshotStore para permitir operar en Modo Offline / Auxiliar de inmediato
    guardarSnapshotRolesOffline();
    exit(0);
}

try {
    $pdo->beginTransaction();

    // 1. Verificar o crear Sistema RRHH
    $systemCode = SystemContext::code();
    $stmt = $pdo->prepare("SELECT SistemaID FROM seguridad.Sistema WHERE Codigo = ?");
    $stmt->execute([$systemCode]);
    $sistemaId = $stmt->fetchColumn();

    if (!$sistemaId) {
        $insSys = $pdo->prepare("INSERT INTO seguridad.Sistema (Codigo, Nombre, Descripcion, Activo) VALUES (?, ?, ?, 1)");
        $insSys->execute([$systemCode, 'Recursos Humanos', 'Sistema de Gestión de Personal, Asistencia y Roles']);
        $sistemaId = (int)$pdo->lastInsertId();
        echo " [+] Sistema '{$systemCode}' creado con ID: {$sistemaId}\n";
    } else {
        $sistemaId = (int)$sistemaId;
        $updSys = $pdo->prepare("UPDATE seguridad.Sistema SET Activo = 1 WHERE SistemaID = ?");
        $updSys->execute([$sistemaId]);
        echo " [*] Sistema '{$systemCode}' existente verificado (ID: {$sistemaId})\n";
    }

    // 2. Módulos oficiales del sistema
    $modulos = [
        'dashboard'      => ['nombre' => 'Panel Principal',          'ruta' => 'index.php?page=dashboard'],
        'personal'       => ['nombre' => 'Directorio de Personal',   'ruta' => 'index.php?page=personal'],
        'horarios'       => ['nombre' => 'Horarios y Turnos',        'ruta' => 'index.php?page=horarios'],
        'programacion'   => ['nombre' => 'Programación Mensual',     'ruta' => 'index.php?page=programacion'],
        'asistencia'     => ['nombre' => 'Control de Asistencia',    'ruta' => 'index.php?page=asistencia'],
        'ausencias'      => ['nombre' => 'Ausencias y CITT',         'ruta' => 'index.php?page=ausencias'],
        'reportes'       => ['nombre' => 'Reportes Oficiales MINSA', 'ruta' => 'index.php?page=reportes'],
        'administracion' => ['nombre' => 'Administración y Seguridad','ruta' => 'index.php?page=administracion']
    ];

    echo "\n --- Sincronizando Módulos del Sistema ---\n";
    $moduloIds = [];
    foreach ($modulos as $modCod => $modInfo) {
        $sMod = $pdo->prepare("SELECT ModuloID FROM seguridad.Modulo WHERE SistemaID = ? AND Codigo = ?");
        $sMod->execute([$sistemaId, $modCod]);
        $modId = $sMod->fetchColumn();

        if (!$modId) {
            $insMod = $pdo->prepare("INSERT INTO seguridad.Modulo (SistemaID, Codigo, Nombre, Ruta, Activo) VALUES (?, ?, ?, ?, 1)");
            $insMod->execute([$sistemaId, $modCod, $modInfo['nombre'], $modInfo['ruta']]);
            $modId = (int)$pdo->lastInsertId();
            echo "  [+] Módulo registrado: {$modCod} (ID: {$modId})\n";
        } else {
            $modId = (int)$modId;
            $updMod = $pdo->prepare("UPDATE seguridad.Modulo SET Nombre = ?, Ruta = ?, Activo = 1 WHERE ModuloID = ?");
            $updMod->execute([$modInfo['nombre'], $modInfo['ruta'], $modId]);
        }
        $moduloIds[$modCod] = $modId;
    }

    // 3. Permisos granulares
    echo "\n --- Sincronizando Permisos Granulares ---\n";
    $permisosDef = [
        ['modulo' => 'dashboard',      'accion' => 'ver',             'codigo' => 'RRHH.dashboard.ver'],
        ['modulo' => 'personal',       'accion' => 'ver',             'codigo' => 'RRHH.personal.ver'],
        ['modulo' => 'horarios',       'accion' => 'ver',             'codigo' => 'RRHH.horarios.ver'],
        ['modulo' => 'programacion',   'accion' => 'ver',             'codigo' => 'RRHH.programacion.ver'],
        ['modulo' => 'programacion',   'accion' => 'editar',          'codigo' => 'RRHH.programacion.editar'],
        ['modulo' => 'asistencia',     'accion' => 'ver',             'codigo' => 'RRHH.asistencia.ver'],
        ['modulo' => 'asistencia',     'accion' => 'regularizar',     'codigo' => 'RRHH.asistencia.regularizar'],
        ['modulo' => 'ausencias',      'accion' => 'ver',             'codigo' => 'RRHH.ausencias.ver'],
        ['modulo' => 'ausencias',      'accion' => 'dictaminar',      'codigo' => 'RRHH.ausencias.dictaminar'],
        ['modulo' => 'reportes',       'accion' => 'ver',             'codigo' => 'RRHH.reportes.ver'],
        ['modulo' => 'reportes',       'accion' => 'exportar_excel',  'codigo' => 'RRHH.reportes.exportar_excel'],
        ['modulo' => 'administracion', 'accion' => 'ver',             'codigo' => 'RRHH.administracion.ver']
    ];

    $permisoIds = [];
    foreach ($permisosDef as $pDef) {
        $modId = $moduloIds[$pDef['modulo']] ?? null;
        if (!$modId) continue;

        $sPer = $pdo->prepare("SELECT PermisoID FROM seguridad.Permiso WHERE Codigo = ?");
        $sPer->execute([$pDef['codigo']]);
        $perId = $sPer->fetchColumn();

        if (!$perId) {
            $insPer = $pdo->prepare("INSERT INTO seguridad.Permiso (ModuloID, Codigo, Nombre, Activo) VALUES (?, ?, ?, 1)");
            $insPer->execute([$modId, $pDef['codigo'], $pDef['modulo'] . ': ' . $pDef['accion']]);
            $perId = (int)$pdo->lastInsertId();
            echo "  [+] Permiso registrado: {$pDef['codigo']} (ID: {$perId})\n";
        } else {
            $perId = (int)$perId;
            $updPer = $pdo->prepare("UPDATE seguridad.Permiso SET ModuloID = ?, Activo = 1 WHERE PermisoID = ?");
            $updPer->execute([$modId, $perId]);
        }
        $permisoIds[$pDef['codigo']] = $perId;
    }

    // 4. Catálogo oficial de roles institucionales
    echo "\n --- Sincronizando Catálogo de Roles Institucionales ---\n";
    $rolesCatalogo = obtenerDefinicionRoles();

    $rolesResumen = [];
    foreach ($rolesCatalogo as $rDef) {
        $sRol = $pdo->prepare("SELECT RolID FROM seguridad.Rol WHERE Codigo = ?");
        $sRol->execute([$rDef['codigo']]);
        $rolId = $sRol->fetchColumn();

        if (!$rolId) {
            $insRol = $pdo->prepare("INSERT INTO seguridad.Rol (SistemaID, Codigo, Nombre, Descripcion, Activo) VALUES (?, ?, ?, ?, 1)");
            $insRol->execute([$sistemaId, $rDef['codigo'], $rDef['nombre'], $rDef['descripcion']]);
            $rolId = (int)$pdo->lastInsertId();
            echo "  [+] Rol CREADO: {$rDef['codigo']} - {$rDef['nombre']} (ID: {$rolId})\n";
        } else {
            $rolId = (int)$rolId;
            $updRol = $pdo->prepare("UPDATE seguridad.Rol SET SistemaID = ?, Nombre = ?, Descripcion = ?, Activo = 1 WHERE RolID = ?");
            $updRol->execute([$sistemaId, $rDef['nombre'], $rDef['descripcion'], $rolId]);
            echo "  [*] Rol ACTUALIZADO: {$rDef['codigo']} - {$rDef['nombre']} (ID: {$rolId})\n";
        }

        // Asignar permisos al rol en seguridad.RolPermiso
        $countPerms = 0;
        foreach ($rDef['permisos'] as $pCod) {
            $pId = $permisoIds[$pCod] ?? null;
            if (!$pId) continue;

            $sRP = $pdo->prepare("SELECT 1 FROM seguridad.RolPermiso WHERE RolID = ? AND PermisoID = ?");
            $sRP->execute([$rolId, $pId]);
            if ($sRP->fetchColumn()) {
                $updRP = $pdo->prepare("UPDATE seguridad.RolPermiso SET Activo = 1 WHERE RolID = ? AND PermisoID = ?");
                $updRP->execute([$rolId, $pId]);
            } else {
                $insRP = $pdo->prepare("INSERT INTO seguridad.RolPermiso (RolID, PermisoID, Activo, FechaAsignacion) VALUES (?, ?, 1, SYSDATETIME())");
                $insRP->execute([$rolId, $pId]);
            }
            $countPerms++;
        }

        $rolesResumen[] = [
            'rol_id' => $rolId,
            'codigo' => $rDef['codigo'],
            'nombre' => $rDef['nombre'],
            'descripcion' => $rDef['descripcion'],
            'color' => $rDef['color'],
            'permisos_count' => $countPerms
        ];
    }

    // Registrar auditoría si existe la tabla
    try {
        $insAud = $pdo->prepare("
            INSERT INTO auditoria.EventoAuditoria (
                UsuarioID, SistemaID, FechaHora, Modulo, Accion, TablaAfectada, RegistroID, Descripcion
            ) VALUES (
                1, ?, SYSDATETIME(), 'SEGURIDAD', 'INICIALIZAR_ROLES', 'seguridad.Rol', NULL,
                'Sincronización del catálogo oficial de 7 roles institucionales y permisos RBAC para RRHH'
            )
        ");
        $insAud->execute([$sistemaId]);
    } catch (Exception $e) {
        // Continuar si auditoría es opcional
    }

    $pdo->commit();
    echo "\n >>> TRANSACCIÓN CONFIRMADA EN SQL SERVER CON ÉXITO <<<\n\n";

    // Guardar en SnapshotStore local para resiliencia offline
    guardarSnapshotRolesOffline();

    echo "Resumen de Roles Disponibles en el Sistema:\n";
    foreach ($rolesResumen as $r) {
        printf("  - [%d] %-20s : %s (%d permisos asignados)\n", $r['rol_id'], $r['codigo'], $r['nombre'], $r['permisos_count']);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "\nERROR: Se revirtió la transacción debido a un fallo: " . $e->getMessage() . "\n");
    exit(1);
}

function obtenerDefinicionRoles(): array {
    return [
        [
            'codigo' => 'RRHH_ADMIN',
            'nombre' => 'Administrador de Recursos Humanos',
            'descripcion' => 'Control total del sistema: gestión de cuentas, asignación de roles, configuración normativa, auditoría y catálogos.',
            'color' => '#2563eb',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.personal.ver', 'RRHH.horarios.ver', 'RRHH.programacion.ver',
                'RRHH.programacion.editar', 'RRHH.asistencia.ver', 'RRHH.asistencia.regularizar',
                'RRHH.ausencias.ver', 'RRHH.ausencias.dictaminar', 'RRHH.reportes.ver',
                'RRHH.reportes.exportar_excel', 'RRHH.administracion.ver'
            ]
        ],
        [
            'codigo' => 'JEFE_RRHH',
            'nombre' => 'Jefatura de Recursos Humanos',
            'descripcion' => 'Supervisión institucional global, aprobación final de roles mensuales, dictamen de licencias/CITT y reportes oficiales.',
            'color' => '#0284c7',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.personal.ver', 'RRHH.horarios.ver', 'RRHH.programacion.ver',
                'RRHH.programacion.editar', 'RRHH.asistencia.ver', 'RRHH.asistencia.regularizar',
                'RRHH.ausencias.ver', 'RRHH.ausencias.dictaminar', 'RRHH.reportes.ver',
                'RRHH.reportes.exportar_excel'
            ]
        ],
        [
            'codigo' => 'JEFE_SERVICIO',
            'nombre' => 'Jefe de Servicio / UPSS',
            'descripcion' => 'Elaboración y validación del rol mensual (regla 150h asistenciales) y reemplazos de urgencia de su servicio hospitalario.',
            'color' => '#d97706',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.personal.ver', 'RRHH.horarios.ver', 'RRHH.programacion.ver',
                'RRHH.programacion.editar', 'RRHH.asistencia.ver', 'RRHH.ausencias.ver',
                'RRHH.reportes.ver', 'RRHH.reportes.exportar_excel'
            ]
        ],
        [
            'codigo' => 'OPERADOR_ASISTENCIA',
            'nombre' => 'Operador de Asistencia',
            'descripcion' => 'Monitoreo diario de marcaciones, regularización de omisiones, registro de permisos/CITT e importación biométrica.',
            'color' => '#059669',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.personal.ver', 'RRHH.horarios.ver', 'RRHH.programacion.ver',
                'RRHH.asistencia.ver', 'RRHH.asistencia.regularizar', 'RRHH.ausencias.ver',
                'RRHH.ausencias.dictaminar', 'RRHH.reportes.ver', 'RRHH.reportes.exportar_excel'
            ]
        ],
        [
            'codigo' => 'AUDITOR_PLANILLAS',
            'nombre' => 'Auditor de Planillas',
            'descripcion' => 'Auditoría de cumplimiento de carga horaria (150h), cómputo de tardanzas e inasistencias y exportación a planillas.',
            'color' => '#7c3aed',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.personal.ver', 'RRHH.horarios.ver', 'RRHH.programacion.ver',
                'RRHH.asistencia.ver', 'RRHH.ausencias.ver', 'RRHH.reportes.ver',
                'RRHH.reportes.exportar_excel', 'RRHH.administracion.ver'
            ]
        ],
        [
            'codigo' => 'SUPERVISOR',
            'nombre' => 'Supervisor de Asistencia y Turnos',
            'descripcion' => 'Supervisión de turnos programados, verificación de cobertura asistencial en tiempo real y apoyo en rol mensual.',
            'color' => '#0891b2',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.horarios.ver', 'RRHH.programacion.ver',
                'RRHH.programacion.editar', 'RRHH.asistencia.ver', 'RRHH.ausencias.ver'
            ]
        ],
        [
            'codigo' => 'CONSULTA',
            'nombre' => 'Usuario de Consulta',
            'descripcion' => 'Acceso de solo lectura a la asistencia diaria, programación mensual y generación de reportes generales.',
            'color' => '#64748b',
            'permisos' => [
                'RRHH.dashboard.ver', 'RRHH.asistencia.ver', 'RRHH.reportes.ver', 'RRHH.reportes.exportar_excel'
            ]
        ]
    ];
}

function guardarSnapshotRolesOffline() {
    $rolesCatalogo = obtenerDefinicionRoles();
    $snapshotData = [];
    $id = 1;
    foreach ($rolesCatalogo as $r) {
        $permissions = [];
        foreach (['dashboard','personal','horarios','programacion','asistencia','ausencias','reportes','administracion'] as $module) {
            $permissions[$module] = ['ver' => in_array("RRHH.{$module}.ver", $r['permisos'], true)];
        }
        $permissions['programacion']['editar'] = in_array('RRHH.programacion.editar', $r['permisos'], true);
        $permissions['asistencia']['regularizar'] = in_array('RRHH.asistencia.regularizar', $r['permisos'], true);
        $permissions['ausencias']['dictaminar'] = in_array('RRHH.ausencias.dictaminar', $r['permisos'], true);
        $permissions['reportes']['exportar_excel'] = in_array('RRHH.reportes.exportar_excel', $r['permisos'], true);

        $snapshotData[] = [
            'rol_id' => $id++,
            'codigo' => $r['codigo'],
            'nombre' => $r['nombre'],
            'descripcion' => $r['descripcion'],
            'color' => $r['color'],
            'usuarios_total' => 0,
            'permisos' => $permissions
        ];
    }
    SnapshotStore::set('roles_catalogo_seguridad', $snapshotData, ['source' => 'OFFLINE_PRESET']);
    echo " [*] Snapshot local generado en SnapshotStore con los 7 roles institucionales.\n";
}
