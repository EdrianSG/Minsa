<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('horarios', ['GET'], ['JEFE_RRHH', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/horarios/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 3
 * 
 * Consulta las asignaciones semanales de horarios del personal
 * Reutiliza: rrhh.Horario, rrhh.Personal, core.Persona, rrhh.Turno
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$userAllowedServ = Auth::getAllowedServiceId();

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$horariosPersonal = [];

if ($dbConnected) {
    try {
        $whereSql = "WHERE h.Estado = 1";
        $params = [];
        if ($userAllowedServ !== null) {
            $whereSql .= " AND s.ServicioID = :servId";
            $params[':servId'] = $userAllowedServ;
        }

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
                s.ServicioID, ISNULL(s.Nombre, 'Servicio General') AS ServicioNombre,
                ISNULL(c.Nombre, 'Personal') AS CargoNombre
            FROM rrhh.Horario h
            INNER JOIN rrhh.Personal p ON h.PersonalID = p.PersonalID
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            INNER JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            {$whereSql}
            ORDER BY per.ApellidoPaterno, h.DiaSemana
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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
                        'servicio_id'      => (int)$r['ServicioID'],
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
                SnapshotStore::set('horarios_lista_completa' . ($userAllowedServ ? '_serv_' . $userAllowedServ : ''), $horariosPersonal, ['source' => 'SQL_SERVER_LIVE']);
            }
        }
        require_once __DIR__ . '/../../includes/SnapshotStore.php';
        SnapshotStore::set('horarios_lista_completa' . ($userAllowedServ ? '_serv_' . $userAllowedServ : ''), $horariosPersonal, ['source'=>'SQL_SERVER_LIVE']);
    } catch (PDOException $e) {
        $dbConnected = false;
    }
}

if (!$dbConnected) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    $snap = $userAllowedServ ? SnapshotStore::get('horarios_lista_completa_serv_' . $userAllowedServ) : null;
    $snap = $snap ?? SnapshotStore::get('horarios_lista_completa');
    if (!$snap) apiError(503, 'SQL Server no está disponible y no existe un respaldo de horarios.');
    $horariosPersonal = $snap['data'];
}
if ($userAllowedServ !== null) $horariosPersonal = array_values(array_filter($horariosPersonal, static fn($p)=>(int)($p['servicio_id'] ?? 0) === $userAllowedServ));

echo json_encode([
    'ok'           => true,
    'total'        => count($horariosPersonal),
    'data'         => $horariosPersonal,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
