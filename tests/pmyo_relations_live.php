<?php
/** Ejercita la edición con catálogos reales. Todos los cambios se revierten; no crea trabajadores. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array('--rollback',$argv,true)) { fwrite(STDERR,"Use --rollback.\n"); exit(1); }
date_default_timezone_set('America/Lima');
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/PersonalRepository.php';
require_once __DIR__.'/../includes/SystemContext.php';
final class PmyoRollbackPDO extends PDO {
    public function __construct(private PDO $real) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { return $this->real->prepare($query,$options); }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false { return $fetchMode===null?$this->real->query($query):$this->real->query($query,$fetchMode,...$args); }
    public function exec(string $sql): int|false { return $this->real->exec($sql); }
    public function beginTransaction(): bool { return $this->real->inTransaction() || $this->real->beginTransaction(); }
    public function commit(): bool { if(!$this->real->inTransaction())throw new RuntimeException('Sin transacción.'); return true; }
    public function rollBack(): bool { return $this->real->rollBack(); }
    public function inTransaction(): bool { return $this->real->inTransaction(); }
}
$p=Database::getConnection(true); if(!$p || $p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') exit(1);
$checks=0;
function verifyPmyo(bool $ok,string $why): void { global $checks; ++$checks; if(!$ok)throw new RuntimeException($why); }
function scalarPmyo(PDO $p,string $sql,array $args=[]): mixed { $s=$p->prepare($sql);$s->execute($args);return $s->fetchColumn(); }
$person=$p->query("SELECT TOP 1 p.* FROM rrhh.Personal p WHERE NOT EXISTS(SELECT 1 FROM rrhh.AsignacionPersonal ap WHERE ap.PersonalID=p.PersonalID) ORDER BY p.PersonalID")->fetch(PDO::FETCH_ASSOC);
if(!$person)throw new RuntimeException('No hay ficha sin asignación para la prueba reversible.');
$id=(int)$person['PersonalID'];
$actor=(int)scalarPmyo($p,"SELECT UsuarioID FROM seguridad.Usuario WHERE NombreUsuario='admin' AND Activo=1 AND Bloqueado=0");
$area1=(int)scalarPmyo($p,'SELECT AreaID FROM organizacion.Area WHERE Nombre=?',['EMERGENCIA']);
$area2=(int)scalarPmyo($p,'SELECT AreaID FROM organizacion.Area WHERE Nombre=?',['MEDICINA GENERAL']);
$cargo1=(int)scalarPmyo($p,'SELECT CargoID FROM rrhh.Cargo WHERE Nombre=?',['MEDICO PEDIATRA']);
$cargo2=(int)scalarPmyo($p,'SELECT CargoID FROM rrhh.Cargo WHERE Nombre=?',['MEDICO GENERAL']);
$prof=(int)scalarPmyo($p,"SELECT ProfesionID FROM rrhh.Profesion WHERE Codigo='MED'");
$bio=(int)scalarPmyo($p,"SELECT ProfesionID FROM rrhh.Profesion WHERE Codigo='BIO'");
$special=(int)scalarPmyo($p,'SELECT EspecialidadID FROM rrhh.Especialidad WHERE Nombre=? AND ProfesionID=?',['Pediatría',$prof]);
if(!$actor || !$area1 || !$area2 || !$cargo1 || !$cargo2 || !$prof || !$special || !$bio)throw new RuntimeException('Faltan catálogos PMYO para probar.');
$system=SystemContext::id($p);
$versionSql="SELECT COALESCE(MAX(AuditoriaID),0) FROM auditoria.EventoAuditoria WHERE SistemaID=? AND TablaAfectada=N'rrhh.Personal' AND RegistroID=? AND Accion IN ('INSERT','UPDATE')";
$version=fn()=>(int)scalarPmyo($p,$versionSql,[$system,(string)$id]);
$initial=$version();
$property=new ReflectionProperty(Database::class,'instance'); $wrapper=new PmyoRollbackPDO($p);$property->setValue(null,$wrapper);
$assignment=fn()=> $p->query('SELECT * FROM rrhh.AsignacionPersonal WHERE PersonalID='.$id.' AND EsActual=1 AND Activo=1')->fetch(PDO::FETCH_ASSOC);
try {
    PersonalRepository::save($wrapper,['area_id'=>$area1,'servicio_id'=>null,'cargo_id'=>$cargo1,'fecha_asignacion'=>null,'accion_asignacion'=>'corregir','asignacion_actual_id'=>0,'version_ficha'=>$initial,'profesion_id'=>$prof,'especialidad_id'=>$special],$actor,$id);
    $first=$assignment(); verifyPmyo($first!==false && (int)$first['AreaID']===$area1,'No se guardó el área seleccionada.');
    verifyPmyo($first['FechaInicio']===null,'Se inventó fecha para la primera relación.');
    verifyPmyo((int)scalarPmyo($p,'SELECT ProfesionID FROM rrhh.Personal WHERE PersonalID=?',[$id])===$prof,'No se guardó profesión.');
    verifyPmyo((int)scalarPmyo($p,'SELECT EspecialidadID FROM rrhh.Personal WHERE PersonalID=?',[$id])===$special,'No se guardó especialidad de su profesión.');
    PersonalRepository::save($wrapper,['accion_asignacion'=>'corregir','asignacion_actual_id'=>(int)$first['AsignacionPersonalID'],'version_ficha'=>$version(),'area_id'=>$area1,'servicio_id'=>null,'cargo_id'=>$cargo1,'fecha_asignacion'=>null],$actor,$id);
    verifyPmyo((int)scalarPmyo($p,'SELECT COUNT(*) FROM rrhh.AsignacionPersonal WHERE PersonalID=?',[$id])===1,'Guardar sin cambiar duplicó la relación.');
    PersonalRepository::save($wrapper,['accion_asignacion'=>'corregir','asignacion_actual_id'=>(int)$first['AsignacionPersonalID'],'version_ficha'=>$version(),'area_id'=>$area2,'servicio_id'=>null,'cargo_id'=>$cargo2,'motivo_asignacion'=>'Prueba de corrección con rollback obligatorio.'],$actor,$id);
    $corrected=$assignment(); verifyPmyo((int)$corrected['AsignacionPersonalID']===(int)$first['AsignacionPersonalID'],'Corregir creó una vigencia nueva.');
    verifyPmyo((int)$corrected['AreaID']===$area2 && (int)$corrected['CargoID']===$cargo2,'La corrección no guardó ambos IDs.');
    $start=date('Y-m-d',strtotime('tomorrow'));
    PersonalRepository::save($wrapper,['accion_asignacion'=>'trasladar','asignacion_actual_id'=>(int)$first['AsignacionPersonalID'],'version_ficha'=>$version(),'area_id'=>$area1,'servicio_id'=>null,'cargo_id'=>$cargo1,'fecha_asignacion'=>$start,'motivo_asignacion'=>'Prueba de nueva vigencia con rollback obligatorio.'],$actor,$id);
    $moved=$assignment(); verifyPmyo((int)$moved['AsignacionPersonalID']!==(int)$first['AsignacionPersonalID'],'El traslado no conservó una fila histórica.');
    verifyPmyo(substr((string)$moved['FechaInicio'],0,10)===$start,'El traslado no guardó su vigencia.');
    verifyPmyo(substr((string)scalarPmyo($p,'SELECT FechaFin FROM rrhh.AsignacionPersonal WHERE AsignacionPersonalID=?',[$first['AsignacionPersonalID']]),0,10)===date('Y-m-d'),'No se cerró el histórico el día anterior.');
    PersonalRepository::save($wrapper,['accion_asignacion'=>'finalizar','asignacion_actual_id'=>(int)$moved['AsignacionPersonalID'],'version_ficha'=>$version(),'fecha_fin_asignacion'=>$start,'motivo_asignacion'=>'Prueba de finalización con rollback obligatorio.'],$actor,$id);
    verifyPmyo($assignment()===false,'Finalizar dejó una asignación actual.');
    verifyPmyo((int)scalarPmyo($p,'SELECT COUNT(*) FROM rrhh.AsignacionPersonal WHERE PersonalID=?',[$id])===2,'Se borraron filas históricas.');
    $p->rollBack();
    verifyPmyo($version()===$initial,'Se conservaron eventos de prueba.');
    $s=$p->prepare('SELECT * FROM rrhh.Personal WHERE PersonalID=?');$s->execute([$id]);verifyPmyo($s->fetch(PDO::FETCH_ASSOC)===$person,'La prueba dejó cambios en la ficha.');
    verifyPmyo((int)scalarPmyo($p,'SELECT COUNT(*) FROM rrhh.AsignacionPersonal WHERE PersonalID=?',[$id])===0,'Quedaron asignaciones de prueba.');
    // La versión de ficha protege incluso una corrección sobre la misma fila de asignación.
    PersonalRepository::save($wrapper,['accion_asignacion'=>'conservar','version_ficha'=>$initial],$actor,$id);
    try { PersonalRepository::save($wrapper,['accion_asignacion'=>'conservar','version_ficha'=>$initial],$actor,$id); throw new RuntimeException('Versión obsoleta aceptada.'); }
    catch(PersonalRelationConflict $e){ verifyPmyo(!$p->inTransaction() && $version()===$initial,'El conflicto no revirtió los cambios temporales.'); }
    try { PersonalRepository::save($wrapper,['profesion_id'=>$bio,'especialidad_id'=>$special],$actor,$id); throw new RuntimeException('Especialidad ajena aceptada.'); }
    catch(InvalidArgumentException $e){verifyPmyo(!$p->inTransaction(),'La especialidad inválida dejó transacción abierta.');}
    echo "OK: $checks comprobaciones reales de relaciones PMYO, corrección, traslado, finalización y concurrencia. Rollback completo; ningún trabajador ni relación persistidos por las pruebas.\n";
} finally { if($p->inTransaction())$p->rollBack(); $property->setValue(null,$p); }
