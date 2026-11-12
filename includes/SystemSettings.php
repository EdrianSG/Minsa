<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/SystemContext.php';
/** Configuración versionada por eventos en la auditoría V4 existente; escritura solo por administración. */
class SystemSettings {
    private static array $cache=[];
    public static function defaults(): array {
        return ['tolerancia_entrada_minutos'=>5,'tardanza_maxima_minutos'=>30,
            'regla_asistencial_horas'=>150,'dias_maximos_regularizar'=>3,
            'citt_obligatorio_salud'=>true,'biometrico_modo'=>'IMPORTACION_INDEPENDIENTE',
            'hospital_nombre'=>'Hospital Distrital de Pacasmayo','red_salud'=>'Red Integrada de Salud Pacasmayo',
            'gerencia_salud'=>'Gerencia Regional de Salud La Libertad',
            'actualizado_por'=>null,'fecha_actualizacion'=>null,'vigente_desde'=>null];
    }
    public static function values(array $data): array {
        $values=[];
        foreach (['tolerancia_entrada_minutos'=>[0,60],'tardanza_maxima_minutos'=>[1,120],'dias_maximos_regularizar'=>[1,30]] as $key=>$range) {
            $v=filter_var($data[$key]??null,FILTER_VALIDATE_INT);
            if ($v===false || $v<$range[0] || $v>$range[1]) throw new InvalidArgumentException('Parámetro inválido: '.$key);
            $values[$key]=$v;
        }
        if ($values['tardanza_maxima_minutos']<$values['tolerancia_entrada_minutos']) throw new InvalidArgumentException('La tardanza máxima debe ser mayor o igual que la tolerancia.');
        if ((int)($data['regla_asistencial_horas']??150)!==150) throw new InvalidArgumentException('La meta institucional confirmada es 150 horas.');
        if (($data['biometrico_modo']??'IMPORTACION_INDEPENDIENTE')!=='IMPORTACION_INDEPENDIENTE') throw new InvalidArgumentException('Modo biométrico no implementado.');
        $citt=filter_var($data['citt_obligatorio_salud']??true,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
        if ($citt===null) throw new InvalidArgumentException('Valor CITT inválido.');
        return $values+['citt_obligatorio_salud'=>$citt];
    }
    public static function read(?string $date=null,?PDO $pdo=null): array {
        $date??=date('Y-m-d');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$d || $d->format('Y-m-d')!==$date) throw new InvalidArgumentException('Fecha de parámetros inválida.');
        $pdo??=Database::getConnection(); if (!$pdo) return self::defaults();
        $key=spl_object_id($pdo).':'.SystemContext::code().':'.$date;
        if (isset(self::$cache[$key])) return self::$cache[$key];
        static $colsChecked = null, $hasModuloCol = false, $pkCol = 'EventoAuditoriaID';
        if ($colsChecked === null) {
            try {
                $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'auditoria' AND TABLE_NAME = 'EventoAuditoria'")->fetchAll(PDO::FETCH_COLUMN);
                $hasModuloCol = in_array('Modulo', $cols, true);
                $pkCol = in_array('AuditoriaID', $cols, true) ? 'AuditoriaID' : 'EventoAuditoriaID';
                $colsChecked = true;
            } catch (Throwable $e) {
                $colsChecked = true;
            }
        }
        $moduloWhere = $hasModuloCol ? "ea.Modulo=N'ADMINISTRACION'" : "(ea.Descripcion LIKE N'[ADMINISTRACION]%' OR ea.RegistroID='PARAMETROS_RRHH')";
        $s=$pdo->prepare("SELECT TOP 1 ea.Descripcion,ea.FechaHora,u.NombreUsuario
            FROM auditoria.EventoAuditoria ea JOIN seguridad.Sistema sis ON sis.SistemaID=ea.SistemaID
            LEFT JOIN seguridad.Usuario u ON u.UsuarioID=ea.UsuarioID
            WHERE sis.Codigo=? AND $moduloWhere AND ea.Accion='CONFIGURAR_RRHH' AND ea.RegistroID='PARAMETROS_RRHH'
            AND JSON_VALUE(CASE WHEN ISJSON(ea.Descripcion)=1 THEN ea.Descripcion ELSE N'{}' END,'$.vigente_desde')<=?
            ORDER BY JSON_VALUE(CASE WHEN ISJSON(ea.Descripcion)=1 THEN ea.Descripcion ELSE N'{}' END,'$.vigente_desde') DESC,ea.FechaHora DESC,ea.$pkCol DESC");
        $s->execute([SystemContext::code(),$date]); $row=$s->fetch(PDO::FETCH_ASSOC);
        if (!$row) return self::$cache[$key]=self::defaults();
        $payload=json_decode($row['Descripcion'],true,512,JSON_THROW_ON_ERROR);
        $values=self::values($payload['valores']??[]);
        return self::$cache[$key]=array_replace(self::defaults(),$values,['actualizado_por'=>$row['NombreUsuario'],
            'fecha_actualizacion'=>$row['FechaHora'],'vigente_desde'=>$payload['vigente_desde']]);
    }
    public static function save(PDO $pdo,array $data,int $user): array {
        $values=self::values($data);
        $effective=date('Y-m-d',strtotime('tomorrow'));
        $description=json_encode(['version'=>1,'vigente_desde'=>$effective,'valores'=>$values],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (mb_strlen($description)>1000) throw new InvalidArgumentException('Configuración demasiado extensa.');
        if ($pdo->inTransaction()) throw new RuntimeException('La configuración requiere una transacción propia.');
        $pdo->beginTransaction();
        try {
            static $saveColsChecked = null, $saveHasModuloCol = false;
            if ($saveColsChecked === null) {
                $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'auditoria' AND TABLE_NAME = 'EventoAuditoria'")->fetchAll(PDO::FETCH_COLUMN);
                $saveHasModuloCol = in_array('Modulo', $cols, true);
                $saveColsChecked = true;
            }
            if ($saveHasModuloCol) {
                $s=$pdo->prepare("INSERT INTO auditoria.EventoAuditoria (UsuarioID,SistemaID,FechaHora,Modulo,Accion,TablaAfectada,RegistroID,DireccionIP,Descripcion)
                    VALUES (?,?,SYSDATETIME(),N'ADMINISTRACION','CONFIGURAR_RRHH',N'auditoria.EventoAuditoria','PARAMETROS_RRHH',?,?)");
                $s->execute([$user,SystemContext::id($pdo),$_SERVER['REMOTE_ADDR']??null,$description]);
            } else {
                $s=$pdo->prepare("INSERT INTO auditoria.EventoAuditoria (UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,DireccionIP,Descripcion)
                    VALUES (?,?,SYSDATETIME(),'CONFIGURAR_RRHH',N'auditoria.EventoAuditoria','PARAMETROS_RRHH',?,?)");
                $s->execute([$user,SystemContext::id($pdo),$_SERVER['REMOTE_ADDR']??null,'[ADMINISTRACION] '.$description]);
            }
            $pdo->commit(); self::$cache=[];
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return self::read($effective,$pdo);
    }
}
