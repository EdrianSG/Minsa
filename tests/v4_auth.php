<?php
require_once __DIR__.'/../includes/auth.php';
/** Ejecuta los predicados de autenticación contra tablas aisladas en memoria. */
class V4AuthPDO extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); $this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        return parent::prepare(str_replace('CAST(GETDATE() AS DATE)',"date('now')",$query),$options);
    }
}
$pdo=new V4AuthPDO();
foreach (['seguridad','rrhh'] as $schema) $pdo->exec("ATTACH DATABASE ':memory:' AS $schema");
$pdo->exec("CREATE TABLE seguridad.Sistema (SistemaID INT,Codigo TEXT,Activo INT);
 INSERT INTO seguridad.Sistema VALUES (7,'RRHH',1),(8,'CLINICA',1);
 CREATE TABLE seguridad.Usuario (UsuarioID INT,PersonalID INT,PasswordHash TEXT,Activo INT,Bloqueado INT);
 INSERT INTO seguridad.Usuario VALUES (1,NULL,'hash',1,0);
 CREATE TABLE rrhh.Personal (PersonalID INT,Activo INT);
 CREATE TABLE seguridad.Rol (RolID INT,Codigo TEXT,SistemaID INT,Activo INT);
 INSERT INTO seguridad.Rol VALUES (1,'ADMIN',7,1),(2,'ADMIN',8,1);
 CREATE TABLE seguridad.UsuarioRol (UsuarioID INT,RolID INT,Activo INT,FechaInicio TEXT,FechaFin TEXT);
 INSERT INTO seguridad.UsuarioRol VALUES (1,1,1,NULL,NULL);
 CREATE TABLE seguridad.UsuarioSistema (UsuarioID INT,SistemaID INT,Activo INT,FechaInicio TEXT,FechaFin TEXT);
 INSERT INTO seguridad.UsuarioSistema VALUES (1,7,1,NULL,NULL)");
function resetV4Session(): void {
    $_SESSION['user']=['usuario_id'=>1,'personal_id'=>null,'rol'=>'ADMIN','auth_stamp'=>hash('sha256','hash'),
        'system_code'=>SystemContext::code(),'database_source'=>Database::sourceId()];
}
function authCheck(bool $ok,string $why): void { if (!$ok) throw new RuntimeException($why); }
resetV4Session(); authCheck(Auth::validateSession($pdo),'Cuenta técnica válida rechazada.');
$pdo->exec('UPDATE seguridad.UsuarioRol SET RolID=2');
resetV4Session(); authCheck(!Auth::validateSession($pdo),'Administrador clínico ganó acceso RRHH.');
$pdo->exec('UPDATE seguridad.UsuarioRol SET RolID=1; UPDATE seguridad.UsuarioSistema SET Activo=0');
resetV4Session(); authCheck(!Auth::validateSession($pdo),'Habilitación revocada aceptada.');
$pdo->exec("UPDATE seguridad.UsuarioSistema SET Activo=1, FechaFin='2000-01-01'");
resetV4Session(); authCheck(!Auth::validateSession($pdo),'Habilitación vencida aceptada.');
$pdo->exec("UPDATE seguridad.UsuarioSistema SET FechaFin=NULL; UPDATE seguridad.UsuarioRol SET FechaInicio='2999-01-01'");
resetV4Session(); authCheck(!Auth::validateSession($pdo),'Rol futuro aceptado.');
$pdo->exec("UPDATE seguridad.UsuarioRol SET FechaInicio=NULL; UPDATE seguridad.Usuario SET PasswordHash='nuevo'");
resetV4Session(); authCheck(!Auth::validateSession($pdo),'Cambio de contraseña no invalidó sesión.');
$_SESSION=[];
echo "OK: 6 verificaciones de SQL de acceso por sistema, cuenta técnica y revocación (SQLite en memoria).\n";
