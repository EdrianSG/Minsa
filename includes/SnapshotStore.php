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
        require_once __DIR__ . '/../config/database.php';
        return getenv('SNAPSHOT_PATH') ?: self::$storagePath . '/' . Database::sourceId();
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
        require_once __DIR__ . '/../config/database.php';
        $extraMeta['database_source'] = Database::sourceId();
        self::ensureDirectory();

        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $filePath = self::directory() . '/' . $safeKey . '.json';

        $lock = @fopen($filePath . '.lock', 'c');
        if (!$lock) return false;
        $temp = false;
        try {
            if (!flock($lock, LOCK_EX)) return false;
            $existing = self::get($key);
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
            $rootFallback = self::$storagePath . '/' . $safeKey . '.json';
            if (file_exists($rootFallback)) {
                $filePath = $rootFallback;
            } else {
                return null;
            }
        }

        $content = @file_get_contents($filePath);
        if (!$content) {
            return null;
        }

        $decoded = @json_decode($content, true);
        if (!$decoded || !isset($decoded['data'])) {
            return null;
        }

        require_once __DIR__ . '/../config/database.php';
        $isAux = class_exists('Auth', false) && Auth::isAuxiliarySession();
        if (!$isAux && ($decoded['_meta']['database_source'] ?? null) !== Database::sourceId()) return null;

        return $decoded;
    }

    /**
     * Comprueba si existe un snapshot para una clave
     */
    public static function has(string $key): bool {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return self::get($key) !== null;
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
        require_once __DIR__.'/WorkloadPolicy.php';
        $workloadSql=WorkloadPolicy::sql();
        $results = [];

        // 1. Personal Completo
        try {
            $sqlPersonal = "
                SELECT 
                    p.PersonalID AS personal_id,
                    p.CodigoEmpleado AS codigo_empleado,
                    p.FechaIngreso AS fecha_ingreso,
                    p.FechaCese AS fecha_cese,
                    p.Activo AS estado,
                    p.NumeroDocumento AS dni,
                    p.NombreCompletoOriginal AS nombre_completo_original,
                    p.DepartamentoOrigen AS departamento_origen,
                    p.CargoOrigen AS cargo_origen,
                    p.GrupoOcupacional AS grupo_ocupacional,
                    {$workloadSql} AS tipo_personal,
                    (SELECT TOP 1 v.BiostarUserID FROM rrhh.VinculacionBiometrica v WHERE v.PersonalID=p.PersonalID ORDER BY v.VinculacionBiometricaID) AS biostar_user_id,
                    p.Nombres AS nombres,
                    p.ApellidoPaterno AS apellido_paterno,
                    p.ApellidoMaterno AS apellido_materno,
                    COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, ''), ' ', p.Nombres))), ''), p.NombreCompletoOriginal, p.CodigoEmpleado) AS nombre_completo,
                    p.Celular AS celular,
                    p.Correo AS correo,
                    p.Telefono AS telefono,
                    c.CargoID AS cargo_id,
                    ISNULL(c.Nombre, 'Sin Cargo') AS cargo_nombre,
                    prof.ProfesionID AS profesion_id,
                    prof.Nombre AS profesion_nombre,
                    esp.EspecialidadID AS especialidad_id,
                    esp.Nombre AS especialidad_nombre,
                    a.AreaID AS area_id,
                    a.Nombre AS area_nombre,
                    s.ServicioID AS servicio_id,
                    s.Nombre AS servicio_nombre,
                    tc.TipoContratoID AS tipo_contrato_id,
                    p.CondicionLaboral AS condicion_laboral,
                    con.NumeroContrato AS numero_contrato,
                    {$workloadSql} AS regimen_laboral,
                    CASE WHEN {$workloadSql} = 'ASISTENCIAL' THEN 1 ELSE NULL END AS aplica_seti
                FROM rrhh.Personal p
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                LEFT JOIN rrhh.Profesion prof ON p.ProfesionID = prof.ProfesionID
                LEFT JOIN rrhh.Especialidad esp ON p.EspecialidadID = esp.EspecialidadID
                LEFT JOIN organizacion.Area a ON ap.AreaID = a.AreaID
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
                LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
                ORDER BY p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres
            ";
            $personalList = $pdo->query($sqlPersonal)->fetchAll(PDO::FETCH_ASSOC);
            if (is_array($personalList)) {
                if (!self::set('personal_catalogo_completo', $personalList, ['source' => 'SQL_SERVER_SYNC'])) throw new RuntimeException('No se pudo escribir el respaldo actualizado.');
                $results['personal'] = count($personalList);
            }
        } catch (Throwable $e) {
            $results['personal_error'] = $e->getMessage();
        }

        // 2. Turnos Parametrizados Oficiales
        try {
            require_once __DIR__ . '/TurnoCatalog.php';
            $turnos = TurnoCatalog::load($pdo);
            if (is_array($turnos)) {
                if (!self::set('turnos_catalogo_oficial', $turnos, ['source' => 'SQL_SERVER_SYNC'])) throw new RuntimeException('No se pudo escribir el respaldo actualizado.');
                $results['turnos'] = count($turnos);
            }
        } catch (Throwable $e) {
            $results['turnos_error'] = $e->getMessage();
        }

        // 3. Horarios y Pautas Semanales
        try {
            // En V3 la programación mensual reside en rrhh.ProgramacionMensual y Detalle
            $results['horarios'] = 0;
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
                        p.NumeroDocumento AS DNI,
                        p.Nombres,
                        p.ApellidoPaterno,
                        p.ApellidoMaterno,
                        p.Telefono,
                        p.Correo AS CorreoElectronico,
                        ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                        ISNULL(prof.Nombre, 'General') AS NombreProfesion,
                        s.ServicioID,
                        ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                        {$workloadSql} AS RegimenLaboral,
                        p.CondicionLaboral AS CondicionLaboral,
                        NULL AS TurnoID,
                        NULL AS NombreTurno,
                        NULL AS HoraProgInicio,
                        NULL AS HoraProgFin,
                        a.AsistenciaID,
                        CONVERT(VARCHAR(8), a.HoraEntrada, 108) AS HoraEntrada,
                        CONVERT(VARCHAR(8), a.HoraSalida, 108) AS HoraSalida,
                        a.Estado AS EstadoAsistencia,
                        a.Observacion
                    FROM rrhh.Personal p
                    LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                    LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                    LEFT JOIN rrhh.Profesion prof ON p.ProfesionID = prof.ProfesionID
                    LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                    OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
                    LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
                    LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
                    WHERE p.Activo = 1
                    ORDER BY p.ApellidoPaterno, p.ApellidoMaterno, p.Nombres
                ";
                $stmtA = $pdo->prepare($sqlAsist);
                $stmtA->execute([':fechaAsist'=>$f]);
                $asistRows = $stmtA->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($asistRows)) {
                    $asistFormatted = [];
                    foreach ($asistRows as $ar) {
                        $nombreCompleto = trim("{$ar['ApellidoPaterno']} {$ar['ApellidoMaterno']} {$ar['Nombres']}");
                        require_once __DIR__ . '/attendance_evaluator.php';
                        $asistFormatted[] = procesarFilaAsistencia($ar, $nombreCompleto, $f);
                    }
                    if (!self::set('asistencia_diaria_' . $f, $asistFormatted, ['source' => 'SQL_SERVER_SYNC', 'fecha' => $f])) throw new RuntimeException('No se pudo escribir el respaldo actualizado.');
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
                    pl.PermisoLaboralID, pl.PersonalID, p.NumeroDocumento AS DNI,
                    p.Nombres, p.ApellidoPaterno, p.ApellidoMaterno,
                    ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                    s.ServicioID, ISNULL(s.Nombre, 'Servicio General') AS NombreServicio,
                    p.CondicionLaboral AS CondicionLaboral,
                    tpl.TipoPermisoLaboralID, tpl.Nombre AS TipoPermisoNombre,
                    pl.FechaInicio, pl.FechaFin, pl.Motivo, pl.Estado
                FROM rrhh.PermisoLaboral pl
                INNER JOIN rrhh.Personal p ON pl.PersonalID = p.PersonalID
                INNER JOIN rrhh.TipoPermisoLaboral tpl ON pl.TipoPermisoLaboralID = tpl.TipoPermisoLaboralID
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
                LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
                WHERE p.Activo = 1
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
                    'servicio_id'         => isset($r['ServicioID']) ? intval($r['ServicioID']) : null,
                    'servicio_nombre'     => $r['NombreServicio'],
                    'condicion_laboral'   => $r['CondicionLaboral'],
                    'tipo_id'             => intval($r['TipoPermisoLaboralID']),
                    'tipo_nombre'         => $r['TipoPermisoNombre'],
                    'fecha_inicio'        => $r['FechaInicio'],
                    'fecha_fin'           => $r['FechaFin'],
                    'dias_solicitados'    => $dias,
                    'motivo'              => $r['Motivo'] ?? '',
                    'documento_sustento'  => preg_match('/^Sustento: (.*?)\. Motivo:/us', (string)$r['Motivo'], $sustento) ? ($sustento[1] ?: null) : null,
                    'goce_haber'          => null,
                    'estado'              => $r['Estado'],
                    'fecha_solicitud'     => $r['FechaInicio']
                ];
            }
            if (is_array($permisos)) {
                if (!self::set('permisos_lista_completa', $permisos, ['source' => 'SQL_SERVER_SYNC'])) throw new RuntimeException('No se pudo escribir el respaldo actualizado.');
                $results['permisos'] = count($permisos);
            }
        } catch (Throwable $e) {
            $results['permisos_error'] = $e->getMessage();
        }

        return $results;
    }
}

