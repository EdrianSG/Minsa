<?php
require_once __DIR__ . '/../includes/auth.php';

function verifyRole(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach (['RRHH_ADMIN', 'ADMIN_RRHH', 'ADMIN'] as $role) {
    $_SESSION['user'] = ['database_source'=>Database::sourceId(), 'system_code'=>SystemContext::code(), 'usuario_id' => 1, 'rol' => $role];
    foreach (['dashboard', 'personal', 'horarios', 'programacion', 'asistencia', 'ausencias', 'reportes', 'administracion'] as $module) {
        verifyRole(Auth::checkPermission($module), "$role no puede abrir $module");
    }
    verifyRole(Auth::hasRole('ADMIN', 'ADMIN_RRHH'), "$role no puede realizar operaciones administrativas");
    verifyRole(Auth::getAllowedServiceId() === null, "$role perdió el alcance global");
    verifyRole(Auth::checkServiceAccess(0) && Auth::checkServiceAccess(10), "$role perdió el acceso a servicios");
    verifyRole($_SESSION['user']['rol'] === $role, 'El código SQL debe conservarse para revalidar la sesión');
}

foreach (['DESCONOCIDO', 'CONSULTA', 'JEFE_SERVICIO'] as $role) {
    $_SESSION['user'] = ['database_source'=>Database::sourceId(), 'system_code'=>SystemContext::code(), 'usuario_id' => 1, 'rol' => $role, 'servicio_id' => 10];
    verifyRole(!Auth::checkPermission('administracion'), "$role recibió permisos administrativos");
    verifyRole(!Auth::hasRole('ADMIN', 'ADMIN_RRHH'), "$role recibió permisos de escritura administrativa");
    verifyRole(!Auth::checkServiceAccess(11), "$role accedió a otro servicio");
}
$_SESSION = [];
echo "OK: roles administrativos compatibles y restricciones conservadas.\n";
