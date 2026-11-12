<?php
/** Prueba el repositorio real sobre fichas importadas; commit se sustituye por ROLLBACK. */
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/PersonalRepository.php';
class PersonalRollbackPDO extends PDO {
 public bool $reverted=false;
 public function commit():bool{$this->reverted=true;return parent::rollBack();}
}
$c=Database::getConfig();if($c['database']!=='BD_HOSPITAL_V3')exit(1);
$p=new PersonalRollbackPDO('sqlsrv:Server='.$c['host'].','.$c['port'].';Database='.$c['database'].';Encrypt=no;TrustServerCertificate=true;LoginTimeout=8',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::SQLSRV_ATTR_ENCODING=>PDO::SQLSRV_ENCODING_UTF8]);
if($p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')exit(1);
$prop=new ReflectionProperty(Database::class,'instance');$prop->setValue(null,$p);
$files=glob(__DIR__.'/../private-audits/carga-personal-v3/manifiesto_*_PREPARADO.json');sort($files);$m=json_decode(file_get_contents(end($files)),true);
$cases=[];
foreach($m['personas'] as $r){if(!$cases)$cases['ficha_normal']=$r['PersonalID'];if($r['datos']['NumeroDocumento']===null)$cases['documento_pendiente']=$r['PersonalID'];if($r['datos']['ApellidoPaterno']===null&&!isset($cases['apellidos_pendientes']))$cases['apellidos_pendientes']=$r['PersonalID'];}
$actor=(int)$p->query("SELECT TOP 1 u.UsuarioID FROM seguridad.Usuario u JOIN seguridad.UsuarioRol ur ON ur.UsuarioID=u.UsuarioID JOIN seguridad.Rol r ON r.RolID=ur.RolID WHERE u.Activo=1 AND ur.Activo=1 AND r.Codigo='RRHH_ADMIN'")->fetchColumn();
$checks=0;
try{
 foreach($cases as $label=>$id){
  $s=$p->prepare('SELECT * FROM rrhh.Personal WHERE PersonalID=?');$s->execute([$id]);$before=$s->fetch();$s->closeCursor();
  $counts=[];foreach(['rrhh.AsignacionPersonal','auditoria.EventoAuditoria'] as $t)$counts[$t]=$p->query('SELECT COUNT_BIG(*) FROM '.$t)->fetchColumn();
  $p->reverted=false;
  $r=PersonalRepository::save($p,['telefono'=>$before['Telefono'],'accion_asignacion'=>'conservar'],$actor,$id);
  if(!$r['ok']||!$p->reverted||$p->inTransaction())throw new RuntimeException('No se revirtió la edición: '.$label);++$checks;
  $s->execute([$id]);$after=$s->fetch();$s->closeCursor();if($after!==$before)throw new RuntimeException('Se alteró una ficha: '.$label);++$checks;
  foreach($counts as $t=>$count){if($p->query('SELECT COUNT_BIG(*) FROM '.$t)->fetchColumn()!==$count)throw new RuntimeException('Persistió una modificación: '.$t);++$checks;}
  if($after['FechaIngreso']!==null||$after['CodigoEmpleado']!==null)throw new RuntimeException('Se inventó un dato de ingreso.');++$checks;
 }
 echo json_encode(['ok'=>true,'modo'=>'REPOSITORIO_REAL_COMMIT_INTERCEPTADO_CON_ROLLBACK','casos'=>array_keys($cases),'comprobaciones'=>$checks,'datos_persistidos'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}finally{if($p->inTransaction())$p->rollBack();$prop->setValue(null,null);}
