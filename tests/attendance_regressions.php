<?php
declare(strict_types=1);
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=1');
require_once __DIR__ . '/../includes/AttendanceRules.php';
require_once __DIR__ . '/../includes/AttendancePeriod.php';
require_once __DIR__ . '/../includes/AttendanceSchedule.php';
require_once __DIR__ . '/../includes/attendance_evaluator.php';
date_default_timezone_set('America/Lima');
$count = 0;
function checkRegression(bool $ok, string $label): void {
    global $count;
    if (!$ok) throw new RuntimeException($label);
    $count++;
}
$window = AttendanceRules::punches(['2026-09-22 07:00', '2026-09-22 19:55', '2026-09-23 08:00', '2026-09-23 12:00'], '2026-09-22', '20:00', '08:00');
checkRegression(date('Y-m-d H:i', $window['entry']) === '2026-09-22 19:55', 'No usa la salida de la guardia anterior como ingreso');
checkRegression(date('Y-m-d H:i', $window['exit']) === '2026-09-23 08:00', 'No toma marcas ajenas posteriores a la ventana');
$late = AttendanceRules::punches(['2026-09-23 00:00', '2026-09-23 08:00'], '2026-09-22', '20:00', '08:00');
checkRegression(($late['entry'] - $late['start']) / 60 === 240, 'Ingreso después de medianoche conserva fecha y retraso');
checkRegression(AttendanceRules::evaluate('00:00', '20:00', '2026-09-22', '08:00')['minutos'] === 240, 'Evaluación y regularización nocturnas conservan el retraso');
$exitOnly = AttendanceRules::punches(['2026-09-23 08:00'], '2026-09-22', '20:00', '08:00');
checkRegression($exitOnly['entry'] === null, 'Una salida aislada no se convierte en ingreso');
checkRegression(AttendanceRules::missingState('2026-09-22', '20:00', '08:00', strtotime('2026-09-23 02:00')) === 'EN_CURSO', 'Guardia del día anterior sigue abierta');
checkRegression(AttendanceRules::missingState('2026-09-22', '20:00', '08:00', strtotime('2026-09-22 19:00')) === 'PROGRAMADO', 'Turno futuro pendiente');
checkRegression(AttendanceRules::missingState('2026-09-22', '20:00', '08:00', strtotime('2026-09-23 09:00')) === 'SIN_DATOS', 'Jornada cerrada sin registro no demuestra falta');
$days = AttendancePeriod::medicalDays([
    ['PersonalID'=>1, 'FechaInicio'=>'2026-08-20', 'FechaFin'=>'2026-10-10'],
    ['PersonalID'=>1, 'FechaInicio'=>'2026-09-03', 'FechaFin'=>'2026-09-05'],
    ['PersonalID'=>2, 'FechaInicio'=>'2026-09-29', 'FechaFin'=>'2026-10-02']
], 2026, 9);
checkRegression($days[1] === 30 && $days[2] === 2, 'CITT incluye permisos que cubren el mes y elimina días duplicados');
$dir = sys_get_temp_dir() . '/minsa-regressions-' . bin2hex(random_bytes(8));
mkdir($dir);
putenv('SNAPSHOT_PATH=' . $dir);
try {
    SnapshotStore::set('turnos_catalogo_oficial', [['codigo'=>'N','turno_id'=>3,'nombre'=>'Noche','hora_inicio'=>'20:00','hora_fin'=>'08:00']]);
    SnapshotStore::set('programacion_serv_7_2026_9', ['personal_servicio'=>[['personal_id'=>1,'patron'=>array_fill(0,30,'3')]]]);
    $row = AttendanceSchedule::apply(['PersonalID'=>1,'ServicioID'=>7,'TurnoID'=>1,'HoraProgInicio'=>'08:00','HoraProgFin'=>'14:00'], '2026-09-22');
    checkRegression($row['HoraProgInicio'] === '20:00' && $row['TurnoID'] === 3, 'Rol mensual prevalece sobre pauta semanal');
    $row += ['DNI'=>'00000001','AsistenciaID'=>null,'Observacion'=>''];
    $evaluated = procesarFilaAsistencia($row, 'Prueba', '2026-09-22');
    checkRegression($evaluated['estado_evaluado'] === 'SIN_DATOS' && !$evaluated['es_ausente'], 'Vista diaria no convierte falta de datos en falta');
    $row['EstadoAsistencia'] = 'FALTA';
    checkRegression(procesarFilaAsistencia($row, 'Prueba', '2026-09-22')['es_ausente'], 'Falta confirmada permanece visible');
} finally {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
    putenv('SNAPSHOT_PATH');
}
echo "OK: $count regresiones de jornadas, CITT y programación.\n";

