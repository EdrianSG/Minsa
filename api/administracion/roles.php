<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET']);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/SnapshotStore.php';

$pdo = Database::getConnection();
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT r.RolID, r.Codigo, r.Nombre, r.Descripcion,
            COUNT(DISTINCT CASE WHEN u.Activo = 1 AND ur.Activo = 1
            AND (ur.FechaInicio IS NULL OR ur.FechaInicio <= CAST(GETDATE() AS DATE)) AND (ur.FechaFin IS NULL OR ur.FechaFin >= CAST(GETDATE() AS DATE)) THEN u.UsuarioID END) AS Usuarios
            FROM seguridad.Rol r LEFT JOIN seguridad.UsuarioRol ur ON ur.RolID = r.RolID
            LEFT JOIN seguridad.Usuario u ON u.UsuarioID = ur.UsuarioID
            WHERE r.Activo = 1 AND r.SistemaID = ?
            GROUP BY r.RolID, r.Codigo, r.Nombre, r.Descripcion ORDER BY r.RolID");
        $stmt->execute([SystemContext::id($pdo)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $colores = [
            'RRHH_ADMIN'          => '#2563eb',
            'ADMIN_RRHH'          => '#2563eb',
            'ADMIN'               => '#2563eb',
            'JEFE_RRHH'           => '#0284c7',
            'JEFE_SERVICIO'       => '#d97706',
            'OPERADOR_ASISTENCIA' => '#059669',
            'AUDITOR_PLANILLAS'   => '#7c3aed',
            'SUPERVISOR'          => '#0891b2',
            'CONSULTA'            => '#64748b'
        ];

        $roles = [];
        foreach ($rows as $r) {
            $admin = Auth::roleAllows($r['Codigo'], 'administracion');
            $permissions = [];
            foreach (['dashboard','personal','horarios','programacion','asistencia','ausencias','reportes','administracion'] as $module) {
                $permissions[$module] = ['ver' => Auth::roleAllows($r['Codigo'], $module)];
            }
            $permissions['programacion']['editar'] = $admin || in_array($r['Codigo'], ['JEFE_RRHH','JEFE_SERVICIO','SUPERVISOR'], true);
            $permissions['asistencia']['regularizar'] = $admin || in_array($r['Codigo'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA'], true);
            $permissions['ausencias']['dictaminar'] = $admin || in_array($r['Codigo'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA'], true);
            $permissions['reportes']['exportar_excel'] = $permissions['reportes']['ver'];
            $roles[] = [
                'rol_id'         => (int)$r['RolID'],
                'codigo'         => $r['Codigo'],
                'nombre'         => $r['Nombre'],
                'descripcion'    => $r['Descripcion'] ?? '',
                'color'          => $colores[$r['Codigo']] ?? '#2563eb',
                'usuarios_total' => (int)$r['Usuarios'],
                'permisos'       => $permissions
            ];
        }

        if (!empty($roles)) {
            SnapshotStore::set('roles_catalogo_seguridad', $roles, ['source' => 'SQL_SERVER_LIVE']);
            echo json_encode(['ok' => true, 'roles' => $roles, 'total' => count($roles)], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (PDOException $e) {
        error_log('Catálogo de roles SQL: ' . $e->getMessage());
    }
}

// No ofrecer identificadores históricos o ficticios para asignar permisos.
apiError(503, 'No hay un catálogo SQL vigente de roles de RRHH. Revise la conexión y los roles del sistema.');
