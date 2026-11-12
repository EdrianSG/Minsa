<?php
/** Catálogos del mapa PMYO. Vista previa por defecto. Nunca asigna perfiles a personas. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/SystemContext.php';
require_once __DIR__.'/../includes/PmyoCatalog.php';
function catalogId(PDO $p,string $table,string $id,string $name,array $extra=[]): int {
    $s=$p->prepare("SELECT [$id] FROM $table WITH (UPDLOCK,HOLDLOCK) WHERE UPPER(LTRIM(RTRIM(Nombre)))=?");
    $s->execute([PmyoCatalog::key($name)]); $rows=$s->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows)>1) throw new RuntimeException('Denominación duplicada en '.$table.': '.$name);
    if ($rows) return (int)$rows[0];
    $columns=['Nombre','Activo',...array_keys($extra)];
    $s=$p->prepare("INSERT INTO $table (".implode(',',$columns).") OUTPUT INSERTED.[$id] VALUES (".implode(',',array_fill(0,count($columns),'?')).')');
    $s->execute([$name,1,...array_values($extra)]); $created=(int)$s->fetchColumn(); $s->closeCursor(); return $created;
}
function restoreEncoding(string $value): string {
    for($i=0;$i<2;$i++) {
        if (!preg_match('/[ÃÂ]/u',$value)) break;
        $candidate=mb_convert_encoding($value,'Windows-1252','UTF-8');
        if (!mb_check_encoding($candidate,'UTF-8') || mb_convert_encoding($candidate,'UTF-8','Windows-1252')!==$value) break;
        $value=$candidate;
    }
    return $value;
}
$p=Database::getConnection(true); if (!$p) { fwrite(STDERR,"SQL no disponible.\n"); exit(1); }
try {
    if ($p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') throw new RuntimeException('Destino distinto de V4.');
    $m=PmyoCatalog::map(); $observed=$p->query('SELECT DepartamentoOriginal AS area,CargoOriginal AS perfil,COUNT(*) AS total FROM rrhh.PadronHorarioOrigen GROUP BY DepartamentoOriginal,CargoOriginal')->fetchAll(PDO::FETCH_ASSOC);
    $counts=[]; $profiles=[];
    foreach($observed as $r) {
        $a=PmyoCatalog::key($r['area']); $c=PmyoCatalog::key($r['perfil']);
        if (!isset($m['operativo'][$a]) || !in_array($c,$m['operativo'][$a]['perfiles'],true)) throw new RuntimeException('El padrón operativo contiene una relación no incluida en el mapa: '.$a.' / '.$c);
        $counts[$a]=($counts[$a]??0)+(int)$r['total']; $profiles[$c]=true;
    }
    foreach($m['operativo'] as $a=>$item) if (($counts[$a]??0)!==$item['total']) throw new RuntimeException('Dotación de origen diferente del mapa para '.$a);
    $summary=['operativo'=>['registros'=>array_sum($counts),'areas'=>count($counts),'perfiles'=>count($profiles)],
        'catalogos_pmyo'=>['areas_operativas'=>count($m['operativo']),'areas_complementarias'=>count($m['complementario']),'perfiles_con_variantes'=>count(PmyoCatalog::profiles()),'profesiones_nuevas_definidas'=>count($m['profesiones'])],
        'reglas'=>['Sin asignaciones automáticas a personas','Sin crear departamentos o servicios supuestos','Internados, UAM y denominaciones ambiguas sin profesión automática','Variantes de origen preservadas; propuestas requieren confirmación']];
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
    if (!in_array('--aplicar',$argv,true)) { echo "Vista previa; sin cambios.\n"; exit; }
    $p->beginTransaction();
    $lock=$p->query("DECLARE @r int; EXEC @r=sys.sp_getapplock @Resource=N'CATALOGO_PMYO_V4',@LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=10000; SELECT @r");
    if ((int)$lock->fetchColumn()<0) throw new RuntimeException('Otra actualización de catálogo está en curso.'); $lock->closeCursor();
    $result=['areas'=>[],'cargos'=>[],'profesiones'=>[],'especialidades'=>[],'textos_corregidos'=>0];
    // Corrección reversible de codificación en los catálogos ya migrados; conserva sus IDs y relaciones.
    foreach(['organizacion.Sede'=>'SedeID','organizacion.Departamento'=>'DepartamentoID','organizacion.Area'=>'AreaID','organizacion.Servicio'=>'ServicioID'] as $table=>$id) {
        $rows=$p->query("SELECT [$id],Nombre FROM $table WITH (UPDLOCK,HOLDLOCK)")->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row) {
            $old=(string)$row['Nombre']; $fixed=restoreEncoding($old);
            if ($fixed===$old) continue;
            $s=$p->prepare("UPDATE $table SET Nombre=? WHERE [$id]=? AND Nombre=?"); $s->execute([$fixed,$row[$id],$old]); $result['textos_corregidos']++;
        }
    }
    foreach(PmyoCatalog::areas() as $name=>$item) $result['areas'][$name]=catalogId($p,'organizacion.Area','AreaID',$name,[
        'Codigo'=>'PMYO-A-'.substr(hash('sha256',$name),0,10),
        'Descripcion'=>$name==='SIS' ? 'Seguro Integral de Salud. Área funcional registrada en el padrón PMYO.' : 'Área funcional PMYO; denominación de origen preservada. Departamento formal pendiente cuando no exista vínculo documentado.'
    ]);
    foreach(PmyoCatalog::profiles() as $name) $result['cargos'][$name]=catalogId($p,'rrhh.Cargo','CargoID',$name,[
        'Codigo'=>'PMYO-C-'.substr(hash('sha256',$name),0,10),
        'Descripcion'=>'Perfil registrado en el padrón PMYO. No acredita por sí solo profesión, especialidad, condición laboral ni título; requiere verificación individual.'
    ]);
    foreach($m['profesiones'] as $code=>$name) {
        $s=$p->prepare('SELECT ProfesionID,Nombre FROM rrhh.Profesion WITH (UPDLOCK,HOLDLOCK) WHERE Codigo=?'); $s->execute([$code]); $old=$s->fetch(PDO::FETCH_ASSOC);
        if ($old && PmyoCatalog::key($old['Nombre'])!==PmyoCatalog::key($name)) throw new RuntimeException('Código de profesión ocupado por otra denominación: '.$code);
        $result['profesiones'][$code]=$old ? (int)$old['ProfesionID'] : catalogId($p,'rrhh.Profesion','ProfesionID',$name,['Codigo'=>$code]);
    }
    $specialties=[['MED','Cirugía general']];
    foreach($m['propuestas'] as [$code,$name]) if ($name!==null) $specialties[]=[$code,$name];
    foreach($specialties as [$code,$name]) {
        $s=$p->prepare('SELECT ProfesionID FROM rrhh.Profesion WHERE Codigo=? AND Activo=1'); $s->execute([$code]); $prof=$s->fetchAll(PDO::FETCH_COLUMN);
        if(count($prof)!==1) throw new RuntimeException('Profesión inexistente o ambigua para especialidad: '.$code);
        $s=$p->prepare('SELECT EspecialidadID FROM rrhh.Especialidad WITH (UPDLOCK,HOLDLOCK) WHERE ProfesionID=? AND Nombre=?'); $s->execute([(int)$prof[0],$name]); $old=$s->fetchAll(PDO::FETCH_COLUMN);
        if(count($old)>1) throw new RuntimeException('Especialidad duplicada: '.$name);
        if (!$old) {
            $s=$p->prepare('INSERT INTO rrhh.Especialidad (ProfesionID,Nombre,Activo) OUTPUT INSERTED.EspecialidadID VALUES (?,?,1)');
            $s->execute([(int)$prof[0],$name]); $old=[(int)$s->fetchColumn()]; $s->closeCursor();
        }
        $result['especialidades'][$code.':'.$name]=(int)$old[0];
    }
    $s=$p->prepare("SELECT TOP 1 u.UsuarioID FROM seguridad.Usuario u JOIN seguridad.UsuarioRol ur ON ur.UsuarioID=u.UsuarioID AND ur.Activo=1 JOIN seguridad.Rol r ON r.RolID=ur.RolID WHERE u.Activo=1 AND u.Bloqueado=0 AND r.Codigo='RRHH_ADMIN' AND r.SistemaID=?");
    $s->execute([SystemContext::id($p)]); $actor=$s->fetchColumn(); if(!$actor) throw new RuntimeException('Sin administrador habilitado para auditar.');
    $s=$p->prepare("INSERT INTO auditoria.EventoAuditoria (UsuarioID,SistemaID,FechaHora,Modulo,Accion,TablaAfectada,RegistroID,Descripcion) VALUES (?,?,SYSDATETIME(),'PERSONAL','CATALOGO_PMYO','organizacion.Area / rrhh.Cargo / rrhh.Profesion / rrhh.Especialidad','MAPA_PMYO_181',?)");
    $s->execute([(int)$actor,SystemContext::id($p),json_encode(['fuente'=>'Mapa explícito de RRHH contrastado con PadronHorarioOrigen','operativo'=>$summary['operativo'],'areas'=>count($result['areas']),'perfiles'=>count($result['cargos']),'especialidades'=>count($result['especialidades']),'sin_asignacion_personal'=>true,'textos_corregidos'=>$result['textos_corregidos']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $p->commit();
    echo json_encode(['resultado'=>'APLICADO','areas_del_mapa'=>count($result['areas']),'perfiles_del_mapa'=>count($result['cargos']),'profesiones_adicionales'=>count($result['profesiones']),'especialidades'=>count($result['especialidades']),'textos_corregidos'=>$result['textos_corregidos'],'personal_modificado'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch(Throwable $e) { if($p->inTransaction()) $p->rollBack(); fwrite(STDERR,'Sin cambios: '.$e->getMessage().PHP_EOL); exit(1); }
