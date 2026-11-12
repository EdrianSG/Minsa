<?php
require_once __DIR__.'/../includes/AttendanceSummary.php';
function verifyDashboard(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$shift = ['turno_id'=>1,'hora_prog_inicio'=>'07:00','hora_prog_fin'=>'13:00'];
$rows = [
    $shift + ['estado_evaluado'=>'ASISTIO','hora_entrada'=>'07:00'],
    $shift + ['estado_evaluado'=>'TARDANZA','hora_entrada'=>'07:10','minutos_tardanza'=>10],
    $shift + ['estado_evaluado'=>'EN_CURSO','hora_entrada'=>null],
    ['estado_evaluado'=>'SIN_DATOS','turno_id'=>null],
    ['estado_evaluado'=>'DESCANSO','turno_id'=>null]
];
$s = AttendanceSummary::fromRows($rows);
verifyDashboard($s['total_programados'] === 3, 'No contar personal sin turno ni descansos como programado.');
verifyDashboard($s['asistio_puntual'] === 1 && $s['tardanzas'] === 1, 'Separar puntualidad de tardanza.');
verifyDashboard($s['sin_programacion'] === 1 && $s['descansos'] === 1, 'Separar descansos y datos incompletos.');
verifyDashboard($s['sin_marcacion'] === 1 && $s['faltas'] === 0, 'Una marcación pendiente no acredita falta.');
verifyDashboard($s['porcentaje_asistencia'] === null, 'Sin porcentaje definitivo con casos pendientes.');
$s = AttendanceSummary::fromRows(array_slice($rows,0,2));
verifyDashboard($s['porcentaje_asistencia'] === 100.0 && $s['porcentaje_puntualidad'] === 50.0, 'Asistencia y puntualidad tienen distinto numerador.');
$s = AttendanceSummary::fromRows([]);
verifyDashboard($s['total_programados'] === 0 && $s['porcentaje_asistencia'] === null, 'Vacío no implica cumplimiento.');
echo "OK: 7 comprobaciones del resumen diario.\n";
