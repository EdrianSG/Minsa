<?php
/** Recupera exclusivamente la cuenta administrativa real de V3 en una V4 sin seguridad poblada.
 * Uso: php database/recuperar_admin_v4.php [--aplicar]
 * No cambia claves, no crea trabajadores, no escribe en la base de origen.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/SystemContext.php';
require_once __DIR__.'/../includes/PasswordHashFormat.php';
require_once __DIR__.'/../includes/auth.php';
$pdo=Database::getConnection(true);
if (!$pdo) { fwrite(STDERR,'No se pudo conectar con SQL Server.'.PHP_EOL); exit(1); }
try {
    if ($pdo->query('SELECT DB_NAME()')->fetchColumn() !== 'BD_HOSPITAL_V4') throw new RuntimeException('Destino incorrecto; se requiere BD_HOSPITAL_V4.');
    $sql="SELECT u.UsuarioID, u.NombreUsuario, u.PasswordHash, u.FechaCreacion,
        r.Codigo AS RolCodigo, r.Nombre AS RolNombre, r.Descripcion AS RolDescripcion,
        s.Codigo AS SistemaCodigo, s.Nombre AS SistemaNombre, s.Descripcion AS SistemaDescripcion
        FROM BD_HOSPITAL_V3.seguridad.Usuario u
        JOIN BD_HOSPITAL_V3.seguridad.UsuarioRol ur ON ur.UsuarioID=u.UsuarioID AND ur.Activo=1
        JOIN BD_HOSPITAL_V3.seguridad.Rol r ON r.RolID=ur.RolID AND r.Activo=1
        JOIN BD_HOSPITAL_V3.seguridad.Sistema s ON s.SistemaID=r.SistemaID AND s.Activo=1
        JOIN BD_HOSPITAL_V3.seguridad.UsuarioSistema us ON us.UsuarioID=u.UsuarioID AND us.SistemaID=s.SistemaID AND us.Activo=1
        WHERE u.NombreUsuario=? AND u.Activo=1 AND u.Bloqueado=0 AND r.Codigo='RRHH_ADMIN'
        AND (ur.FechaInicio IS NULL OR ur.FechaInicio<=CAST(GETDATE() AS DATE))
        AND (ur.FechaFin IS NULL OR ur.FechaFin>=CAST(GETDATE() AS DATE))
        AND (us.FechaInicio IS NULL OR us.FechaInicio<=CAST(GETDATE() AS DATE))
        AND (us.FechaFin IS NULL OR us.FechaFin>=CAST(GETDATE() AS DATE))";
    $s=$pdo->prepare($sql); $s->execute(['admin']); $sources=$s->fetchAll(PDO::FETCH_ASSOC);
    if (count($sources)!==1) throw new RuntimeException('No se encontró una única cuenta admin autorizada y vigente en V3.');
    $source=$sources[0];
    $compatible=PasswordHashFormat::supported($source['PasswordHash']);
    if (in_array('--verificar',$argv,true)) {
        $s=$pdo->prepare('SELECT UsuarioID,PersonalID,PasswordHash FROM seguridad.Usuario WHERE NombreUsuario=?');
        $s->execute([$source['NombreUsuario']]); $target=$s->fetch(PDO::FETCH_ASSOC);
        if (!$target) throw new RuntimeException('Cuenta no encontrada en V4.');
        $same=hash_equals($source['PasswordHash'],(string)$target['PasswordHash']);
        $_SESSION['user']=['usuario_id'=>(int)$target['UsuarioID'],'personal_id'=>$target['PersonalID'],
            'rol'=>$source['RolCodigo'],'auth_stamp'=>hash('sha256',$target['PasswordHash']),
            'database_source'=>Database::sourceId(),'system_code'=>SystemContext::code()];
        $valid=Auth::validateSession($pdo);
        $admin=$valid && Auth::checkPermission('administracion');
        $_SESSION=[];
        echo json_encode(['hash_conservado'=>$same,'cuenta_habilitada_para_rrhh'=>$valid,
            'permiso_administracion'=>$admin,'sin_personal_ficticio'=>$target['PersonalID']===null],JSON_PRETTY_PRINT),PHP_EOL;
        exit($same && $valid && $admin ? 0 : 1);
    }

    if (in_array('--diagnosticar',$argv,true)) {
        echo json_encode(['usuario'=>$source['NombreUsuario'],'sistema'=>$source['SistemaCodigo'],'rol'=>$source['RolCodigo'],'hash_compatible_php'=>$compatible,'tipo_hash'=>gettype($source['PasswordHash']),'longitud_hash'=>strlen((string)$source['PasswordHash']),'algoritmo'=>password_get_info((string)$source['PasswordHash'])['algoName']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
        exit;
    }
    if (!$compatible) throw new RuntimeException('La clave de origen no es un hash PHP compatible.');
    echo json_encode(['origen'=>'BD_HOSPITAL_V3','destino'=>'BD_HOSPITAL_V4','usuario'=>$source['NombreUsuario'],
        'sistema'=>$source['SistemaCodigo'],'rol'=>$source['RolCodigo'],
        'clave'=>'Se conserva exactamente el hash existente; no se muestra ni cambia.',
        'personal'=>'Sin vínculo laboral en V4; no se copian IDs de trabajadores de otra base.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
    if (!in_array('--aplicar',$argv,true)) { echo 'Vista previa. No se modificaron datos.',PHP_EOL; exit; }
    if ($source['SistemaCodigo']!==SystemContext::code()) throw new RuntimeException('El código real del sistema no coincide con la configuración de esta aplicación.');
    $pdo->beginTransaction();
    foreach (['Sistema','Usuario','Rol','UsuarioRol','UsuarioSistema'] as $table) {
        $count=(int)$pdo->query("SELECT COUNT(*) FROM seguridad.[$table] WITH (UPDLOCK,HOLDLOCK)")->fetchColumn();
        if ($count) throw new RuntimeException('La seguridad V4 ya contiene registros. Se cancela para no sobrescribir ni duplicar cuentas.');
    }
    $s=$pdo->prepare('INSERT INTO seguridad.Sistema (Codigo,Nombre,Descripcion,Activo) OUTPUT INSERTED.SistemaID VALUES (?,?,?,1)');
    $s->execute([$source['SistemaCodigo'],$source['SistemaNombre'],$source['SistemaDescripcion']]); $system=(int)$s->fetchColumn(); $s->closeCursor();
    $s=$pdo->prepare('INSERT INTO seguridad.Rol (Codigo,Nombre,Descripcion,SistemaID,Activo) OUTPUT INSERTED.RolID VALUES (?,?,?,?,1)');
    $s->execute([$source['RolCodigo'],$source['RolNombre'],$source['RolDescripcion'],$system]); $role=(int)$s->fetchColumn(); $s->closeCursor();
    $s=$pdo->prepare('INSERT INTO seguridad.Usuario (PersonalID,NombreUsuario,PasswordHash,FechaCreacion,Activo,Bloqueado) OUTPUT INSERTED.UsuarioID VALUES (NULL,?,?,?,1,0)');
    $s->execute([$source['NombreUsuario'],$source['PasswordHash'],$source['FechaCreacion']]); $user=(int)$s->fetchColumn(); $s->closeCursor();
    $s=$pdo->prepare('INSERT INTO seguridad.UsuarioRol (UsuarioID,RolID,FechaAsignacion,FechaInicio,Activo) VALUES (?,?,SYSDATETIME(),CAST(GETDATE() AS DATE),1)');
    $s->execute([$user,$role]);
    $s=$pdo->prepare('INSERT INTO seguridad.UsuarioSistema (UsuarioID,SistemaID,FechaInicio,Activo) VALUES (?,?,CAST(GETDATE() AS DATE),1)');
    $s->execute([$user,$system]);
    $s=$pdo->prepare('INSERT INTO auditoria.EventoAuditoria (UsuarioID,SistemaID,FechaHora,Modulo,Accion,TablaAfectada,RegistroID,Descripcion) VALUES (?,?,SYSDATETIME(),?,?,?, ?,?)');
    $s->execute([$user,$system,'SEGURIDAD','RECUPERAR_ADMIN','seguridad.Usuario',(string)$user,'Cuenta administrativa existente recuperada desde BD_HOSPITAL_V3. Hash conservado; sin trabajador asociado ni cambios en origen.']);
    $s=$pdo->prepare('SELECT PasswordHash FROM seguridad.Usuario WHERE UsuarioID=?'); $s->execute([$user]);
    if (!hash_equals($source['PasswordHash'],(string)$s->fetchColumn())) throw new RuntimeException('El hash de destino no coincide con el origen.');
    $pdo->commit();
    echo 'Cuenta administrativa y habilitación RR. HH. recuperadas. Base de origen sin cambios.',PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'No se aplicaron cambios: '.$e->getMessage().PHP_EOL); exit(1);
}
