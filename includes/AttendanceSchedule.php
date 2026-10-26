<?php
require_once __DIR__ . '/SnapshotStore.php';
require_once __DIR__ . '/TurnoCatalog.php';
require_once __DIR__ . '/../config/database.php';

/** El rol mensual SQL prevalece sobre la pauta semanal; archivos solo sin conexión. */
class AttendanceSchedule {
    private static array $sqlMonths = [];
    private static array $sqlCatalogs = [];

    public static function monthly(int $personalId, string $date, ?int $serviceId = null): ?array {
        $year = (int)substr($date, 0, 4); $month = (int)substr($date, 5, 2); $day = (int)substr($date, 8, 2);
        $pdo = Database::getConnection();
        if ($pdo) {
            $connection = spl_object_id($pdo);
            $key = "$connection:$year:$month";
            if (!array_key_exists($key, self::$sqlMonths)) {
                $stmt = $pdo->prepare('SELECT PersonalID, Dia, CodigoTurno FROM rrhh.ProgramacionMensualDetalle WHERE Anio = ? AND Mes = ?');
                $stmt->execute([$year,$month]);
                $rows = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int)$row['PersonalID']][(int)$row['Dia']] = $row['CodigoTurno'];
                self::$sqlMonths[$key] = $rows;
            }
            $code = self::$sqlMonths[$key][$personalId][$day] ?? null;
            if ($code === null) return null;
            if (!isset(self::$sqlCatalogs[$connection])) self::$sqlCatalogs[$connection] = TurnoCatalog::load($pdo);
            return self::resolve($code, self::$sqlCatalogs[$connection]);
        }
        $keys = $serviceId > 0 ? ["programacion_serv_{$serviceId}_{$year}_{$month}"] : [];
        $keys[] = "programacion_serv_0_{$year}_{$month}";
        foreach ($keys as $key) {
            $snapshot = SnapshotStore::get($key);
            foreach ($snapshot['data']['personal_servicio'] ?? [] as $worker) {
                if ((int)$worker['personal_id'] === $personalId && isset($worker['patron'][$day-1])) {
                    return self::resolve((string)$worker['patron'][$day-1], SnapshotStore::get('turnos_catalogo_oficial')['data'] ?? []);
                }
            }
        }
        return null;
    }

    public static function resolve(string $code, array $catalog): array {
        if (in_array($code, ['LIB','0','VAC','V','DESC','C','CITT','F','P','PER'], true)) {
            return ['TurnoID'=>null, 'HoraProgInicio'=>null, 'HoraProgFin'=>null, 'TurnoCodigo'=>$code, 'NombreTurno'=>$code];
        }
        $aliases = ['1'=>'M','2'=>'T','3'=>'N','GN'=>'N','G'=>'GD','1/2'=>'GD',
            'VAC-1'=>'M','VAC-2'=>'T','VAC-3'=>'N','V1'=>'M','V2'=>'T','V3'=>'N'];
        $canonical = $aliases[$code] ?? $code;
        $byCode = [];
        foreach ($catalog as $shift) $byCode[$shift['codigo'] === 'G' ? 'GD' : $shift['codigo']] = $shift;
        if (isset($byCode[$code])) $canonical = $code;
        if (isset($byCode[$canonical])) {
            $shift = $byCode[$canonical];
            return ['TurnoID'=>$shift['turno_id'], 'HoraProgInicio'=>$shift['hora_inicio'],
                'HoraProgFin'=>$shift['hora_fin'], 'TurnoCodigo'=>$code, 'NombreTurno'=>$shift['nombre']];
        }
        if (in_array($code, ['2/3','1/2/3','G24'], true)) {
            $first = $byCode[$code === '2/3' ? 'T' : 'M'] ?? null;
            $night = $byCode['N'] ?? null;
            if ($first && $night) return ['TurnoID'=>$night['turno_id'], 'HoraProgInicio'=>$first['hora_inicio'],
                'HoraProgFin'=>$night['hora_fin'], 'TurnoCodigo'=>$code, 'NombreTurno'=>$code];
        }
        throw new RuntimeException("El turno $code no tiene horario en el catálogo SQL.");
    }

    public static function apply(array $row, string $date): array {
        $monthly = self::monthly((int)$row['PersonalID'], $date, isset($row['ServicioID']) ? (int)$row['ServicioID'] : null);
        return $monthly === null ? $row : array_replace($row, $monthly);
    }
}