// Ejecuta la conciliación real con un PDO de memoria, sin sockets ni escrituras SQL.
require_once __DIR__ . '/../includes/AttendanceReconciler.php';
class RegressionPDO extends PDO {
    public array $writes = [];
    public function __construct(public array $people, public array $marks) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $rows = str_contains($query, 'FROM rrhh.Personal p') ? $this->people
            : (str_contains($query, 'SELECT PersonalID, FechaHora') ? $this->marks : []);
        return new RegressionStatement($rows, $query, $this);
    }
}
class RegressionStatement extends PDOStatement {
    public function __construct(private array $rows, private string $sql, private RegressionPDO $connection) {}
    public function execute(?array $params = null): bool {
        if (str_contains($this->sql, 'INSERT INTO rrhh.Asistencia') || str_contains($this->sql, 'UPDATE rrhh.Asistencia')) {
            $this->connection->writes[] = $params;
        }
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
$dir = sys_get_temp_dir() . '/minsa-reconciler-' . bin2hex(random_bytes(8));
mkdir($dir);
putenv('SNAPSHOT_PATH=' . $dir);
try {
    $person = ['PersonalID'=>2, 'ServicioID'=>7, 'TurnoID'=>3, 'HoraProgInicio'=>'20:00', 'HoraProgFin'=>'08:00',
        'AsistenciaID'=>null, 'EstadoActual'=>null, 'ObservacionActual'=>''];
    $pdo = new RegressionPDO([
        $person,
        array_replace($person, ['PersonalID'=>3]),
        array_replace($person, ['PersonalID'=>4, 'TurnoID'=>null]),
        array_replace($person, ['PersonalID'=>5, 'AsistenciaID'=>9, 'EstadoActual'=>'LICENCIA']),
        array_replace($person, ['PersonalID'=>6])
    ], [
        ['PersonalID'=>2, 'FechaHora'=>'2026-09-22 07:00'],
        ['PersonalID'=>2, 'FechaHora'=>'2026-09-22 19:55'],
        ['PersonalID'=>2, 'FechaHora'=>'2026-09-23 08:00'],
        ['PersonalID'=>2, 'FechaHora'=>'2026-09-23 12:00'],
        ['PersonalID'=>6, 'FechaHora'=>'2026-09-23 00:00'],
        ['PersonalID'=>6, 'FechaHora'=>'2026-09-23 08:00']
    ]);
    $summary = AttendanceReconciler::conciliarFecha('2026-09-22', $pdo);
    $writes = array_column($pdo->writes, null, ':personalId');
    checkRegression($writes[2][':horaEntrada'] === '19:55:00' && $writes[2][':horaSalida'] === '08:00:00', 'Conciliación real delimita marcas por jornada');
    checkRegression($writes[3][':estado'] === 'SIN_DATOS', 'Conciliación no inventa una falta sin marcas');
    checkRegression(!isset($writes[4]) && !isset($writes[5]) && $summary['preservados_manuales'] === 1, 'Respeta descansos y licencias');
    checkRegression($writes[6][':estado'] === 'Falta Administrativa' && str_contains($writes[6][':observacion'], '+240 min'), 'Conciliación califica ingreso nocturno con fecha correcta');
} finally {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
    putenv('SNAPSHOT_PATH');
}
echo "OK: 4 regresiones del motor de conciliación con PDO simulado.\n";
