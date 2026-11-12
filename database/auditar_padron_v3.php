<?php
/** Inspección exclusiva de lectura de V3. No contiene modo de carga ni de borrado. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
$source=realpath($argv[1]??(__DIR__.'/../padron_personal_con_turnos.txt'));
if(!$source||!is_readable($source))throw new InvalidArgumentException('Indique un archivo de personal legible.');
$outputLabel=$argv[2]??'padron-v3';
if(!preg_match('/^[A-Za-z0-9_-]+$/D',$outputLabel))throw new InvalidArgumentException('Nombre de carpeta privada inválido.');
function auditRead(PDO $pdo,string $sql): array {
    if(!preg_match('/^SELECT\s/i',ltrim($sql))) throw new RuntimeException('Solo se permiten SELECT.');
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row)foreach($row as $key=>&$value){
        if(str_starts_with($key,'is_')&&$value!==null)$value=(bool)(int)$value;
        elseif(in_array($key,['column_id','max_length','precision','scale','key_ordinal','constraint_column_id'],true)&&$value!==null)$value=(int)$value;
    }
    unset($row,$value);
    return $rows;
}
function auditIdentifier(string $value): string {return '['.str_replace(']',']]',$value).']';}
$p=Database::getConnection(true);if(!$p){fwrite(STDERR,Database::getLastError().PHP_EOL);exit(1);}
if($p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3')throw new RuntimeException('Esta inspección solo admite BD_HOSPITAL_V3.');
$r=['fecha'=>date(DATE_ATOM),'base'=>'BD_HOSPITAL_V3','modo'=>'SOLO_SELECT','archivo_origen'=>basename($source),'archivo_sha256'=>hash_file('sha256',$source)];
$r['database_metadata']=auditRead($p,"SELECT name,collation_name,compatibility_level FROM sys.databases WHERE database_id=DB_ID()");
$r['columns']=auditRead($p,"SELECT s.name AS schema_name,t.name AS table_name,c.column_id,c.name AS column_name,ty.name AS data_type,c.max_length,c.precision,c.scale,c.is_nullable,c.is_identity,c.is_computed,dc.definition AS default_definition FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id JOIN sys.columns c ON c.object_id=t.object_id JOIN sys.types ty ON ty.user_type_id=c.user_type_id LEFT JOIN sys.default_constraints dc ON dc.object_id=c.default_object_id WHERE t.is_ms_shipped=0 ORDER BY s.name,t.name,c.column_id");
$r['foreign_keys']=auditRead($p,"SELECT fk.name,ps.name AS parent_schema,pt.name AS parent_table,pc.name AS parent_column,rs.name AS referenced_schema,rt.name AS referenced_table,rc.name AS referenced_column,fkc.constraint_column_id,fk.delete_referential_action_desc,fk.update_referential_action_desc,fk.is_disabled,fk.is_not_trusted FROM sys.foreign_keys fk JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id=fk.object_id JOIN sys.tables pt ON pt.object_id=fk.parent_object_id JOIN sys.schemas ps ON ps.schema_id=pt.schema_id JOIN sys.columns pc ON pc.object_id=pt.object_id AND pc.column_id=fkc.parent_column_id JOIN sys.tables rt ON rt.object_id=fk.referenced_object_id JOIN sys.schemas rs ON rs.schema_id=rt.schema_id JOIN sys.columns rc ON rc.object_id=rt.object_id AND rc.column_id=fkc.referenced_column_id ORDER BY ps.name,pt.name,fk.name,fkc.constraint_column_id");
$r['indexes']=auditRead($p,"SELECT s.name AS schema_name,t.name AS table_name,i.name,i.is_unique,i.is_primary_key,i.filter_definition,c.name AS column_name,ic.key_ordinal,ic.is_included_column FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id JOIN sys.indexes i ON i.object_id=t.object_id JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id JOIN sys.columns c ON c.object_id=t.object_id AND c.column_id=ic.column_id WHERE t.is_ms_shipped=0 ORDER BY s.name,t.name,i.name,ic.key_ordinal");
$r['checks']=auditRead($p,"SELECT s.name AS schema_name,t.name AS table_name,cc.name,cc.definition,cc.is_disabled,cc.is_not_trusted FROM sys.check_constraints cc JOIN sys.tables t ON t.object_id=cc.parent_object_id JOIN sys.schemas s ON s.schema_id=t.schema_id");
$r['triggers']=auditRead($p,"SELECT s.name AS schema_name,t.name AS table_name,tr.name,tr.is_disabled,sm.definition FROM sys.triggers tr JOIN sys.tables t ON t.object_id=tr.parent_id JOIN sys.schemas s ON s.schema_id=t.schema_id LEFT JOIN sys.sql_modules sm ON sm.object_id=tr.object_id");
$r['dependencies']=auditRead($p,"SELECT OBJECT_SCHEMA_NAME(d.referencing_id) AS referencing_schema,OBJECT_NAME(d.referencing_id) AS referencing_name,d.referenced_schema_name,d.referenced_entity_name FROM sys.sql_expression_dependencies d WHERE d.referencing_id IS NOT NULL");
$tables=[];foreach($r['columns'] as $c)$tables[$c['schema_name'].'.'.$c['table_name']][]=$c['column_name'];
foreach($tables as $table=>$columns){[$schema,$name]=explode('.',$table);$r['counts'][$table]=(int)$p->query('SELECT COUNT_BIG(*) FROM '.auditIdentifier($schema).'.'.auditIdentifier($name))->fetchColumn();}
$catalogs=['catalogo.TipoDocumento','rrhh.Profesion','rrhh.Especialidad','rrhh.Cargo','rrhh.TipoContrato','rrhh.Turno','organizacion.Departamento','organizacion.Area','organizacion.Servicio'];
$data=['dbo.StagingPersonalPacasmayo','rrhh.Personal','rrhh.AsignacionPersonal','rrhh.Horario','rrhh.ProgramacionMensual','rrhh.ProgramacionDetalle','rrhh.Programacion','rrhh.ProgramacionDiaria','rrhh.Contrato','rrhh.VinculacionBiometrica','rrhh.PadronBiometricoOrigen','rrhh.PadronHorarioOrigen'];
foreach(array_merge($catalogs,$data) as $table){if(!isset($tables[$table]))continue;[$schema,$name]=explode('.',$table);$r['data'][$table]=auditRead($p,'SELECT * FROM '.auditIdentifier($schema).'.'.auditIdentifier($name));}
foreach($tables as $table=>$columns){if(str_starts_with($table,'rrhh.')&&str_contains($table,'Programacion')&&!isset($r['data'][$table])){[$schema,$name]=explode('.',$table);$r['data'][$table]=auditRead($p,'SELECT * FROM '.auditIdentifier($schema).'.'.auditIdentifier($name));}}
if(isset($tables['rrhh.MarcacionBiometrica'])){
 $cols=array_values(array_intersect(['BiostarUserID','PersonalID'],$tables['rrhh.MarcacionBiometrica']));
 if($cols)$r['biometric_distinct']=auditRead($p,'SELECT DISTINCT '.implode(',',array_map('auditIdentifier',$cols)).' FROM rrhh.MarcacionBiometrica');
}
foreach($r['foreign_keys'] as $fk){
 if($fk['referenced_schema']!== 'rrhh'||$fk['referenced_table']!=='Personal')continue;
 $qualified=auditIdentifier($fk['parent_schema']).'.'.auditIdentifier($fk['parent_table']);
 $column=auditIdentifier($fk['parent_column']);
 $r['personal_inbound_references'][]=['table'=>$fk['parent_schema'].'.'.$fk['parent_table'],'column'=>$fk['parent_column'],'non_null'=>(int)$p->query('SELECT COUNT_BIG(*) FROM '.$qualified.' WHERE '.$column.' IS NOT NULL')->fetchColumn(),'orphans'=>(int)$p->query('SELECT COUNT_BIG(*) FROM '.$qualified.' t LEFT JOIN rrhh.Personal p ON p.PersonalID=t.'.$column.' WHERE t.'.$column.' IS NOT NULL AND p.PersonalID IS NULL')->fetchColumn()];
}
if(isset($tables['seguridad.Usuario']))$r['user_personal_references']=auditRead($p,'SELECT UsuarioID,PersonalID,Activo FROM seguridad.Usuario');
$folder=__DIR__.'/../private-audits/'.$outputLabel;if(!is_dir($folder))mkdir($folder,0700,true);
$path=$folder.'/esquema_y_datos_v3.json';file_put_contents($path,json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));chmod($path,0600);
echo json_encode(['base'=>$r['base'],'modo'=>$r['modo'],'tablas'=>count($tables),'columnas'=>count($r['columns']),'claves_foraneas'=>count(array_unique(array_map(static fn($f)=>$f['parent_schema'].'.'.$f['parent_table'].'.'.$f['name'],$r['foreign_keys']))),'pares_columnas_fk'=>count($r['foreign_keys']),'conteos_relevantes'=>array_filter($r['counts'],static fn($k)=>str_starts_with($k,'rrhh.')||str_starts_with($k,'organizacion.'),ARRAY_FILTER_USE_KEY)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
