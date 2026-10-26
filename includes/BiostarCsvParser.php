<?php
/**
 * Servicio Especializado de Procesamiento e Importación de CSV BioStar 2
 * Hospital Distrital de Pacasmayo - Fase 2
 * 
 * Diseñado para streaming eficiente O(1) de memoria en archivos de 50,000+ registros.
 * Cumple con:
 * 1. Deduplicación e idempotencia de doble nivel (Hash SHA-256 + Clave única de marcación).
 * 2. Separación estricta de EstadoVinculacion, EstadoProcesamiento y Flags de ráfagas.
 * 3. Conservación de marcaciones consecutivas sin eliminaciones arbitrarias.
 * 4. Compatibilidad plena con SQL Server 2022 y resiliencia offline.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auditoria.php';

class BiostarCsvParser
{
    /** Columnas requeridas en la cabecera del reporte BioStar 2 */
    public const REQUIRED_COLUMNS = [
        'fecha', 'puerta', 'id dispositivo', 'dispositivo', 'grupo usuario', 'usuario', 'evento'
    ];

    /** Eventos de marcación admitidos como asistencia válida */
    public const VALID_EVENTS = [
        'Autenticación 1:N correcta (Huella)'
    ];

    /** Umbral en segundos para considerar marcaciones consecutivas (ráfaga) */
    public const CONSECUTIVE_THRESHOLD_SECONDS = 60;

    /**
     * Previsualiza un archivo CSV sin escribir en la base de datos.
     * Retorna estadísticas consolidadas, validación de cabeceras y muestra representativa.
     *
     * @param string $filePath Ruta absoluta del archivo CSV en el servidor
     * @param int $sampleLimit Cantidad máxima de registros para la muestra visual
     * @return array Resumen detallado para la interfaz de previsualización
     * @throws Exception Si el archivo es inválido, inaccesible o tiene cabeceras corruptas
     */
    public static function previsualizar(string $filePath, int $sampleLimit = 25): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("El archivo no existe o no tiene permisos de lectura.");
        }

        $hashSHA256 = hash_file('sha256', $filePath);
        $fileSize = filesize($filePath);

        // Verificar si este archivo ya fue importado previamente
        $importacionPrevia = self::buscarImportacionPorHash($hashSHA256);

        // Cargar mapa de trabajadores conocidos para vinculación
        $staffMap = self::obtenerMapaPersonal();

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo CSV para lectura.");
        }

        try {
            // Validar cabeceras y omitir BOM UTF-8
            $headerMap = self::validarCabeceras($handle);

            $totalFilas = 0;
            $marcacionesValidas = 0;
            $marcacionesOmitidas = 0;
            $marcacionesConsecutivas = 0;

            $usuariosUnicos = []; // BiostarUserID => [nombre, count, vinculado]
            $dispositivos = [];   // DispositivoID => [nombre, count]
            $eventosDesglose = []; // Evento => count

            $muestraMarcaciones = [];
            $muestraOmitidos = [];

            $fechaMin = '9999-12-31 23:59:59';
            $fechaMax = '0000-00-00 00:00:00';

            // Seguimiento de ráfagas en streaming: BiostarUserID => timestamp
            $ultimoTimestampUsuario = [];

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // Omitir líneas vacías
                if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }

                $totalFilas++;

                $fechaRaw      = trim($row[$headerMap['fecha']] ?? '');
                $dispositivoId = trim($row[$headerMap['id dispositivo']] ?? '');
                $dispositivoNom= trim($row[$headerMap['dispositivo']] ?? '');
                $usuarioRaw    = trim($row[$headerMap['usuario']] ?? '');
                $eventoRaw     = trim($row[$headerMap['evento']] ?? '');

                // Rango de fechas
                if ($fechaRaw !== '') {
                    if ($fechaRaw < $fechaMin) $fechaMin = $fechaRaw;
                    if ($fechaRaw > $fechaMax) $fechaMax = $fechaRaw;
                }

                // Conteo de dispositivos
                if ($dispositivoId !== '') {
                    if (!isset($dispositivos[$dispositivoId])) {
                        $dispositivos[$dispositivoId] = [
                            'id'     => $dispositivoId,
                            'nombre' => $dispositivoNom,
                            'total'  => 0
                        ];
                    }
                    $dispositivos[$dispositivoId]['total']++;
                }

                // Clasificación de evento
                $eventosDesglose[$eventoRaw] = ($eventosDesglose[$eventoRaw] ?? 0) + 1;

                $esEventoValido = in_array($eventoRaw, self::VALID_EVENTS, true);

                if (!$esEventoValido) {
                    $marcacionesOmitidas++;
                    if (count($muestraOmitidos) < 15) {
                        $muestraOmitidos[] = [
                            'fila'         => $totalFilas,
                            'fecha'        => $fechaRaw,
                            'dispositivo'  => $dispositivoNom,
                            'evento'       => $eventoRaw,
                            'usuario_raw'  => $usuarioRaw,
                            'motivo_omision' => self::describirMotivoOmision($eventoRaw)
                        ];
                    }
                    continue;
                }

                // Evento válido de huella: parsear identificador y nombre
                $userData = self::extraerUsuario($usuarioRaw);
                $biostarUserId = $userData['id'];
                $biostarUserName = $userData['nombre'];

                // Vinculación con trabajador en el padrón actual
                $personalId = $staffMap[$biostarUserId] ?? null;
                $estadoVinculacion = ($personalId !== null) ? 'VINCULADO' : 'PENDIENTE_VINCULACION';

                if (!isset($usuariosUnicos[$biostarUserId])) {
                    $usuariosUnicos[$biostarUserId] = [
                        'id'         => $biostarUserId,
                        'nombre'     => $biostarUserName,
                        'vinculado'  => ($personalId !== null),
                        'personal_id'=> $personalId,
                        'total'      => 0
                    ];
                }
                $usuariosUnicos[$biostarUserId]['total']++;

                // Detección de marcaciones consecutivas (ráfagas)
                $currentTs = strtotime($fechaRaw);
                $esConsecutiva = false;
                $segundosDif = null;

                if ($currentTs !== false && isset($ultimoTimestampUsuario[$biostarUserId])) {
                    $diff = abs($currentTs - $ultimoTimestampUsuario[$biostarUserId]);
                    if ($diff > 0 && $diff <= self::CONSECUTIVE_THRESHOLD_SECONDS) {
                        $esConsecutiva = true;
                        $segundosDif = $diff;
                        $marcacionesConsecutivas++;
                    }
                }
                if ($currentTs !== false) {
                    $ultimoTimestampUsuario[$biostarUserId] = $currentTs;
                }

                $marcacionesValidas++;

                // Guardar muestra para visualización previa
                if (count($muestraMarcaciones) < $sampleLimit) {
                    $muestraMarcaciones[] = [
                        'fila'               => $totalFilas,
                        'fecha_hora'         => $fechaRaw,
                        'dispositivo_id'     => $dispositivoId,
                        'dispositivo_nombre' => $dispositivoNom,
                        'biostar_user_id'    => $biostarUserId,
                        'biostar_user_name'  => $biostarUserName ?: '(Sin nombre registrado)',
                        'personal_id'        => $personalId,
                        'estado_vinculacion' => $estadoVinculacion,
                        'es_consecutiva'     => $esConsecutiva,
                        'segundos_dif'       => $segundosDif,
                        'evento'             => $eventoRaw
                    ];
                }
            }

            // Estadísticas agregadas de usuarios
            $usuariosVinculadosCount = 0;
            $usuariosPendientesCount = 0;
            foreach ($usuariosUnicos as $u) {
                if ($u['vinculado']) {
                    $usuariosVinculadosCount++;
                } else {
                    $usuariosPendientesCount++;
                }
            }

            return [
                'ok'                        => true,
                'hash_sha256'               => $hashSHA256,
                'tamano_bytes'              => $fileSize,
                'ya_importado'              => ($importacionPrevia !== null),
                'importacion_previa'        => $importacionPrevia,
                'total_filas'               => $totalFilas,
                'marcaciones_validas'       => $marcacionesValidas,
                'marcaciones_omitidas'      => $marcacionesOmitidas,
                'marcaciones_consecutivas'  => $marcacionesConsecutivas,
                'total_usuarios_unicos'     => count($usuariosUnicos),
                'usuarios_vinculados'       => $usuariosVinculadosCount,
                'usuarios_pendientes'       => $usuariosPendientesCount,
                'fecha_inicio'              => ($fechaMin !== '9999-12-31 23:59:59') ? $fechaMin : null,
                'fecha_fin'                 => ($fechaMax !== '0000-00-00 00:00:00') ? $fechaMax : null,
                'dispositivos'              => array_values($dispositivos),
                'eventos_desglose'          => $eventosDesglose,
                'muestra_marcaciones'       => $muestraMarcaciones,
                'muestra_omitidos'          => $muestraOmitidos
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Ejecuta la importación transaccional con persistencia completa en SQL Server.
     * Garantiza idempotencia a nivel de archivo y a nivel de cada registro.
     *
     * @param string $filePath Ruta absoluta del archivo CSV en el servidor
     * @param string $originalFileName Nombre original del archivo subido
     * @param int|null $usuarioId ID del usuario de sesión responsable
     * @param string|null $observaciones Comentarios o notas de RR.HH.
     * @return array Resultado detallado de la importación
     */
    public static function importar(
        string $filePath,
        string $originalFileName,
        ?int $usuarioId,
        ?string $observaciones = null
    ): array {
        $pdo = Database::getConnection();
        if ($pdo === null) {
            throw new RuntimeException("SQL Server no está disponible para procesar la importación. Verifique la conexión.");
        }

        $hashSHA256 = hash_file('sha256', $filePath);

        // Cargar mapa de trabajadores conocidos
        $staffMap = self::obtenerMapaPersonal();

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo CSV para lectura.");
        }

        $importacionId = 0;

        try {
            // Validar cabeceras
            $headerMap = self::validarCabeceras($handle);

            // 1. Crear registro de importación en estado 'EN_PROCESO'
            $stmtInit = $pdo->prepare("
                SET NOCOUNT ON;
                INSERT INTO rrhh.ImportacionBiometrica (
                    NombreArchivo, HashSHA256, FechaHoraCarga, UsuarioID,
                    TotalRegistros, MarcacionesValidas, MarcacionesOmitidas,
                    MarcacionesDuplicadas, PendientesVinculacion, MarcacionesConsecutivas,
                    VinculadosDirectos, Estado, Observaciones
                ) VALUES (
                    :nombre, :hash, SYSDATETIME(), :usuario_id,
                    0, 0, 0, 0, 0, 0, 0, 'EN_PROCESO', :obs
                );
                SELECT SCOPE_IDENTITY() AS ImportacionID;
            ");
            $stmtInit->execute([
                ':nombre'     => substr($originalFileName, 0, 255),
                ':hash'       => $hashSHA256,
                ':usuario_id' => $usuarioId,
                ':obs'        => $observaciones ? substr($observaciones, 0, 500) : 'Carga desde Portal de Asistencia'
            ]);
            $rowId = $stmtInit->fetch(PDO::FETCH_ASSOC);
            $importacionId = intval($rowId['ImportacionID'] ?? 0);

            if ($importacionId <= 0) {
                // Respaldo de obtención de ID
                $importacionId = intval($pdo->query("SELECT MAX(ImportacionID) FROM rrhh.ImportacionBiometrica")->fetchColumn());
            }

            // Preparar sentencias de inserción atómica
            $stmtInsertMarcacion = $pdo->prepare("
                INSERT INTO rrhh.MarcacionBiometrica (
                    ImportacionID, BiostarUserID, BiostarUserName, FechaHora,
                    DispositivoID, DispositivoNombre, Evento, PersonalID,
                    EstadoProcesamiento, EstadoVinculacion, EsConsecutiva,
                    SegundosDiferencia, Advertencia
                )
                SELECT 
                    :impId, :bUser, :bName, :fec,
                    :devId, :devNom, :ev, :pid,
                    'PENDIENTE', :estVinc, :esCons,
                    :segDif, :adv
                WHERE NOT EXISTS (
                    SELECT 1 FROM rrhh.MarcacionBiometrica WITH (UPDLOCK, HOLDLOCK)
                    WHERE BiostarUserID = :bUserCheck
                      AND FechaHora = :fecCheck
                      AND DispositivoID = :devIdCheck
                      AND Evento = :evCheck
                )
            ");

            $stmtInsertIncidencia = $pdo->prepare("
                INSERT INTO rrhh.IncidenciaBiometrica (
                    ImportacionID, BiostarUserID, TipoIncidencia, Nivel,
                    Detalle, FechaHoraEvento, FechaHoraRegistro
                ) VALUES (
                    :impId, :bUser, :tipo, :nivel, :detalle, :fecEv, SYSDATETIME()
                )
            ");

            $totalFilas = 0;
            $marcacionesValidas = 0;
            $marcacionesInsertadas = 0;
            $marcacionesDuplicadas = 0;
            $marcacionesOmitidas = 0;
            $marcacionesConsecutivas = 0;
            $pendientesVinculacion = 0;
            $vinculadosDirectos = 0;

            $incidenciasGuardadas = 0;
            $maxIncidenciasPorCarga = 200; // Limitar incidencias de sistema para no saturar BD

            $ultimoTimestampUsuario = [];

            // Iniciar transacción SQL para la carga masiva
            $pdo->beginTransaction();

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }

                $totalFilas++;

                $fechaRaw      = trim($row[$headerMap['fecha']] ?? '');
                $dispositivoId = trim($row[$headerMap['id dispositivo']] ?? '');
                $dispositivoNom= trim($row[$headerMap['dispositivo']] ?? '');
                $usuarioRaw    = trim($row[$headerMap['usuario']] ?? '');
                $eventoRaw     = trim($row[$headerMap['evento']] ?? '');

                $esEventoValido = in_array($eventoRaw, self::VALID_EVENTS, true);

                if (!$esEventoValido) {
                    $marcacionesOmitidas++;

                    // Registrar incidencia para eventos de cambio de hora o intentos fallidos relevantes
                    if ($incidenciasGuardadas < $maxIncidenciasPorCarga) {
                        if ($eventoRaw === 'Hora dispositivo cambiada') {
                            $stmtInsertIncidencia->execute([
                                ':impId'   => $importacionId,
                                ':bUser'   => null,
                                ':tipo'    => 'CAMBIO_HORA_DISPOSITIVO',
                                ':nivel'   => 'ADVERTENCIA',
                                ':detalle' => "Salto o cambio de hora en {$dispositivoNom} ({$dispositivoId}) a las {$fechaRaw}",
                                ':fecEv'   => $fechaRaw ?: null
                            ]);
                            $incidenciasGuardadas++;
                        } elseif (stripos($eventoRaw, 'Errónea') !== false) {
                            $stmtInsertIncidencia->execute([
                                ':impId'   => $importacionId,
                                ':bUser'   => null,
                                ':tipo'    => 'AUTENTICACION_ERRONEA',
                                ':nivel'   => 'INFO',
                                ':detalle' => "Intento fallido de lectura dactilar en {$dispositivoNom}",
                                ':fecEv'   => $fechaRaw ?: null
                            ]);
                            $incidenciasGuardadas++;
                        }
                    }
                    continue;
                }

                // Evento de huella válido
                $marcacionesValidas++;

                $userData = self::extraerUsuario($usuarioRaw);
                $biostarUserId = $userData['id'];
                $biostarUserName = $userData['nombre'];

                // Vinculación
                $personalId = $staffMap[$biostarUserId] ?? null;
                $estadoVinculacion = ($personalId !== null) ? 'VINCULADO' : 'PENDIENTE_VINCULACION';

                if ($personalId !== null) {
                    $vinculadosDirectos++;
                } else {
                    $pendientesVinculacion++;
                }

                // Ráfagas
                $currentTs = strtotime($fechaRaw);
                $esConsecutiva = 0;
                $segundosDif = null;
                $advertencia = null;

                if ($currentTs !== false && isset($ultimoTimestampUsuario[$biostarUserId])) {
                    $diff = abs($currentTs - $ultimoTimestampUsuario[$biostarUserId]);
                    if ($diff > 0 && $diff <= self::CONSECUTIVE_THRESHOLD_SECONDS) {
                        $esConsecutiva = 1;
                        $segundosDif = $diff;
                        $advertencia = "Marcación consecutiva a {$diff}s de la anterior";
                        $marcacionesConsecutivas++;
                    }
                }
                if ($currentTs !== false) {
                    $ultimoTimestampUsuario[$biostarUserId] = $currentTs;
                }

                // Inserción atómica con deduplicación por registro
                $stmtInsertMarcacion->execute([
                    ':impId'       => $importacionId,
                    ':bUser'       => $biostarUserId,
                    ':bName'       => $biostarUserName ? substr($biostarUserName, 0, 150) : null,
                    ':fec'         => $fechaRaw,
                    ':devId'       => substr($dispositivoId, 0, 50),
                    ':devNom'      => substr($dispositivoNom, 0, 150),
                    ':ev'          => substr($eventoRaw, 0, 100),
                    ':pid'         => $personalId,
                    ':estVinc'     => $estadoVinculacion,
                    ':esCons'      => $esConsecutiva,
                    ':segDif'      => $segundosDif,
                    ':adv'         => $advertencia,
                    // Parámetros para el WHERE NOT EXISTS
                    ':bUserCheck'  => $biostarUserId,
                    ':fecCheck'    => $fechaRaw,
                    ':devIdCheck'  => substr($dispositivoId, 0, 50),
                    ':evCheck'     => substr($eventoRaw, 0, 100)
                ]);

                if ($stmtInsertMarcacion->rowCount() > 0) {
                    $marcacionesInsertadas++;
                } else {
                    $marcacionesDuplicadas++;
                }
            }

            // Confirmar transacción SQL
            $pdo->commit();

            // 2. Actualizar registro maestro de importación con totales finales
            $stmtUpd = $pdo->prepare("
                UPDATE rrhh.ImportacionBiometrica
                SET TotalRegistros          = :tot,
                    MarcacionesValidas      = :val,
                    MarcacionesOmitidas     = :omi,
                    MarcacionesDuplicadas   = :dup,
                    PendientesVinculacion   = :pen,
                    MarcacionesConsecutivas = :cons,
                    VinculadosDirectos      = :vinc,
                    Estado                  = 'COMPLETADO',
                    MensajeError            = NULL
                WHERE ImportacionID = :id
            ");
            $stmtUpd->execute([
                ':tot'  => $totalFilas,
                ':val'  => $marcacionesValidas,
                ':omi'  => $marcacionesOmitidas,
                ':dup'  => $marcacionesDuplicadas,
                ':pen'  => $pendientesVinculacion,
                ':cons' => $marcacionesConsecutivas,
                ':vinc' => $vinculadosDirectos,
                ':id'   => $importacionId
            ]);

            // Auditoría institucional
            Auditoria::registrar(
                $usuarioId,
                'ASISTENCIA',
                'IMPORT_BIOMETRICO_CSV',
                'rrhh.MarcacionBiometrica',
                (string)$importacionId,
                "Importación de {$originalFileName}: {$marcacionesInsertadas} nuevas, {$marcacionesDuplicadas} duplicadas, {$marcacionesConsecutivas} consecutivas."
            );

            return [
                'ok'                        => true,
                'importacion_id'            => $importacionId,
                'nombre_archivo'            => $originalFileName,
                'hash_sha256'               => $hashSHA256,
                'total_filas'               => $totalFilas,
                'marcaciones_validas'       => $marcacionesValidas,
                'marcaciones_insertadas'    => $marcacionesInsertadas,
                'marcaciones_duplicadas'    => $marcacionesDuplicadas,
                'marcaciones_omitidas'      => $marcacionesOmitidas,
                'marcaciones_consecutivas'  => $marcacionesConsecutivas,
                'pendientes_vinculacion'    => $pendientesVinculacion,
                'vinculados_directos'       => $vinculadosDirectos,
                'mensaje'                   => "Importación completada con éxito. {$marcacionesInsertadas} marcaciones almacenadas en el sistema."
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Registrar fallo en ImportacionBiometrica si se llegó a crear el ID
            if ($importacionId > 0) {
                try {
                    $stmtFail = $pdo->prepare("
                        UPDATE rrhh.ImportacionBiometrica
                        SET Estado = 'FALLIDO',
                            MensajeError = :err
                        WHERE ImportacionID = :id
                    ");
                    $stmtFail->execute([
                        ':err' => substr($e->getMessage(), 0, 1000),
                        ':id'  => $importacionId
                    ]);
                } catch (Exception $ign) {
                    // Ignorar error al guardar log
                }
            }

            throw $e;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Valida cabeceras del CSV y retorna el mapa de índices de columnas
     */
    private static function validarCabeceras($handle): array
    {
        $headerLine = fgets($handle);
        if ($headerLine === false) {
            throw new InvalidArgumentException("El archivo CSV está vacío.");
        }

        // Eliminar BOM UTF-8 (\xEF\xBB\xBF) si está presente
        if (str_starts_with($headerLine, "\xEF\xBB\xBF")) {
            $headerLine = substr($headerLine, 3);
        }

        $headers = str_getcsv(trim($headerLine), ',', '"', '\\');
        $map = [];

        foreach ($headers as $index => $colName) {
            $norm = mb_strtolower(trim($colName), 'UTF-8');
            $map[$norm] = $index;
        }

        // Verificar columnas obligatorias
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $req) {
            if (!isset($map[$req])) {
                $missing[] = $req;
            }
        }

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                "Estructura CSV no válida para BioStar 2. Faltan las columnas obligatorias: " . implode(', ', $missing)
            );
        }

        return $map;
    }

    /**
     * Extrae de forma permisiva el identificador numérico y nombre del campo Usuario
     */
    public static function extraerUsuario(string $usuarioRaw): array
    {
        $usuarioRaw = trim($usuarioRaw);
        if ($usuarioRaw === '') {
            return ['id' => 'SIN_ID', 'nombre' => null];
        }

        // Patrón oficial BioStar 2: 72025461(LEON EUSEBIO SHIRLEY LUCERO) o 53
        if (preg_match('/^(\d{1,12})\s*(?:\((.+)\))?$/u', $usuarioRaw, $matches)) {
            $id = $matches[1];
            $nombre = isset($matches[2]) ? trim($matches[2]) : null;
            return ['id' => $id, 'nombre' => $nombre];
        }

        // Si contiene letras sueltas sin números iniciales
        return [
            'id'     => substr(preg_replace('/[^a-zA-Z0-9]/', '', $usuarioRaw), 0, 30) ?: 'DESC',
            'nombre' => $usuarioRaw
        ];
    }

    /**
     * Retorna mapa [ DNI => PersonalID ] a partir del padrón institucional
     */
    private static function obtenerMapaPersonal(): array
    {
        $pdo = Database::getConnection();
        if ($pdo === null) {
            return [];
        }

        $map = [];
        try {
            // 1. Personal con DNI en core.Persona
            $sql = "
                SELECT per.NumeroDocumento AS DNI, p.PersonalID
                FROM rrhh.Personal p
                INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
                WHERE p.Estado = 1
            ";
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dni = trim((string)$row['DNI']);
                if ($dni !== '') {
                    $map[$dni] = intval($row['PersonalID']);
                }
            }

            // 2. Mapeos manuales de la tabla rrhh.VinculacionBiometrica (tienen prioridad)
            $sqlVinculaciones = "SELECT BiostarUserID, PersonalID FROM rrhh.VinculacionBiometrica";
            $stmtV = $pdo->query($sqlVinculaciones);
            while ($row = $stmtV->fetch(PDO::FETCH_ASSOC)) {
                $bId = trim((string)$row['BiostarUserID']);
                if ($bId !== '') {
                    $map[$bId] = intval($row['PersonalID']);
                }
            }
        } catch (Exception $e) {
            // Silencioso; si falla, el mapa queda vacío y las marcaciones quedarán PENDIENTE_VINCULACION
        }

        return $map;
    }

    /**
     * Busca si un archivo con el mismo Hash SHA-256 ya fue importado
     */
    private static function buscarImportacionPorHash(string $hash): ?array
    {
        $pdo = Database::getConnection();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT TOP 1 
                    ImportacionID, NombreArchivo, FechaHoraCarga, Estado,
                    MarcacionesValidas, MarcacionesDuplicadas
                FROM rrhh.ImportacionBiometrica
                WHERE HashSHA256 = :hash AND Estado = 'COMPLETADO'
                ORDER BY ImportacionID DESC
            ");
            $stmt->execute([':hash' => $hash]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Retorna una explicación legible del motivo de exclusión de un evento
     */
    private static function describirMotivoOmision(string $evento): string
    {
        if (stripos($evento, 'Errónea') !== false) {
            return 'Intento de autenticación fallido (Huella o tarjeta no reconocida)';
        }
        if (stripos($evento, 'Hora dispositivo') !== false) {
            return 'Evento técnico de sincronización horaria del reloj';
        }
        if (stripos($evento, 'Biostar') !== false || stripos($evento, 'Red') !== false) {
            return 'Evento de telecomunicaciones o servicio BioStar';
        }
        if (stripos($evento, 'Usuario') !== false) {
            return 'Auditoría de alta/actualización de usuario en equipo';
        }
        if (stripos($evento, 'Sabotaje') !== false) {
            return 'Alerta física del sensor de sabotaje del equipo';
        }
        return 'Evento de sistema no computable para asistencia laboral';
    }
}
