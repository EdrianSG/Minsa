<?php
require_once __DIR__.'/SystemContext.php';
require_once __DIR__.'/../config/database.php';

/** Reglas aplicadas y confirmadas al asignar una pauta; no reinterpretan el grupo ocupacional. */
final class WorkloadPolicy {
    private static ?bool $hasModulo = null;
    private static ?string $pkCol = null;

    private static function detectSchema(): void {
        if (self::$hasModulo !== null && self::$pkCol !== null) return;
        try {
            $pdo = Database::getConnection();
            if ($pdo) {
                $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'auditoria' AND TABLE_NAME = 'EventoAuditoria'")->fetchAll(PDO::FETCH_COLUMN);
                self::$hasModulo = in_array('Modulo', $cols, true);
                self::$pkCol = in_array('AuditoriaID', $cols, true) ? 'AuditoriaID' : 'EventoAuditoriaID';
            } else {
                self::$hasModulo = false;
                self::$pkCol = 'EventoAuditoriaID';
            }
        } catch (Throwable $e) {
            self::$hasModulo = false;
            self::$pkCol = 'EventoAuditoriaID';
        }
    }

    public static function fromAudit(?string $description): ?string {
        $data=json_decode($description??'',true);
        $type=is_array($data)?($data['tipo_personal']??null):null;
        return in_array($type,['ASISTENCIAL','ADMINISTRATIVO'],true)?$type:null;
    }

    public static function sql(string $personal='p.PersonalID',?string $date=null): string {
        if (!preg_match('/^[a-z]+\.PersonalID$/D',$personal)) throw new InvalidArgumentException('Referencia de personal inválida.');
        $date??=date('Y-m-d');
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Fecha de reglas inválida.');
        $code=str_replace("'","''",SystemContext::code());
        $json="CASE WHEN ISJSON(wp.Descripcion)=1 THEN wp.Descripcion ELSE N'{}' END";

        self::detectSchema();
        $moduloCondition = self::$hasModulo ? "wp.Modulo=N'HORARIOS'" : "(wp.Descripcion LIKE N'[HORARIOS]%' OR wp.Descripcion LIKE N'%\"tipo_personal\"%')";
        $pk = self::$pkCol ?? 'EventoAuditoriaID';

        return "(SELECT TOP 1 JSON_VALUE($json,'$.tipo_personal') FROM auditoria.EventoAuditoria wp
            JOIN seguridad.Sistema ws ON ws.SistemaID=wp.SistemaID AND ws.Codigo=N'$code'
            WHERE $moduloCondition AND wp.Accion='ASIGNAR' AND wp.TablaAfectada=N'rrhh.Horario'
            AND wp.RegistroID=CAST($personal AS VARCHAR(100))
            AND JSON_VALUE($json,'$.fecha_inicio')<='$date'
            AND JSON_VALUE($json,'$.tipo_personal') IN ('ASISTENCIAL','ADMINISTRATIVO')
            ORDER BY JSON_VALUE($json,'$.fecha_inicio') DESC,wp.FechaHora DESC,wp.$pk DESC)";
    }
}
