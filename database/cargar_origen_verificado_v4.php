<?php
/** Carga conservadora V4. Sin --aplicar solo muestra conteos; nunca escribe en las bases de origen.
 * Traslada catálogos V3, fichas parciales del padrón y biometría original sin vínculos DNI.
 * No migra contratos, asignaciones, asistencia evaluada ni documentos/fechas laborales inferidos.
 */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/SystemContext.php';
const V4_LOAD_KEY='PADRON_Y_BIOMETRIA_ORIGEN_2026_11';
function columnsFor(PDO $pdo,string $db,string $schema,string $table): array {
    $s=$pdo->prepare("SELECT c.name,c.is_identity FROM [$db].sys.columns c JOIN [$db].sys.tables t ON t.object_id=c.object_id JOIN [$db].sys.schemas s ON s.schema_id=t.schema_id WHERE s.name=? AND t.name=? ORDER BY c.column_id");
    $s->execute([$schema,$table]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function copyVerifiedTable(PDO $pdo,string $table,string $sourceDb,string $sourceTable,array $overrides=[]): int {
    [$schema,$name]=explode('.',$table); [$srcSchema,$srcName]=explode('.',$sourceTable);
    $target=columnsFor($pdo,'BD_HOSPITAL_V4',$schema,$name);
    $source=array_column(columnsFor($pdo,$sourceDb,$srcSchema,$srcName),'name');
    $cols=[]; $expressions=[]; $identity=false;
    foreach ($target as $c) {
        $col=$c['name'];
        if (array_key_exists($col,$overrides)) $expr=$overrides[$col];
        elseif (in_array($col,$source,true)) $expr='s.['.$col.']';
        else continue;
        $cols[]='['.$col.']'; $expressions[]=$expr;
        if ($c['is_identity']) $identity=true;
    }
    if (!$cols) throw new RuntimeException('Sin columnas compatibles para '.$table);
    if ($identity) $pdo->exec("SET IDENTITY_INSERT [$schema].[$name] ON");
    try {
        $sql="INSERT INTO [$schema].[$name] (".implode(',',$cols).') SELECT '.implode(',',$expressions)." FROM [$sourceDb].[$srcSchema].[$srcName] s";
        $pdo->exec($sql);
    } finally { if ($identity) $pdo->exec("SET IDENTITY_INSERT [$schema].[$name] OFF"); }
    $sourceCount=(int)$pdo->query("SELECT COUNT_BIG(*) FROM [$sourceDb].[$srcSchema].[$srcName]")->fetchColumn();
    $targetCount=(int)$pdo->query("SELECT COUNT_BIG(*) FROM [$schema].[$name]")->fetchColumn();
    if ($sourceCount!==$targetCount) throw new RuntimeException('No coinciden los conteos de '.$table);
    return $targetCount;
}
$pdo=Database::getConnection(true);
if (!$pdo) { fwrite(STDERR,'SQL Server no disponible.'.PHP_EOL); exit(1); }
try {
    if ($pdo->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') throw new RuntimeException('Destino incorrecto.');
    $checkpoint=$pdo->prepare("SELECT COUNT(*) FROM auditoria.EventoAuditoria WHERE Accion='CARGA_ORIGEN_V4' AND RegistroID=?");
    $checkpoint->execute([V4_LOAD_KEY]);
    if ((int)$checkpoint->fetchColumn()>0) { echo "Carga ya aplicada y auditada; no se insertó ni modificó ningún registro.\n"; exit; }
    $catalogs=['catalogo.TipoDocumento','catalogo.Sexo','catalogo.EstadoCivil','organizacion.Sede',
        'organizacion.Departamento','organizacion.Area','organizacion.Servicio','rrhh.Profesion','rrhh.Cargo','rrhh.TipoContrato','rrhh.Turno'];
    $plan=['modo'=>in_array('--aplicar',$argv,true)?'APLICAR_TRANSACCION':'PREVISUALIZACION',
        'catalogos_v3'=>[], 'personal_desde_padron'=>0,'personal_sin_correspondencia_v3'=>0,'ids_especiales_pendientes'=>0,
        'datos_excluidos'=>['documentos y fechas laborales inferidos','asignaciones y contratos no conciliados','asistencia evaluada sobre vínculos DNI','usuarios y permisos históricos sin mapeo'],
        'filas_destino_existentes'=>[]];
    foreach ($catalogs as $table) $plan['catalogos_v3'][$table]=(int)$pdo->query("SELECT COUNT(*) FROM BD_HOSPITAL_V3.$table")->fetchColumn();
    $plan['personal_desde_padron']=(int)$pdo->query("SELECT COUNT(*) FROM rrhh.PadronBiometricoOrigen b JOIN BD_HOSPITAL_V3.rrhh.Personal p ON p.CodigoEmpleado='BIO-'+b.BiostarUserID WHERE LEN(b.BiostarUserID)=8 AND b.BiostarUserID NOT LIKE '%[^0-9]%'")->fetchColumn();
    $plan['personal_sin_correspondencia_v3']=(int)$pdo->query("SELECT COUNT(*) FROM rrhh.PadronBiometricoOrigen b WHERE LEN(b.BiostarUserID)=8 AND b.BiostarUserID NOT LIKE '%[^0-9]%' AND NOT EXISTS(SELECT 1 FROM BD_HOSPITAL_V3.rrhh.Personal p WHERE p.CodigoEmpleado='BIO-'+b.BiostarUserID)")->fetchColumn();
    $plan['ids_especiales_pendientes']=(int)$pdo->query("SELECT COUNT(*) FROM rrhh.PadronBiometricoOrigen WHERE LEN(BiostarUserID)<>8 OR BiostarUserID LIKE '%[^0-9]%'")->fetchColumn();
    $plan['marcas_originales']=(int)$pdo->query('SELECT COUNT(*) FROM BD_HOSPITAL.rrhh.MarcacionBiometrica')->fetchColumn();
    $plan['importaciones_originales']=(int)$pdo->query('SELECT COUNT(*) FROM BD_HOSPITAL.rrhh.ImportacionBiometrica')->fetchColumn();
    $plan['incidencias_originales']=(int)$pdo->query('SELECT COUNT(*) FROM BD_HOSPITAL.rrhh.IncidenciaBiometrica')->fetchColumn();
    $plan['marcas_identicas_en_origen']=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT BiostarUserID,FechaHora,DispositivoID,Evento FROM BD_HOSPITAL.rrhh.MarcacionBiometrica GROUP BY BiostarUserID,FechaHora,DispositivoID,Evento HAVING COUNT(*)>1) d')->fetchColumn();
    $tables=[...$catalogs,'rrhh.Personal','rrhh.VinculacionBiometrica','rrhh.ImportacionBiometrica','rrhh.MarcacionBiometrica','rrhh.IncidenciaBiometrica','rrhh.TipoPermisoLaboral'];
    foreach ($tables as $table) $plan['filas_destino_existentes'][$table]=(int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
    if (!in_array('--aplicar',$argv,true)) exit;
    if ($plan['personal_sin_correspondencia_v3'] || $plan['marcas_identicas_en_origen']) throw new RuntimeException('Correspondencias o marcas repetidas requieren conciliación antes de cargar.');
    if (!$plan['personal_desde_padron']) throw new RuntimeException('No hay fichas verificables.');
    if (array_sum($plan['filas_destino_existentes'])) throw new RuntimeException('Las tablas de destino no están vacías; requiere plan de conciliación, sin sobrescribir.');
    $pdo->beginTransaction();
    $lock=$pdo->query("DECLARE @r int; EXEC @r=sys.sp_getapplock @Resource=N'CARGA_ORIGEN_V4',@LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=10000; SELECT @r");
    if ((int)$lock->fetchColumn()<0) throw new RuntimeException('Otra carga está en curso.'); $lock->closeCursor();
    foreach ($tables as $table) if ((int)$pdo->query("SELECT COUNT(*) FROM $table WITH (UPDLOCK,HOLDLOCK)")->fetchColumn()>0) throw new RuntimeException('El destino cambió durante la revisión.');
    $counts=[];
    foreach ($catalogs as $table) $counts[$table]=copyVerifiedTable($pdo,$table,'BD_HOSPITAL_V3',$table);
    $counts['rrhh.TipoPermisoLaboral']=copyVerifiedTable($pdo,'rrhh.TipoPermisoLaboral','BD_HOSPITAL','rrhh.TipoPermisoLaboral',['Activo'=>'s.Estado']);
    $pdo->exec('SET IDENTITY_INSERT rrhh.Personal ON');
    try {
        $pdo->exec("INSERT INTO rrhh.Personal (PersonalID,CodigoEmpleado,NombreCompletoOriginal,DepartamentoOrigen,CargoOrigen,Celular,Correo,FechaAltaBiometrica,FechaVencimientoBiometrico,Activo,Observacion)
            SELECT p.PersonalID,p.CodigoEmpleado,b.NombreOriginal,b.DepartamentoOriginal,b.CargoOriginal,b.TelefonoOriginal,b.CorreoOriginal,b.FechaInicioOriginal,b.FechaFinOriginal,NULL,
                N'Ficha parcial desde padrón original; identidad documental, situación y datos laborales pendientes de confirmación.'
            FROM rrhh.PadronBiometricoOrigen b JOIN BD_HOSPITAL_V3.rrhh.Personal p ON p.CodigoEmpleado='BIO-'+b.BiostarUserID
            WHERE LEN(b.BiostarUserID)=8 AND b.BiostarUserID NOT LIKE '%[^0-9]%'");
    } finally { $pdo->exec('SET IDENTITY_INSERT rrhh.Personal OFF'); }
    $pdo->exec("INSERT INTO rrhh.VinculacionBiometrica (BiostarUserID,PersonalID,TipoVinculacion,Observacion,FechaCreacion)
        SELECT b.BiostarUserID,p.PersonalID,'PADRON_ORIGEN',N'Identificador BioStar exacto del padrón de origen; no constituye documento de identidad.',SYSDATETIME()
        FROM rrhh.PadronBiometricoOrigen b JOIN rrhh.Personal p ON p.CodigoEmpleado='BIO-'+b.BiostarUserID");
    $counts['rrhh.Personal']=(int)$pdo->query('SELECT COUNT(*) FROM rrhh.Personal')->fetchColumn();
    $counts['rrhh.VinculacionBiometrica']=(int)$pdo->query('SELECT COUNT(*) FROM rrhh.VinculacionBiometrica')->fetchColumn();
    if ($counts['rrhh.Personal']!==$plan['personal_desde_padron'] || $counts['rrhh.VinculacionBiometrica']!==$counts['rrhh.Personal']) throw new RuntimeException('No coinciden fichas y vínculos.');
    $counts['rrhh.ImportacionBiometrica']=copyVerifiedTable($pdo,'rrhh.ImportacionBiometrica','BD_HOSPITAL','rrhh.ImportacionBiometrica',[
        'ImportacionBiometricaID'=>'s.ImportacionID','UsuarioID'=>'NULL']);
    $counts['rrhh.MarcacionBiometrica']=copyVerifiedTable($pdo,'rrhh.MarcacionBiometrica','BD_HOSPITAL','rrhh.MarcacionBiometrica',[
        'MarcacionBiometricaID'=>'s.MarcacionID','ImportacionBiometricaID'=>'s.ImportacionID',
        'PersonalID'=>'(SELECT v.PersonalID FROM rrhh.VinculacionBiometrica v WHERE v.BiostarUserID=s.BiostarUserID)',
        'EstadoVinculacion'=>"CASE WHEN EXISTS(SELECT 1 FROM rrhh.VinculacionBiometrica v WHERE v.BiostarUserID=s.BiostarUserID) THEN 'VINCULADO' ELSE 'PENDIENTE_VINCULACION' END",
        'EstadoProcesamiento'=>"'PENDIENTE'"]);
    $counts['rrhh.IncidenciaBiometrica']=copyVerifiedTable($pdo,'rrhh.IncidenciaBiometrica','BD_HOSPITAL','rrhh.IncidenciaBiometrica',[
        'IncidenciaBiometricaID'=>'s.IncidenciaID','ImportacionBiometricaID'=>'s.ImportacionID','MarcacionBiometricaID'=>'s.MarcacionID']);
    $unknown=(int)$pdo->query('SELECT COUNT(*) FROM rrhh.MarcacionBiometrica WHERE PersonalID IS NULL')->fetchColumn();
    $actor=$pdo->query("SELECT TOP 1 u.UsuarioID FROM seguridad.Usuario u JOIN seguridad.UsuarioRol ur ON ur.UsuarioID=u.UsuarioID JOIN seguridad.Rol r ON r.RolID=ur.RolID WHERE u.Activo=1 AND u.Bloqueado=0 AND r.Codigo='RRHH_ADMIN' AND ur.Activo=1 ORDER BY u.UsuarioID")->fetchColumn();
    if (!$actor) throw new RuntimeException('No existe administrador habilitado para auditar.');
    $s=$pdo->prepare('INSERT INTO auditoria.EventoAuditoria (UsuarioID,SistemaID,FechaHora,Modulo,Accion,RegistroID,Descripcion) VALUES (?,?,SYSDATETIME(),?,?,?,?)');
    $s->execute([(int)$actor,SystemContext::id($pdo),'MIGRACION','CARGA_ORIGEN_V4',V4_LOAD_KEY,'Carga de fichas parciales y biometría desde fuentes existentes. Sin DNI ni datos laborales inferidos. PK preservadas; vínculos por ID BioStar de padrón. Estados de procesamiento pendientes; actores históricos sin equivalencia quedan NULL.']);
    $pdo->commit();
    echo json_encode(['resultado'=>'APLICADO','conteos'=>$counts,'marcas_sin_vinculo'=>$unknown,'origen'=>'SIN_CAMBIOS','asistencia_evaluada'=>'NO_MIGRADA; requiere homologación laboral y programación'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'Carga no aplicada: '.$e->getMessage().PHP_EOL); exit(1);
}
