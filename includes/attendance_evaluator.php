<?php
require_once __DIR__ . '/AttendanceRules.php';
require_once __DIR__ . '/AttendanceSchedule.php';
/**
 * Procesa y califica una fila de asistencia según las normas MINSA
 */
function procesarFilaAsistencia($row, $nombreCompleto, $fecha) {
    $row = AttendanceSchedule::apply($row, $fecha);
    $horaProgInicio = $row['HoraProgInicio'] ?? null;
    $horaProgFin    = $row['HoraProgFin'] ?? null;
    $horaEntrada    = $row['HoraEntrada'] ?? null;
    $horaSalida     = $row['HoraSalida'] ?? null;
    $estadoBd       = $row['EstadoAsistencia'] ?? null;

    $minutosTardanza = 0;
    $minutosSalidaAnticipada = 0;
    $estadoEvaluado = 'ASISTIO';
    $observacionRegla = '';

    if (in_array($estadoBd, ['PERMISO', 'LICENCIA'], true)) {
        $estadoEvaluado = $estadoBd;
        $observacionRegla = $row['Observacion'] ?: 'Ausencia aprobada';
    } elseif (empty($horaProgInicio) || empty($horaProgFin) || ($row['TurnoID'] ?? null) === null) {
        $rest = in_array($row['TurnoCodigo'] ?? null,['LIB','0','VAC','V','DESC','C','CITT','F','P','PER'],true);
        $estadoEvaluado = $rest ? 'DESCANSO' : 'SIN_DATOS';
        $observacionRegla = $rest ? 'Descanso o ausencia programada' : 'Sin programación confirmada';
    } elseif (empty($horaEntrada)) {
        if (stripos((string)$estadoBd, 'FALTA') !== false) {
            $estadoEvaluado = 'FALTA';
            $observacionRegla = $row['Observacion'] ?: 'Falta registrada';
        } else {
            $estadoEvaluado = AttendanceRules::missingState($fecha, $horaProgInicio, $horaProgFin);
            $observacionRegla = 'Sin marcación conciliada; pendiente de verificación';
        }
    } else {
        $evaluation = AttendanceRules::evaluate($horaEntrada, $horaProgInicio, $fecha, $horaProgFin);
        $estadoEvaluado = $evaluation['estado'];
        $minutosTardanza = $evaluation['minutos'];
        $observacionRegla = $estadoEvaluado === 'ASISTIO' ? 'Ingreso puntual o dentro de tolerancia' : "Tardanza de {$minutosTardanza} minutos";

        // Evaluación de salida
        if (!empty($horaSalida)) {
            $segSalidaProg = strtotime("$fecha $horaProgFin");
            $segSalidaReal = strtotime("$fecha $horaSalida");
            if ($horaProgFin <= $horaProgInicio) {
                $segSalidaProg += 86400;
                if ($horaSalida < $horaProgInicio) $segSalidaReal += 86400;
            }
            if ($segSalidaReal < $segSalidaProg) {
                $minutosSalidaAnticipada = round(($segSalidaProg - $segSalidaReal) / 60);
                $observacionRegla .= " | Salida anticipada ({$minutosSalidaAnticipada} min antes)";
            }
        } else {
            $observacionRegla .= " | Salida no registrada";
        }
    }

    return [
        'personal_id'              => intval($row['PersonalID']),
        'dni'                      => $row['DNI'],
        'nombre_completo'          => $nombreCompleto,
        'telefono'                 => $row['Telefono'] ?? '',
        'correo'                   => $row['CorreoElectronico'] ?? '',
        'cargo'                    => $row['NombreCargo'] ?? 'Personal Hospitalario',
        'profesion'                => $row['NombreProfesion'] ?? null,
        'area_id'                  => isset($row['AreaID']) ? (int)$row['AreaID'] : null,
        'area_nombre'              => $row['NombreArea'] ?? null,
        'servicio_id'              => isset($row['ServicioID']) ? intval($row['ServicioID']) : null,
        'servicio_nombre'          => $row['NombreServicio'] ?? null,
        'regimen_laboral'          => $row['RegimenLaboral'] ?? null,
        'condicion_laboral'        => $row['CondicionLaboral'] ?? null,
        'turno_id'                 => $row['TurnoID'] ? intval($row['TurnoID']) : null,
        'turno_nombre'             => $row['NombreTurno'] ?? null,
        'turno_codigo'             => $row['TurnoCodigo'] ?? null,
        'hora_prog_inicio'         => $horaProgInicio !== null ? substr($horaProgInicio, 0, 5) : null,
        'hora_prog_fin'            => $horaProgFin !== null ? substr($horaProgFin, 0, 5) : null,
        'asistencia_id'            => $row['AsistenciaID'] ? intval($row['AsistenciaID']) : null,
        'hora_entrada'             => $horaEntrada ? substr($horaEntrada, 0, 5) : null,
        'hora_salida'              => $horaSalida ? substr($horaSalida, 0, 5) : null,
        'minutos_tardanza'         => $minutosTardanza,
        'minutos_salida_anticipada'=> $minutosSalidaAnticipada,
        'estado_evaluado'          => $estadoEvaluado,
        'observacion'              => $row['Observacion'] ?? '',
        'observacion_regla'        => $observacionRegla,
        'es_ausente'               => in_array($estadoEvaluado, ['FALTA', 'FALTA_ADMINISTRATIVA'])
    ];
}

