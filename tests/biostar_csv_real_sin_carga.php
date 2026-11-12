<?php
/** CSV real, importador real y persistencia en memoria. No abre conexión SQL. */
declare(strict_types=1);
require_once __DIR__.'/../includes/BiostarCsvParser.php';
require_once __DIR__.'/V4ImportFixture.php';
$source=__DIR__.'/../Report_20261015T070613_1.csv';
$roster=__DIR__.'/../padron_personal_con_turnos.txt';
if(!is_file($source)||!is_file($roster))throw new RuntimeException('Se requieren los archivos privados de origen.');
$hasModulo=!in_array('--auditoria-legacy',$argv,true);
$checks=0;
function bioCheck(bool $condition,string $message):void{global $checks;++$checks;if(!$condition)throw new RuntimeException($message);}
$people=[];$h=fopen($roster,'r');$header=fgetcsv($h,0,"\t",'"','\\');while(($row=fgetcsv($h,0,"\t",'"','\\'))!==false)$people[$row[0]]=$row[1];fclose($h);
$users=[];$h=fopen($source,'r');$head=fgets($h);while(($row=fgetcsv($h,0,',','"','\\'))!==false){if(($row[6]??'')!=='Autenticación 1:N correcta (Huella)')continue;$u=BiostarCsvParser::extraerUsuario($row[5]);$users[$u['id']]=$u['nombre'];}fclose($h);
$prop=new ReflectionProperty(Database::class,'instance');$db=new V4ImportPDO();$db->staffMap=[];$db->hasModulo=$hasModulo;$prop->setValue(null,$db);
$temp=tempnam(sys_get_temp_dir(),'biostar-test-');
try{
 $a=BiostarCsvParser::importar($source,basename($source),null);
 bioCheck($a['total_filas']===50000 && $a['marcaciones_insertadas']===41382,'Lectura íntegra del CSV real.');
 bioCheck($a['vinculados_directos']===0 && $a['pendientes_vinculacion']===41382,'Tener un DNI coincidente no crea vínculos automáticamente.');
 bioCheck(count(array_filter($db->marks,static fn($r)=>$r[':pid']!==null))===0,'Sin vínculos, ninguna marca recibe PersonalID.');
 bioCheck(count($db->incidents)===200,'El importador limita a 200 las incidencias de sistema por archivo; no confundirlas con marcaciones.');
 bioCheck($a['marcaciones_consecutivas']===6461 && count($db->marks)===41382,'Ráfagas conservadas, sin eliminarlas como duplicados exactos.');
 $a2=BiostarCsvParser::importar($source,basename($source),null);
 bioCheck($a2['ya_importado'] && count($db->imports)===1 && count($db->marks)===41382,'Repetir el mismo archivo no duplica importaciones ni marcas.');
 unset($a,$a2,$db);$prop->setValue(null,null);gc_collect_cycles();
 $db=new V4ImportPDO();$db->staffMap=[];$db->hasModulo=$hasModulo;
 foreach($users as $bio=>$name)if(isset($people[$bio]))$db->staffMap[(string)$bio]=1000+count($db->staffMap); // IDs sintéticos, solo memoria.
 bioCheck(count($db->staffMap)===266,'266 candidatos de ID coincidente; no acreditación de DNI ni aprobación de carga.');
 $prop->setValue(null,$db);$b=BiostarCsvParser::importar($source,basename($source),null);
 bioCheck($b['vinculados_directos']===40899 && $b['pendientes_vinculacion']===483,'Con mapa explícito simulado, se vinculan 40899 marcas y quedan 483 pendientes.');
 bioCheck(count(array_filter($db->marks,static fn($r)=>$r[':pid']!==null))===40899,'El importador usa el PersonalID del mapa, no el documento como PK.');
 // Otro hash con una marca idéntica de origen: no se duplica la marca ya leída.
 $first=array_values($db->marks)[0];$f=fopen($temp,'w');fwrite($f,$head);fputcsv($f,[$first[':fec'],'', $first[':devId'],$first[':devNom'],'',$first[':bUser'].'('.$first[':bName'].')',$first[':ev']],',','"','\\');fclose($f);
 $c=BiostarCsvParser::importar($temp,'fragmento-prueba.csv',null);
 bioCheck($c['marcaciones_insertadas']===0 && $c['marcaciones_duplicadas']===1 && count($db->marks)===41382,'Otro archivo con la misma marca tampoco duplica datos.');
 unset($b,$c,$db);$prop->setValue(null,null);gc_collect_cycles();
 $db=new V4ImportPDO();$db->staffMap=[];$db->failAudit=true;$prop->setValue(null,$db);
 try{BiostarCsvParser::importar($temp,'fallo-auditoria.csv',null);throw new RuntimeException('No se rechazó el fallo de auditoría.');}
 catch(RuntimeException $e){bioCheck(str_contains($e->getMessage(),'auditar'),'El fallo previsto es el de auditoría.');}
 bioCheck($db->imports===[] && $db->marks===[] && $db->incidents===[] && !$db->tx,'Fallo de auditoría revierte por completo el lote simulado.');
 // Documento distinto de ID BioStar: solo vale la relación explícita.
 $db=new V4ImportPDO();$db->staffMap=[$first[':bUser']=>874];$prop->setValue(null,$db);BiostarCsvParser::importar($temp,'id-distinto-dni.csv',null);
 bioCheck(array_values($db->marks)[0][':pid']===874,'Se respeta PersonalID explícito sin transformarlo en DNI.');
 // Una fecha inválida después de una marca válida no deja un lote parcial.
 $db=new V4ImportPDO();$db->staffMap=[];$prop->setValue(null,$db);
 $f=fopen($temp,'a');fputcsv($f,['fecha-invalida','',$first[':devId'],$first[':devNom'],'',$first[':bUser'],$first[':ev']],',','"','\\');fclose($f);
 try{BiostarCsvParser::importar($temp,'fecha-invalida.csv',null);throw new RuntimeException('No se rechazó la fecha inválida.');}
 catch(InvalidArgumentException $e){bioCheck(str_contains($e->getMessage(),'Fecha biométrica inválida'),'Fecha inválida rechazada por el importador real.');}
 bioCheck($db->imports===[] && $db->marks===[] && !$db->tx,'Fecha inválida revierte también las marcas válidas previas.');
 echo json_encode(['modo'=>'SOLO_MEMORIA_SIN_CONEXION_SQL','auditoria_modulo_texto'=>$hasModulo,'comprobaciones'=>$checks,'eventos_csv'=>50000,'marcas_validas'=>41382,'candidatos_no_aprobados'=>266,'escenario_mapa_explicito'=>['vinculadas'=>40899,'pendientes'=>483],'datos_cargados_en_v3'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}finally{unlink($temp);$prop->setValue(null,null);}
