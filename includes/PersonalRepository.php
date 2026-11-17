<?php
/** Escrituras V4: ausencia de dato se conserva como NULL; relaciones por ID. */
class PersonalRelationConflict extends RuntimeException {}

class PersonalRepository {
    public const FIELDS = [
        'activo'=>'Activo', 'codigo_empleado'=>'CodigoEmpleado', 'tipo_documento_id'=>'TipoDocumentoID', 'dni'=>'NumeroDocumento',
        'nombres'=>'Nombres', 'apellido_paterno'=>'ApellidoPaterno', 'apellido_materno'=>'ApellidoMaterno',
        'fecha_nacimiento'=>'FechaNacimiento', 'sexo_id'=>'SexoID', 'estado_civil_id'=>'EstadoCivilID',
        'celular'=>'Celular', 'correo'=>'Correo', 'telefono'=>'Telefono', 'fecha_ingreso'=>'FechaIngreso',
        'profesion_id'=>'ProfesionID', 'especialidad_id'=>'EspecialidadID', 'sede_id'=>'SedeID',
        'grupo_ocupacional'=>'GrupoOcupacional', 'regimen_laboral'=>'RegimenLaboral', 'condicion_laboral'=>'CondicionLaboral'
    ];
    public static function normalize(array $data): array {
        $out = [];
        foreach (self::FIELDS + ['departamento_id'=>'', 'area_id'=>'', 'servicio_id'=>'', 'cargo_id'=>'', 'tipo_contrato_id'=>'',
            'numero_contrato'=>'', 'contrato_inicio'=>'', 'contrato_fin'=>'', 'fecha_asignacion'=>'', 'fecha_fin_asignacion'=>'', 'motivo_asignacion'=>''] as $key=>$column) {
            if (!array_key_exists($key, $data)) continue;
            if (!is_scalar($data[$key]) && $data[$key] !== null) throw new InvalidArgumentException('Campo inválido: ' . $key);
            if ($key === 'activo') {
                if (!in_array($data[$key],[null,'',true,false,0,1,'0','1'],true)) throw new InvalidArgumentException('Estado laboral inválido.');
                $out[$key]=($data[$key] === null || $data[$key] === '') ? null : (int)(bool)$data[$key];
                continue;
            }
            $value = trim((string)($data[$key] ?? ''));
            $out[$key] = $value === '' ? null : $value;
            if ($out[$key] === null) continue;
            if (str_ends_with($key, '_id')) {
                if (!ctype_digit($value) || (int)$value <= 0) throw new InvalidArgumentException('Identificador inválido: ' . $key);
                $out[$key] = (int)$value;
            }
            if (str_starts_with($key, 'fecha_') || in_array($key, ['contrato_inicio', 'contrato_fin'], true)) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Fecha inválida: ' . $key);
            }
        }
        foreach (['codigo_empleado'=>30,'dni'=>20,'nombres'=>100,'apellido_paterno'=>100,'apellido_materno'=>100,
            'celular'=>30,'telefono'=>30,'correo'=>150,'regimen_laboral'=>30,'condicion_laboral'=>30,
            'nombre_completo_original'=>250,'numero_contrato'=>50,'grupo_ocupacional'=>30,'motivo_asignacion'=>200] as $key=>$max) {
            if (isset($out[$key]) && mb_strlen($out[$key]) > $max) throw new InvalidArgumentException('Longitud excedida: ' . $key);
        }
        if (!empty($out['correo']) && !filter_var($out['correo'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo inválido.');
        if (!empty($out['contrato_inicio']) && !empty($out['contrato_fin']) && $out['contrato_fin'] < $out['contrato_inicio']) throw new InvalidArgumentException('Vigencia de contrato inválida.');

        // Normalización compatible con CHECK constraints de BD_HOSPITAL_V3
        if (isset($out['regimen_laboral'])) {
            $regUpper = mb_strtoupper(trim($out['regimen_laboral']), 'UTF-8');
            if (in_array($regUpper,['ADMINISTRATIVO','ASISTENCIAL'],true)) {
                // Compatibilidad con el formulario anterior sin inferir un régimen legal.
                if (isset($data['tipo_personal']) && trim((string)$data['tipo_personal'])!==$regUpper) {
                    throw new InvalidArgumentException('La clasificación de personal recibida es contradictoria.');
                }
                $out['tipo_personal']=$regUpper;
                unset($out['regimen_laboral']);
            } elseif (in_array($regUpper, ['276', 'D.L. 276', 'D.L 276', 'DL276'], true)) {
                $out['regimen_laboral'] = 'DL 276';
            } elseif (in_array($regUpper, ['728', 'D.L. 728', 'D.L 728', 'DL728'], true)) {
                $out['regimen_laboral'] = 'DL 728';
            } elseif (in_array($regUpper, ['1057', 'CAS', 'DL 1057', 'D.L. 1057', 'DL 1057 CAS'], true)) {
                $out['regimen_laboral'] = 'DL 1057 CAS';
            } elseif (in_array($regUpper, ['1153', 'DL 1153', 'D.L. 1153'], true)) {
                $out['regimen_laboral'] = 'DL 1153';
            }
        }
        if (isset($out['condicion_laboral'])) {
            $condUpper = mb_strtoupper(trim($out['condicion_laboral']), 'UTF-8');
            if (str_contains($condUpper, 'NOMB')) $out['condicion_laboral'] = 'NOMBRADO';
            elseif (str_contains($condUpper, 'CAS')) $out['condicion_laboral'] = 'CAS';
            elseif (str_contains($condUpper, 'DESTAC')) $out['condicion_laboral'] = 'DESTACADO';
            elseif (str_contains($condUpper, 'CONTRAT')) $out['condicion_laboral'] = 'CONTRATADO';
        }
        if (isset($out['grupo_ocupacional'])) {
            $grupUpper = mb_strtoupper(trim($out['grupo_ocupacional']), 'UTF-8');
            if (str_contains($grupUpper, 'ADMIN')) $out['grupo_ocupacional'] = 'ADMINISTRATIVO';
            elseif (str_contains($grupUpper, 'PROF')) $out['grupo_ocupacional'] = 'PROFESIONAL';
            elseif (str_contains($grupUpper, 'TEC')) $out['grupo_ocupacional'] = 'TECNICO';
            elseif (str_contains($grupUpper, 'AUX')) $out['grupo_ocupacional'] = 'AUXILIAR';
        }
        if (array_key_exists('tipo_personal',$data)) {
            if (!is_scalar($data['tipo_personal']) && $data['tipo_personal']!==null) throw new InvalidArgumentException('Clasificación inválida.');
            $type=mb_strtoupper(trim((string)($data['tipo_personal']??'')),'UTF-8');
            if (!in_array($type,['','ASISTENCIAL','ADMINISTRATIVO'],true)) throw new InvalidArgumentException('Clasificación de personal inválida.');
            $out['tipo_personal']=$type===''?null:$type;
        }
        return $out;
    }
    public static function validateRelations(PDO $pdo, array $d): void {
        $refs = ['tipo_documento_id'=>['catalogo.TipoDocumento','TipoDocumentoID'], 'sexo_id'=>['catalogo.Sexo','SexoID'],
            'estado_civil_id'=>['catalogo.EstadoCivil','EstadoCivilID'], 'sede_id'=>['organizacion.Sede','SedeID'],
            'profesion_id'=>['rrhh.Profesion','ProfesionID'], 'especialidad_id'=>['rrhh.Especialidad','EspecialidadID'],
            'departamento_id'=>['organizacion.Departamento','DepartamentoID'], 'area_id'=>['organizacion.Area','AreaID'], 'servicio_id'=>['organizacion.Servicio','ServicioID'],
            'cargo_id'=>['rrhh.Cargo','CargoID'], 'tipo_contrato_id'=>['rrhh.TipoContrato','TipoContratoID']];
        foreach ($refs as $key=>[$table,$column]) {
            if (!isset($d[$key])) continue;
            $s = $pdo->prepare("SELECT $column FROM $table WHERE $column = ?"); $s->execute([$d[$key]]);
            if ($s->fetchColumn() === false) throw new InvalidArgumentException('Referencia inexistente: ' . $key);
        }
        foreach ([['especialidad_id','profesion_id','rrhh.Especialidad','EspecialidadID','ProfesionID'],
                  ['servicio_id','area_id','organizacion.Servicio','ServicioID','AreaID']] as [$child,$parent,$table,$pk,$fk]) {
            if (!isset($d[$child])) continue;
            $s=$pdo->prepare("SELECT $fk FROM $table WHERE $pk = ?"); $s->execute([$d[$child]]); $actual=$s->fetchColumn();
            if (!isset($d[$parent]) || $actual === false || $actual === null || (int)$actual !== $d[$parent]) {
                throw new InvalidArgumentException("La relación $child / $parent debe seleccionarse explícitamente y coincidir.");
            }
        }
        if (isset($d['departamento_id'])) {
            if (!isset($d['area_id'])) throw new InvalidArgumentException('El departamento se vincula mediante un área; seleccione el área correspondiente.');
            $s=$pdo->prepare('SELECT DepartamentoID FROM organizacion.Area WHERE AreaID=?'); $s->execute([$d['area_id']]);
            $department=$s->fetchColumn();
            if ($department===false || $department===null || (int)$department!==(int)$d['departamento_id']) throw new InvalidArgumentException('El área no pertenece al departamento seleccionado.');
        }
        if (isset($d['dni'])) {
            if (!isset($d['tipo_documento_id'])) throw new InvalidArgumentException('Indique el tipo del documento proporcionado.');
            $s = $pdo->prepare('SELECT Codigo FROM catalogo.TipoDocumento WHERE TipoDocumentoID = ?');
            $s->execute([$d['tipo_documento_id']]);
            $codigoDoc = $s->fetchColumn();
            if ($codigoDoc === false) throw new InvalidArgumentException('Tipo de documento inexistente.');
            if ($codigoDoc === 'DNI' && (!ctype_digit($d['dni']) || strlen($d['dni']) !== 8)) {
                throw new InvalidArgumentException('El DNI debe contener exactamente 8 dígitos numéricos.');
            }
        }
    }
    public static function save(PDO $pdo, array $input, int $actor, ?int $id = null): array {
        $d=self::normalize($input);
        $pdo->beginTransaction();
        try {
            if ($id !== null) {
                $s=$pdo->prepare('SELECT * FROM rrhh.Personal WITH (UPDLOCK, HOLDLOCK) WHERE PersonalID = ?'); $s->execute([$id]);
                $old=$s->fetch(PDO::FETCH_ASSOC);
                if (!$old) throw new InvalidArgumentException('Trabajador inexistente.');
                if (array_key_exists('version_ficha',$input)) {
                    $expected=filter_var($input['version_ficha'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
                    if ($expected===false) throw new InvalidArgumentException('Versión de ficha inválida.');
                    require_once __DIR__.'/SystemContext.php';
                    $v=$pdo->prepare("SELECT COALESCE(MAX(EventoAuditoriaID),0) FROM auditoria.EventoAuditoria WHERE SistemaID=? AND TablaAfectada=N'rrhh.Personal' AND RegistroID=? AND Accion IN ('INSERT','UPDATE')");
                    $v->execute([SystemContext::id($pdo),(string)$id]);
                    if ((int)$v->fetchColumn()!==$expected) throw new PersonalRelationConflict('La ficha fue editada por otro usuario. Recárguela antes de guardar.');
                }

                $merged=[]; foreach (self::FIELDS as $key=>$column) $merged[$key]=$old[$column]!==null && str_ends_with($key,'_id') ? (int)$old[$column] : $old[$column];
                $merged=array_replace($merged,$d);
            } else $merged=$d;
            if ($id === null && empty($merged['sede_id'])) {
                if (!empty($input['area_id'])) {
                    $s = $pdo->prepare('SELECT d.SedeID FROM organizacion.Area a INNER JOIN organizacion.Departamento d ON a.DepartamentoID = d.DepartamentoID WHERE a.AreaID = ?');
                    $s->execute([(int)$input['area_id']]);
                    $sede = $s->fetchColumn();
                    if ($sede !== false && $sede !== null) { $d['sede_id'] = (int)$sede; $merged['sede_id'] = (int)$sede; }
                }
                if (empty($merged['sede_id'])) {
                    $s = $pdo->query('SELECT TOP 1 SedeID FROM organizacion.Sede WHERE Activo = 1 ORDER BY SedeID');
                    $sede = $s ? $s->fetchColumn() : null;
                    if ($sede !== false && $sede !== null) { $d['sede_id'] = (int)$sede; $merged['sede_id'] = (int)$sede; }
                }
            }
            if ($id === null && empty($merged['fecha_ingreso'])) {
                $today = date('Y-m-d');
                $d['fecha_ingreso'] = $today;
                $merged['fecha_ingreso'] = $today;
            }
            if ($id === null && empty($merged['tipo_documento_id'])) {
                $d['tipo_documento_id'] = 1;
                $merged['tipo_documento_id'] = 1;
            }
            if ($id === null && empty($merged['codigo_empleado'])) {
                $code = !empty($merged['dni']) ? 'EMP-' . $merged['dni'] : 'EMP-' . date('YmdHis');
                $d['codigo_empleado'] = $code;
                $merged['codigo_empleado'] = $code;
            }
            if ($id === null && empty($merged['dni']) && !empty($merged['codigo_empleado'])) {
                $d['dni'] = $merged['codigo_empleado'];
                $merged['dni'] = $merged['codigo_empleado'];
            }
            if (empty($merged['dni']) && empty($merged['nombres']) && empty($merged['apellido_paterno']) && empty($merged['nombre_completo_original']) && empty($merged['codigo_empleado'])) {
                throw new InvalidArgumentException('Proporcione al menos un dato real que permita reconocer la ficha.');
            }
            self::validateRelations($pdo,array_diff_key($merged,array_flip(['departamento_id','area_id','servicio_id','cargo_id'])));
            $fields=[]; $values=[];
            foreach (self::FIELDS as $key=>$column) if (array_key_exists($key,$d)) { $fields[]=$column; $values[]=$d[$key]; }
            if ($id === null) {
                $id=self::insertIdentity($pdo,'INSERT INTO rrhh.Personal ('.implode(',',$fields).',FechaRegistro) VALUES ('.implode(',',array_fill(0,count($values),'?')).',SYSDATETIME())',$values);
            } elseif ($fields) {
                $s=$pdo->prepare('UPDATE rrhh.Personal SET '.implode(',',array_map(fn($f)=>$f.' = ?',$fields)).', FechaActualizacion=SYSDATETIME() WHERE PersonalID = ?');
                $s->execute([...$values,$id]);
            }
            $assignment=self::saveAssignment($pdo,$id,$input,$d);
            if (!empty($input['registrar_contrato'])) {
                $contractFields=['tipo_contrato_id'=>'TipoContratoID','numero_contrato'=>'NumeroContrato','contrato_inicio'=>'FechaInicio','contrato_fin'=>'FechaFin'];
                $contractId=filter_var($input['contrato_id']??null,FILTER_VALIDATE_INT);
                if ($contractId) {
                    $s=$pdo->prepare('SELECT ContratoID,FechaInicio,FechaFin FROM rrhh.Contrato WITH (UPDLOCK,HOLDLOCK) WHERE ContratoID=? AND PersonalID=?');
                    $s->execute([$contractId,$id]);
                    $oldContract=$s->fetch(PDO::FETCH_ASSOC);
                    if (!$oldContract) throw new InvalidArgumentException('Contrato ajeno o inexistente.');
                    $start=array_key_exists('contrato_inicio',$d) ? $d['contrato_inicio'] : $oldContract['FechaInicio'];
                    $end=array_key_exists('contrato_fin',$d) ? $d['contrato_fin'] : $oldContract['FechaFin'];
                    if ($start && $end && $end<$start) throw new InvalidArgumentException('Vigencia de contrato inválida.');
                    $columns=[]; $params=[];
                    foreach ($contractFields as $key=>$col) if (array_key_exists($key,$d)) { $columns[]=$col.'=?'; $params[]=$d[$key]; }
                    if ($columns) {
                        $s=$pdo->prepare('UPDATE rrhh.Contrato SET '.implode(',',$columns).' WHERE ContratoID=? AND PersonalID=?');
                        $s->execute([...$params,$contractId,$id]);
                    }
                } else {
                    if (!array_filter(array_intersect_key($d,$contractFields),fn($v)=>$v!==null)) throw new InvalidArgumentException('Proporcione información real del contrato.');
                    if (!empty($d['numero_contrato'])) {
                        $s=$pdo->prepare('SELECT ContratoID FROM rrhh.Contrato WITH (UPDLOCK,HOLDLOCK) WHERE PersonalID=? AND NumeroContrato=?');
                        $s->execute([$id,$d['numero_contrato']]);
                        if ($s->fetchColumn()!==false) throw new InvalidArgumentException('El contrato ya está registrado para este trabajador.');
                    }
                    $s=$pdo->prepare('INSERT INTO rrhh.Contrato (PersonalID,TipoContratoID,NumeroContrato,FechaInicio,FechaFin,Activo) VALUES (?,?,?,?,?,1)');
                    $s->execute([$id,$d['tipo_contrato_id']??null,$d['numero_contrato']??null,$d['contrato_inicio']??null,$d['contrato_fin']??null]);
                }
            }
            require_once __DIR__.'/auditoria.php';
            $audit=['profesion_antes'=>$old['ProfesionID']??null,'profesion_despues'=>$merged['profesion_id']??null,
                'especialidad_antes'=>$old['EspecialidadID']??null,'especialidad_despues'=>$merged['especialidad_id']??null,'relacion'=>$assignment];
            if (array_key_exists('tipo_personal',$d)) {
                $audit['tipo_personal_confirmado']=true;
                $audit['tipo_personal']=$d['tipo_personal'];
                // Fecha de confirmación de la clasificación, independiente del ingreso y del contrato.
                $audit['fecha_inicio']=date('Y-m-d');
            }
            $description=json_encode($audit,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if (mb_strlen($description,'UTF-8')>989) throw new InvalidArgumentException('El detalle de auditoría es demasiado extenso; reduzca el motivo de la modificación.');
            if (!Auditoria::registrar($actor,'PERSONAL',isset($old)?'UPDATE':'INSERT','rrhh.Personal',(string)$id,$description)) throw new RuntimeException('No se pudo auditar la ficha.');
            $pdo->commit();
            return ['success'=>true,'ok'=>true,'message'=>'Ficha guardada.','data'=>['personal_id'=>$id]];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    /** Identidad de esta inserción y ámbito, compatible con PDO ODBC y triggers de auditoría. */
    private static function insertIdentity(PDO $pdo,string $insert,array $values): int {
        $stmt=$pdo->prepare($insert . '; SELECT CAST(SCOPE_IDENTITY() AS int) AS inserted_id;');
        $stmt->execute($values);
        try {
            do {
                if ($stmt->columnCount()===0) continue;
                $row=$stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row) && array_key_exists('inserted_id',$row)) {
                    $id=filter_var($row['inserted_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    if ($id===false) throw new RuntimeException('No se obtuvo una identidad válida al guardar.');
                    return $id;
                }
            } while ($stmt->nextRowset());
            throw new RuntimeException('SQL Server no devolvió la identidad del registro.');
        } finally {
            $stmt->closeCursor();
        }
    }

    /** Corrige una relación o registra una vigencia nueva, sin confundir ambas operaciones. */
    private static function saveAssignment(PDO $pdo,int $personal,array $input,array $data): array {
        $keys=['area_id'=>'AreaID','servicio_id'=>'ServicioID','cargo_id'=>'CargoID','fecha_asignacion'=>'FechaInicio'];
        $hasData=(bool)array_intersect_key($data,$keys);
        $action=(string)($input['accion_asignacion']??($hasData?'corregir':'conservar'));
        if (!in_array($action,['conservar','corregir','trasladar','finalizar'],true)) throw new InvalidArgumentException('Acción de asignación inválida.');
        if ($action==='conservar') return ['accion'=>'conservar'];
        $s=$pdo->prepare('SELECT AsignacionPersonalID,AreaID,ServicioID,CargoID,FechaInicio,FechaFin FROM rrhh.AsignacionPersonal WITH (UPDLOCK,HOLDLOCK) WHERE PersonalID=? AND EsActual=1 AND Activo=1');
        $s->execute([$personal]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>1) throw new PersonalRelationConflict('Hay varias asignaciones actuales; requiere conciliación.');
        $current=$rows[0]??null;
        if (array_key_exists('asignacion_actual_id',$input)) {
            $expected=filter_var($input['asignacion_actual_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if ($expected===false) throw new InvalidArgumentException('Versión de asignación inválida.');
            if ($expected!==(int)($current['AsignacionPersonalID']??0)) throw new PersonalRelationConflict('La asignación cambió desde que abrió la ficha. Recárguela antes de guardar.');
        }
        $before=[]; foreach($keys as $key=>$column) {
            $v=$current[$column]??null;
            $before[$key]=$v!==null && str_ends_with($key,'_id')?(int)$v:($v!==null?substr((string)$v,0,10):null);
        }
        $after=array_replace($before,array_intersect_key($data,$keys));
        if ($current && $action==='corregir' && $before===$after) return ['accion'=>'sin_cambios','asignacion_id'=>(int)$current['AsignacionPersonalID']];
        $reason=$data['motivo_asignacion']??null;
        if ($current && !$reason) throw new InvalidArgumentException('Indique el motivo de la corrección, traslado o finalización.');
        if ($action==='finalizar') {
            if (!$current) throw new InvalidArgumentException('El trabajador no tiene una asignación actual para finalizar.');
            $end=$data['fecha_fin_asignacion']??null;
            if (!$end || ($before['fecha_asignacion'] && $end<$before['fecha_asignacion'])) throw new InvalidArgumentException('Fecha de fin de asignación inválida.');
            $s=$pdo->prepare('UPDATE rrhh.AsignacionPersonal SET EsActual=0,FechaFin=? WHERE AsignacionPersonalID=?'); $s->execute([$end,$current['AsignacionPersonalID']]);
            return ['accion'=>$action,'asignacion_id'=>(int)$current['AsignacionPersonalID'],'antes'=>$before,'fecha_fin'=>$end,'motivo'=>$reason];
        }
        self::validateRelations($pdo,$after+array_intersect_key($data,['departamento_id'=>true]));
        if ($after['area_id']===null && $after['servicio_id']===null && $after['cargo_id']===null) {
            if (!$current && $action==='corregir') return ['accion'=>'sin_asignacion'];
            throw new InvalidArgumentException('Indique área o cargo. Para retirar la relación actual seleccione Finalizar asignación.');
        }
        if ($after['area_id'] === null) {
            throw new InvalidArgumentException('Debe seleccionar el área institucional para la asignación.');
        }
        if ($after['cargo_id'] === null) {
            throw new InvalidArgumentException('Debe seleccionar el cargo funcional para la asignación.');
        }
        if (empty($after['fecha_asignacion'])) {
            $after['fecha_asignacion'] = $data['fecha_ingreso'] ?? date('Y-m-d');
        }
        if ($action==='trasladar') {
            $start=$data['fecha_asignacion']??null;
            if (!$start || ($before['fecha_asignacion'] && $start<=$before['fecha_asignacion'])) throw new InvalidArgumentException('El traslado requiere una vigencia posterior a la asignación actual.');
            if ($current) {
                $s=$pdo->prepare('UPDATE rrhh.AsignacionPersonal SET EsActual=0,FechaFin=CASE WHEN FechaFin IS NULL OR FechaFin>=? THEN DATEADD(day,-1,CAST(? AS date)) ELSE FechaFin END WHERE AsignacionPersonalID=?');
                $s->execute([$start,$start,$current['AsignacionPersonalID']]);
            }
        }
        if ($current && $action==='corregir') {
            if ($after['fecha_asignacion'] && $after['fecha_asignacion']!==$before['fecha_asignacion']) {
                $s=$pdo->prepare('SELECT COUNT(*) FROM rrhh.AsignacionPersonal WHERE PersonalID=? AND AsignacionPersonalID<>? AND Activo=1 AND (FechaFin IS NULL OR FechaFin>=?)');
                $s->execute([$personal,$current['AsignacionPersonalID'],$after['fecha_asignacion']]);
                if ((int)$s->fetchColumn()>0) throw new InvalidArgumentException('La fecha corregida se superpone con otra vigencia.');
            }
            $s=$pdo->prepare('UPDATE rrhh.AsignacionPersonal SET AreaID=?,ServicioID=?,CargoID=?,FechaInicio=? WHERE AsignacionPersonalID=?');
            $s->execute([$after['area_id'],$after['servicio_id'],$after['cargo_id'],$after['fecha_asignacion'],$current['AsignacionPersonalID']]);
            $assignment=(int)$current['AsignacionPersonalID'];
        } else {
            $assignment=self::insertIdentity($pdo,'INSERT INTO rrhh.AsignacionPersonal (PersonalID,AreaID,ServicioID,CargoID,FechaInicio,EsActual,Activo) VALUES (?,?,?,?,?,1,1)',
                [$personal,$after['area_id'],$after['servicio_id'],$after['cargo_id'],$after['fecha_asignacion']]);
        }
        return ['accion'=>$action,'asignacion_id'=>$assignment,'antes'=>$before,'despues'=>$after,'motivo'=>$reason];
    }

}
