<?php
/** Comparación de solo lectura con una copia del endpoint anterior. No imprime datos personales. */
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_file($argv[1])) exit(1);
$runtime = sys_get_temp_dir().'/minsa-directory-'.bin2hex(random_bytes(6));
mkdir($runtime,0700);
putenv('SNAPSHOT_PATH='.$runtime);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/PersonalDirectory.php';
$pdo = Database::getConnection(true);
if (!$pdo) {fwrite(STDERR,"SQL no disponible.\n");exit(1);}
if ($pdo->query('SELECT DB_NAME()')->fetchColumn() !== 'BD_HOSPITAL_V3') exit(1);
$stmt = $pdo->prepare("SELECT u.UsuarioID,u.PasswordHash,r.Codigo FROM seguridad.Usuario u
 JOIN seguridad.UsuarioRol ur ON ur.UsuarioID=u.UsuarioID JOIN seguridad.Rol r ON r.RolID=ur.RolID
 WHERE u.NombreUsuario='admin' AND r.Codigo='RRHH_ADMIN' AND r.SistemaID=?");
$stmt->execute([SystemContext::id($pdo)]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) exit(1);
$_SESSION['user']=['usuario_id'=>(int)$admin['UsuarioID'],'rol'=>$admin['Codigo'],
 'database_source'=>Database::sourceId(),'system_code'=>SystemContext::code(),
 'auth_stamp'=>hash('sha256',$admin['PasswordHash'])];
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']='localhost';
$code = str_replace('__DIR__',var_export(dirname(__DIR__).'/api/personal',true),file_get_contents($argv[1]));
function beforeDirectory(string $code, array $params): array {
    $_GET=$params;
    ob_start();
    try { eval('?>'.$code); return json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR); }
    finally { ob_end_clean(); }
}
$defaults=['q'=>'','servicio'=>'','areaFilter'=>'','condicion'=>'','regimen'=>'','estado'=>null,'page'=>1,'limit'=>10];
$cases=[[],['page'=>2],['page'=>46],['page'=>1000],['estado'=>1],['estado'=>0],
 ['regimen'=>'ASISTENCIAL'],['regimen'=>'ADMINISTRATIVO'],['regimen'=>'DL 1153'],
 ['condicion'=>'CAS'],['condicion'=>'NOMBRADO'],['q'=>'sin coincidencias 987xyz']];
$sample=$pdo->query("SELECT TOP 1 p.NumeroDocumento,ap.AreaID,ap.ServicioID FROM rrhh.Personal p
 LEFT JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID=p.PersonalID AND ap.EsActual=1 AND ap.Activo=1
 WHERE p.NumeroDocumento IS NOT NULL AND ap.AreaID IS NOT NULL ORDER BY p.PersonalID")->fetch(PDO::FETCH_ASSOC);
if ($sample) {
 $cases[]=['q'=>$sample['NumeroDocumento']];
 $cases[]=['areaFilter'=>(string)$sample['AreaID']];
 $cases[]=['areaFilter'=>(string)$sample['AreaID'],'estado'=>1];
}
$service=$pdo->query('SELECT TOP 1 ServicioID FROM organizacion.Servicio ORDER BY ServicioID')->fetchColumn();
if ($service) $cases[]=['servicio'=>(string)$service];
$results=[];
foreach ($cases as $index=>$case) {
    $filters=array_replace($defaults,$case);
    $query=$filters;
    $query['area']=$query['areaFilter']; unset($query['areaFilter']);
    $start=microtime(true);
    $before=beforeDirectory($code,$query);
    $beforeMs=round((microtime(true)-$start)*1000,1);
    $start=microtime(true);
    $after=PersonalDirectory::page($pdo,$filters);
    $afterMs=round((microtime(true)-$start)*1000,1);
    if ($before != $after) {
        fwrite(STDERR,json_encode(['caso'=>$index,'campos_diferentes'=>array_keys(array_filter($before,static fn($value,$key)=>$value!=($after[$key]??null),ARRAY_FILTER_USE_BOTH))])."\n");
        exit(1);
    }
    $results[]=['caso'=>$index,'antes_ms'=>$beforeMs,'despues_ms'=>$afterMs,'equivalente'=>true];
}
$start = microtime(true);
$oldOptions = [];
$oldBytes = 0;
$pages = 1;
for ($page=1; $page<=$pages; $page++) {
    $response = beforeDirectory($code, ['limit'=>50,'page'=>$page]);
    $oldOptions = array_merge($oldOptions,$response['data']);
    $oldBytes += strlen(json_encode($response));
    $pages = $response['total_paginas'];
}
$oldOptionsMs = round((microtime(true)-$start)*1000,1);
$start = microtime(true);
$newCode = str_replace('__DIR__',var_export(dirname(__DIR__).'/api/personal',true),file_get_contents(__DIR__.'/../api/personal/opciones.php'));
$newOptions = beforeDirectory($newCode, ['contexto'=>'reportes']);
$newOptionsMs = round((microtime(true)-$start)*1000,1);
$oldIds = array_column($oldOptions,'personal_id');
$newIds = array_column($newOptions['data'],'personal_id');
if ($oldIds != $newIds) throw new RuntimeException('El selector no conserva todos los trabajadores y su orden.');
$optionsMetrics = ['antes_peticiones'=>$pages,'despues_peticiones'=>1,'antes_ms'=>$oldOptionsMs,'despues_ms'=>$newOptionsMs,
    'antes_bytes'=>$oldBytes,'despues_bytes'=>strlen(json_encode($newOptions)),'trabajadores'=>count($newIds)];
foreach (glob($runtime.'/*') ?: [] as $file) unlink($file);
rmdir($runtime);
$_SESSION=[];
if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
echo json_encode(['casos'=>count($results),'resultados'=>$results,'selector'=>$optionsMetrics],JSON_PRETTY_PRINT),"\n";
