<?php
/** Auditoría solo lectura: esquema instalado, volumen y relaciones; no imprime datos personales. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/WorkloadPolicy.php';
$pdo=Database::getConnection(true);
if (!$pdo) { fwrite(STDERR,'SQL Server no disponible.'.PHP_EOL); exit(1); }
try {
    if ($pdo->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') throw new RuntimeException('Destino distinto de V4.');
    $expected=[]; $h=fopen(__DIR__.'/../BD_HOSPITAL_V4/DICCIONARIO_DE_CAMPOS_V4.csv','r');
    $head=fgetcsv($h,0,',','"','\\');
    while (($r=fgetcsv($h,0,',','"','\\'))!==false) $expected[strtolower($r[0].'.'.$r[1])][strtolower($r[2])]=true;
    fclose($h);
    $report=['fecha'=>date(DATE_ATOM),'modo'=>'SOLO_LECTURA','bases'=>[]];
    foreach (['BD_HOSPITAL_V4','BD_HOSPITAL_V3','BD_HOSPITAL'] as $db) {
        $s=$pdo->prepare('SELECT DB_ID(?)'); $s->execute([$db]); if (!$s->fetchColumn()) continue;
        $cols=$pdo->query("SELECT s.name AS Esquema,t.name AS Tabla,c.name AS Columna,c.is_nullable AS Nullable,c.is_identity AS Identidad,ty.name AS Tipo,c.max_length AS Longitud,c.precision AS Precision,c.scale AS Escala FROM [$db].sys.tables t JOIN [$db].sys.schemas s ON s.schema_id=t.schema_id JOIN [$db].sys.columns c ON c.object_id=t.object_id JOIN [$db].sys.types ty ON ty.user_type_id=c.user_type_id WHERE t.is_ms_shipped=0 ORDER BY s.name,t.name,c.column_id")->fetchAll(PDO::FETCH_ASSOC);
        $tables=[]; foreach ($cols as $c) $tables[$c['Esquema'].'.'.$c['Tabla']][]=$c;
        $counts=[];
        foreach ($tables as $table=>$columns) {
            [$schema,$name]=explode('.',$table);
            if ($db==='BD_HOSPITAL_V4' || in_array($schema,['rrhh','seguridad','organizacion','catalogo'],true)) $counts[$table]=(int)$pdo->query("SELECT COUNT_BIG(*) FROM [$db].[$schema].[$name]")->fetchColumn();
        }
        $report['bases'][$db]=['conteos'=>$counts,'columnas'=>$cols];
    }
    $actual=[];
    foreach ($report['bases']['BD_HOSPITAL_V4']['columnas'] as $c) $actual[strtolower($c['Esquema'].'.'.$c['Tabla'])][strtolower($c['Columna'])]=true;
    $differences=[];
    foreach ($expected as $table=>$cols) foreach ($cols as $col=>$_) if (!isset($actual[$table][$col])) $differences[]=['tabla'=>$table,'columna_faltante'=>$col];
    foreach ($actual as $table=>$cols) foreach ($cols as $col=>$_) if (!isset($expected[$table][$col])) $differences[]=['tabla'=>$table,'columna_adicional'=>$col];
    $policySql=WorkloadPolicy::sql();
    $checks=[
        'especialidades_incoherentes'=>'SELECT COUNT(*) FROM rrhh.Personal p JOIN rrhh.Especialidad e ON e.EspecialidadID=p.EspecialidadID WHERE p.ProfesionID IS NULL OR e.ProfesionID IS NULL OR p.ProfesionID<>e.ProfesionID',
        'servicios_areas_incoherentes'=>'SELECT COUNT(*) FROM rrhh.AsignacionPersonal ap JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID WHERE ap.AreaID IS NULL OR s.AreaID IS NULL OR ap.AreaID<>s.AreaID',
        'documentos_duplicados'=>'SELECT COUNT(*) FROM (SELECT TipoDocumentoID,NumeroDocumento FROM rrhh.Personal WHERE TipoDocumentoID IS NOT NULL AND NumeroDocumento IS NOT NULL GROUP BY TipoDocumentoID,NumeroDocumento HAVING COUNT(*)>1) d',
        'marcas_identicas_repetidas'=>'SELECT COUNT(*) FROM (SELECT BiostarUserID,FechaHora,DispositivoID,Evento FROM rrhh.MarcacionBiometrica GROUP BY BiostarUserID,FechaHora,DispositivoID,Evento HAVING COUNT(*)>1) d',
        'asignaciones_actuales_multiples'=>'SELECT COUNT(*) FROM (SELECT PersonalID FROM rrhh.AsignacionPersonal WHERE Activo=1 AND EsActual=1 AND PersonalID IS NOT NULL GROUP BY PersonalID HAVING COUNT(*)>1) d',
        'personal_sin_documento'=>'SELECT COUNT(*) FROM rrhh.Personal WHERE NumeroDocumento IS NULL',
        'personal_sin_fecha_ingreso'=>'SELECT COUNT(*) FROM rrhh.Personal WHERE FechaIngreso IS NULL',
        'personal_sin_reglas_horarias_confirmadas'=>"SELECT COUNT(*) FROM rrhh.Personal p WHERE $policySql IS NULL",
    ];
    $checks['fks_deshabilitadas']='SELECT COUNT(*) FROM sys.foreign_keys WHERE is_disabled=1';
    $checks['fks_no_verificadas']='SELECT COUNT(*) FROM sys.foreign_keys WHERE is_not_trusted=1';
    $checks['marcas_crudas_diferentes_al_origen']='SELECT COUNT(*) FROM (SELECT MarcacionID,ImportacionID,BiostarUserID,BiostarUserName,FechaHora,DispositivoID,DispositivoNombre,Evento,EsConsecutiva,SegundosDiferencia,Advertencia FROM BD_HOSPITAL.rrhh.MarcacionBiometrica EXCEPT SELECT MarcacionBiometricaID,ImportacionBiometricaID,BiostarUserID,BiostarUserName,FechaHora,DispositivoID,DispositivoNombre,Evento,EsConsecutiva,SegundosDiferencia,Advertencia FROM rrhh.MarcacionBiometrica) d';
    foreach ($checks as $name=>$sql) $report['validaciones'][$name]=(int)$pdo->query($sql)->fetchColumn();
    $profileQueries=[
        'v3_documentos_no_nulos'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.Personal WHERE NumeroDocumento IS NOT NULL",
        'v3_codigos_bio'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.Personal WHERE CodigoEmpleado LIKE 'BIO-%'",
        'v3_codigos_coincidentes_padron'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.Personal p JOIN rrhh.PadronBiometricoOrigen b ON p.CodigoEmpleado='BIO-'+b.BiostarUserID",
        'v3_documentos_iguales_a_id_padron'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.Personal p JOIN rrhh.PadronBiometricoOrigen b ON p.NumeroDocumento=b.BiostarUserID",
        'v3_observacion_con_origen'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.Personal WHERE Observacion LIKE '%origen%' OR Observacion LIKE '%BioStar%' OR Observacion LIKE '%Excel%'",
        'v3_fecha_ingreso_igual_alta_biometrica'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.Personal p JOIN rrhh.PadronBiometricoOrigen b ON p.NumeroDocumento=b.BiostarUserID WHERE p.FechaIngreso=CAST(b.FechaInicioOriginal AS DATE)",
        'v3_asignacion_area_servicio_incoherente'=>"SELECT COUNT(*) FROM BD_HOSPITAL_V3.rrhh.AsignacionPersonal ap JOIN BD_HOSPITAL_V3.organizacion.Servicio s ON s.ServicioID=ap.ServicioID WHERE ap.AreaID IS NULL OR ap.AreaID<>s.AreaID",
        'original_vinculos_en_padron'=>"SELECT COUNT(*) FROM BD_HOSPITAL.rrhh.VinculacionBiometrica v JOIN rrhh.PadronBiometricoOrigen b ON b.BiostarUserID=v.BiostarUserID",
        'original_vinculos_tipo_dni'=>"SELECT COUNT(*) FROM BD_HOSPITAL.rrhh.VinculacionBiometrica WHERE TipoVinculacion LIKE '%DNI%'",
        'padron_ids_ambiguos'=>"SELECT COUNT(*) FROM rrhh.PadronBiometricoOrigen WHERE LEN(BiostarUserID)<>8 OR BiostarUserID LIKE '%[^0-9]%'",
    ];
    foreach ($profileQueries as $name=>$sql) $report['perfiles_origen'][$name]=(int)$pdo->query($sql)->fetchColumn();
    $report['diferencias_diccionario']=$differences;
    $path=__DIR__.'/../storage/compatibilidad_v4.json';
    if (file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('No se pudo guardar el informe local.');
    chmod($path,0600);
    $summary=['modo'=>$report['modo'],'diferencias_diccionario'=>$differences,'validaciones'=>$report['validaciones'],'perfiles_origen'=>$report['perfiles_origen']];
    foreach ($report['bases'] as $db=>$r) $summary['conteos'][$db]=array_filter($r['conteos'],fn($v)=>$v>0);
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
