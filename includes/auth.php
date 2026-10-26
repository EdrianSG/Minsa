<?php
/**
 * Módulo de Autenticación y Contexto de Sesión Institucional
 * Sistema de Gestión de RR.HH. - Hospital Distrital de Pacasmayo
 * 
 * Conexión directa a Microsoft SQL Server 2022 (BD_HOSPITAL):
 * seguridad.Usuario, seguridad.UsuarioRol, seguridad.Rol, core.Persona
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true]);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auditoria.php';

class Auth {
    /**
     * Comprueba si existe una sesión de usuario válida activa
     */
    public static function isAuthenticated(): bool {
        return !empty($_SESSION['user']) && !empty($_SESSION['user']['usuario_id']);
    }

    /**
     * Exige autenticación; si no hay sesión activa, redirige a login.php
     */
    public static function requireAuth(): void {
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
            'persona_id'      => 0,
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
        $identificador = trim($identificador);


        if ($identificador === '' || $password === '') {
            return [
                'ok' => false,
                'mensaje' => 'Por favor ingrese su usuario o DNI y su contraseña.'
            ];
        }

        $pdo = Database::getConnection();

        // 1. Consulta en la base de datos SQL Server
        if ($pdo !== null) {
            try {
                $sql = "
                    SELECT 
                        u.UsuarioID,
                        u.PersonaID,
                        u.NombreUsuario,
                        u.PasswordHash,
                        u.Bloqueado,
                        u.Estado,
                        u.UltimoAcceso,
                        per.NumeroDocumento AS DNI,
                        per.Nombres,
                        per.ApellidoPaterno,
                        per.ApellidoMaterno,
                        per.Correo,
                        r.RolID,
                        r.Codigo AS RolCodigo,
                        r.Nombre AS RolNombre,
                        s.ServicioID,
                        ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS ServicioNombre,
                        ISNULL(c.Nombre, 'Servidor Público') AS CargoNombre
                    FROM seguridad.Usuario u
                    INNER JOIN core.Persona per ON u.PersonaID = per.PersonaID
                    LEFT JOIN seguridad.UsuarioRol ur ON u.UsuarioID = ur.UsuarioID AND ur.Estado = 1
                    LEFT JOIN seguridad.Rol r ON ur.RolID = r.RolID
                    LEFT JOIN rrhh.Personal p ON per.PersonaID = p.PersonaID
                    LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
                    LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                    LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                    WHERE (u.NombreUsuario = ? OR per.NumeroDocumento = ?) AND u.Estado = 1
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$identificador, $identificador]);
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
                $passwordValida = password_verify($password, $user['PasswordHash']);

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
                $nombreCompleto = trim("{$nombres} {$apePat} {$apeMat}");
                
                $primerNombre = explode(' ', $nombres)[0] ?? $nombres;
                $letraApe = mb_substr($apePat, 0, 1, 'UTF-8');
                $nombreCorto = "{$primerNombre} {$letraApe}.";

                $ini1 = mb_strtoupper(mb_substr($nombres, 0, 1, 'UTF-8'), 'UTF-8');
                $ini2 = mb_strtoupper(mb_substr($apePat, 0, 1, 'UTF-8'), 'UTF-8');
                $iniciales = ($ini1 ?: 'U') . ($ini2 ?: 'S');

                // Establecer sesión segura
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'usuario_id'      => (int)$user['UsuarioID'],
                    'persona_id'      => (int)$user['PersonaID'],
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
                    'login_time'      => time()
                ];

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

        $userRole = $_SESSION['user']['rol'] ?? '';
        return in_array($userRole, $roles, true);
    }

    /**
     * Comprueba si el usuario tiene permiso para el módulo actual
     */
    public static function checkPermission(string $modulo): bool {
        if (!self::isAuthenticated()) {
            return false;
        }

        $rol = $_SESSION['user']['rol'] ?? '';
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

        $rol = $_SESSION['user']['rol'] ?? '';
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
        $rol = $_SESSION['user']['rol'] ?? '';
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
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
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

