<?php
/** Validación de lectura de los catálogos reales y su mapa; no consulta identidades personales. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/PmyoCatalog.php';
$p=Database::getConnection(true); if(!$p || $p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4')exit(1);
$checks=0;
function catalogCheck(bool $ok,string $why):void {global $checks;++$checks;if(!$ok)throw new RuntimeException($why);}
$data=[];
foreach([
 'areas'=>'SELECT AreaID AS id,Nombre AS nombre,DepartamentoID AS departamento_id,Activo AS activo FROM organizacion.Area',
 'cargos'=>'SELECT CargoID AS id,Nombre AS nombre,Activo AS activo FROM rrhh.Cargo',
 'profesiones'=>'SELECT ProfesionID AS id,Codigo AS codigo,Nombre AS nombre,Activo AS activo FROM rrhh.Profesion',
 'especialidades'=>'SELECT EspecialidadID AS id,ProfesionID AS profesion_id,Nombre AS nombre,Activo AS activo FROM rrhh.Especialidad',
] as $key=>$sql)$data[$key]=$p->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$data=PmyoCatalog::enrich($data); $map=PmyoCatalog::map();
foreach(PmyoCatalog::areas() as $name=>$definition) {
 $found=array_filter($data['areas'],fn($a)=>PmyoCatalog::key($a['nombre'])===$name);
 catalogCheck(count($found)===1,'Área faltante o ambigua: '.$name);
}
foreach(PmyoCatalog::profiles() as $name) {
 $found=array_filter($data['cargos'],fn($a)=>PmyoCatalog::key($a['nombre'])===$name);
 catalogCheck(count($found)===1,'Perfil faltante o ambiguo: '.$name);
}
foreach($map['profesiones'] as $code=>$name)catalogCheck(count(array_filter($data['profesiones'],fn($a)=>$a['codigo']===$code && PmyoCatalog::key($a['nombre'])===PmyoCatalog::key($name)))===1,'Profesión incorrecta: '.$code);
$profIds=array_map('intval',array_column($data['profesiones'],'id'));
foreach($data['especialidades'] as $e)catalogCheck(in_array((int)$e['profesion_id'],$profIds,true),'Especialidad sin profesión.');
foreach($data['cargos'] as $c) if($c['formacion'])catalogCheck($c['profesion_propuesta_id']===null,'Se acreditó un título por un internado.');
$lab=array_values(array_filter($data['areas'],fn($a)=>PmyoCatalog::key($a['nombre'])==='LABORATORIO'))[0];
$bio=array_values(array_filter($data['cargos'],fn($a)=>PmyoCatalog::key($a['nombre'])==='BIOLOGO'))[0];
catalogCheck(in_array($lab['id'],$bio['areas_pmyo'],true),'No se ofrece Biología para el área de laboratorio.');
$pharmacy=array_values(array_filter($data['areas'],fn($a)=>PmyoCatalog::key($a['nombre'])==='FARMACIA'))[0];
$nursing=array_values(array_filter($data['cargos'],fn($a)=>PmyoCatalog::key($a['nombre'])==='TEC ENFERMERIA'))[0];
catalogCheck(in_array($pharmacy['id'],$nursing['areas_pmyo'],true),'El filtro rechaza técnico de enfermería en farmacia pese al mapa.');
$raw=$p->query('SELECT COUNT(*) AS total,COUNT(DISTINCT DepartamentoOriginal) AS areas,COUNT(DISTINCT CargoOriginal) AS perfiles FROM rrhh.PadronHorarioOrigen')->fetch(PDO::FETCH_ASSOC);
catalogCheck((int)$raw['total']===181 && (int)$raw['areas']===29 && (int)$raw['perfiles']===57,'Cambió el padrón operativo.');
echo json_encode(['ok'=>true,'comprobaciones'=>$checks,'base'=>'BD_HOSPITAL_V4','catalogos_totales'=>array_map('count',$data),'mapa'=>['areas_operativas'=>29,'complementarias'=>7,'perfiles_operativos'=>57,'perfiles_con_variantes'=>68],'sin_escrituras'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
