<?php
require_once __DIR__ . '/SnapshotStore.php';
require_once __DIR__ . '/TurnoCatalog.php';

class ProgramacionConflict extends RuntimeException {
    public function __construct(public int $version) { parent::__construct('El rol fue actualizado por otro usuario. Recargue la programación.'); }
}

/** SQL es la fuente oficial; los archivos contienen únicamente copias de consulta. */
class ProgramacionStore {
    public static function roster(PDO $pdo, int $service): array {
        $sql = "SELECT p.PersonalID AS personal_id, p.CodigoEmpleado AS codigo_empleado,
            per.NumeroDocumento AS dni,
            CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
            ISNULL(c.Nombre, 'Personal') AS cargo_nombre, s.ServicioID AS servicio_id,
            ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre, ISNULL(tc.Nombre, 'CAS') AS condicion_laboral,
            CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 'ADMINISTRATIVO' ELSE 'ASISTENCIAL' END AS regimen_laboral,
            CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 0 ELSE 1 END AS aplica_seti
            FROM rrhh.Personal p INNER JOIN core.Persona per ON per.PersonaID = p.PersonaID
            INNER JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID = p.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            INNER JOIN organizacion.Servicio s ON s.ServicioID = ap.ServicioID
            LEFT JOIN rrhh.Cargo c ON c.CargoID = ap.CargoID
            OUTER APPLY (SELECT TOP 1 co.TipoContratoID FROM rrhh.Contrato co WHERE co.PersonalID = p.PersonalID AND co.Estado = 1 ORDER BY co.ContratoID DESC) contrato
            LEFT JOIN rrhh.TipoContrato tc ON tc.TipoContratoID = contrato.TipoContratoID
            WHERE p.Estado = 1";
        if ($service > 0) $sql .= ' AND s.ServicioID = :servicio';
        $sql .= ' ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($service > 0 ? [':servicio'=>$service] : []);
        $people = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $people[(int)$row['personal_id']] = $row;
        return array_values($people);
    }

