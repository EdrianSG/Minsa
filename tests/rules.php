<?php
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=1');
require_once __DIR__ . '/../includes/AttendanceRules.php';
require_once __DIR__ . '/../includes/SnapshotStore.php';
$count = 0;
function verify(bool $condition, string $label): void {
    global $count;
    if (!$condition) throw new RuntimeException($label);
    $count++;
}
foreach ([['07:59','ASISTIO',0],['08:05','ASISTIO',0],['08:06','TARDANZA',6],
    ['08:30','TARDANZA',30],['08:31','FALTA_ADMINISTRATIVA',31],[null,'FALTA',0]] as [$entry,$state,$minutes]) {
    $result = AttendanceRules::evaluate($entry, '08:00');
    verify($result['estado'] === $state && $result['minutos'] === $minutes, 'Límite de tolerancia');
}
$dir = sys_get_temp_dir() . '/minsa-rules-' . bin2hex(random_bytes(8));
mkdir($dir);
putenv('SNAPSHOT_PATH=' . $dir);
try {
    verify(SnapshotStore::set('test', ['turno'=>'M']), 'Primer guardado');
    verify(SnapshotStore::getVersion('test') === 1, 'Versión inicial');
    verify(SnapshotStore::set('test', ['turno'=>'N']), 'Segundo guardado');
    verify(SnapshotStore::getVersion('test') === 2, 'Incremento de versión');
    verify(!SnapshotStore::set('test', [NAN]), 'JSON inválido no se confirma');
    verify(SnapshotStore::get('test')['data']['turno'] === 'N', 'Fallo conserva contenido anterior');
    putenv('SNAPSHOT_PATH=/dev/null/minsa');
    verify(!SnapshotStore::set('test', []), 'Error de disco no se confirma');
} finally {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
}
echo "OK: $count comprobaciones de reglas y persistencia.\n";
require_once __DIR__ . '/../includes/attendance_evaluator.php';
$row = ['PersonalID'=>1, 'DNI'=>'00000001', 'TurnoID'=>3, 'AsistenciaID'=>1,
    'HoraProgInicio'=>'20:00', 'HoraProgFin'=>'08:00', 'HoraEntrada'=>'20:00',
    'HoraSalida'=>'23:00', 'EstadoAsistencia'=>'ASISTIO', 'Observacion'=>''];
$result = procesarFilaAsistencia($row, 'Prueba', '2026-09-22');
verify($result['minutos_salida_anticipada'] === 540.0, 'Guardia nocturna: salida antes de medianoche');
$row['HoraSalida'] = '07:30';
verify(procesarFilaAsistencia($row, 'Prueba', '2026-09-22')['minutos_salida_anticipada'] === 30.0,
    'Guardia nocturna: salida del día siguiente');
$row['EstadoAsistencia'] = 'LICENCIA';
verify(procesarFilaAsistencia($row, 'Prueba', '2026-09-22')['estado_evaluado'] === 'LICENCIA',
    'Licencia aprobada prevalece sobre marcación');
echo "OK: 3 comprobaciones de guardias y licencias.\n";
