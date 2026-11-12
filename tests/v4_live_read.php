<?php
/** Comprueba endpoints de lectura contra V4 sin mostrar sus datos ni emitir sesión web. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
$endpoints=['api/personal/detalle.php','api/personal/pendientes.php','api/reportes/individual.php','api/personal/listar.php','api/personal/catalogos.php','api/turnos/listar.php','api/horarios/listar.php',
    'api/asistencia/diaria.php','api/asistencia/ausentes.php','api/dashboard/resumen.php',
    'api/permisos/listar.php','api/permisos/tipos.php','api/programacion/mensual.php',
    'api/administracion/usuarios.php','api/administracion/roles.php','api/administracion/auditoria.php',
    'api/administracion/parametros.php','api/reportes/consolidado.php','api/reportes/seti.php',
    'api/asistencia/biometrico/historial.php','api/asistencia/biometrico/marcaciones.php','api/asistencia/biometrico/incidencias.php','api/data_revision.php'];
if (!isset($argv[1])) {
    $results=[]; $failed=false;
    foreach ($endpoints as $endpoint) {
        $proc=proc_open([PHP_BINARY,__FILE__,$endpoint],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($proc)) throw new RuntimeException('No se pudo iniciar la prueba.');
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc);
        $record=json_decode($out,true);
        if (!$record || $exit!==0) $record=['endpoint'=>$endpoint,'error'=>'Fallo del proceso de prueba.'];
        $results[]=$record;
        if (($record['status']??500)!==200 || !($record['ok']??false)) $failed=true;
    }
    echo json_encode(['modo'=>'LECTURA_SQL','resultados'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
    exit($failed?1:0);
}
$endpoint=$argv[1]; if (!in_array($endpoint,$endpoints,true)) exit(1);
require_once __DIR__.'/../includes/auth.php';
$pdo=Database::getConnection(true); if (!$pdo) { fwrite(STDERR,'SQL no disponible.'); exit(1); }
if ($pdo->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') exit(1);
$s=$pdo->prepare("SELECT u.UsuarioID,u.PasswordHash,r.Codigo FROM seguridad.Usuario u JOIN seguridad.UsuarioRol ur ON ur.UsuarioID=u.UsuarioID JOIN seguridad.Rol r ON r.RolID=ur.RolID WHERE u.NombreUsuario=? AND r.Codigo='RRHH_ADMIN' AND r.SistemaID=?");
$s->execute(['admin',SystemContext::id($pdo)]); $admin=$s->fetch(PDO::FETCH_ASSOC); if (!$admin) exit(1);
$_SESSION['user']=['usuario_id'=>(int)$admin['UsuarioID'],'rol'=>$admin['Codigo'],'database_source'=>Database::sourceId(),
    'system_code'=>SystemContext::code(),'auth_stamp'=>hash('sha256',$admin['PasswordHash'])];
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['HTTP_HOST']='localhost';
$_GET=['mes'=>'11','anio'=>'2026','fecha'=>'2026-11-03','servicio_id'=>'0','limit'=>'5','importacion_id'=>'1'];
$realPerson=$pdo->query('SELECT TOP 1 PersonalID FROM rrhh.Personal ORDER BY PersonalID')->fetchColumn();
if ($realPerson!==false) { $_GET['id']=(string)$realPerson; $_GET['personal_id']=(string)$realPerson; }
ob_start();
register_shutdown_function(function() use ($endpoint) {
    $body=ob_get_clean(); $json=json_decode($body,true);
    $result=['endpoint'=>$endpoint,'status'=>http_response_code()?:200,'ok'=>$json['ok']??$json['success']??false];
    if (!$result['ok']) $result['mensaje']=$json['mensaje']??$json['message']??'Respuesta no JSON.';
    if (isset($json['conexion_sql'])) $result['conexion_sql']=$json['conexion_sql'];
    if (isset($json['total_registros'])) $result['total_registros']=$json['total_registros'];
    echo json_encode($result,JSON_UNESCAPED_UNICODE),PHP_EOL;
    $_SESSION=[];
});
require __DIR__.'/../'.$endpoint;