    public static function read(PDO $pdo, int $year, int $month, int $service): array {
        $stmt = $pdo->prepare('SELECT Version FROM rrhh.ProgramacionMensual WHERE Anio = :anio AND Mes = :mes');
        $stmt->execute([':anio'=>$year, ':mes'=>$month]);
        $version = (int)($stmt->fetchColumn() ?: 1);
        $people = self::roster($pdo, $service);
        $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $first = sprintf('%04d-%02d-01', $year, $month);
        $next = date('Y-m-d', strtotime($first . ' +1 month'));
        $weekly = $pdo->prepare('SELECT h.PersonalID, h.DiaSemana, h.FechaInicio, h.FechaFin, t.Nombre
            FROM rrhh.Horario h INNER JOIN rrhh.Turno t ON t.TurnoID = h.TurnoID
            WHERE h.Estado = 1 AND h.FechaInicio < :hasta AND (h.FechaFin IS NULL OR h.FechaFin >= :desde)
            ORDER BY h.FechaInicio');
        $weekly->execute([':hasta'=>$next, ':desde'=>$first]);
        $byPerson = [];
        foreach ($weekly->fetchAll(PDO::FETCH_ASSOC) as $row) $byPerson[(int)$row['PersonalID']][] = $row;
        $detail = $pdo->prepare('SELECT PersonalID, Dia, CodigoTurno FROM rrhh.ProgramacionMensualDetalle WHERE Anio = :anio AND Mes = :mes');
        $detail->execute([':anio'=>$year, ':mes'=>$month]);
        $overrides = [];
        foreach ($detail->fetchAll(PDO::FETCH_ASSOC) as $row) $overrides[(int)$row['PersonalID']][(int)$row['Dia']] = $row['CodigoTurno'];
        foreach ($people as &$person) {
            $id = (int)$person['personal_id'];
            $person['patron'] = [];
            for ($day = 1; $day <= $days; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $code = 'LIB';
                foreach ($byPerson[$id] ?? [] as $row) {
                    if ((int)$row['DiaSemana'] === (int)date('N', strtotime($date))
                        && substr($row['FechaInicio'], 0, 10) <= $date
                        && (empty($row['FechaFin']) || substr($row['FechaFin'], 0, 10) >= $date)) $code = TurnoCatalog::code($row['Nombre']);
                }
                $person['patron'][] = $overrides[$id][$day] ?? $code;
            }
        }
        unset($person);
        return ['personal_servicio'=>$people, 'servicio_id'=>$service, 'mes'=>$month, 'anio'=>$year, 'version'=>$version];
    }

    public static function save(PDO $pdo, int $year, int $month, int $service, int $base, bool $force, array $changes, int $user): int {
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            // Bloqueo de rango y fila por periodo: global y servicios comparten versión.
            $lock = $pdo->prepare('SELECT Version FROM rrhh.ProgramacionMensual WITH (UPDLOCK, HOLDLOCK) WHERE Anio = :anio AND Mes = :mes');
            $lock->execute([':anio'=>$year, ':mes'=>$month]);
            $version = $lock->fetchColumn();
            if ($version === false) {
                $pdo->prepare('INSERT INTO rrhh.ProgramacionMensual (Anio, Mes, Version, UsuarioID) VALUES (?, ?, 1, ?)')->execute([$year,$month,$user]);
                $version = 1;
            }
            if (!$force && (int)$version !== $base) throw new ProgramacionConflict((int)$version);
            $allowed = array_column(self::roster($pdo, $service), 'personal_id');
            foreach ($changes as $change) {
                if (!in_array((int)$change['personal_id'], array_map('intval', $allowed), true)) throw new InvalidArgumentException('El trabajador no pertenece a este servicio.');
            }
            $existing = $pdo->prepare('SELECT PersonalID, Dia FROM rrhh.ProgramacionMensualDetalle WHERE Anio = :anio AND Mes = :mes');
            $existing->execute([':anio'=>$year, ':mes'=>$month]);
            $present = [];
            foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) $present[(int)$row['PersonalID']][(int)$row['Dia']] = true;
            $update = $pdo->prepare('UPDATE rrhh.ProgramacionMensualDetalle SET CodigoTurno = ? WHERE Anio = ? AND Mes = ? AND PersonalID = ? AND Dia = ?');
            $insert = $pdo->prepare('INSERT INTO rrhh.ProgramacionMensualDetalle (Anio, Mes, PersonalID, Dia, CodigoTurno) VALUES (?, ?, ?, ?, ?)');
            foreach ($changes as $change) {
                $id = (int)$change['personal_id']; $day = (int)$change['dia']; $code = $change['codigo_turno'];
                if (isset($present[$id][$day])) $update->execute([$code,$year,$month,$id,$day]);
                else { $insert->execute([$year,$month,$id,$day,$code]); $present[$id][$day] = true; }
            }
            $pdo->prepare('UPDATE rrhh.ProgramacionMensual SET Version = Version + 1, ActualizadoEn = SYSDATETIME(), UsuarioID = ? WHERE Anio = ? AND Mes = ?')->execute([$user,$year,$month]);
            if ($ownsTransaction) $pdo->commit();
            return (int)$version + 1;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function backup(PDO $pdo, int $year, int $month): bool {
        $data = self::read($pdo, $year, $month, 0);
        $ok = self::snapshot($data);
        $services = array_unique(array_column($data['personal_servicio'], 'servicio_id'));
        foreach ($services as $service) {
            $scoped = $data;
            $scoped['servicio_id'] = (int)$service;
            $scoped['personal_servicio'] = array_values(array_filter($data['personal_servicio'], static fn($p)=>(int)$p['servicio_id'] === (int)$service));
            $ok = self::snapshot($scoped) && $ok;
        }
        return $ok;
    }
    public static function snapshot(array $data): bool {
        return SnapshotStore::set("programacion_serv_{$data['servicio_id']}_{$data['anio']}_{$data['mes']}", $data,
            ['source'=>'SQL_SERVER', 'sql_version'=>$data['version'], 'persistencia'=>'SQL_SERVER']);
    }
}
