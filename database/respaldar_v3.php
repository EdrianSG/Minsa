<?php
/** Backup nativo COPY_ONLY; no carga padrones ni modifica datos de negocio. CLI. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
$mode=$argv[1]??'--inspeccionar';
if(!in_array($mode,['--inspeccionar','--crear'],true))throw new InvalidArgumentException('Modos: --inspeccionar o --crear.');
$p=Database::getConnection(true);if(!$p){fwrite(STDERR,Database::getLastError().PHP_EOL);exit(1);}
if($p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('Se requiere BD_HOSPITAL_V3.');
$meta=$p->query("SELECT CONVERT(nvarchar(4000),SERVERPROPERTY('InstanceDefaultBackupPath')) AS backup_directory,CONVERT(nvarchar(128),SERVERPROPERTY('ProductVersion')) AS version,HAS_PERMS_BY_NAME(DB_NAME(),'DATABASE','BACKUP DATABASE') AS can_backup")->fetch();
if($mode==='--inspeccionar'){echo json_encode(['modo'=>'SOLO_LECTURA','base'=>'BD_HOSPITAL_V3','version'=>$meta['version'],'puede_respaldar'=>(bool)(int)$meta['can_backup'],'directorio_servidor'=>$meta['backup_directory']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;exit;}
if(!(int)$meta['can_backup']||!$meta['backup_directory'])throw new RuntimeException('No se dispone de permiso o directorio de backup confirmado.');
$stamp=gmdate('Ymd\THis\Z').'_'.bin2hex(random_bytes(4));
$hasRoster=(int)$p->query("SELECT COUNT_BIG(*) FROM auditoria.EventoAuditoria WHERE Accion='IMPORT_PERSONAL_TXT'")->fetchColumn()>0;
$name='BD_HOSPITAL_V3_'.($hasRoster?'CON_PERSONAL_':'SIN_CARGAR_PADRON_').$stamp.'.bak';
$remote=rtrim($meta['backup_directory'],'\\/').'\\'.$name;
$dir=__DIR__.'/../private-audits/entrega-v3';if(!is_dir($dir))mkdir($dir,0700,true);
$file=$dir.'/backup_'.$stamp.'.json';
$report=['estado'=>'PREPARADO','base'=>'BD_HOSPITAL_V3','origen'=>Database::getConfig()['host'],'version_sql'=>$meta['version'],'ruta_en_servidor'=>$remote,'archivo_local'=>null,'fecha_utc'=>gmdate(DATE_ATOM),'copy_only'=>true,'checksum'=>true,'datos_padron_cargados'=>$hasRoster,'restauracion_probada'=>false,'verificado_verifyonly'=>false];
$save=static function()use(&$report,$file):void{file_put_contents($file,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));chmod($file,0600);};
$save();
try{
 $s=$p->prepare('DECLARE @destino nvarchar(4000)=?; BACKUP DATABASE [BD_HOSPITAL_V3] TO DISK=@destino WITH COPY_ONLY,CHECKSUM;');
 $s->execute([$remote]);$s->closeCursor();$report['estado']='CREADO_EN_SERVIDOR';$save();
 try{
  $s=$p->prepare('DECLARE @origen nvarchar(4000)=?; RESTORE VERIFYONLY FROM DISK=@origen WITH CHECKSUM;');$s->execute([$remote]);$s->closeCursor();$report['verificado_verifyonly']=true;
 }catch(Throwable $e){$report['pendiente_verificacion']=$e->getMessage();}
 $save();echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable $e){$report['estado']='ERROR_BACKUP';$report['error']=$e->getMessage();$save();fwrite(STDERR,'No se completó el backup. Ver informe privado.'.PHP_EOL);exit(1);}
