<?php
/**
 * SnapshotStore - Motor de Persistencia Local y Resiliencia en Disco
 * Hospital Distrital de Pacasmayo
 * 
 * Gestiona copias fieles (Snapshots) de los datos reales de SQL Server en disco local.
 * Si la red o SQL Server cae, los usuarios continúan visualizando y operando
 * sobre el último estado real conocido con control de versiones y auditoría.
 */

class SnapshotStore {
    private static string $storagePath = __DIR__ . '/../storage/snapshots';

    public static function directory(): string {
        return getenv('SNAPSHOT_PATH') ?: self::$storagePath;
    }

    /**
     * Inicializa el directorio de almacenamiento
     */
    private static function ensureDirectory(): void {
        if (!is_dir(self::directory())) {
            @mkdir(self::directory(), 0775, true);
        }
    }

    /**
     * Guarda un snapshot de datos reales con metadatos de versión y timestamp
     * 
     * @param string $key Identificador único (ej: "permisos_global", "programacion_serv_7_2026_9")
     * @param mixed $data Datos reales obtenidos de SQL Server
     * @param array $extraMeta Metadatos adicionales (ej: servicio_id, usuario_id)
     */
    public static function set(string $key, $data, array $extraMeta = []): bool {
        self::ensureDirectory();

        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $filePath = self::directory() . '/' . $safeKey . '.json';

        $lock = @fopen($filePath . '.lock', 'c');
        if (!$lock) return false;
        $temp = false;
        try {
            if (!flock($lock, LOCK_EX)) return false;
            $existing = is_file($filePath) ? json_decode(file_get_contents($filePath), true) : null;
            $payload = [
                '_meta' => array_merge($extraMeta, [
                    'key' => $key,
                    'version' => (int)($existing['_meta']['version'] ?? 0) + 1,
                    'saved_at' => date('Y-m-d H:i:s'), 'timestamp' => time(),
                    'hash' => md5(json_encode($data)), 'is_snapshot' => true
                ]),
                'data' => $data
            ];
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $temp = tempnam(self::directory(), '.snapshot-');
            if ($temp === false || file_put_contents($temp, $encoded) !== strlen($encoded)) return false;
            return rename($temp, $filePath);
        } catch (Throwable $e) {
            error_log('SnapshotStore: ' . $e->getMessage());
            return false;
        } finally {
            if ($temp && is_file($temp)) unlink($temp);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Obtiene el último snapshot almacenado
     * 
     * @param string $key Identificador
     * @return array|null Retorna ['data' => ..., '_meta' => ...] o null si no existe
     */
    public static function get(string $key): ?array {
        self::ensureDirectory();

        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $filePath = self::directory() . '/' . $safeKey . '.json';

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if (!$content) {
            return null;
        }

        $decoded = @json_decode($content, true);
        if (!$decoded || !isset($decoded['data'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Comprueba si existe un snapshot para una clave
     */
    public static function has(string $key): bool {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return file_exists(self::directory() . '/' . $safeKey . '.json');
    }

    /**
     * Elimina un snapshot del disco para forzar recarga
     */
    public static function delete(string $key): bool {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $filePath = self::directory() . '/' . $safeKey . '.json';
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }

    /**
     * Retorna la versión actual registrada para una clave
     */
    public static function getVersion(string $key): int {
        $snap = self::get($key);
        return $snap['_meta']['version'] ?? 1;
    }

    /**
     * Sincroniza y vuelca el estado vivo completo de la base de datos SQL Server
     * hacia los snapshots locales de resiliencia en disco.
     * Garantiza que al desconectarse de la red, los datos que se muestren sean
     * exactamente los de la base de datos institucional y no mocks desactualizados.
     */
    public static function syncAllFromDatabase(PDO $pdo): array
    {
        $results = [];

        // 1. Personal Completo
        try {
            $sqlPersonal = "
                SELECT 
                    p.PersonalID AS personal_id,
                    p.PersonaID AS persona_id,
                    p.CodigoEmpleado AS codigo_empleado,
                    p.FechaIngreso AS fecha_ingreso,
                    p.FechaCese AS fecha_cese,
                    p.Estado AS estado,
                    per.NumeroDocumento AS dni,
                    per.Nombres AS nombres,
                    per.ApellidoPaterno AS apellido_paterno,
                    per.ApellidoMaterno AS apellido_materno,
                    CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
                    per.Celular AS celular,
                    per.Correo AS correo,
                    per.Telefono AS telefono,
                    c.CargoID AS cargo_id,
                    ISNULL(c.Nombre, 'Sin Cargo') AS cargo_nombre,
                    prof.ProfesionID AS profesion_id,
                    ISNULL(prof.Nombre, 'General') AS profesion_nombre,
                    esp.EspecialidadID AS especialidad_id,
                    esp.Nombre AS especialidad_nombre,
                    a.AreaID AS area_id,
                    ISNULL(a.Nombre, 'General') AS area_nombre,
                    s.ServicioID AS servicio_id,
                    ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre,
                    tc.TipoContratoID AS tipo_contrato_id,
                    ISNULL(tc.Nombre, 'CAS') AS condicion_laboral,
                    con.NumeroContrato AS numero_contrato,
                    CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 'ADMINISTRATIVO' ELSE 'ASISTENCIAL' END AS regimen_laboral,
                    CASE WHEN c.Nombre LIKE '%Admin%' OR c.Nombre LIKE '%Inform%' THEN 0 ELSE 1 END AS aplica_seti
                FROM rrhh.Personal p
                INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                LEFT JOIN rrhh.Profesion prof ON ap.ProfesionID = prof.ProfesionID
                LEFT JOIN rrhh.Especialidad esp ON ap.EspecialidadID = esp.EspecialidadID
                LEFT JOIN organizacion.Area a ON ap.AreaID = a.AreaID
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
                LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
                WHERE 1=1
                ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
            ";
            $personalList = $pdo->query($sqlPersonal)->fetchAll(PDO::FETCH_ASSOC);
            if (is_array($personalList)) {
                self::set('personal_catalogo_completo', $personalList, ['source' => 'SQL_SERVER_SYNC']);
                $results['personal'] = count($personalList);
            }
        } catch (Throwable $e) {
            $results['personal_error'] = $e->getMessage();
        }

        // 2. Turnos Parametrizados Oficiales
        try {
            $sqlTurnos = "SELECT TurnoID, Nombre, HoraInicio, HoraFin, Estado FROM rrhh.Turno ORDER BY TurnoID";
            $rawTurnos = $pdo->query($sqlTurnos)->fetchAll(PDO::FETCH_ASSOC);
            $turnos = [];
            foreach ($rawTurnos as $r) {
                $inicio = substr($r['HoraInicio'], 0, 5);
                $fin = substr($r['HoraFin'], 0, 5);
                $time1 = strtotime($inicio);
                $time2 = strtotime($fin);
                $cruza = ($time2 < $time1);
                $diffHours = $cruza ? ((strtotime('24:00') - $time1 + $time2 - strtotime('00:00')) / 3600) : (($time2 - $time1) / 3600);
                
                if (stripos($r['Nombre'], 'Mañana') !== false) {
                    $codigo = 'M';
                } elseif (stripos($r['Nombre'], 'Tarde') !== false) {
                    $codigo = 'T';
                } elseif (stripos($r['Nombre'], 'Nocturna') !== false || stripos($r['Nombre'], 'Noche') !== false) {
                    $codigo = 'N';
                } elseif (stripos($r['Nombre'], 'Diurna') !== false) {
                    $codigo = 'GD';
                } elseif (stripos($r['Nombre'], 'Admin') !== false) {
                    $codigo = 'ADM';
                } elseif (stripos($r['Nombre'], 'Retén') !== false) {
                    $codigo = 'RET';
                } else {
                    $codigo = strtoupper(mb_substr($r['Nombre'], 0, 3, 'UTF-8'));
                }
                $esGuardia = ($diffHours >= 11.5) ? 1 : 0;
                $turnos[] = [
                    'turno_id'          => intval($r['TurnoID']),
                    'codigo'            => $codigo,
                    'nombre'            => $r['Nombre'],
                    'descripcion'       => "Turno institucional de $inicio a $fin",
                    'hora_inicio'       => $inicio,
                    'hora_fin'          => $fin,
                    'horas_computables' => round($diffHours, 2),
                    'cruza_medianoche'  => $cruza ? 1 : 0,
                    'es_guardia'        => $esGuardia,
                    'color'             => $cruza ? '#7c3aed' : ($diffHours >= 11 ? '#0d9488' : '#0284c7'),
                    'estado'            => intval($r['Estado'])
                ];
            }
            if (is_array($turnos)) {
                self::set('turnos_catalogo_oficial', $turnos, ['source' => 'SQL_SERVER_SYNC']);
                $results['turnos'] = count($turnos);
            }
        } catch (Throwable $e) {
            $results['turnos_error'] = $e->getMessage();
        }

        // 3. Horarios y Pautas Semanales
        try {
            $sqlHorarios = "
                SELECT 
                    h.HorarioID,
                    h.PersonalID,
                    h.DiaSemana,
                    h.FechaInicio,
                    h.FechaFin,
                    t.TurnoID,
                    t.Nombre AS TurnoNombre,
                    CONVERT(VARCHAR(5), t.HoraInicio, 108) AS HoraInicio,
                    CONVERT(VARCHAR(5), t.HoraFin, 108) AS HoraFin,
                    CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS NombreCompleto,
                    per.NumeroDocumento AS DNI,
                    p.CodigoEmpleado,
                    ISNULL(c.Nombre, 'Sin Cargo') AS CargoNombre,
                    s.ServicioID, ISNULL(s.Nombre, 'Sin Servicio') AS ServicioNombre
                FROM rrhh.Horario h
                INNER JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
                INNER JOIN rrhh.Personal p ON h.PersonalID = p.PersonalID
                INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                WHERE h.Estado = 1 AND p.Estado = 1
                ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres, h.DiaSemana
            ";
            $rawHorarios = $pdo->query($sqlHorarios)->fetchAll(PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rawHorarios as $r) {
                $pid = intval($r['PersonalID']);
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
                $inicio = $r['HoraInicio'];
                $fin = $r['HoraFin'];
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
            if (is_array($horariosPersonal)) {
                self::set('horarios_lista_completa', $horariosPersonal, ['source' => 'SQL_SERVER_SYNC']);
                $results['horarios'] = count($horariosPersonal);
            }
        } catch (Throwable $e) {
            $results['horarios_error'] = $e->getMessage();
        }

        // 4. Asistencias Diarias Recientes
        try {
            $fechasRecientes = $pdo->query("SELECT DISTINCT TOP 7 Fecha FROM rrhh.Asistencia ORDER BY Fecha DESC")->fetchAll(PDO::FETCH_COLUMN);
            $totalAsistenciasSync = 0;
            foreach ($fechasRecientes as $f) {
                $sqlAsist = "
                    SELECT 
                        p.PersonalID,
                        per.NumeroDocumento AS DNI,
                        per.Nombres,
                        per.ApellidoPaterno,
                        per.ApellidoMaterno,
                        per.Telefono,
                        per.Correo AS CorreoElectronico,
                        ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                        ISNULL(prof.Nombre, 'General') AS NombreProfesion,
                        s.ServicioID,
                        ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                        'ASISTENCIAL' AS RegimenLaboral,
                        ISNULL(tc.Nombre, 'CAS') AS CondicionLaboral,
                        t.TurnoID,
                        ISNULL(t.Nombre, 'Mañana Ordinario') AS NombreTurno,
                        ISNULL(CONVERT(VARCHAR(8), t.HoraInicio, 108), '07:00:00') AS HoraProgInicio,
                        ISNULL(CONVERT(VARCHAR(8), t.HoraFin, 108), '13:00:00') AS HoraProgFin,
                        a.AsistenciaID,
                        CONVERT(VARCHAR(8), a.HoraEntrada, 108) AS HoraEntrada,
                        CONVERT(VARCHAR(8), a.HoraSalida, 108) AS HoraSalida,
                        a.Estado AS EstadoAsistencia,
                        a.Observacion
                    FROM rrhh.Personal p
                    INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
                    LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
                    LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                    LEFT JOIN rrhh.Profesion prof ON ap.ProfesionID = prof.ProfesionID
                    LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                    LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
                    LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
                    LEFT JOIN rrhh.Horario h ON p.PersonalID = h.PersonalID AND h.DiaSemana = :diaSemana AND h.Estado = 1 AND h.FechaInicio <= :desde AND (h.FechaFin IS NULL OR h.FechaFin >= :hasta)
                    LEFT JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
                    LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
                    WHERE p.Estado = 1
                    ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
                ";
                $stmtA = $pdo->prepare($sqlAsist);
                $stmtA->execute([':diaSemana'=>(int)date('N', strtotime($f)), ':desde'=>$f, ':hasta'=>$f, ':fechaAsist'=>$f]);
                $asistRows = $stmtA->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($asistRows)) {
                    $asistFormatted = [];
                    foreach ($asistRows as $ar) {
                        $nombreCompleto = trim("{$ar['ApellidoPaterno']} {$ar['ApellidoMaterno']} {$ar['Nombres']}");
                        require_once __DIR__ . '/attendance_evaluator.php';
                        $asistFormatted[] = procesarFilaAsistencia($ar, $nombreCompleto, $f);
                    }
                    self::set('asistencia_diaria_' . $f, $asistFormatted, ['source' => 'SQL_SERVER_SYNC', 'fecha' => $f]);
                    $totalAsistenciasSync += count($asistFormatted);
                }
            }
            $results['asistencias'] = $totalAsistenciasSync;
        } catch (Throwable $e) {
            $results['asistencias_error'] = $e->getMessage();
        }

        // 5. Permisos y Ausencias
        try {
            $sqlPermisos = "
                SELECT 
                    pl.PermisoLaboralID, pl.PersonalID, per.NumeroDocumento AS DNI,
                    per.Nombres, per.ApellidoPaterno, per.ApellidoMaterno,
                    ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                    s.ServicioID, ISNULL(s.Nombre, 'Servicio General') AS NombreServicio,
                    ISNULL(tc.Nombre, 'CAS') AS CondicionLaboral,
                    tpl.TipoPermisoLaboralID, tpl.Nombre AS TipoPermisoNombre,
                    pl.FechaInicio, pl.FechaFin, pl.Motivo, pl.Estado
                FROM rrhh.PermisoLaboral pl
                INNER JOIN rrhh.Personal p ON pl.PersonalID = p.PersonalID
                INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
                INNER JOIN rrhh.TipoPermisoLaboral tpl ON pl.TipoPermisoLaboralID = tpl.TipoPermisoLaboralID
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
                LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
                ORDER BY pl.PermisoLaboralID DESC
            ";
            $rawPermisos = $pdo->query($sqlPermisos)->fetchAll(PDO::FETCH_ASSOC);
            $permisos = [];
            foreach ($rawPermisos as $r) {
                $nombreCompleto = trim("{$r['ApellidoPaterno']} {$r['ApellidoMaterno']} {$r['Nombres']}");
                $t1 = strtotime($r['FechaInicio']);
                $t2 = strtotime($r['FechaFin']);
                $dias = ($t1 && $t2) ? max(1, intval(($t2 - $t1) / 86400) + 1) : 1;
                $permisos[] = [
                    'permiso_id'          => intval($r['PermisoLaboralID']),
                    'personal_id'         => intval($r['PersonalID']),
                    'dni'                 => $r['DNI'],
                    'nombre_completo'     => $nombreCompleto,
                    'cargo'               => $r['NombreCargo'],
                    'servicio_id'         => intval($r['ServicioID'] ?? 1),
                    'servicio_nombre'     => $r['NombreServicio'],
                    'condicion_laboral'   => $r['CondicionLaboral'],
                    'tipo_id'             => intval($r['TipoPermisoLaboralID']),
                    'tipo_nombre'         => $r['TipoPermisoNombre'],
                    'fecha_inicio'        => $r['FechaInicio'],
                    'fecha_fin'           => $r['FechaFin'],
                    'dias_solicitados'    => $dias,
                    'motivo'              => $r['Motivo'] ?? '',
                    'documento_sustento'  => 'Papeleta N° ' . str_pad($r['PermisoLaboralID'], 4, '0', STR_PAD_LEFT),
                    'goce_haber'          => 1,
                    'estado'              => $r['Estado'],
                    'fecha_solicitud'     => $r['FechaInicio']
                ];
            }
            if (is_array($permisos)) {
                self::set('permisos_lista_completa', $permisos, ['source' => 'SQL_SERVER_SYNC']);
                $results['permisos'] = count($permisos);
            }
        } catch (Throwable $e) {
            $results['permisos_error'] = $e->getMessage();
        }

        return $results;
    }
}

