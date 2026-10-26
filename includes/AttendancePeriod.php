<?php
/** Fechas y días de permisos: intervalos inclusivos, acotados al mes, sin duplicados. */
class AttendancePeriod {
    public static function bounds(int $year, int $month): array {
        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Periodo inválido.');
        }
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        return [$start, $start->modify('+1 month')];
    }

    public static function medicalDays(array $rows, int $year, int $month): array {
        [$start, $end] = self::bounds($year, $month);
        $days = [];
        foreach ($rows as $row) {
            $first = max($start, new DateTimeImmutable(substr($row['FechaInicio'], 0, 10)));
            $last = min($end->modify('-1 day'), new DateTimeImmutable(substr($row['FechaFin'], 0, 10)));
            for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
                $days[(int)$row['PersonalID']][$date->format('Y-m-d')] = true;
            }
        }
        return array_map('count', $days);
    }

    public static function loadMedicalDays(PDO $pdo, int $year, int $month): array {
        [$start, $end] = self::bounds($year, $month);
        $stmt = $pdo->prepare("SELECT pl.PersonalID, pl.FechaInicio, pl.FechaFin
            FROM rrhh.PermisoLaboral pl
            INNER JOIN rrhh.TipoPermisoLaboral tp ON tp.TipoPermisoLaboralID = pl.TipoPermisoLaboralID
            WHERE (tp.Nombre LIKE N'%CITT%' OR tp.Nombre LIKE N'%Descanso Médico%') AND pl.Estado = 'APROBADO'
              AND pl.FechaInicio < :finExclusivo AND pl.FechaFin >= :inicioMes");
        $stmt->execute([':inicioMes' => $start->format('Y-m-d'), ':finExclusivo' => $end->format('Y-m-d')]);
        return self::medicalDays($stmt->fetchAll(PDO::FETCH_ASSOC), $year, $month);
    }
}
