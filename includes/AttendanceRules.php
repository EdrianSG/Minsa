<?php
/** Reglas de asistencia documentadas por el hospital; no interpreta normativa externa. */
class AttendanceRules {
    public static function evaluate(?string $entry, string $scheduled, ?string $date = null, ?string $end = null): array {
        if ($entry === null || $entry === '') return ['estado' => 'FALTA', 'minutos' => 0];
        $date = $date ?? '2000-01-01';
        $start = (new DateTimeImmutable("$date $scheduled", new DateTimeZone('America/Lima')))->getTimestamp();
        $real = (new DateTimeImmutable("$date $entry", new DateTimeZone('America/Lima')))->getTimestamp();
        if ($end !== null && $end <= $scheduled && $real < $start - 7200) $real += 86400;
        $minutes = ($real - $start) / 60;
        if ($minutes <= 5) return ['estado' => 'ASISTIO', 'minutos' => 0];
        return ['estado' => $minutes <= 30 ? 'TARDANZA' : 'FALTA_ADMINISTRATIVA',
            'minutos' => (int)round($minutes)];
    }

    public static function interval(string $date, string $start, string $end): array {
        $zone = new DateTimeZone('America/Lima');
        $first = new DateTimeImmutable("$date $start", $zone);
        $last = new DateTimeImmutable("$date $end", $zone);
        if ($last <= $first) $last = $last->modify('+1 day');
        return [$first->getTimestamp(), $last->getTimestamp()];
    }

    public static function missingState(string $date, string $start, string $end, ?int $now = null): string {
        [$first, $last] = self::interval($date, $start, $end);
        $now = $now ?? time();
        return $now < $first ? 'PROGRAMADO' : ($now < $last ? 'EN_CURSO' : 'SIN_DATOS');
    }

    /** Ventana técnica: 2h antes/después, ingreso hasta la mitad de la jornada.
     * Las fechas completas permiten distinguir ingresos posteriores a medianoche.
     */
    public static function punches(array $marks, string $date, string $start, string $end): array {
        [$first, $last] = self::interval($date, $start, $end);
        $times = [];
        foreach ($marks as $mark) {
            $stamp = (new DateTimeImmutable($mark, new DateTimeZone('America/Lima')))->getTimestamp();
            if ($stamp >= $first - 7200 && $stamp <= $last + 7200) $times[] = $stamp;
        }
        $times = array_values(array_unique($times));
        sort($times);
        $entry = null;
        foreach ($times as $stamp) {
            if ($stamp <= $first + min(14400, ($last - $first) / 2)) {
                $entry = $stamp;
                break;
            }
        }
        $exit = null;
        if ($entry !== null) {
            foreach ($times as $stamp) {
                if ($stamp - $entry >= 1800 && ($exit === null || abs($stamp - $last) < abs($exit - $last))) {
                    $exit = $stamp;
                }
            }
        }
        return ['entry' => $entry, 'exit' => $exit, 'start' => $first, 'end' => $last];
    }
}
