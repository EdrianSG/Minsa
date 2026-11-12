<?php
/** Describe SQL del importador en el servidor: se compila, no se ejecuta su DML. */
declare(strict_types=1);
require_once __DIR__.'/../includes/BiostarCsvParser.php';
require_once __DIR__.'/V4ImportFixture.php';
$prop=new ReflectionProperty(Database::class,'instance');$fake=new V4ImportPDO();$fake->hasModulo=!in_array('--auditoria-legacy',$argv,true);$prop->setValue(null,$fake);
$temp=tempnam(sys_get_temp_dir(),'metadata-bio-');
try{
 file_put_contents($temp,"Fecha,Puerta,ID Dispositivo,Dispositivo,Grupo Usuario,Usuario,Evento\n2026-01-01 08:00:00,P,1,D,G,BIO-A(Nombre),Autenticación 1:N correcta (Huella)\n2026-01-01 08:01:00,P,1,D,G,BIO-A(Nombre),Hora dispositivo cambiada\n");
 BiostarCsvParser::importar($temp,'solo-memoria.csv',null);$queries=array_values(array_unique($fake->queries));
}finally{unlink($temp);$prop->setValue(null,null);}
$p=Database::getConnection(true);if(!$p||$p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('Conexión real V3 requerida.');
$results=[];$failed=false;
foreach($queries as $sql){
 if(str_contains($sql,'sp_getapplock'))continue; // No adquirir bloqueos reales para esta prueba.
 $converted=preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/','@$1',$sql);
 $index=0;$converted=preg_replace_callback('/\?/',static function()use(&$index){return '@pos'.(++$index);},$converted);
 $label=preg_match('/\b(?:INTO|FROM|UPDATE)\s+([a-zA-Z0-9_.]+)/i',$sql,$m)?$m[1]:'metadatos';
 try{
  $s=$p->prepare('EXEC sys.sp_describe_undeclared_parameters @tsql=?;');$s->execute([$converted]);$types=$s->fetchAll(PDO::FETCH_ASSOC);$s->closeCursor();$results[]=['objeto'=>$label,'valido'=>true,'parametros'=>count($types)];
 }catch(Throwable $e){$failed=true;$results[]=['objeto'=>$label,'valido'=>false,'error'=>$e->getMessage()];}
}
echo json_encode(['modo'=>'SOLO_DESCRIPCION_METADATA_NO_EJECUTA_DML','base'=>'BD_HOSPITAL_V3','resultados'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
exit($failed?1:0);
