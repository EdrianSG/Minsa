<?php
declare(strict_types=1);
putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT=1');
require_once __DIR__ . '/../includes/SnapshotStore.php';
require_once __DIR__ . '/../includes/LoginThrottle.php';
require_once __DIR__ . '/../includes/AuditFallback.php';
$dir = sys_get_temp_dir() . '/sigh-security-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
putenv('SNAPSHOT_PATH=' . $dir . '/snapshots');
putenv('AUTH_RATE_PATH=' . $dir . '/rate');
putenv('AUDIT_PATH=' . $dir . '/audit');
function securityCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
    foreach (['personal_catalogo_completo','turnos_catalogo_oficial','permisos_lista_completa','programacion_serv_0_2026_10','asistencia_diaria_2026-10-27','ausentes_2026-10-27_serv_0'] as $key) {
        putenv('DB_NAME=base_a');
        securityCheck(SnapshotStore::set($key, ['dato'=>'base a']), 'No se guardó el respaldo.');
        securityCheck(SnapshotStore::has($key), 'No se puede leer un respaldo propio.');
        putenv('DB_NAME=base_b');
        securityCheck(SnapshotStore::get($key) === null && !SnapshotStore::has($key), 'Se aceptó un respaldo de otra base: ' . $key);
        putenv('DB_NAME=base_a');
        securityCheck(SnapshotStore::get($key)['data']['dato'] === 'base a', 'Se perdió el respaldo de la base original.');
    }
    file_put_contents($dir . '/snapshots/sin_origen.json', json_encode(['data'=>['antiguo']]));
    securityCheck(SnapshotStore::get('sin_origen') === null, 'Se aceptó un respaldo sin procedencia.');
    for ($i=0; $i<10; $i++) securityCheck(LoginThrottle::allow('usuario'), 'El límite se activó antes de diez intentos.');
    securityCheck(!LoginThrottle::allow('usuario'), 'No se limitó el undécimo intento.');
    securityCheck(AuditFallback::append(['usuario_id'=>null,'nombre_usuario'=>null,'accion'=>'PRUEBA']), 'No hay respaldo durable de auditoría.');
    $file = glob($dir . '/audit/*.jsonl')[0];
    $event = json_decode(trim(file_get_contents($file)), true);
    securityCheck($event['persistencia'] === 'ARCHIVO_PENDIENTE_SQL' && $event['usuario_id'] === null, 'La auditoría inventó una identidad o una persistencia SQL.');
    echo "OK: procedencia de respaldos, aislamiento entre bases, límite de acceso y auditoría durable.\n";
} finally {
    foreach (glob($dir . '/*/*') ?: [] as $file) unlink($file);
    foreach (glob($dir . '/*') ?: [] as $folder) rmdir($folder);
    rmdir($dir);
}
