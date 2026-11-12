<?php
/** Extrae esquema con SqlPackage oficial. No ejecuta Publish, Import ni scripts DDL. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
$binary=$argv[1]??'/tmp/minsa-sqlpackage/sqlpackage';
if(!is_executable($binary))throw new RuntimeException('Indique la ruta al SqlPackage oficial.');
$p=Database::getConnection(true);if(!$p||$p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('Conexión V3 requerida.');
$c=Database::getConfig();$root=__DIR__.'/../private-audits/entrega-v3';if(!is_dir($root))mkdir($root,0700,true);
$stamp=gmdate('Ymd\THis\Z');$dir=$root.'/esquema_'.$stamp;
if(!mkdir($dir,0700))throw new RuntimeException('No se pudo crear la carpeta privada.');
$escape=static fn(string $v):string=>'"'.str_replace('"','""',$v).'"';
$conn='Server='.$escape($c['host'].','.$c['port']).';Initial Catalog=BD_HOSPITAL_V3;User ID='.$escape($c['username']).';Password='.$escape($c['password']).';Encrypt='.($c['encrypt']?'True':'False').';TrustServerCertificate='.($c['trust_server_certificate']?'True':'False').';Connect Timeout=15;';
$results=[];
foreach(['DacPac'=>'BD_HOSPITAL_V3_REAL.dacpac','File'=>'BD_HOSPITAL_V3_ESQUEMA_REAL.sql'] as $format=>$name){
 $args=[$binary,'/Action:Extract','/SourceConnectionString:'.$conn,'/TargetFile:'.$dir.'/'.$name,'/OverwriteFiles:False','/p:ExtractTarget='.$format,'/p:ExtractAllTableData=False','/p:ExtractReferencedServerScopedElements=False','/p:IgnorePermissions=False','/p:IgnoreUserLoginMappings=True','/p:VerifyExtraction=True','/p:CommandTimeout=120','/MaxParallelism:2'];
 $rawLog=$dir.'/'.$format.'.raw.log';
 $proc=proc_open($args,[1=>['file',$rawLog,'w'],2=>['file',$rawLog,'a']],$pipes);
 if(!is_resource($proc))throw new RuntimeException('No se pudo iniciar SqlPackage.');
 chmod($rawLog,0600);
 $exit=proc_close($proc);
 $log=str_replace([$c['password'],$conn],['[REDACTADO]','[CONEXION REDACTADA]'],file_get_contents($rawLog));
 file_put_contents($dir.'/'.$format.'.log',$log);chmod($dir.'/'.$format.'.log',0600);
 unlink($rawLog);
 $results[$format]=['exit'=>$exit,'archivo'=>$name];
 if(is_file($dir.'/'.$name)){chmod($dir.'/'.$name,0600);$results[$format]['sha256']=hash_file('sha256',$dir.'/'.$name);}
 echo json_encode(['formato'=>$format,'exit'=>$exit,'archivo'=>$name],JSON_UNESCAPED_UNICODE),PHP_EOL;
 if($exit!==0)break;
}
file_put_contents($dir.'/resultado.json',json_encode(['base'=>'BD_HOSPITAL_V3','fecha_utc'=>gmdate(DATE_ATOM),'esquema_sin_datos'=>true,'mapeos_login_servidor_excluidos'=>true,'resultados'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));chmod($dir.'/resultado.json',0600);
if($exit!==0){fwrite(STDERR,'Extracción no validada; consultar log privado.'.PHP_EOL);exit(1);}
