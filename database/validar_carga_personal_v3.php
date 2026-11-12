<?php
/** Validación posterior exclusivamente SELECT; no corrige ni rellena datos. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../config/database.php';
$p=Database::getConnection(true);if(!$p||$p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('V3 requerida.');
$folder=__DIR__.'/../private-audits/carga-personal-v3';$plan=json_decode(file_get_contents($folder.'/plan_autorizado.json'),true,512,JSON_THROW_ON_ERROR);
$manifests=glob($folder.'/manifiesto_*_PREPARADO.json')?:[];sort($manifests);if(!$manifests)throw new RuntimeException('Falta manifiesto de confirmación.');
$file=end($manifests);$m=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
$s=$p->prepare("SELECT TOP 1 Descripcion FROM auditoria.EventoAuditoria WHERE Accion='IMPORT_PERSONAL_TXT' AND RegistroID=? ORDER BY EventoAuditoriaID DESC");$s->execute([$plan['batch_id']]);$audit=json_decode($s->fetchColumn()?:'null',true);$s->closeCursor();
if(!$audit||!hash_equals($audit['sha256_manifiesto'],hash_file('sha256',$file)))throw new RuntimeException('El manifiesto no coincide con el evento confirmado.');
$checks=0;$failures=[];
$check=static function(bool $ok,string $why)use(&$checks,&$failures):void{++$checks;if(!$ok)$failures[]=$why;};
$all=$p->query('SELECT * FROM rrhh.Personal')->fetchAll(PDO::FETCH_ASSOC);$people=[];foreach($all as $r)$people[(int)$r['PersonalID']]=$r;
foreach($m['previos']['rrhh.Personal'] as $r)$check(isset($people[(int)$r['PersonalID']])&&$people[(int)$r['PersonalID']]===$r,'Ficha previa modificada.');
$nullable=0;$surnamePending=0;$knownDni=0;
foreach($m['personas'] as $r){
 $actual=$people[$r['PersonalID']]??null;$check($actual!==null,'Ficha importada ausente.');if(!$actual)continue;
 foreach($r['datos'] as $col=>$expected){$equal=is_int($expected)?($actual[$col]!==null&&(int)$actual[$col]===$expected):$actual[$col]===$expected;$check($equal,'Dato distinto de fuente: línea '.$r['linea'].' / '.$col);}
 $check($actual['FechaCese']===null,'Se registró un cese no informado.');
 $check((int)$actual['Activo']===1,'Ficha nueva deshabilitada.');
 $check($actual['ProfesionID']===null&&$actual['EspecialidadID']===null&&$actual['RegimenLaboral']===null&&$actual['CondicionLaboral']===null,'Se infirió información profesional o legal.');
 $nullable+=(int)($actual['NumeroDocumento']===null);$surnamePending+=(int)($actual['ApellidoPaterno']===null);$knownDni+=(int)($actual['NumeroDocumento']!==null);
}
foreach([['rrhh.AsignacionPersonal','AsignacionPersonalID','asignaciones'],['rrhh.VinculacionBiometrica','VinculacionBiometricaID','vinculos']] as [$t,$pk,$key]){
 $rows=$p->query('SELECT * FROM '.$t)->fetchAll(PDO::FETCH_ASSOC);$index=[];foreach($rows as $row)$index[(int)$row[$pk]]=$row;
 foreach($m['previos'][$t] as $r)$check(isset($index[(int)$r[$pk]])&&$index[(int)$r[$pk]]===$r,'Relación previa modificada: '.$t);
 foreach($m[$key] as $r){$actual=$index[$r[$pk]]??null;$check($actual!==null,'Relación ausente: '.$t);if(!$actual)continue;foreach($r as $col=>$value)$check((string)$actual[$col]===(string)$value,'Relación incorrecta: '.$t.'.'.$col);}
}
foreach($m['conteos_protegidos'] as $t=>$n)$check((int)$p->query('SELECT COUNT_BIG(*) FROM '.$t)->fetchColumn()===$n,'Conteo protegido alterado: '.$t);
$rules=[
 'documentos_duplicados'=>"SELECT COUNT(*) FROM (SELECT TipoDocumentoID,NumeroDocumento FROM rrhh.Personal WHERE TipoDocumentoID IS NOT NULL AND NumeroDocumento IS NOT NULL GROUP BY TipoDocumentoID,NumeroDocumento HAVING COUNT(*)>1) d",
 'biostar_duplicados'=>"SELECT COUNT(*) FROM (SELECT BiostarUserID FROM rrhh.VinculacionBiometrica GROUP BY BiostarUserID HAVING COUNT(*)>1) d",
 'vinculos_huerfanos'=>"SELECT COUNT(*) FROM rrhh.VinculacionBiometrica v LEFT JOIN rrhh.Personal p ON p.PersonalID=v.PersonalID WHERE p.PersonalID IS NULL",
 'asignaciones_huerfanas'=>"SELECT COUNT(*) FROM rrhh.AsignacionPersonal a LEFT JOIN rrhh.Personal p ON p.PersonalID=a.PersonalID LEFT JOIN organizacion.Area ar ON ar.AreaID=a.AreaID LEFT JOIN rrhh.Cargo c ON c.CargoID=a.CargoID WHERE p.PersonalID IS NULL OR ar.AreaID IS NULL OR c.CargoID IS NULL",
 'multiples_asignaciones_actuales'=>"SELECT COUNT(*) FROM (SELECT PersonalID FROM rrhh.AsignacionPersonal WHERE EsActual=1 GROUP BY PersonalID HAVING COUNT(*)>1) d",
 'fk_deshabilitadas'=>"SELECT COUNT(*) FROM sys.foreign_keys WHERE is_disabled=1 OR is_not_trusted=1",
];
foreach($rules as $name=>$sql){$results[$name]=(int)$p->query($sql)->fetchColumn();$check($results[$name]===0,$name);}
$out=['ok'=>!$failures,'base'=>'BD_HOSPITAL_V3','fecha_utc'=>gmdate(DATE_ATOM),'modo'=>'SOLO_SELECT','comprobaciones'=>$checks,'fallos'=>$failures,'totales'=>['personal'=>count($people),'nuevos'=>count($m['personas']),'asignaciones_nuevas'=>count($m['asignaciones']),'vinculos_nuevos'=>count($m['vinculos']),'dni_informados_nuevos'=>$knownDni,'documentos_pendientes'=>$nullable,'apellidos_pendientes'=>$surnamePending],'integridad'=>$results,'originales_preservados'=>!$failures];
file_put_contents($folder.'/validacion_posterior.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));chmod($folder.'/validacion_posterior.json',0600);echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;exit($failures?1:0);
