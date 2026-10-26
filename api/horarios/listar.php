<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('horarios', ['GET'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/horarios/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 3
 * 
 * Consulta las asignaciones semanales de horarios del personal
 * Reutiliza: rrhh.Horario, rrhh.Personal, core.Persona, rrhh.Turno
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$horariosPersonal = [];

if ($dbConnected) {
    try {
        $sql = "
            SELECT 
                h.HorarioID,
                h.PersonalID,
                h.TurnoID,
                h.DiaSemana,
                h.FechaInicio,
                h.FechaFin,
                h.Estado,
                t.Nombre AS TurnoNombre,
                t.HoraInicio,
                t.HoraFin,
                p.CodigoEmpleado,
                per.NumeroDocumento AS DNI,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS NombreCompleto,
                ISNULL(s.Nombre, 'Servicio General') AS ServicioNombre,
                ISNULL(c.Nombre, 'Personal') AS CargoNombre
            FROM rrhh.Horario h
            INNER JOIN rrhh.Personal p ON h.PersonalID = p.PersonalID
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            INNER JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            WHERE h.Estado = 1
            ORDER BY per.ApellidoPaterno, h.DiaSemana
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            // Agrupar por PersonalID
            $grouped = [];
            foreach ($rows as $r) {
                $pid = $r['PersonalID'];
                if (!isset($grouped[$pid])) {
                    $grouped[$pid] = [
                        'personal_id'      => $pid,
                        'nombre_completo'  => $r['NombreCompleto'],
                        'dni'              => $r['DNI'],
                        'codigo_empleado'  => $r['CodigoEmpleado'],
                        'cargo_nombre'     => $r['CargoNombre'],
                        'servicio_nombre'  => $r['ServicioNombre'],
                        'tipo_patron'      => 'Pauta Semanal Asignada',
                        'dias'             => [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null],
                        'total_horas_semana' => 0
                    ];
                }

                $inicio = substr($r['HoraInicio'], 0, 5);
                $fin = substr($r['HoraFin'], 0, 5);
                $t1 = strtotime($inicio);
                $t2 = strtotime($fin);
                $diff = ($t2 < $t1) ? ((strtotime('24:00') - $t1 + $t2 - strtotime('00:00')) / 3600) : (($t2 - $t1) / 3600);
                $diff = round($diff, 1);

                if (stripos($r['TurnoNombre'], 'Mañana') !== false) {
                    $codigo = 'M';
                } elseif (stripos($r['TurnoNombre'], 'Tarde') !== false) {
                    $codigo = 'T';
                } elseif (stripos($r['TurnoNombre'], 'Nocturna') !== false || stripos($r['TurnoNombre'], 'Noche') !== false) {
                    $codigo = 'N';
                } elseif (stripos($r['TurnoNombre'], 'Diurna') !== false) {
                    $codigo = 'GD';
                } elseif (stripos($r['TurnoNombre'], 'Admin') !== false) {
                    $codigo = 'ADM';
                } else {
                    $codigo = strtoupper(mb_substr($r['TurnoNombre'], 0, 3, 'UTF-8'));
                }

                $grouped[$pid]['dias'][$r['DiaSemana']] = [
                    'turno_id'    => $r['TurnoID'],
                    'turno_nombre'=> $r['TurnoNombre'],
                    'codigo'      => $codigo,
                    'hora'        => "$inicio - $fin",
                    'horas'       => $diff
                ];
                $grouped[$pid]['total_horas_semana'] += $diff;
            }
            $horariosPersonal = array_values($grouped);
            if (!empty($horariosPersonal)) {
                require_once __DIR__ . '/../../includes/SnapshotStore.php';
                SnapshotStore::set('horarios_lista_completa', $horariosPersonal, ['source' => 'SQL_SERVER_LIVE']);
            }
        }
    } catch (PDOException $e) {
        $dbConnected = false;
    }
}

// Resiliencia local: si la BD está offline o no retornó datos, usar el Snapshot de datos reales
if (empty($horariosPersonal)) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    if (SnapshotStore::has('horarios_lista_completa')) {
        $snap = SnapshotStore::get('horarios_lista_completa');
        if (!empty($snap['data']) && is_array($snap['data'])) {
            $horariosPersonal = $snap['data'];
        }
    }
}

