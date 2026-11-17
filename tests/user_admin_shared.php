<?php
// Fixture SQLite aislado; incluye regresiones de edición previas. No usa SQL Server.
require __DIR__.'/test_user_admin_edition.php';
$pdo->exec("INSERT INTO seguridad.Sistema VALUES (2,'LABORATORIO',1);
 INSERT INTO seguridad.Rol VALUES (20,'LAB_OPERADOR',2,1),(4,'CONSULTA',1,1);
 INSERT INTO seguridad.Usuario (UsuarioID,PersonalID,NombreUsuario,PasswordHash,Activo,Bloqueado)
 VALUES (7,100,'cuenta_laboratorio','hash_laboratorio_sin_modificar',1,0);
 INSERT INTO seguridad.UsuarioSistema VALUES (7,2,1,NULL,NULL);
 INSERT INTO seguridad.UsuarioRol VALUES (7,20,1,NULL,NULL,datetime('now'));");
function snapshotShared(PDO $pdo): array {
    return [
        $pdo->query('SELECT * FROM seguridad.Usuario WHERE UsuarioID=7')->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query('SELECT * FROM seguridad.UsuarioSistema WHERE UsuarioID=7 AND SistemaID=2')->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query('SELECT * FROM seguridad.UsuarioRol WHERE UsuarioID=7 AND RolID=20')->fetchAll(PDO::FETCH_ASSOC)
    ];
}
function rejectedShared(callable $fn): void {
    $rejected=false;
    try { $fn(); } catch (InvalidArgumentException $e) { $rejected=true; }
    testAssert($rejected,'Se aceptó una operación que debe rechazarse.');
}
function checkSharedAccess(PDO $pdo,bool $expected): void {
    $_SESSION['user']=['usuario_id'=>7,'personal_id'=>100,'servicio_id'=>5,'rol'=>'CONSULTA',
        'auth_stamp'=>hash('sha256','hash_laboratorio_sin_modificar'),
        'system_code'=>SystemContext::code(),'database_source'=>Database::sourceId()];
    testAssert(Auth::validateSession($pdo)===$expected,'Acceso RRHH incoherente con su habilitación.');
    $_SESSION=[];
}
$before=snapshotShared($pdo);
checkSharedAccess($pdo,false);
rejectedShared(fn()=>UserAdmin::change($pdo,['accion'=>'habilitar','usuario_id'=>7,'rol_id'=>20],1));
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=7 AND SistemaID=1')->fetchColumn()===0,'Un rol de Laboratorio habilitó RRHH.');
$res=UserAdmin::change($pdo,['accion'=>'habilitar','usuario_id'=>7,'rol_id'=>4],1);
testAssert($res['ok'] && !isset($res['clave_temporal']),'Se generó contraseña al habilitar.');
checkSharedAccess($pdo,true);
testAssert(snapshotShared($pdo)===$before,'Se modificó la cuenta global o Laboratorio.');
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=7 AND SistemaID=1 AND Activo=1')->fetchColumn()===1,'No se creó vínculo RRHH único.');
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioRol WHERE UsuarioID=7 AND RolID=4 AND Activo=1')->fetchColumn()===1,'No se asignó CONSULTA de RRHH.');
rejectedShared(fn()=>UserAdmin::change($pdo,['accion'=>'habilitar','usuario_id'=>7,'rol_id'=>4],1));
UserAdmin::change($pdo,['accion'=>'editar','usuario_id'=>7,'username'=>'cuenta_laboratorio','personal_id'=>100,'rol_id'=>2,'activo'=>true,'bloqueado'=>false],1);
testAssert(snapshotShared($pdo)===$before,'La edición del rol RRHH alteró Laboratorio o la identidad.');
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioRol WHERE UsuarioID=7 AND RolID=2 AND Activo=1')->fetchColumn()===1,'No cambió exclusivamente el rol RRHH.');
foreach (['reset_password','cambiar_bloqueo'] as $action) rejectedShared(fn()=>UserAdmin::change($pdo,['accion'=>$action,'usuario_id'=>7,'bloqueado'=>true],1));
rejectedShared(fn()=>UserAdmin::change($pdo,['accion'=>'editar','usuario_id'=>7,'username'=>'otro_login','personal_id'=>100,'rol_id'=>2],1));
testAssert(snapshotShared($pdo)===$before,'Un rechazo alteró datos compartidos.');
$pdo->exec("INSERT INTO seguridad.Usuario (UsuarioID,PersonalID,NombreUsuario,PasswordHash,Activo,Bloqueado) VALUES (8,100,'inactivo','hash',0,0),(9,100,'bloqueado','hash',1,1);");
foreach ([8,9] as $id) rejectedShared(fn()=>UserAdmin::change($pdo,['accion'=>'habilitar','usuario_id'=>$id,'rol_id'=>4],1));
$pdo->exec('INSERT INTO rrhh.Personal VALUES (103,1); INSERT INTO rrhh.AsignacionPersonal VALUES (103,9,1,1,1)');
$created=UserAdmin::change($pdo,['accion'=>'crear','username'=>'nueva_rrhh','personal_id'=>103,'rol_id'=>4],1);
testAssert($created['ok'] && isset($created['clave_temporal']),'No creó cuenta nueva con clave.');
$createdId=(int)$created['usuario_id'];
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioRol WHERE UsuarioID='.$createdId.' AND RolID=4 AND Activo=1')->fetchColumn()===1,'El alta nueva quedó sin rol RRHH.');
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID='.$createdId.' AND SistemaID=1 AND Activo=1')->fetchColumn()===1,'El alta nueva quedó sin vínculo RRHH.');
$pdo->exec("CREATE TRIGGER auditoria.rechazar_habilitacion BEFORE INSERT ON EventoAuditoria WHEN NEW.Accion='HABILITAR' BEGIN SELECT RAISE(ABORT,'fallo auditoria simulado'); END;
 INSERT INTO seguridad.Usuario (UsuarioID,PersonalID,NombreUsuario,PasswordHash,Activo,Bloqueado) VALUES (30,100,'sin_vinculo','hash',1,0);");
$failed=false;
try { UserAdmin::change($pdo,['accion'=>'habilitar','usuario_id'=>30,'rol_id'=>4],1); } catch (RuntimeException $e) { $failed=true; }
testAssert($failed,'No falló al rechazar auditoría.');
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=30')->fetchColumn()===0,'No revirtió vínculo al fallar auditoría.');
testAssert((int)$pdo->query('SELECT COUNT(*) FROM seguridad.UsuarioRol WHERE UsuarioID=30')->fetchColumn()===0,'No revirtió rol al fallar auditoría.');
echo "OK: habilitación RRHH, aislamiento de Laboratorio, contraseña intacta, roles ajenos, duplicados, bloqueo global y reversión por auditoría.\n";
