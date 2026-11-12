<?php
/** Genera un plan DDL para una base nueva. Action:Script nunca aplica el plan. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/SchemaDelivery.php';
$source=$argv[1]??'';$target='BD_HOSPITAL_V3_ENTREGA_NUEVA';$tool=$argv[2]??'/tmp/minsa-sqlpackage/sqlpackage';
if(!is_file($source)||!str_ends_with($source,'.dacpac')||!is_executable($tool))throw new InvalidArgumentException('Uso: php generar_instalador_v3.php archivo.dacpac [sqlpackage]');
$dir=dirname(realpath($source));$output=$dir.'/BD_HOSPITAL_V3_INSTALADOR_BASE_NUEVA.sql';
if(is_file($output))throw new RuntimeException('Ya existe el instalador; no sobrescribir.');
$c=Database::getConfig();if($c['database']!=='BD_HOSPITAL_V3')throw new RuntimeException('Origen distinto de V3.');
$args=[$tool,'/Action:Script','/SourceFile:'.realpath($source),'/TargetServerName:'.$c['host'].','.$c['port'],'/TargetDatabaseName:'.$target,'/TargetUser:'.$c['username'],'/TargetPassword:'.$c['password'],'/TargetEncryptConnection:'.($c['encrypt']?'True':'False'),'/TargetTrustServerCertificate:'.($c['trust_server_certificate']?'True':'False'),'/DeployScriptPath:'.$output,'/OverwriteFiles:False','/p:CreateNewDatabase=False','/p:DropObjectsNotInSource=False','/p:BlockOnPossibleDataLoss=True','/p:ScriptDatabaseOptions=True','/MaxParallelism:2'];
$raw=$dir.'/instalador.raw.log';$proc=proc_open($args,[1=>['file',$raw,'w'],2=>['file',$raw,'a']],$pipes);if(!is_resource($proc))throw new RuntimeException('No se pudo ejecutar SqlPackage.');chmod($raw,0600);$status=proc_close($proc);
file_put_contents($dir.'/instalador.log',str_replace($c['password'],'[REDACTADO]',file_get_contents($raw)));chmod($dir.'/instalador.log',0600);unlink($raw);
if($status!==0){fwrite(STDERR,'No se generó un instalador validado; consultar el log privado.'.PHP_EOL);exit(1);}
if(is_file($output)){
 $sql=SchemaDelivery::newDatabaseOnly(file_get_contents($output),$target);
 file_put_contents($output,$sql);chmod($output,0600);
 echo json_encode(['modo'=>'SOLO_GENERACION_DDL','destino_planificado'=>$target,'base_creada'=>false,'archivo'=>basename($output),'sha256'=>hash_file('sha256',$output)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}else throw new RuntimeException('SqlPackage no produjo el archivo esperado.');