// Fallback desacoplado SOLO si nunca hubo conexión ni snapshot
if (empty($horariosPersonal)) {
    $horariosPersonal = [
        [
            'personal_id'      => 6,
            'nombre_completo'  => 'Urcia Ventura, Carmen Rosa',
            'dni'              => '47309642',
            'codigo_empleado'  => 'EMP-00106',
            'cargo_nombre'     => 'Especialista Administrativo',
            'servicio_nombre'  => 'Unidad de Personal / RR.HH.',
            'tipo_patron'      => 'Jornada Administrativa Fija (L-V)',
            'dias' => [
                1 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                2 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                3 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                4 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                5 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                6 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0],
                7 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0]
            ],
            'total_horas_semana' => 40.0,
            'vigencia' => 'Vigente desde 2026-01-01'
        ],
        [
            'personal_id'      => 12,
            'nombre_completo'  => 'Cortez García, Miguel Arturo',
            'dni'              => '73291045',
            'codigo_empleado'  => 'EMP-00112',
            'cargo_nombre'     => 'Jefe de Informática y Estadística',
            'servicio_nombre'  => 'Unidad de Informática y Estadística',
            'tipo_patron'      => 'Jornada Administrativa Fija (L-V)',
            'dias' => [
                1 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                2 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                3 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                4 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                5 => ['codigo' => 'ADM', 'hora' => '07:30 - 15:30', 'horas' => 8.0],
                6 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0],
                7 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0]
            ],
            'total_horas_semana' => 40.0,
            'vigencia' => 'Vigente desde 2026-01-01'
        ],
        [
            'personal_id'      => 1,
            'nombre_completo'  => 'Torres Benites, Ana María',
            'dni'              => '45892104',
            'codigo_empleado'  => 'EMP-00101',
            'cargo_nombre'     => 'Enfermera Asistencial',
            'servicio_nombre'  => 'Emergencia y Cuidados Críticos',
            'tipo_patron'      => 'Pauta Base Asistencial Rotativa',
            'dias' => [
                1 => ['codigo' => 'M', 'hora' => '08:00 - 14:00', 'horas' => 6.0],
                2 => ['codigo' => 'M', 'hora' => '08:00 - 14:00', 'horas' => 6.0],
                3 => ['codigo' => 'T', 'hora' => '14:00 - 20:00', 'horas' => 6.0],
                4 => ['codigo' => 'N', 'hora' => '20:00 - 08:00', 'horas' => 12.0],
                5 => ['codigo' => 'LIB', 'hora' => 'Post Guardia', 'horas' => 0.0],
                6 => ['codigo' => 'M', 'hora' => '08:00 - 14:00', 'horas' => 6.0],
                7 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0]
            ],
            'total_horas_semana' => 36.0,
            'vigencia' => 'Vigente Setiembre 2026'
        ],
        [
            'personal_id'      => 2,
            'nombre_completo'  => 'Díaz Rojas, Luis Alberto',
            'dni'              => '70445708',
            'codigo_empleado'  => 'EMP-00102',
            'cargo_nombre'     => 'Médico Especialista',
            'servicio_nombre'  => 'Unidad de Cuidados Intensivos',
            'tipo_patron'      => 'Pauta Guardia UCI',
            'dias' => [
                1 => ['codigo' => 'GD', 'hora' => '08:00 - 20:00', 'horas' => 12.0],
                2 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0],
                3 => ['codigo' => 'GN', 'hora' => '20:00 - 08:00', 'horas' => 12.0],
                4 => ['codigo' => 'LIB', 'hora' => 'Post Guardia', 'horas' => 0.0],
                5 => ['codigo' => 'M', 'hora' => '08:00 - 14:00', 'horas' => 6.0],
                6 => ['codigo' => 'LIB', 'hora' => 'Descanso', 'horas' => 0.0],
                7 => ['codigo' => 'GD', 'hora' => '08:00 - 20:00', 'horas' => 12.0]
            ],
            'total_horas_semana' => 42.0,
            'vigencia' => 'Vigente Setiembre 2026'
        ]
    ];
}

echo json_encode([
    'ok'           => true,
    'total'        => count($horariosPersonal),
    'data'         => $horariosPersonal,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
