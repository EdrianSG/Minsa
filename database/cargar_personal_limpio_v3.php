<?php
/** Carga autorizada y transaccional en V3 de pruebas; no importa marcaciones ni horarios. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auditoria.php';
$mode=$argv[1]??'--previsualizar';
if(!in_array($mode,['--previsualizar','--rollback','--aplicar'],true))throw new InvalidArgumentException('Modo inválido.');
if($mode==='--aplicar'&&!in_array('--backup-manual-confirmado',$argv,true))throw new RuntimeException('La aplicación requiere confirmación explícita del backup manual.');
$folder=__DIR__.'/../private-audits/carga-personal-v3';
$plan=json_decode(file_get_contents($folder.'/plan_autorizado.json'),true,512,JSON_THROW_ON_ERROR);
$baseline=json_decode(file_get_contents($folder.'/esquema_y_datos_v3.json'),true,512,JSON_THROW_ON_ERROR);
if(($plan['formato']??'')!=='CARGA_PERSONAL_V3_1'||count($plan['filas'])!==450||empty($plan['estado_tecnico_no_mapea_activo']))throw new RuntimeException('Plan no revisado.');
function sourceHashes(array $plan):void{
 foreach([[$plan['fuente'],$plan['fuente_sha256']],[$plan['csv_biostar'],$plan['csv_sha256']]] as [$file,$hash]){
  if(basename($file)!==$file||!hash_equals($hash,hash_file('sha256',__DIR__.'/../'.$file)))throw new RuntimeException('Cambió una fuente; rehacer plan.');
 }
}
function keyName(string $s):string{
 $s=strtr(mb_strtoupper(trim($s),'UTF-8'),['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
 return preg_replace('/\s+/u',' ',$s);
}
function privateJson(string $file,array $value):void{if(file_put_contents($file,json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('No se pudo conservar el manifiesto.');chmod($file,0600);}
function qid(string $s):string{return '['.str_replace(']',']]',$s).']';}
function nullableBusinessFields(PDO $p):array{
 $refs=(int)$p->query("SELECT COUNT(*) FROM sys.foreign_key_columns f JOIN sys.columns c ON c.object_id=f.referenced_object_id AND c.column_id=f.referenced_column_id WHERE f.referenced_object_id=OBJECT_ID(N'rrhh.Personal') AND c.name IN ('CodigoEmpleado','TipoDocumentoID','NumeroDocumento')")->fetchColumn();
 if($refs)throw new RuntimeException('Una FK depende de claves de negocio; requiere revisión.');
 $definitions=['CodigoEmpleado'=>'varchar(30)','TipoDocumentoID'=>'int','NumeroDocumento'=>'varchar(20)','ApellidoPaterno'=>'nvarchar(100)','FechaIngreso'=>'date'];$changed=[];
 foreach(['UQ_Personal_Codigo','UQ_Personal_Documento'] as $constraint){
  $s=$p->prepare("SELECT 1 FROM sys.key_constraints WHERE parent_object_id=OBJECT_ID(N'rrhh.Personal') AND name=? AND type='UQ'");$s->execute([$constraint]);
  if($s->fetchColumn())$p->exec('ALTER TABLE rrhh.Personal DROP CONSTRAINT '.qid($constraint));$s->closeCursor();
 }
 foreach($definitions as $column=>$type){
  $s=$p->prepare("SELECT is_nullable FROM sys.columns WHERE object_id=OBJECT_ID(N'rrhh.Personal') AND name=?");$s->execute([$column]);$nullable=$s->fetchColumn();$s->closeCursor();
  if($nullable===false)throw new RuntimeException('Campo requerido inexistente: '.$column);
  if(!(int)$nullable){$p->exec('ALTER TABLE rrhh.Personal ALTER COLUMN '.qid($column).' '.$type.' NULL');$changed[]='rrhh.Personal.'.$column;}
 }
 foreach(['UX_Personal_Codigo_Informado'=>['CodigoEmpleado','CodigoEmpleado IS NOT NULL'],'UX_Personal_Documento_Informado'=>['TipoDocumentoID,NumeroDocumento','TipoDocumentoID IS NOT NULL AND NumeroDocumento IS NOT NULL']] as $name=>[$cols,$filter]){
  $s=$p->prepare("SELECT is_unique,filter_definition FROM sys.indexes WHERE object_id=OBJECT_ID(N'rrhh.Personal') AND name=?");$s->execute([$name]);$index=$s->fetch(PDO::FETCH_ASSOC);$s->closeCursor();
  if(!$index)$p->exec('CREATE UNIQUE INDEX '.qid($name).' ON rrhh.Personal ('.$cols.') WHERE '.$filter);
  elseif(!(int)$index['is_unique']||!$index['filter_definition'])throw new RuntimeException('Índice incompatible: '.$name);
 }
 $s=$p->query("SELECT is_nullable FROM sys.columns WHERE object_id=OBJECT_ID(N'rrhh.AsignacionPersonal') AND name='FechaInicio'");$nullable=$s->fetchColumn();$s->closeCursor();
 if($nullable===false)throw new RuntimeException('Falta FechaInicio de asignación.');
 if(!(int)$nullable){$p->exec('ALTER TABLE rrhh.AsignacionPersonal ALTER COLUMN FechaInicio date NULL');$changed[]='rrhh.AsignacionPersonal.FechaInicio';}
 return $changed;
}
function snapshotRows(PDO $p,string $table,string $pk,bool $lock=false):array{
 return $p->query('SELECT * FROM '.$table.($lock?' WITH (TABLOCKX,HOLDLOCK)':'').' ORDER BY '.qid($pk))->fetchAll(PDO::FETCH_ASSOC);
}
function assertPreserved(PDO $p,string $table,string $pk,array $before):void{
 if(!$before)return;$after=snapshotRows($p,$table,$pk);$index=[];foreach($after as $r)$index[(string)$r[$pk]]=$r;
 foreach($before as $r)if(!isset($index[(string)$r[$pk]])||$index[(string)$r[$pk]]!==$r)throw new RuntimeException('Se modificó un registro previo: '.$table);
}
sourceHashes($plan);$config=Database::getConfig();
if($config['host']!==$plan['servidor']||$config['database']!=='BD_HOSPITAL_V3')throw new RuntimeException('Solo servidor/base de pruebas autorizados.');
$p=Database::getConnection(true);if(!$p||$p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('Conexión V3 requerida.');
$system=SystemContext::id($p);$operator=$p->query('SELECT ORIGINAL_LOGIN()')->fetchColumn();
$prior=$p->prepare("SELECT TOP 1 EventoAuditoriaID FROM auditoria.EventoAuditoria WHERE SistemaID=? AND Accion='IMPORT_PERSONAL_TXT' AND RegistroID=? ORDER BY EventoAuditoriaID DESC");
$prior->execute([$system,$plan['batch_id']]);$audit=$prior->fetchColumn();$prior->closeCursor();
if($audit!==false){echo json_encode(['ok'=>true,'ya_cargado'=>true,'auditoria_id'=>(int)$audit,'sin_cambios'=>true]),PHP_EOL;exit;}
if($mode==='--previsualizar'){echo json_encode(['modo'=>$mode,'base'=>'BD_HOSPITAL_V3','resumen'=>$plan['resumen'],'activo_desde_estado_tecnico'=>false,'crear_horarios'=>false,'importar_marcaciones'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;exit;}
$stamp=gmdate('Ymd\THis\Z').'_'.bin2hex(random_bytes(3));$manifestFile=$folder.'/manifiesto_'.$stamp.($mode==='--rollback'?'_ENSAYO':'_PREPARADO').'.json';
$manifest=['estado'=>'EN_TRANSACCION','modo'=>$mode,'batch_id'=>$plan['batch_id'],'fuente_sha256'=>$plan['fuente_sha256'],'csv_sha256'=>$plan['csv_sha256'],'fecha_utc'=>gmdate(DATE_ATOM),'operador_sql'=>$operator,'personas'=>[],'asignaciones'=>[],'vinculos'=>[],'previos'=>[]];
try{
 $p->exec('SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT, CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON; SET NUMERIC_ROUNDABORT OFF; SET LOCK_TIMEOUT 5000; SET XACT_ABORT ON;');$p->beginTransaction();
 $s=$p->prepare("DECLARE @r int; EXEC @r=sys.sp_getapplock @Resource=?,@LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=5000; SELECT @r");$s->execute(['CARGA_PERSONAL_V3:'.$plan['fuente_sha256']]);$lock=(int)$s->fetchColumn();$s->closeCursor();if($lock<0)throw new RuntimeException('Otra carga está en curso.');
 $prior->execute([$system,$plan['batch_id']]);if($prior->fetchColumn()!==false)throw new RuntimeException('El lote se confirmó durante la preparación; reintentar para verificar.');$prior->closeCursor();
 $protected=['rrhh.Personal'=>'PersonalID','rrhh.AsignacionPersonal'=>'AsignacionPersonalID','rrhh.VinculacionBiometrica'=>'VinculacionBiometricaID'];
 foreach($protected as $table=>$pk){$manifest['previos'][$table]=snapshotRows($p,$table,$pk,true);if(count($manifest['previos'][$table])!==$baseline['counts'][$table])throw new RuntimeException('Cambió la base desde el preflight: '.$table);}
 $unchanged=['rrhh.MarcacionBiometrica','rrhh.Asistencia','rrhh.Horario','rrhh.Contrato','rrhh.ImportacionBiometrica','rrhh.IncidenciaBiometrica','rrhh.ProgramacionMensual','rrhh.ProgramacionMensualDetalle','dbo.StagingPersonalPacasmayo','laboratorio.Solicitud','laboratorio.Resultado','seguridad.Usuario'];
 foreach($unchanged as $t)$beforeCounts[$t]=(int)$p->query('SELECT COUNT_BIG(*) FROM '.$t)->fetchColumn();
 if($beforeCounts['rrhh.MarcacionBiometrica']!==0)throw new RuntimeException('Existen marcaciones: se requiere un plan de enlace de pendientes independiente.');
 $schemaBefore=$p->query("SELECT c.name,c.is_nullable FROM sys.columns c WHERE c.object_id=OBJECT_ID(N'rrhh.Personal') OR c.object_id=OBJECT_ID(N'rrhh.AsignacionPersonal') ORDER BY c.object_id,c.column_id")->fetchAll(PDO::FETCH_ASSOC);
 $manifest['campos_hechos_opcionales']=nullableBusinessFields($p);
 $areas=$p->query('SELECT AreaID,Nombre,Activo FROM organizacion.Area WITH (HOLDLOCK)')->fetchAll(PDO::FETCH_ASSOC);$cargos=$p->query('SELECT CargoID,Nombre,Activo FROM rrhh.Cargo WITH (HOLDLOCK)')->fetchAll(PDO::FETCH_ASSOC);
 foreach([['area',$areas,'AreaID'],['cargo',$cargos,'CargoID']] as [$kind,$items,$id])foreach($items as $r){$catalog[$kind][(int)$r[$id]]=$r;if((int)$r['Activo'])$canonical[$kind][keyName($r['Nombre'])][]=(int)$r[$id];}
 $known=[];foreach($manifest['previos']['rrhh.Personal'] as $r)if($r['TipoDocumentoID']!==null&&$r['NumeroDocumento']!==null)$known[(int)$r['TipoDocumentoID'].'|'.$r['NumeroDocumento']]=$r;
 $knownBio=[];foreach($manifest['previos']['rrhh.VinculacionBiometrica'] as $r)$knownBio[$r['BiostarUserID']]=$r;
 $columns=['CodigoEmpleado','TipoDocumentoID','NumeroDocumento','Nombres','ApellidoPaterno','ApellidoMaterno','NombreCompletoOriginal','FechaIngreso','CargoOrigen','Observacion'];
 $insert=$p->prepare('SET NOCOUNT ON; INSERT INTO rrhh.Personal ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).'); SELECT CAST(SCOPE_IDENTITY() AS int)');
 $insertAssignment=$p->prepare('SET NOCOUNT ON; INSERT INTO rrhh.AsignacionPersonal (PersonalID,AreaID,CargoID,FechaInicio,EsActual,Activo) VALUES (?,?,?,NULL,1,1); SELECT CAST(SCOPE_IDENTITY() AS int)');
 $insertBio=$p->prepare("SET NOCOUNT ON; INSERT INTO rrhh.VinculacionBiometrica (BiostarUserID,PersonalID,TipoVinculacion,Observacion,UsuarioID) VALUES (?,?,'PADRON_CSV_VERIFICADO',?,NULL); SELECT CAST(SCOPE_IDENTITY() AS int)");
 foreach($plan['filas'] as $row){
  $d=$row['datos'];if(array_keys($d)!==$columns||$d['FechaIngreso']!==null||$d['CodigoEmpleado']!==null)throw new RuntimeException('Plan de campos distinto del revisado.');
  if($d['NumeroDocumento']!==null&&!preg_match('/^[0-9]{8}$/D',$d['NumeroDocumento']))throw new RuntimeException('DNI inválido en plan.');
  $key=$d['TipoDocumentoID']!==null?$d['TipoDocumentoID'].'|'.$d['NumeroDocumento']:null;
  if($key!==null&&isset($known[$key])){
   $existing=$known[$key];$name=$existing['NombreCompletoOriginal']?:trim(implode(' ',array_filter([$existing['ApellidoPaterno'],$existing['ApellidoMaterno'],$existing['Nombres']])));
   if(keyName($name)!==keyName($d['NombreCompletoOriginal']))throw new RuntimeException('Documento ya registrado con otro nombre.');$id=(int)$existing['PersonalID'];$created=false;
  }else{
   $insert->execute(array_values($d));$id=(int)$insert->fetchColumn();$insert->closeCursor();if($id<=0)throw new RuntimeException('No se obtuvo PersonalID.');$created=true;
  }
  $manifest['personas'][]=['linea'=>$row['linea'],'PersonalID'=>$id,'accion'=>$created?'INSERTADO':'REUTILIZADO','datos'=>$d];
  if($created&&$row['asignacion']!==null){
   $a=$row['asignacion'];foreach([['area','AreaID','AreaNombre'],['cargo','CargoID','CargoNombre']] as [$kind,$fk,$label]){if(!isset($catalog[$kind][$a[$fk]])||!(int)$catalog[$kind][$a[$fk]]['Activo']||keyName($catalog[$kind][$a[$fk]]['Nombre'])!==keyName($a[$label])||count($canonical[$kind][keyName($a[$label])]??[])!==1)throw new RuntimeException('Cambió el catálogo: '.$kind);}
   $insertAssignment->execute([$id,$a['AreaID'],$a['CargoID']]);$aid=(int)$insertAssignment->fetchColumn();$insertAssignment->closeCursor();$manifest['asignaciones'][]=['AsignacionPersonalID'=>$aid,'PersonalID'=>$id,'AreaID'=>$a['AreaID'],'CargoID'=>$a['CargoID'],'FechaInicio'=>null];
  }
  if($row['biostar_user_id']!==null){
   $bio=$row['biostar_user_id'];if(!$d['NumeroDocumento']||!in_array($row['criterio_biostar'],['ID_EXACTO_Y_NOMBRE','CEROS_INICIALES_CANDIDATO'],true))throw new RuntimeException('Vínculo no autorizado.');
   if(isset($knownBio[$bio])){if((int)$knownBio[$bio]['PersonalID']!==$id)throw new RuntimeException('BioStar ya vinculado con otra persona.');}
   else{$note='Fuente TXT SHA '.substr($plan['fuente_sha256'],0,16).'; linea '.$row['linea'].'; CSV '.substr($plan['csv_sha256'],0,16).'; nombre, total y ultima marca contrastados; ID original conservado.';$insertBio->execute([$bio,$id,$note]);$vid=(int)$insertBio->fetchColumn();$insertBio->closeCursor();$knownBio[$bio]=['PersonalID'=>$id];$manifest['vinculos'][]=['VinculacionBiometricaID'=>$vid,'BiostarUserID'=>$bio,'PersonalID'=>$id];}
  }
 }
 foreach($protected as $t=>$pk)assertPreserved($p,$t,$pk,$manifest['previos'][$t]);
 foreach($unchanged as $t)if((int)$p->query('SELECT COUNT_BIG(*) FROM '.$t)->fetchColumn()!==$beforeCounts[$t])throw new RuntimeException('Cambió una tabla fuera del lote: '.$t);
 $bad=(int)$p->query("SELECT COUNT(*) FROM rrhh.Personal p JOIN catalogo.TipoDocumento t ON p.TipoDocumentoID=t.TipoDocumentoID WHERE t.Codigo='DNI' AND (LEN(p.NumeroDocumento)<>8 OR p.NumeroDocumento LIKE '%[^0-9]%')")->fetchColumn();if($bad)throw new RuntimeException('Hay DNI inválidos tipados como DNI.');
 $manifest['originales_preservados']=true;$manifest['conteos_protegidos']=$beforeCounts;$manifest['estado']='PREPARADO_PARA_CONFIRMACION';privateJson($manifestFile,$manifest);$manifestHash=hash_file('sha256',$manifestFile);
 $description=json_encode(['lote'=>$plan['batch_id'],'fuente_sha256'=>$plan['fuente_sha256'],'manifiesto'=>basename($manifestFile),'sha256_manifiesto'=>$manifestHash,'personal'=>count($manifest['personas']),'asignaciones'=>count($manifest['asignaciones']),'vinculos'=>count($manifest['vinculos']),'operador_sql'=>$operator,'sin_bajas'=>true,'sin_fechas_inventadas'=>true],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 if(!Auditoria::registrar(null,'PERSONAL','IMPORT_PERSONAL_TXT','rrhh.Personal',$plan['batch_id'],$description))throw new RuntimeException('No se pudo auditar el lote.');
 sourceHashes($plan);
 $result=['ok'=>true,'modo'=>$mode,'base'=>'BD_HOSPITAL_V3','personal_insertado'=>count(array_filter($manifest['personas'],static fn($r)=>$r['accion']==='INSERTADO')),'personal_reutilizado'=>count(array_filter($manifest['personas'],static fn($r)=>$r['accion']==='REUTILIZADO')),'asignaciones_insertadas'=>count($manifest['asignaciones']),'vinculos_insertados'=>count($manifest['vinculos']),'documentos_pendientes'=>$plan['resumen']['documentos_pendientes'],'apellidos_pendientes'=>$plan['resumen']['apellidos_sin_desglosar'],'bajas'=>0,'marcaciones_importadas'=>0,'horarios_creados'=>0,'manifiesto'=>basename($manifestFile),'sha256_manifiesto'=>$manifestHash,'originales_preservados'=>true];
 if($mode==='--rollback'){
  $p->rollBack();$result['persistido']=false;
  foreach($protected as $t=>$pk)if(snapshotRows($p,$t,$pk)!==$manifest['previos'][$t])throw new RuntimeException('Rollback incompleto de datos: '.$t);
  $state=$p->query("SELECT c.name,c.is_nullable FROM sys.columns c WHERE c.object_id=OBJECT_ID(N'rrhh.Personal') OR c.object_id=OBJECT_ID(N'rrhh.AsignacionPersonal') ORDER BY c.object_id,c.column_id")->fetchAll(PDO::FETCH_ASSOC);if($state!==$schemaBefore)throw new RuntimeException('Rollback incompleto del esquema.');
 }else{$p->commit();$result['persistido']=true;}
 privateJson($folder.'/resultado_'.$stamp.'.json',$result);echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();fwrite(STDERR,'Carga revertida/no aplicada: '.$e->getMessage().PHP_EOL);exit(1);}
