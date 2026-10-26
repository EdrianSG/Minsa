<?php
/**
 * Motor de Conciliación Biométrica Institucional
 * Hospital Distrital de Pacasmayo - RR.HH.
 * 
 * Cruza las marcaciones periciales de rrhh.MarcacionBiometrica con la programación
 * de turnos de rrhh.Horario y genera/actualiza los registros en rrhh.Asistencia,
 * aplicando estrictamente las directivas MINSA y preservando regularizaciones manuales.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AttendanceRules.php';
require_once __DIR__ . '/SnapshotStore.php';
require_once __DIR__ . '/AttendanceSchedule.php';

class AttendanceReconciler {

    /**
     * Concilia la asistencia de una fecha específica.
     * 
     * @param string $fecha Formato 'YYYY-MM-DD'
     * @param PDO|null $pdo Conexión PDO opcional
     * @return array Resumen de la conciliación con KPIs
     */
    public static function conciliarFecha(string $fecha, ?PDO $pdo = null): array {
        $pdo = $pdo ?? Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("No hay conexión a la base de datos.");
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) || date('Y-m-d', strtotime($fecha)) !== $fecha) throw new InvalidArgumentException('Fecha inválida.');
        $diaSemana = (int)date('N', strtotime($fecha)); // 1=Lunes .. 7=Domingo

        // 1. Obtener todo el personal activo con su horario programado para ese día de la semana
        $sqlPersonal = "
            SELECT 
                p.PersonalID,
                (SELECT TOP 1 ap.ServicioID FROM rrhh.AsignacionPersonal ap WHERE ap.PersonalID = p.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1) AS ServicioID,
                per.NumeroDocumento AS DNI,
                per.Nombres,
                per.ApellidoPaterno,
                per.ApellidoMaterno,
                t.TurnoID,
                t.Nombre AS NombreTurno,
                ISNULL(CONVERT(VARCHAR(8), t.HoraInicio, 108), '07:00:00') AS HoraProgInicio,
                ISNULL(CONVERT(VARCHAR(8), t.HoraFin, 108), '13:00:00') AS HoraProgFin,
                a.AsistenciaID,
                CONVERT(VARCHAR(8), a.HoraEntrada, 108) AS HoraEntradaActual,
                CONVERT(VARCHAR(8), a.HoraSalida, 108) AS HoraSalidaActual,
                a.Estado AS EstadoActual,
                a.Observacion AS ObservacionActual
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.Horario h ON p.PersonalID = h.PersonalID 
                 AND h.DiaSemana = :diaSemana 
                 AND h.Estado = 1 
                 AND h.FechaInicio <= :fechaIni 
                 AND (h.FechaFin IS NULL OR h.FechaFin >= :fechaFin)
            LEFT JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
            WHERE p.Estado = 1
            ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
        ";

        $stmtPers = $pdo->prepare($sqlPersonal);
        $stmtPers->execute([
            ':diaSemana'   => $diaSemana,
            ':fechaIni'    => $fecha,
            ':fechaFin'    => $fecha,
            ':fechaAsist'  => $fecha
        ]);
        $personalList = $stmtPers->fetchAll(PDO::FETCH_ASSOC);

        // Leer un rango completo; cada trabajador se filtra después por su jornada.
        $stmtMarc = $pdo->prepare("SELECT PersonalID, FechaHora FROM rrhh.MarcacionBiometrica
            WHERE FechaHora >= :desde AND FechaHora < :hasta ORDER BY FechaHora ASC");
        $stmtMarc->execute([
            ':desde' => date('Y-m-d 22:00:00', strtotime("$fecha -1 day")),
            ':hasta' => date('Y-m-d 02:00:00', strtotime("$fecha +2 days"))
        ]);
        $marcacionesPorPersonal = [];
        foreach ($stmtMarc->fetchAll(PDO::FETCH_ASSOC) as $mark) {
            $marcacionesPorPersonal[(int)$mark['PersonalID']][] = $mark['FechaHora'];
        }

        // 3. Preparar statements para INSERT / UPDATE en rrhh.Asistencia
        $stmtInsert = $pdo->prepare("
            INSERT INTO rrhh.Asistencia (
                PersonalID, Fecha, HoraEntrada, HoraSalida, Estado, Observacion
            ) VALUES (
                :personalId, :fecha, :horaEntrada, :horaSalida, :estado, :observacion
            )
        ");

        $stmtUpdate = $pdo->prepare("
            UPDATE rrhh.Asistencia
            SET HoraEntrada = :horaEntrada,
                HoraSalida = :horaSalida,
                Estado = :estado,
                Observacion = :observacion
            WHERE AsistenciaID = :asistenciaId
        ");

        // Métricas de resultado
        $totalEvaluados = count($personalList);
        $totalConMarcacion = 0;
        $totalPuntuales = 0;
        $totalTardanzas = 0;
        $totalFaltasAdmin = 0;
        $totalFaltas = 0;
        $totalPreservados = 0;
        $insertados = 0;
        $actualizados = 0;

        foreach ($personalList as $p) {
            $p = AttendanceSchedule::apply($p, $fecha);
            $personalId = (int)$p['PersonalID'];
            $asistenciaId = !empty($p['AsistenciaID']) ? (int)$p['AsistenciaID'] : null;
            $estadoActual = strtoupper(trim((string)($p['EstadoActual'] ?? '')));
            $obsActual = (string)($p['ObservacionActual'] ?? '');

            // Regla de preservación: NO SOBRESCRIBIR regularizaciones manuales, permisos ni licencias
            $esManualOPermiso = (
                in_array($estadoActual, ['PERMISO', 'LICENCIA', 'VACACIONES', 'COMISION', 'JUSTIFICADO'], true) ||
                preg_match('/(memo|regulariz|sustento|resoluc|aprobad|manual)/i', $obsActual)
            );

            if ($asistenciaId !== null && $esManualOPermiso) {
                $totalPreservados++;
                continue;
            }

            // Sin turno no se generan faltas para días de descanso.
            if (empty($p['TurnoID'])) continue;
            $horaProgInicio = $p['HoraProgInicio'];
            $horaProgFin = $p['HoraProgFin'];
            $window = AttendanceRules::punches($marcacionesPorPersonal[$personalId] ?? [], $fecha, $horaProgInicio, $horaProgFin);
            $zone = new DateTimeZone('America/Lima');
            $horaEntrada = $window['entry'] === null ? null : (new DateTimeImmutable('@' . $window['entry']))->setTimezone($zone)->format('H:i:s');
            $horaSalida = $window['exit'] === null ? null : (new DateTimeImmutable('@' . $window['exit']))->setTimezone($zone)->format('H:i:s');
            $estado = AttendanceRules::missingState($fecha, $horaProgInicio, $horaProgFin);
            $observacion = 'Sin marcación conciliada; pendiente de verificación';

            if ($window['entry'] !== null) {
                $totalConMarcacion++;
                $difMinutos = ($window['entry'] - $window['start']) / 60;

                if ($difMinutos <= 5) {
                    $estado = 'Puntual';
                    $totalPuntuales++;
                    $observacion = ($difMinutos <= 0) 
                        ? 'Marcación biométrica puntual (' . substr($horaEntrada, 0, 5) . ')'
                        : 'Dentro de tolerancia de 5 min (' . substr($horaEntrada, 0, 5) . ')';
                } elseif ($difMinutos <= 30) {
                    $estado = 'Tardanza';
                    $totalTardanzas++;
                    $minTard = (int)round($difMinutos);
                    $observacion = "Tardanza de {$minTard} min en biométrico (" . substr($horaEntrada, 0, 5) . ")";
                } else {
                    $estado = 'Falta Administrativa';
                    $totalFaltasAdmin++;
                    $minTard = (int)round($difMinutos);
                    $observacion = "Ingreso a las " . substr($horaEntrada, 0, 5) . " (+{$minTard} min). Falta administrativa";
                }

                // Verificación de salida
                if (!empty($horaSalida)) {
                    $segSalProg = $window['end'];
                    $segSalReal = $window['exit'];
                    if ($segSalReal < $segSalProg) {
                        $minAnt = (int)round(($segSalProg - $segSalReal) / 60);
                        $observacion .= " | Salida anticipada ({$minAnt} min antes)";
                    } else {
                        $observacion .= " | Salida registrada (" . substr($horaSalida, 0, 5) . ")";
                    }
                } else {
                    $observacion .= " | Salida no registrada";
                }
            } else {
                // La conciliación no prueba que el reloj haya sido importado por completo.
                // Conservar faltas ya dictaminadas y no crear faltas a partir de datos ausentes.
                if (strpos($estadoActual, 'FALTA') !== false) continue;
            }

            // Guardar en rrhh.Asistencia
            if ($asistenciaId !== null) {
                $stmtUpdate->execute([
                    ':horaEntrada'  => $horaEntrada,
                    ':horaSalida'   => $horaSalida,
                    ':estado'       => $estado,
                    ':observacion'  => $observacion,
                    ':asistenciaId' => $asistenciaId
                ]);
                $actualizados++;
            } else {
                $stmtInsert->execute([
                    ':personalId'   => $personalId,
                    ':fecha'        => $fecha,
                    ':horaEntrada'  => $horaEntrada,
                    ':horaSalida'   => $horaSalida,
                    ':estado'       => $estado,
                    ':observacion'  => $observacion
                ]);
                $insertados++;
            }
        }

        // 4. Marcar registros procesados en rrhh.MarcacionBiometrica
        $stmtMarkProcessed = $pdo->prepare("
            UPDATE rrhh.MarcacionBiometrica
            SET EstadoProcesamiento = 'PROCESADO'
            WHERE CAST(FechaHora AS DATE) = :fecha AND EstadoProcesamiento = 'PENDIENTE'
        ");
        $stmtMarkProcessed->execute([':fecha' => $fecha]);

        // 5. Invalidar/Actualizar SnapshotStore de esa fecha
        SnapshotStore::delete("asistencia_diaria_{$fecha}");
        foreach (glob(SnapshotStore::directory() . "/asistencia_diaria_{$fecha}_serv_*.json") ?: [] as $file) {
            SnapshotStore::delete(basename($file, '.json'));
        }

        return [
            'fecha'                   => $fecha,
            'total_evaluados'         => $totalEvaluados,
            'con_marcacion'           => $totalConMarcacion,
            'puntuales'               => $totalPuntuales,
            'tardanzas'               => $totalTardanzas,
            'faltas_administrativas'  => $totalFaltasAdmin,
            'faltas_sin_marcacion'    => $totalFaltas,
            'preservados_manuales'    => $totalPreservados,
            'insertados'              => $insertados,
            'actualizados'            => $actualizados
        ];
    }

    /**
     * Concilia un rango de fechas.
     */
    public static function conciliarRango(string $fechaInicio, string $fechaFin, ?PDO $pdo = null): array {
        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        $fin->modify('+1 day');

        $periodo = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
        $resultados = [];

        foreach ($periodo as $dt) {
            $f = $dt->format('Y-m-d');
            $resultados[$f] = self::conciliarFecha($f, $pdo);
        }

        return $resultados;
    }
}
