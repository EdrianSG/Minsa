<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET']);
header('Content-Type: application/json; charset=utf-8');
$pdo = apiRequireDatabase();
try {
    $stmt = $pdo->prepare("SELECT r.RolID, r.Codigo, r.Nombre, r.Descripcion,
        COUNT(DISTINCT CASE WHEN u.Activo = 1 AND ur.Activo = 1
        AND (ur.FechaInicio IS NULL OR ur.FechaInicio <= CAST(GETDATE() AS DATE)) AND (ur.FechaFin IS NULL OR ur.FechaFin >= CAST(GETDATE() AS DATE)) THEN u.UsuarioID END) AS Usuarios
        FROM seguridad.Rol r LEFT JOIN seguridad.UsuarioRol ur ON ur.RolID = r.RolID
        LEFT JOIN seguridad.Usuario u ON u.UsuarioID = ur.UsuarioID
        WHERE r.Activo = 1 AND r.SistemaID = ? GROUP BY r.RolID, r.Codigo, r.Nombre, r.Descripcion ORDER BY r.RolID");
    $stmt->execute([SystemContext::id($pdo)]);
    $rows=$stmt->fetchAll();
    $roles = [];
    foreach ($rows as $r) {
        $admin = Auth::roleAllows($r['Codigo'], 'administracion');
        $permissions = [];
        foreach (['dashboard','personal','horarios','programacion','asistencia','ausencias','reportes','administracion'] as $module) {
            $permissions[$module] = ['ver'=>Auth::roleAllows($r['Codigo'], $module)];
        }
        $permissions['programacion']['editar'] = $admin || in_array($r['Codigo'], ['JEFE_RRHH','JEFE_SERVICIO','SUPERVISOR'], true);
        $permissions['asistencia']['regularizar'] = $admin || in_array($r['Codigo'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA'], true);
        $permissions['ausencias']['dictaminar'] = $admin || in_array($r['Codigo'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA'], true);
        $permissions['reportes']['exportar_excel'] = $permissions['reportes']['ver'];
        $roles[] = ['rol_id'=>(int)$r['RolID'], 'codigo'=>$r['Codigo'], 'nombre'=>$r['Nombre'],
            'descripcion'=>$r['Descripcion'] ?? '', 'color'=>'#2563eb', 'usuarios_total'=>(int)$r['Usuarios'], 'permisos'=>$permissions];
    }
    echo json_encode(['ok'=>true,'roles'=>$roles,'total'=>count($roles)], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Catálogo de roles: ' . $e->getMessage());
    apiError(503, 'No se pudieron consultar los roles de SQL Server.');
}
