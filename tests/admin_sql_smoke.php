<?php
/** SQL real: todos los registros creados por esta prueba se revierten. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/UserAdmin.php';
require_once __DIR__ . '/../includes/SystemSettings.php';
date_default_timezone_set('America/Lima');

/** Convierte las transacciones de los servicios en savepoints dentro de una transacción de prueba. */
class AuditSavepointPDO extends PDO {
    private bool $active = false;
    public function __construct(private PDO $inner) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return $this->inner->prepare($query, $options); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        return $fetchMode === null ? $this->inner->query($query) : $this->inner->query($query, $fetchMode, ...$args);
    }
    public function beginTransaction(): bool { $this->inner->exec('SAVE TRANSACTION admin_smoke'); return $this->active = true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->active = false; return true; }
    public function rollBack(): bool { $this->inner->exec('ROLLBACK TRANSACTION admin_smoke'); $this->active = false; return true; }
}
function ensureAdmin(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$pdo = Database::getConnection(true);
if (!$pdo) { fwrite(STDERR, "SQL no disponible.\n"); exit(1); }
$startUsers = (int)$pdo->query('SELECT COUNT(*) FROM seguridad.Usuario')->fetchColumn();
$startWorkers = (int)$pdo->query('SELECT COUNT(*) FROM rrhh.Personal')->fetchColumn();
$revisionBefore = (string)$pdo->query('SELECT Revision FROM seguridad.VersionDatos')->fetchColumn();
$beforeSettings = SystemSettings::read();
$pdo->beginTransaction();
try {
    $db = new AuditSavepointPDO($pdo);
    $admin = $pdo->query("SELECT TOP 1 u.UsuarioID, u.PasswordHash, r.RolID, r.Codigo FROM seguridad.Usuario u
        JOIN seguridad.UsuarioRol ur ON ur.UsuarioID = u.UsuarioID AND ur.Activo = 1
        JOIN seguridad.Rol r ON r.RolID = ur.RolID AND r.Activo = 1
        WHERE u.Activo = 1 AND u.Bloqueado = 0 AND r.Codigo IN ('ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN') ORDER BY u.UsuarioID")->fetch();
    ensureAdmin((bool)$admin, 'Se necesita una cuenta administradora para la prueba.');
    $_SESSION['user'] = ['usuario_id'=>(int)$admin['UsuarioID'], 'rol'=>$admin['Codigo'], 'auth_stamp'=>hash('sha256', $admin['PasswordHash'])];
    ensureAdmin(Auth::validateSession($pdo), 'La sesión SQL válida se rechazó.');
    $_SESSION['user']['rol'] = 'ROL_RETIRADO';
    ensureAdmin(!Auth::validateSession($pdo) && !Auth::isAuthenticated(), 'La API aceptó un rol retirado.');
    $suffix = bin2hex(random_bytes(5));
    $docType = (int)$pdo->query('SELECT TOP 1 TipoDocumentoID FROM catalogo.TipoDocumento ORDER BY TipoDocumentoID')->fetchColumn();
    $s = $pdo->prepare('SET NOCOUNT ON; INSERT INTO rrhh.Personal (CodigoEmpleado,TipoDocumentoID,NumeroDocumento,Nombres,ApellidoPaterno,FechaIngreso) VALUES (?, ?, ?, ?, ?, CAST(GETDATE() AS DATE)); SELECT CAST(SCOPE_IDENTITY() AS INT)');
    $s->execute(['TEST' . $suffix, $docType, $suffix, 'Prueba', 'Reversible']);
    $worker = (int)$s->fetchColumn(); $s->closeCursor();
    $created = UserAdmin::change($db, ['accion'=>'crear','username'=>'test_' . $suffix,'personal_id'=>$worker,'rol_id'=>(int)$admin['RolID']], (int)$admin['UsuarioID']);
    $id = $created['usuario_id'];
    $s = $pdo->prepare('SELECT PasswordHash FROM seguridad.Usuario WHERE UsuarioID = ?'); $s->execute([$id]);
    $initialHash = $s->fetchColumn();
    $login = Auth::login('test_' . $suffix, $created['clave_temporal']);
    ensureAdmin($login['ok'] && Auth::validateSession($pdo), 'La cuenta creada no pudo iniciar sesión.');
    ensureAdmin(password_verify($created['clave_temporal'], $initialHash), 'Creación no guardó una contraseña utilizable.');
    $reset = UserAdmin::change($db, ['accion'=>'reset_password','usuario_id'=>$id], (int)$admin['UsuarioID']);
    $s->execute([$id]); $newHash = $s->fetchColumn(); ensureAdmin(password_verify($reset['clave_temporal'], $newHash), 'Reset no cambió el hash.');
    $_SESSION['user'] = ['usuario_id'=>$id,'rol'=>$admin['Codigo'],'auth_stamp'=>hash('sha256', $initialHash)];
    ensureAdmin(!Auth::validateSession($pdo), 'El reset no revocó la sesión anterior.');
    foreach ([true, false] as $blocked) {
        UserAdmin::change($db, ['accion'=>'cambiar_bloqueo','usuario_id'=>$id,'bloqueado'=>$blocked], (int)$admin['UsuarioID']);
        $_SESSION['user'] = ['usuario_id'=>$id,'rol'=>$admin['Codigo'],'auth_stamp'=>hash('sha256', $newHash)];
        ensureAdmin(Auth::validateSession($pdo) === !$blocked, 'Bloqueo/desbloqueo no se aplica a la API.');
    }
    UserAdmin::change($db, ['accion'=>'cambiar_rol','usuario_id'=>$id,'rol_id'=>(int)$admin['RolID']], (int)$admin['UsuarioID']);
    try {
        UserAdmin::change($db, ['accion'=>'cambiar_bloqueo','usuario_id'=>(int)$admin['UsuarioID'],'bloqueado'=>true], (int)$admin['UsuarioID']);
        throw new RuntimeException('Se aceptó el autobloqueo.');
    } catch (InvalidArgumentException $expected) {}
    $settings = SystemSettings::defaults(); $settings['tolerancia_entrada_minutos'] = 8;
    $saved = SystemSettings::save($db, $settings, (int)$admin['UsuarioID']);
    require_once __DIR__ . '/../includes/AttendanceRules.php';
    ensureAdmin(AttendanceRules::evaluate('08:07:00', '08:00:00', $saved['vigente_desde'], '14:00:00')['estado'] === 'ASISTIO', 'El evaluador no aplica la tolerancia persistida.');
    ensureAdmin($saved['tolerancia_entrada_minutos'] === 8 && $saved['vigente_desde'] === date('Y-m-d', strtotime('tomorrow')), 'Parámetro no persistió con vigencia futura.');
    ensureAdmin(SystemSettings::read('2020-01-01', $db)['tolerancia_entrada_minutos'] === 5, 'Se modificó la regla histórica.');
    $pdo->rollBack();
    ensureAdmin((int)$pdo->query('SELECT COUNT(*) FROM seguridad.Usuario')->fetchColumn() === $startUsers, 'Quedaron cuentas de prueba.');
    ensureAdmin((int)$pdo->query('SELECT COUNT(*) FROM rrhh.Personal')->fetchColumn() === $startWorkers, 'Quedaron trabajadores de prueba.');
    ensureAdmin((string)$pdo->query('SELECT Revision FROM seguridad.VersionDatos')->fetchColumn() === $revisionBefore, 'La revisión no se restauró.');
    $_SESSION = [];
    echo "OK: cuentas, contraseña, revocación, bloqueo, roles y parámetros SQL; todos los registros de prueba revertidos.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Prueba SQL revertida: ' . $e->getMessage() . "\n"); exit(1);
}
