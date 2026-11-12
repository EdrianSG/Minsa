<?php
/** Resume estados ya evaluados sin inferir asistencia ni situación laboral. */
final class AttendanceSummary {
    public static function fromRows(array $rows): array {
        $summary = [
            'total_evaluados'=>count($rows), 'total_programados'=>0, 'asistio_puntual'=>0,
            'tardanzas'=>0, 'minutos_tardanza_total'=>0, 'faltas'=>0, 'permisos'=>0,
            'descansos'=>0, 'pendientes'=>0, 'sin_programacion'=>0,
            'por_iniciar'=>0, 'en_curso'=>0, 'sin_marcacion'=>0,
            'porcentaje_asistencia'=>null, 'porcentaje_puntualidad'=>null
        ];
        foreach ($rows as $row) {
            $scheduled = !empty($row['turno_id']) && !empty($row['hora_prog_inicio']) && !empty($row['hora_prog_fin']);
            $state = $row['estado_evaluado'] ?? 'SIN_DATOS';
            if ($scheduled) $summary['total_programados']++;
            if (!$scheduled && $state !== 'DESCANSO') $summary['sin_programacion']++;
            if ($state === 'ASISTIO') $summary['asistio_puntual']++;
            if ($state === 'TARDANZA') {
                $summary['tardanzas']++;
                $summary['minutos_tardanza_total'] += (int)($row['minutos_tardanza'] ?? 0);
            }
            if (in_array($state, ['FALTA','FALTA_ADMINISTRATIVA'], true)) $summary['faltas']++;
            if (in_array($state, ['PERMISO','LICENCIA'], true)) $summary['permisos']++;
            if ($state === 'DESCANSO') $summary['descansos']++;
            if (in_array($state, ['SIN_DATOS','PROGRAMADO','EN_CURSO'], true)) $summary['pendientes']++;
            if ($state === 'PROGRAMADO') $summary['por_iniciar']++;
            if ($state === 'EN_CURSO') $summary['en_curso']++;
            if ($scheduled && empty($row['hora_entrada']) && in_array($state, ['SIN_DATOS','PROGRAMADO','EN_CURSO'], true)) {
                $summary['sin_marcacion']++;
            }
        }
        // El porcentaje permanece pendiente mientras falte confirmar la evaluación del día.
        if ($summary['total_programados'] > 0 && $summary['pendientes'] === 0) {
            $summary['porcentaje_asistencia'] = round(100 * ($summary['asistio_puntual'] + $summary['tardanzas']) / $summary['total_programados'], 1);
            $summary['porcentaje_puntualidad'] = round(100 * $summary['asistio_puntual'] / $summary['total_programados'], 1);
        }
        return $summary;
    }
}
