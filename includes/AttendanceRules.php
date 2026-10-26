<?php
/** Reglas de asistencia documentadas por el hospital; no interpreta normativa externa. */
class AttendanceRules {
    public static function evaluate(?string $entry, string $scheduled): array {
        if ($entry === null || $entry === '') return ['estado' => 'FALTA', 'minutos' => 0];
        $minutes = (strtotime('2000-01-01 ' . $entry) - strtotime('2000-01-01 ' . $scheduled)) / 60;
        if ($minutes <= 5) return ['estado' => 'ASISTIO', 'minutos' => 0];
        return ['estado' => $minutes <= 30 ? 'TARDANZA' : 'FALTA_ADMINISTRATIVA',
            'minutos' => (int)round($minutes)];
    }
}
