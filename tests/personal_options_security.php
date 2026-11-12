<?php
if (PHP_SAPI!=='cli') exit(1);
$folder=$argv[2] ?? sys_get_temp_dir().'/minsa-options-test-'.bin2hex(random_bytes(6));
if (!isset($argv[1])) mkdir($folder,0700);
if (isset($argv[1])) {
    $folder=$argv[2];
    putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT=1');
    putenv('SNAPSHOT_PATH='.$folder.'/snapshots');
    require_once __DIR__.'/../includes/auth.php';
    require_once __DIR__.'/../includes/SnapshotStore.php';
    $_SESSION['user']=['usuario_id'=>1,'rol'=>'SUPERVISOR','servicio_id'=>1,
        'database_source'=>Database::sourceId(),'system_code'=>SystemContext::code()];
    $_SERVER['REQUEST_METHOD']='GET';
    $_GET=['contexto'=>'horarios'];
    foreach ([null=>99,1=>11] as $service=>$id) {
        $scope=$service===''?null:(int)$service;
        $key='personal_opciones_'.hash('sha256',json_encode(['horarios',$scope,'']));
        SnapshotStore::set($key,[['personal_id'=>$id]],['source'=>'SQL_SERVER_LIVE']);
    }
    if ($argv[1]==='module') $_GET['contexto']='administracion';
    if ($argv[1]==='invalid') $_GET['contexto']='otro';
    if ($argv[1]==='unassigned') $_SESSION['user']['servicio_id']=0;
    if ($argv[1]==='state') $_GET['estado']='activo';
    ob_start();
    register_shutdown_function(function() {
        $body=json_decode(ob_get_clean(),true);
        echo json_encode(['status'=>http_response_code()?:200,'ids'=>array_column($body['data']??[],'personal_id')]);
    });
    require __DIR__.'/../api/personal/opciones.php';
    exit;
}
try {
    foreach (['scoped'=>200,'module'=>403,'invalid'=>400,'unassigned'=>403,'state'=>400] as $case=>$status) {
        $process=proc_open([PHP_BINARY,'-d','session.save_path='.$folder,__FILE__,$case,$folder],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
        $data=json_decode($output,true);
        if($exit!==0 || !$data || $data['status']!==$status) throw new RuntimeException($case.': '.$output.$error);
        if($case==='scoped' && $data['ids']!==[11]) throw new RuntimeException('El respaldo filtrado permitió datos globales.');
    }
    echo "OK: permiso por módulo, servicio asignado, respaldo aislado y filtros inválidos.\n";
} finally {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    }
    rmdir($folder);
}
