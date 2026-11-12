<?php
require_once __DIR__ . '/SnapshotStore.php';
require_once __DIR__ . '/TurnoCatalog.php';
require_once __DIR__.'/WorkloadPolicy.php';

class ProgramacionConflict extends RuntimeException {
    public function __construct(public int $version) { parent::__construct('El rol fue actualizado por otro usuario. Recargue la programación.'); }
}

/** SQL es la fuente oficial; los archivos contienen únicamente copias de consulta. */
class ProgramacionStore {
    public static function roster(PDO $pdo, int $service): array {
        $workloadSql=WorkloadPolicy::sql();
        $sql = "SELECT p.PersonalID AS personal_id, p.CodigoEmpleado AS codigo_empleado,
            p.NumeroDocumento AS dni,
            COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, ''), ' ', p.Nombres))), ''), p.NombreCompletoOriginal, p.CodigoEmpleado) AS nombre_completo,
            ISNULL(c.Nombre, 'Personal') AS cargo_nombre, s.ServicioID AS servicio_id,
            s.Nombre AS servicio_nombre, ap.AreaID AS area_id, p.CondicionLaboral AS condicion_laboral,
            {$workloadSql} AS regimen_laboral,
            CASE WHEN {$workloadSql} = 'ASISTENCIAL' THEN 1 ELSE NULL END AS aplica_seti
            FROM rrhh.Personal p
            LEFT JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID = p.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN organizacion.Servicio s ON s.ServicioID = ap.ServicioID
            LEFT JOIN rrhh.Cargo c ON c.CargoID = ap.CargoID
            OUTER APPLY (SELECT TOP 1 co.TipoContratoID FROM rrhh.Contrato co WHERE co.PersonalID = p.PersonalID AND co.Activo = 1 ORDER BY co.ContratoID DESC) contrato
            LEFT JOIN rrhh.TipoContrato tc ON tc.TipoContratoID = contrato.TipoContratoID
            WHERE p.Activo = 1";
        if ($service > 0) $sql .= ' AND s.ServicioID = :servicio';
        $sql .= ' ORDER BY p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres';
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

        // En V3 la programación oficial reside en rrhh.ProgramacionMensualDetalle
        $detail = $pdo->prepare('SELECT PersonalID, Dia, CodigoTurno FROM rrhh.ProgramacionMensualDetalle WHERE Anio = :anio AND Mes = :mes');
        $detail->execute([':anio'=>$year, ':mes'=>$month]);
        $overrides = [];
        foreach ($detail->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overrides[(int)$row['PersonalID']][(int)$row['Dia']] = $row['CodigoTurno'];
        }

        foreach ($people as &$person) {
            $id = (int)$person['personal_id'];
            $person['patron'] = [];
            for ($day = 1; $day <= $days; $day++) {
                $person['patron'][] = $overrides[$id][$day] ?? null;
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
            $usuarioIdFk = ($user > 0) ? $user : null;
            if ($version === false) {
                $pdo->prepare('INSERT INTO rrhh.ProgramacionMensual (Anio, Mes, Version, UsuarioID) VALUES (?, ?, 1, ?)')->execute([$year,$month,$usuarioIdFk]);
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
                $id = (int)$change['personal_id']; $day = (int)$change['dia']; $code = strtoupper(trim((string)$change['codigo_turno']));
                if (isset($present[$id][$day])) $update->execute([$code,$year,$month,$id,$day]);
                else { $insert->execute([$year,$month,$id,$day,$code]); $present[$id][$day] = true; }
            }
            $pdo->prepare('UPDATE rrhh.ProgramacionMensual SET Version = Version + 1, ActualizadoEn = SYSDATETIME(), UsuarioID = ? WHERE Anio = ? AND Mes = ?')->execute([$usuarioIdFk,$year,$month]);
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
