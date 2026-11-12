<?php
/** Pruebas reales con rollback obligatorio. No agrega personas ni conserva cambios de configuración. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array('--rollback',$argv,true)) { fwrite(STDERR,"Use --rollback para ejecutar las pruebas transaccionales.\n"); exit(1); }
date_default_timezone_set('America/Lima');
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/PersonalRepository.php';
require_once __DIR__.'/../includes/SystemSettings.php';
require_once __DIR__.'/../includes/WorkloadPolicy.php';
final class RollbackOnlyPDO extends PDO {
    public function __construct(private PDO $real) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { return $this->real->prepare($query,$options); }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false { return $fetchMode===null ? $this->real->query($query) : $this->real->query($query,$fetchMode,...$args); }
    public function exec(string $statement): int|false { return $this->real->exec($statement); }
    public function beginTransaction(): bool { return $this->real->beginTransaction(); }
    public function inTransaction(): bool { return $this->real->inTransaction(); }
    public function commit(): bool { if (!$this->real->inTransaction()) throw new RuntimeException('No hay transacción de prueba.'); return true; }
    public function rollBack(): bool { return $this->real->rollBack(); }
    public function lastInsertId(?string $name=null): string|false { return $this->real->lastInsertId($name); }
}
$real=Database::getConnection(true); if (!$real) exit(1);
if ($real->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') exit(1);
$checkCount=0;
function liveCheck(bool $ok,string $why): void { global $checkCount; ++$checkCount; if (!$ok) throw new RuntimeException($why); }
$actor=(int)$real->query("SELECT TOP 1 UsuarioID FROM seguridad.Usuario WHERE NombreUsuario='admin' AND Activo=1 AND Bloqueado=0")->fetchColumn();
$person=$real->query('SELECT TOP 1 * FROM rrhh.Personal ORDER BY PersonalID')->fetch(PDO::FETCH_ASSOC);
if (!$actor || !$person) throw new RuntimeException('Faltan cuenta y ficha reales para comprobar.');
$startAudit=(int)$real->query('SELECT COUNT(*) FROM auditoria.EventoAuditoria')->fetchColumn();
$wrapper=new RollbackOnlyPDO($real); $property=new ReflectionProperty(Database::class,'instance'); $property->setValue(null,$wrapper);
try {
    $saved=PersonalRepository::save($wrapper,['nombre_completo_original'=>$person['NombreCompletoOriginal'],'fecha_ingreso'=>$person['FechaIngreso'],'activo'=>$person['Activo']],$actor,(int)$person['PersonalID']);
    liveCheck($saved['ok']===true && $real->inTransaction(),'La edición parcial no se mantuvo dentro de la transacción.');
    $real->rollBack();
    $s=$real->prepare('SELECT * FROM rrhh.Personal WHERE PersonalID=?'); $s->execute([$person['PersonalID']]);
    liveCheck($s->fetch(PDO::FETCH_ASSOC)===$person,'El rollback no conservó la ficha original.');
    $areas=$real->query('SELECT AreaID FROM organizacion.Area ORDER BY AreaID')->fetchAll(PDO::FETCH_COLUMN);
    $service=$real->query('SELECT TOP 1 ServicioID,AreaID FROM organizacion.Servicio WHERE AreaID IS NOT NULL ORDER BY ServicioID')->fetch(PDO::FETCH_ASSOC);
    $other=array_values(array_filter($areas,fn($id)=>(int)$id!==(int)$service['AreaID']))[0]??null;
    if ($other===null) throw new RuntimeException('No hay dos áreas para comprobar la relación.');
    try {
        PersonalRepository::save($wrapper,['area_id'=>(int)$other,'servicio_id'=>(int)$service['ServicioID']],$actor,(int)$person['PersonalID']);
        throw new RuntimeException('Se aceptó un servicio de otra área.');
    } catch (InvalidArgumentException $e) { liveCheck(!$real->inTransaction(),'La relación inválida no revirtió la transacción.'); }
    $future=SystemSettings::save($wrapper,SystemSettings::defaults(),$actor);
    liveCheck($real->inTransaction() && $future['vigente_desde']===date('Y-m-d',strtotime('tomorrow')),'La configuración no conservó la vigencia futura.');
    liveCheck($future['actualizado_por']==='admin' && $future['fecha_actualizacion']!==null,'La configuración no quedó auditada con usuario real.');
    $real->rollBack();
    $cache=new ReflectionProperty(SystemSettings::class,'cache'); $cache->setValue(null,[]);
    liveCheck((int)$real->query('SELECT COUNT(*) FROM auditoria.EventoAuditoria')->fetchColumn()===$startAudit,'Se conservaron eventos de prueba.');
    $sql=WorkloadPolicy::sql();
    $s=$real->query("SELECT TOP 1 $sql AS tipo_personal FROM rrhh.Personal p ORDER BY p.PersonalID");
    liveCheck($s->fetchColumn()===null,'Se inventó una clasificación sin pauta confirmada.');
    echo "OK: $checkCount comprobaciones reales en SQL Server; edición parcial, relación inválida, configuración versionada y rollback. Sin cambios conservados.\n";
} finally {
    if ($real->inTransaction()) $real->rollBack();
    $property->setValue(null,$real);
}
