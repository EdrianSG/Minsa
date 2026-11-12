<?php
/** Adaptación aditiva para el proyecto actual; no carga personal ni marcaciones. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
$mode=$argv[1]??'--inspeccionar';
if(!in_array($mode,['--inspeccionar','--probar-rollback','--aplicar'],true))throw new InvalidArgumentException('Modo no reconocido.');
$p=Database::getConnection(true);if(!$p||$p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('Base V3 requerida.');
$cols=$p->query("SELECT c.name,c.is_computed,c.is_nullable,ty.name AS tipo,c.max_length,cc.definition FROM sys.columns c JOIN sys.types ty ON ty.user_type_id=c.user_type_id LEFT JOIN sys.computed_columns cc ON cc.object_id=c.object_id AND cc.column_id=c.column_id WHERE c.object_id=OBJECT_ID(N'auditoria.EventoAuditoria') ORDER BY c.column_id")->fetchAll(PDO::FETCH_ASSOC);
$byName=[];foreach($cols as $c)$byName[$c['name']]=$c;
if(!isset($byName['EventoAuditoriaID'],$byName['ModuloID']))throw new RuntimeException('Esquema de auditoría distinto del revisado.');
if(isset($byName['AuditoriaID'])&&(!(int)$byName['AuditoriaID']['is_computed']||!preg_match('/^\(?\[EventoAuditoriaID\]\)?$/D',trim($byName['AuditoriaID']['definition']))))throw new RuntimeException('AuditoriaID existente no es el alias esperado; no se sobrescribe.');
if(isset($byName['Modulo'])&&((int)$byName['Modulo']['is_computed']||!(int)$byName['Modulo']['is_nullable']||$byName['Modulo']['tipo']!=='nvarchar'||(int)$byName['Modulo']['max_length']!==200))throw new RuntimeException('Modulo existente no es el campo opcional esperado.');
$originalNames=array_values(array_filter(array_keys($byName),static fn($n)=>!in_array($n,['AuditoriaID','Modulo'],true)));
$select='SELECT '.implode(',',array_map(static fn($n)=>'['.str_replace(']',']]',$n).']',$originalNames)).' FROM auditoria.EventoAuditoria ORDER BY EventoAuditoriaID';
$report=['base'=>'BD_HOSPITAL_V3','fecha_utc'=>gmdate(DATE_ATOM),'modo'=>$mode,'agregar_alias'=>!isset($byName['AuditoriaID']),'agregar_modulo_opcional'=>!isset($byName['Modulo']),'personal_cargado'=>false,'marcaciones_cargadas'=>false];
if($mode==='--inspeccionar'){echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;exit;}
if($mode==='--aplicar'){
 $bak=glob(__DIR__.'/../private-audits/entrega-v3/backup_*.json')?:[];$found=false;
 foreach($bak as $f){$b=json_decode(file_get_contents($f),true);if(($b['estado']??'')==='CREADO_EN_SERVIDOR'&&($b['base']??'')==='BD_HOSPITAL_V3'&&($b['origen']??'')===Database::getConfig()['host'])$found=true;}
 if(!$found)throw new RuntimeException('Primero debe existir un respaldo previo registrado.');
}
try{
 $p->exec('SET LOCK_TIMEOUT 5000; SET XACT_ABORT ON;');$p->beginTransaction();
 $p->query('SELECT COUNT_BIG(*) FROM auditoria.EventoAuditoria WITH (TABLOCKX,HOLDLOCK)')->fetchColumn();
 $before=$p->query($select)->fetchAll(PDO::FETCH_ASSOC);$hash=hash('sha256',json_encode($before,JSON_THROW_ON_ERROR));
 if($report['agregar_alias'])$p->exec('ALTER TABLE auditoria.EventoAuditoria ADD AuditoriaID AS (EventoAuditoriaID);');
 if($report['agregar_modulo_opcional'])$p->exec('ALTER TABLE auditoria.EventoAuditoria ADD Modulo nvarchar(100) NULL;');
 $known=[];foreach($p->query('SELECT ModuloID,SistemaID,Codigo FROM seguridad.Modulo')->fetchAll(PDO::FETCH_ASSOC) as $m)$known[(int)$m['ModuloID']]=$m;
 $write=$p->prepare('UPDATE auditoria.EventoAuditoria SET Modulo=? WHERE EventoAuditoriaID=? AND Modulo IS NULL');$derived=0;
 foreach($before as $row){
  $module=null;$m=$known[(int)($row['ModuloID']??0)]??null;
  if($m&&(int)$m['SistemaID']===(int)$row['SistemaID'])$module=$m['Codigo'];
  elseif(preg_match('/^\[([A-Z_]{1,100})\]/',(string)$row['Descripcion'],$matches))$module=$matches[1];
  if($module!==null){$write->execute([$module,$row['EventoAuditoriaID']]);$derived+=$write->rowCount();}
 }
 $p->query('SELECT TOP (1) AuditoriaID,Modulo FROM auditoria.EventoAuditoria ORDER BY AuditoriaID')->fetchAll();
 $after=$p->query($select)->fetchAll(PDO::FETCH_ASSOC);
 if(hash('sha256',json_encode($after,JSON_THROW_ON_ERROR))!==$hash)throw new RuntimeException('Cambió un campo histórico de auditoría. Revertir.');
 $report['filas_historicas']=count($before);$report['modulos_derivados_de_fuente_existente']=$derived;$report['campos_originales_identicos']=true;$report['sha256_campos_originales']=$hash;
 if($mode==='--probar-rollback'){$p->rollBack();$report['persistido']=false;
  $s=$p->query("SELECT name FROM sys.columns WHERE object_id=OBJECT_ID(N'auditoria.EventoAuditoria') ORDER BY column_id")->fetchAll(PDO::FETCH_COLUMN);
  if($s!==array_keys($byName))throw new RuntimeException('El rollback no devolvió las columnas iniciales.');
 }else{$p->commit();$report['persistido']=true;}
 $folder=__DIR__.'/../private-audits/entrega-v3';$path=$folder.'/compatibilidad_auditoria_'.($mode==='--aplicar'?'aplicada':'rollback').'.json';file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));chmod($path,0600);
 echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();fwrite(STDERR,'Adaptación no completada: '.$e->getMessage().PHP_EOL);exit(1);}
