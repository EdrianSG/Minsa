<?php
/**
 * Módulo de Autenticación y Contexto de Sesión Institucional
 * Sistema de Gestión de RR.HH. - Hospital Distrital de Pacasmayo
 * 
 * Conexión directa a Microsoft SQL Server 2022 (BD_HOSPITAL):
 * seguridad.Usuario, seguridad.UsuarioRol, seguridad.Rol, rrhh.Personal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true]);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/SystemContext.php';

class Auth {
    /** Compatibilidad con el código administrativo de BD_HOSPITAL_V3. */
    private static function permissionRole(string $role): string {
        return $role === 'RRHH_ADMIN' ? 'ADMIN_RRHH' : $role;
    }

    /**
     * Obtiene la configuración de la cuenta auxiliar temporal
     */
    public static function getAuxiliaryConfig(): array {
        static $cfg = null;
        if ($cfg === null) {
            $file = __DIR__ . '/../config/auxiliary_account.php';
            $cfg = is_file($file) ? (include $file) : [];
            if (!is_array($cfg)) $cfg = [];
            $cfg['enabled'] = $cfg['enabled'] ?? true;
            $cfg['username'] = $cfg['username'] ?? 'auxiliar';
            $cfg['password'] = $cfg['password'] ?? 'Auxiliar2026!';
            $cfg['allow_admin_fallback'] = $cfg['allow_admin_fallback'] ?? true;
            $cfg['admin_offline_pass'] = $cfg['admin_offline_pass'] ?? 'Admin2026!';
            $cfg['usuario_id'] = $cfg['usuario_id'] ?? 999999;
            $cfg['nombre_corto'] = $cfg['nombre_corto'] ?? 'Admin Auxiliar';
            $cfg['nombre_completo'] = $cfg['nombre_completo'] ?? 'Administrador de Contingencia (Modo Auxiliar)';
            $cfg['cargo'] = $cfg['cargo'] ?? 'Administrador de Emergencia / Respaldo';
            $cfg['rol'] = $cfg['rol'] ?? 'ADMIN_RRHH';
            $cfg['rol_nombre'] = $cfg['rol_nombre'] ?? 'Administrador Auxiliar (Contingencia)';
            $cfg['servicio_id'] = null;
            $cfg['area'] = $cfg['area'] ?? 'Hospital Distrital de Pacasmayo';
            $cfg['servicio'] = $cfg['servicio'] ?? 'Hospital Distrital de Pacasmayo (Contingencia)';
            $cfg['correo'] = $cfg['correo'] ?? 'contingencia@hospitalpacasmayo.gob.pe';
        }
        return $cfg;
    }

    /**
     * Comprueba si la sesión activa corresponde a la cuenta auxiliar temporal
     */
    public static function isAuxiliarySession(): bool {
        return !empty($_SESSION['user']['is_auxiliary']);
    }

    /**
     * Inicializa la sesión con los privilegios de la cuenta auxiliar
     */
    public static function startAuxiliarySession(array $auxConfig, string $auditAction = 'LOGIN_AUXILIAR'): array {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'usuario_id'      => (int)($auxConfig['usuario_id'] ?? 999999),
            'personal_id'     => null,
            'system_code'     => SystemContext::code(),
            'database_source' => 'OFFLINE_AUXILIARY',
            'is_auxiliary'    => true,
            'username'        => $auxConfig['username'] ?? 'auxiliar',
            'dni'             => '00000000',
            'nombre_corto'    => $auxConfig['nombre_corto'] ?? 'Admin Auxiliar',
            'nombre_completo' => $auxConfig['nombre_completo'] ?? 'Administrador de Contingencia',
            'cargo'           => $auxConfig['cargo'] ?? 'Administrador de Emergencia / Respaldo',
            'servicio_id'     => null,
            'area'            => $auxConfig['area'] ?? 'Hospital Distrital de Pacasmayo',
            'servicio'        => $auxConfig['servicio'] ?? 'Hospital Distrital de Pacasmayo (Contingencia)',
            'hospital'        => 'Hospital Distrital de Pacasmayo',
            'rol'             => $auxConfig['rol'] ?? 'ADMIN_RRHH',
            'rol_nombre'      => $auxConfig['rol_nombre'] ?? 'Administrador Auxiliar (Contingencia)',
            'correo'          => $auxConfig['correo'] ?? '',
            'iniciales'       => 'AX',
            'avatar'          => null,
            'ultimo_acceso'   => date('Y-m-d H:i:s'),
            'auth_stamp'      => hash('sha256', 'auxiliary_offline_session'),
            'login_time'      => time()
        ];
        $_SESSION['last_activity'] = time();

        Auditoria::registrar(
            (int)$_SESSION['user']['usuario_id'],
            'SEGURIDAD',
            $auditAction,
            'seguridad.Usuario',
            (string)$_SESSION['user']['usuario_id'],
            'Inicio de sesión en Modo Auxiliar (Sin Conexión a Base de Datos)'
        );

        return [
            'ok'           => true,
            'is_auxiliary' => true,
            'mensaje'      => 'Acceso autorizado en Modo Auxiliar (Sin conexión a BD). Redirigiendo...',
            'usuario'      => $_SESSION['user']
        ];
    }

    /** Revalidación obligatoria del servidor; el rol SQL se conserva sin alias. */
    public static function validateSession(PDO $pdo): bool {
        if (!empty($_SESSION['user']['is_auxiliary'])) {
            return true;
        }

        $user = self::getCurrentUser();
        $stmt = $pdo->prepare("SELECT u.UsuarioID, u.PasswordHash FROM seguridad.Usuario u
            LEFT JOIN rrhh.Personal p ON p.PersonalID = u.PersonalID
            INNER JOIN seguridad.UsuarioRol ur ON ur.UsuarioID = u.UsuarioID AND ur.Activo = 1
            INNER JOIN seguridad.Rol r ON r.RolID = ur.RolID AND r.Activo = 1
            WHERE u.UsuarioID = ? AND u.Activo = 1 AND u.Bloqueado = 0 AND r.Codigo = ?
            AND (ur.FechaInicio IS NULL OR ur.FechaInicio <= CAST(GETDATE() AS DATE))
            AND (ur.FechaFin IS NULL OR ur.FechaFin >= CAST(GETDATE() AS DATE))
            AND r.SistemaID = ? AND " . SystemContext::membershipSql() . "
            AND ((u.PersonalID IS NULL AND r.Codigo IN ('ADMIN','ADMIN_RRHH','RRHH_ADMIN')) OR p.Activo = 1)");
        if (($user['database_source'] ?? '') !== Database::sourceId() || ($user['system_code'] ?? '') !== SystemContext::code()) { unset($_SESSION['user']); return false; }
        $stmt->execute([(int)$user['usuario_id'], $user['rol'], SystemContext::id($pdo)]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        $valid = $account !== false;
        if ($valid) $valid = isset($user['auth_stamp'], $account['PasswordHash']) && hash_equals($user['auth_stamp'], hash('sha256', $account['PasswordHash']));
        $service = self::getAllowedServiceId();
        if ($valid && $service !== null) {
            $stmt = $pdo->prepare('SELECT ap.ServicioID FROM seguridad.Usuario u
                INNER JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID = u.PersonalID
                AND ap.EsActual = 1 AND ap.Activo = 1
                WHERE u.UsuarioID = ? AND ap.ServicioID = ?');
            $stmt->execute([(int)$user['usuario_id'], $service]);
            $valid = $stmt->fetchColumn() !== false;
        }
        if (!$valid) unset($_SESSION['user']);
        return $valid;
    }

    /**
     * Comprueba si existe una sesión de usuario válida activa
     */
    public static function isAuthenticated(): bool {
        if (empty($_SESSION['user']['usuario_id'])) {
            return false;
        }
        if (!empty($_SESSION['user']['is_auxiliary'])) {
            return true;
        }
        return ($_SESSION['user']['database_source'] ?? '') === Database::sourceId()
            && ($_SESSION['user']['system_code'] ?? '') === SystemContext::code();
    }

    /**
     * Exige autenticación; si no hay sesión activa, redirige a login.php
     */
    public static function requireAuth(): void {
        if (self::isAuthenticated()) {
            if (!self::isAuxiliarySession()) {
                $pdo = Database::getConnection();
                if ($pdo) {
                    try { self::validateSession($pdo); }
                    catch (Throwable $e) { unset($_SESSION['user']); }
                }
            }
        }
        if (!self::isAuthenticated()) {
            $currentUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
            // Prevenir redirección en bucle a login
            if (strpos($currentUri, 'login.php') === false) {
                header('Location: login.php?redirect=' . urlencode($currentUri));
                exit;
            }
        }
    }

    /**
     * Retorna los datos del usuario actualmente autenticado
     */
    public static function getCurrentUser(): array {
        if (self::isAuthenticated()) {
            return $_SESSION['user'];
        }

        return [
            'usuario_id'      => 0,
            'personal_id'     => null,
            'username'        => 'invitado',
            'dni'             => '',
            'nombre_corto'    => 'Invitado',
            'nombre_completo' => 'Usuario No Autenticado',
            'cargo'           => 'Sin Sesión',
            'area'            => 'General',
            'servicio'        => 'Hospital Distrital de Pacasmayo',
            'hospital'        => 'Hospital Distrital de Pacasmayo',
            'rol'             => 'INVITADO',
            'rol_nombre'      => 'Sin Rol Asignado',
            'iniciales'       => '??',
            'avatar'          => null,
            'ultimo_acceso'   => null
        ];
    }

    /**
     * Valida credenciales e inicializa la sesión en el sistema
     */
    public static function login(string $identificador, string $password): array {
        require_once __DIR__ . '/LoginThrottle.php';
        try {
            if (!LoginThrottle::allow(trim($identificador))) return ['ok'=>false, 'status'=>429, 'mensaje'=>'Demasiados intentos de acceso. Espere 15 minutos.'];
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            return ['ok'=>false, 'status'=>503, 'mensaje'=>'No se pudo verificar el acceso. Intente nuevamente.'];
        }
        return self::performLogin($identificador, $password);
    }

    private static function performLogin(string $identificador, string $password): array {
        $identificador = trim($identificador);

        if ($identificador === '' || $password === '') {
            return [
                'ok' => false,
                'mensaje' => 'Por favor ingrese su usuario o DNI y su contraseña.'
            ];
        }

        $auxConfig = self::getAuxiliaryConfig();

        // Acceso directo con cuenta auxiliar de contingencia (no requiere SQL Server)
        if (!empty($auxConfig['enabled']) && strcasecmp($identificador, (string)$auxConfig['username']) === 0) {
            if (hash_equals((string)$auxConfig['password'], $password)) {
                return self::startAuxiliarySession($auxConfig, 'LOGIN_AUXILIAR');
            }
            return [
                'ok'      => false,
                'status'  => 401,
                'mensaje' => 'Contraseña incorrecta para la cuenta auxiliar de contingencia.'
            ];
        }

        $pdo = Database::getConnection();
        if ($pdo === null) {
            error_log('Conexión durante login: ' . Database::getLastError());

            // Permitir fallback offline de emergencia para administrador
            if (!empty($auxConfig['enabled']) && !empty($auxConfig['allow_admin_fallback'])) {
                if (in_array(strtolower($identificador), ['admin', 'administrador'], true) && hash_equals((string)$auxConfig['admin_offline_pass'], $password)) {
                    return self::startAuxiliarySession($auxConfig, 'LOGIN_AUXILIAR_ADMIN');
                }
            }

            return [
                'ok'         => false,
                'status'     => 503,
                'db_offline' => true,
                'mensaje'    => 'No se pudo conectar con la base de datos SQL Server. Puede acceder con la Cuenta Auxiliar de contingencia (Usuario: ' . htmlspecialchars($auxConfig['username']) . ' / Clave: ' . htmlspecialchars($auxConfig['password']) . ').'
            ];
        }

        // 1. Consulta en la base de datos SQL Server
        if ($pdo !== null) {
            try {
                $sql = "
                    SELECT 
                        u.UsuarioID,
                        u.PersonalID,
                        u.NombreUsuario,
                        u.PasswordHash,
                        u.Bloqueado,
                        u.Activo,
                        u.UltimoAcceso,
                        p.NumeroDocumento AS DNI,
                        p.Nombres,
                        p.ApellidoPaterno,
                        p.ApellidoMaterno,
                        p.Correo,
                        r.RolID,
                        r.Codigo AS RolCodigo,
                        r.Nombre AS RolNombre,
                        s.ServicioID,
                        ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS ServicioNombre,
                        ISNULL(c.Nombre, 'Servidor Público') AS CargoNombre
                    FROM seguridad.Usuario u
                    LEFT JOIN rrhh.Personal p ON u.PersonalID = p.PersonalID
                    LEFT JOIN seguridad.UsuarioRol ur ON u.UsuarioID = ur.UsuarioID AND ur.Activo = 1 AND (ur.FechaInicio IS NULL OR ur.FechaInicio <= CAST(GETDATE() AS DATE)) AND (ur.FechaFin IS NULL OR ur.FechaFin >= CAST(GETDATE() AS DATE))
                    LEFT JOIN seguridad.Rol r ON ur.RolID = r.RolID AND r.Activo = 1
                    LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                    LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                    LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                    WHERE (u.NombreUsuario = ? OR p.NumeroDocumento = ?) AND u.Activo = 1 AND ((u.PersonalID IS NULL AND r.Codigo IN ('ADMIN','ADMIN_RRHH','RRHH_ADMIN')) OR p.Activo = 1) AND r.RolID IS NOT NULL
                    AND r.SistemaID = ? AND " . SystemContext::membershipSql() . "
                    ORDER BY CASE WHEN r.Codigo IN ('ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN') THEN 0 ELSE 1 END, r.RolID
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$identificador, $identificador, SystemContext::id($pdo)]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    Auditoria::registrar(0, 'SEGURIDAD', 'LOGIN_FALLIDO', 'seguridad.Usuario', null, "Intento de acceso fallido para: {$identificador}");
                    return [
                        'ok' => false,
                        'mensaje' => 'Usuario o contraseña incorrectos. Verifique sus datos.'
                    ];
                }

                if ((bool)$user['Bloqueado']) {
                    Auditoria::registrar((int)$user['UsuarioID'], 'SEGURIDAD', 'LOGIN_BLOQUEADO', 'seguridad.Usuario', (string)$user['UsuarioID'], "Acceso denegado por cuenta bloqueada: {$identificador}");
                    return [
                        'ok' => false,
                        'mensaje' => 'Esta cuenta de usuario ha sido bloqueada por seguridad. Contacte con Informática o RR.HH.'
                    ];
                }

                // Validación estricta con hash de contraseñas de Microsoft SQL Server (seguridad.Usuario)
                $passwordValida = is_string($user['PasswordHash']) && password_verify($password, $user['PasswordHash']);

                if (!$passwordValida) {
                    Auditoria::registrar((int)$user['UsuarioID'], 'SEGURIDAD', 'LOGIN_FALLIDO', 'seguridad.Usuario', (string)$user['UsuarioID'], "Contraseña incorrecta para el usuario: {$user['NombreUsuario']}");
                    return [
                        'ok' => false,
                        'mensaje' => 'Usuario o contraseña incorrectos. Verifique sus datos.'
                    ];
                }

                // Actualizar último acceso en la tabla seguridad.Usuario
                try {
                    $upd = $pdo->prepare("UPDATE seguridad.Usuario SET UltimoAcceso = SYSDATETIME() WHERE UsuarioID = ?");
                    $upd->execute([(int)$user['UsuarioID']]);
                } catch (Exception $e) {
                    // Continuar si falla la actualización de timestamp
                }

                // Generar nombres y formatos de visualización
                $nombres = trim($user['Nombres'] ?? '');
                $apePat  = trim($user['ApellidoPaterno'] ?? '');
                $apeMat  = trim($user['ApellidoMaterno'] ?? '');
                $nombreCompleto = trim("{$nombres} {$apePat} {$apeMat}") ?: $user['NombreUsuario'];
                
                $primerNombre = explode(' ', $nombres)[0] ?? $nombres;
                $letraApe = mb_substr($apePat, 0, 1, 'UTF-8');
                $nombreCorto = $nombres !== '' ? "{$primerNombre} {$letraApe}." : $user['NombreUsuario'];

                $ini1 = mb_strtoupper(mb_substr($nombres, 0, 1, 'UTF-8'), 'UTF-8');
                $ini2 = mb_strtoupper(mb_substr($apePat, 0, 1, 'UTF-8'), 'UTF-8');
                $iniciales = ($ini1 ?: 'U') . ($ini2 ?: 'S');

                // Establecer sesión segura
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'usuario_id'      => (int)$user['UsuarioID'],
                    'personal_id'     => $user['PersonalID'] !== null ? (int)$user['PersonalID'] : null,
                    'system_code'     => SystemContext::code(),
                    'database_source' => Database::sourceId(),
                    'username'        => $user['NombreUsuario'],
                    'dni'             => $user['DNI'],
                    'nombre_corto'    => $nombreCorto,
                    'nombre_completo' => $nombreCompleto,
                    'cargo'           => $user['CargoNombre'] ?? 'Servidor Público',
                    'servicio_id'     => !empty($user['ServicioID']) ? (int)$user['ServicioID'] : null,
                    'area'            => $user['ServicioNombre'] ?? 'Hospital Distrital de Pacasmayo',
                    'servicio'        => $user['ServicioNombre'] ?? 'Hospital Distrital de Pacasmayo',
                    'hospital'        => 'Hospital Distrital de Pacasmayo',
                    'rol'             => $user['RolCodigo'] ?? 'OPERADOR',
                    'rol_nombre'      => $user['RolNombre'] ?? 'Operador del Sistema',
                    'correo'          => $user['Correo'] ?? '',
                    'iniciales'       => $iniciales,
                    'avatar'          => null,
                    'ultimo_acceso'   => $user['UltimoAcceso'] ?? date('Y-m-d H:i:s'),
                    'auth_stamp'      => hash('sha256', $user['PasswordHash']),
                    'login_time'      => time()
                ];

                $_SESSION['last_activity'] = time();
                Auditoria::registrar(
                    (int)$user['UsuarioID'], 
                    'SEGURIDAD', 
                    'LOGIN_EXITOSO', 
                    'seguridad.Usuario',
                    (string)$user['UsuarioID'],
                    "Inicio de sesión exitoso de {$user['NombreUsuario']} ({$_SESSION['user']['rol']})"
                );

                return [
                    'ok' => true,
                    'mensaje' => 'Inicio de sesión exitoso. Redirigiendo...',
                    'usuario' => $_SESSION['user']
                ];

            } catch (Exception $e) {
                error_log("Error en Auth::login(): " . $e->getMessage());

                // Permitir fallback offline de emergencia para administrador si la BD falla
                if (!empty($auxConfig['enabled']) && !empty($auxConfig['allow_admin_fallback'])) {
                    if (in_array(strtolower($identificador), ['admin', 'administrador'], true) && hash_equals((string)$auxConfig['admin_offline_pass'], $password)) {
                        return self::startAuxiliarySession($auxConfig, 'LOGIN_AUXILIAR_ADMIN');
                    }
                }

                return [
                    'ok'         => false,
                    'status'     => 503,
                    'db_offline' => true,
                    'mensaje'    => 'No se pudo verificar el acceso en la base de datos SQL Server. Puede ingresar con la Cuenta Auxiliar temporal (Usuario: ' . htmlspecialchars($auxConfig['username']) . ' / Clave: ' . htmlspecialchars($auxConfig['password']) . ').'
                ];
            }
        }

        return ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    /**
     * Cierra la sesión activa del usuario y limpia las cookies
     */
    public static function logout(): void {
        if (self::isAuthenticated()) {
            Auditoria::registrar(
                (int)$_SESSION['user']['usuario_id'],
                'SEGURIDAD',
                'LOGOUT',
                'seguridad.Usuario',
                (string)$_SESSION['user']['usuario_id'],
                "Cierre de sesión del usuario {$_SESSION['user']['username']}"
            );
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Comprueba si el usuario tiene uno de los roles especificados
     */
    public static function hasRole(string ...$roles): bool {
        if (!self::isAuthenticated()) {
            return false;
        }

        $userRole = self::permissionRole($_SESSION['user']['rol'] ?? '');
        $roles = array_map([self::class, 'permissionRole'], $roles);
        return in_array($userRole, $roles, true);
    }

    /**
     * Comprueba si el usuario tiene permiso para el módulo actual
     */
    public static function checkPermission(string $modulo): bool {
        if (!self::isAuthenticated()) {
            return false;
        }

        return self::roleAllows($_SESSION['user']['rol'] ?? '', $modulo);
    }

    public static function roleAllows(string $role, string $modulo): bool {
        $rol = self::permissionRole($role);
        if ($rol === 'ADMIN' || $rol === 'ADMIN_RRHH') {
            return true;
        }

        // Permisos por módulo
        $matriz = [
            'JEFE_RRHH' => ['dashboard', 'personal', 'horarios', 'programacion', 'asistencia', 'ausencias', 'reportes'],
            'SUPERVISOR' => ['dashboard', 'horarios', 'programacion', 'asistencia', 'ausencias'],
            'JEFE_SERVICIO' => ['dashboard', 'personal', 'horarios', 'programacion', 'asistencia', 'ausencias', 'reportes'],
            'OPERADOR_ASISTENCIA' => ['dashboard', 'personal', 'horarios', 'programacion', 'asistencia', 'ausencias', 'reportes'],
            'AUDITOR_PLANILLAS' => ['dashboard', 'personal', 'horarios', 'programacion', 'asistencia', 'ausencias', 'reportes'],
            'CONSULTA' => ['dashboard', 'reportes', 'asistencia']
        ];

        return in_array($modulo, $matriz[$rol] ?? [], true);
    }

    /**
     * Verifica si el usuario actual tiene autorización para ver u operar un servicio específico.
     * Los roles de administración o RR.HH. tienen alcance global (todos los servicios).
     * Los jefes de servicio y supervisores están estrictamente limitados a su ServicioID asignado.
     * 
     * @param int|null $targetServicioId ID del servicio a consultar (0 o null = Todos los servicios)
     * @return bool
     */
    public static function checkServiceAccess(?int $targetServicioId): bool {
        if (!self::isAuthenticated()) {
            return false;
        }

        $rol = self::permissionRole($_SESSION['user']['rol'] ?? '');
        // Roles institucionales con alcance global a todos los servicios
        if (in_array($rol, ['ADMIN', 'ADMIN_RRHH', 'JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'AUDITOR_PLANILLAS'], true)) {
            return true;
        }

        $userServicioId = isset($_SESSION['user']['servicio_id']) ? (int)$_SESSION['user']['servicio_id'] : null;

        // Si la jefatura o supervisor no tiene servicio asignado, denegar por seguridad
        if ($userServicioId === null || $userServicioId <= 0) {
            return false;
        }

        // Una jefatura de servicio no puede consultar "Todos los servicios" (0 o null)
        if ($targetServicioId === null || $targetServicioId === 0) {
            return false;
        }

        return $targetServicioId === $userServicioId;
    }

    /**
     * Retorna el ServicioID asignado, 0 si falta asignación, o null exclusivamente para alcance global.
     */
    public static function getAllowedServiceId(): ?int {
        if (!self::isAuthenticated()) {
            return null;
        }
        $rol = self::permissionRole($_SESSION['user']['rol'] ?? '');
        if (in_array($rol, ['ADMIN', 'ADMIN_RRHH', 'JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'AUDITOR_PLANILLAS'], true)) {
            return null; // Alcance global
        }
        return max(0, (int)($_SESSION['user']['servicio_id'] ?? 0));
    }

    /**
     * Verifica si el usuario actual tiene autorización para ver u operar un trabajador específico.
     * Si el usuario tiene alcance global, retorna true.
     * Si es supervisor o jefe de servicio, verifica que el trabajador pertenezca a su mismo ServicioID.
     */
    public static function checkPersonalAccess(int $personalId): bool {
        if (!self::isAuthenticated()) {
            return false;
        }
        $allowedServId = self::getAllowedServiceId();
        if ($allowedServId === null) {
            return true; // Alcance institucional global
        }
        if ($allowedServId <= 0 || $personalId <= 0) {
            return false;
        }

        $pdo = Database::getConnection();
        if ($pdo === null) return false;

        try {
            $stmt = $pdo->prepare("
                SELECT ap.ServicioID
                FROM rrhh.Personal p
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                WHERE p.PersonalID = ?
            ");
            $stmt->execute([$personalId]);
            $servId = $stmt->fetchColumn();
            return ((int)$servId === (int)$allowedServId);
        } catch (Exception $e) {
            return false;
        }
    }
}

