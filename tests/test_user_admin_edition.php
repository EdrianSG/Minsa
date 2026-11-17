<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/UserAdmin.php';

class MockUserAdminPDO extends PDO {
    public function __construct() {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $q = str_replace(
            ['CAST(GETDATE() AS DATE)', 'SYSDATETIME()', 'WITH (UPDLOCK, HOLDLOCK)'],
            ["date('now')", "datetime('now')", ''],
            $query
        );
        // Adaptar solo la recuperación de identidad SQL Server a SQLite para probar el alta real.
        if (str_contains($q,'INSERT INTO seguridad.Usuario (') && str_contains($q,'SCOPE_IDENTITY()')) {
            $q=str_replace(['SET NOCOUNT ON; ', '; SELECT CAST(SCOPE_IDENTITY() AS INT)'], ['', ' RETURNING UsuarioID'], $q);
        }
        return parent::prepare($q, $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        $q = str_replace(
            ['CAST(GETDATE() AS DATE)', 'SYSDATETIME()', 'WITH (UPDLOCK, HOLDLOCK)'],
            ["date('now')", "datetime('now')", ''],
            $query
        );
        return $fetchMode === null ? parent::query($q) : parent::query($q, $fetchMode, ...$args);
    }
}

$pdo = new MockUserAdminPDO();
foreach (['seguridad', 'rrhh', 'auditoria'] as $schema) {
    $pdo->exec("ATTACH DATABASE ':memory:' AS $schema");
}

$pdo->exec("
    CREATE TABLE seguridad.Sistema (SistemaID INTEGER PRIMARY KEY, Codigo TEXT, Activo INTEGER);
    INSERT INTO seguridad.Sistema VALUES (1, 'RRHH', 1);

    CREATE TABLE seguridad.Usuario (
        UsuarioID INTEGER PRIMARY KEY AUTOINCREMENT,
        PersonalID INTEGER,
        NombreUsuario TEXT UNIQUE,
        PasswordHash TEXT,
        Activo INTEGER DEFAULT 1,
        Bloqueado INTEGER DEFAULT 0,
        FechaCreacion TEXT
    );
    INSERT INTO seguridad.Usuario (UsuarioID, PersonalID, NombreUsuario, PasswordHash, Activo, Bloqueado)
    VALUES (1, NULL, 'admin', '" . password_hash('Admin2026!', PASSWORD_DEFAULT) . "', 1, 0),
           (2, 100, 'operador1', '" . password_hash('Clave123!', PASSWORD_DEFAULT) . "', 1, 0);

    CREATE TABLE rrhh.Personal (PersonalID INTEGER PRIMARY KEY, Activo INTEGER);
    INSERT INTO rrhh.Personal VALUES (100, 1), (101, 1), (102, 0);

    CREATE TABLE rrhh.AsignacionPersonal (
        PersonalID INTEGER, ServicioID INTEGER, CargoID INTEGER, EsActual INTEGER, Activo INTEGER
    );
    INSERT INTO rrhh.AsignacionPersonal VALUES (100, 5, 1, 1, 1), (101, 6, 2, 1, 1);

    CREATE TABLE seguridad.Rol (RolID INTEGER PRIMARY KEY, Codigo TEXT, SistemaID INTEGER, Activo INTEGER);
    INSERT INTO seguridad.Rol VALUES
        (1, 'ADMIN_RRHH', 1, 1),
        (2, 'OPERADOR_ASISTENCIA', 1, 1),
        (3, 'JEFE_SERVICIO', 1, 1);

    CREATE TABLE seguridad.UsuarioRol (
        UsuarioID INTEGER, RolID INTEGER, Activo INTEGER, FechaInicio TEXT, FechaFin TEXT, FechaAsignacion TEXT
    );
    INSERT INTO seguridad.UsuarioRol VALUES
        (1, 1, 1, date('now'), NULL, datetime('now')),
        (2, 2, 1, date('now'), NULL, datetime('now'));

    CREATE TABLE seguridad.UsuarioSistema (
        UsuarioID INTEGER, SistemaID INTEGER, Activo INTEGER, FechaInicio TEXT, FechaFin TEXT
    );
    INSERT INTO seguridad.UsuarioSistema VALUES
        (1, 1, 1, date('now'), NULL),
        (2, 1, 1, date('now'), NULL);

    CREATE TABLE auditoria.EventoAuditoria (
        EventoID INTEGER PRIMARY KEY AUTOINCREMENT,
        UsuarioID INTEGER, SistemaID INTEGER, FechaHora TEXT, Modulo TEXT, Accion TEXT,
        TablaAfectada TEXT, RegistroID TEXT, DireccionIP TEXT, Descripcion TEXT
    );
");

function testAssert(bool $condition, string $msg): void {
    if (!$condition) throw new RuntimeException("FALLÓ: $msg");
}

echo "--- INICIANDO PRUEBAS DE ADMINISTRACIÓN DE USUARIOS ---\n";

// 1. Probar reset_password con contraseña personalizada escrita por el usuario
$res1 = UserAdmin::change($pdo, [
    'accion' => 'reset_password',
    'usuario_id' => 2,
    'password' => 'MiClavePersonalizada2026!'
], 1);
testAssert($res1['ok'] === true, 'Reset password personalizado falló');
testAssert($res1['clave_temporal'] === 'MiClavePersonalizada2026!', 'Clave retornada no coincide');
$stmt = $pdo->prepare('SELECT PasswordHash FROM seguridad.Usuario WHERE UsuarioID = ?');
$stmt->execute([2]);
$newHash = $stmt->fetchColumn();
testAssert(password_verify('MiClavePersonalizada2026!', $newHash), 'El hash guardado no verifica la clave personalizada');
echo "[OK] Reset password con contraseña personalizada manual funciona correctamente.\n";

// 2. Probar reset_password con generación aleatoria (clave vacía)
$res2 = UserAdmin::change($pdo, [
    'accion' => 'reset_password',
    'usuario_id' => 2
], 1);
testAssert($res2['ok'] === true, 'Reset password aleatorio falló');
testAssert(strlen($res2['clave_temporal']) >= 10, 'Clave aleatoria demasiado corta');
$stmt->execute([2]);
testAssert(password_verify($res2['clave_temporal'], $stmt->fetchColumn()), 'Hash no coincide con clave aleatoria');
echo "[OK] Reset password con generación aleatoria funciona correctamente.\n";

// 3. Probar edición de usuario (cambiar username, rol y trabajador)
$res3 = UserAdmin::change($pdo, [
    'accion' => 'editar',
    'usuario_id' => 2,
    'username' => 'jefe_medicina',
    'personal_id' => 101,
    'rol_id' => 3, // JEFE_SERVICIO
    'activo' => 1,
    'bloqueado' => 0
], 1);
testAssert($res3['ok'] === true, 'Edición de usuario falló');

$uStmt = $pdo->prepare('SELECT NombreUsuario, PersonalID, Activo, Bloqueado FROM seguridad.Usuario WHERE UsuarioID = ?');
$uStmt->execute([2]);
$uData = $uStmt->fetch(PDO::FETCH_ASSOC);
testAssert($uData['NombreUsuario'] === 'jefe_medicina', 'Nombre de usuario no se actualizó');
testAssert((int)$uData['PersonalID'] === 101, 'PersonalID no se actualizó');

$rStmt = $pdo->prepare('SELECT RolID FROM seguridad.UsuarioRol WHERE UsuarioID = ? AND Activo = 1');
$rStmt->execute([2]);
$currentRol = (int)$rStmt->fetchColumn();
testAssert($currentRol === 3, 'Rol del usuario no se actualizó a JEFE_SERVICIO (3)');
echo "[OK] Edición de usuario (username, personal_id y rol_id) funciona correctamente.\n";

// 4. Probar auto-degradación de administrador (actor no puede degradar su propio rol)
try {
    UserAdmin::change($pdo, [
        'accion' => 'editar',
        'usuario_id' => 1,
        'username' => 'admin',
        'personal_id' => null,
        'rol_id' => 2 // Intentar cambiarse a OPERADOR_ASISTENCIA
    ], 1);
    throw new RuntimeException('Debió rechazar auto-degradación');
} catch (InvalidArgumentException $e) {
    testAssert(strpos($e->getMessage(), 'Use otra cuenta administradora') !== false, 'Mensaje de auto-degradación incorrecto');
    echo "[OK] Bloqueo de auto-degradación de administrador verificado.\n";
}

// 5. Probar auto-bloqueo del administrador activo
try {
    UserAdmin::change($pdo, [
        'accion' => 'editar',
        'usuario_id' => 1,
        'username' => 'admin',
        'personal_id' => null,
        'rol_id' => 1,
        'bloqueado' => 1
    ], 1);
    throw new RuntimeException('Debió rechazar auto-bloqueo');
} catch (InvalidArgumentException $e) {
    testAssert(strpos($e->getMessage(), 'No puede bloquear su propia cuenta') !== false, 'Mensaje de auto-bloqueo incorrecto');
    echo "[OK] Bloqueo de auto-bloqueo del administrador verificado.\n";
}

// 6. Probar validación de nombre de usuario duplicado
try {
    UserAdmin::change($pdo, [
        'accion' => 'editar',
        'usuario_id' => 2,
        'username' => 'admin', // ya existe para usuario 1
        'personal_id' => 101,
        'rol_id' => 3
    ], 1);
    throw new RuntimeException('Debió rechazar username duplicado');
} catch (InvalidArgumentException $e) {
    testAssert(strpos($e->getMessage(), 'ya está registrado en otra cuenta') !== false, 'Mensaje de duplicado incorrecto');
    echo "[OK] Validación de nombre de usuario duplicado verificada.\n";
}

// 7. Probar auditoría de eventos
$audStmt = $pdo->query("SELECT Modulo, Accion FROM auditoria.EventoAuditoria ORDER BY EventoID DESC");
$audRows = $audStmt->fetchAll(PDO::FETCH_ASSOC);
testAssert(count($audRows) >= 3, 'No se registraron los eventos de auditoría');
testAssert($audRows[0]['Modulo'] === 'SEGURIDAD' && $audRows[0]['Accion'] === 'EDITAR', 'Auditoría de EDITAR incorrecta');
echo "[OK] Registro de auditoría verificado.\n";

echo "--- TODAS LAS PRUEBAS DE EDICIÓN Y CLAVE MANUAL PASARON CON ÉXITO ---\n";
