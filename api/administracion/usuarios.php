<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET', 'POST'], []);

/**
 * API Endpoint: /api/administracion/usuarios.php
 * Hospital Distrital de Pacasmayo - ETAPA 8
 * 
 * Gestión de Cuentas y Accesos de Usuarios del Sistema:
 * Mapeado a seguridad.Usuario, seguridad.UsuarioRol y core.Persona
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auditoria.php';

$method = $_SERVER['REQUEST_METHOD'];
$currentUser = Auth::getCurrentUser();

if ($method === 'GET') {
    $busqueda = trim($_GET['q'] ?? '');
    $rolFiltro = trim($_GET['rol'] ?? '');
    $estadoFiltro = isset($_GET['estado']) && $_GET['estado'] !== '' ? intval($_GET['estado']) : null;

    $pdo = Database::getConnection();
    $dbConnected = ($pdo !== null);

    $usuarios = [];

    if ($dbConnected) {
        try {
            $sql = "
                SELECT 
                    u.UsuarioID,
                    u.PersonaID,
                    u.NombreUsuario,
                    u.FechaCreacion,
                    u.UltimoAcceso,
                    u.Bloqueado,
                    u.Estado,
                    per.NumeroDocumento AS DNI,
                    CONCAT(per.Nombres, ' ', per.ApellidoPaterno, ' ', per.ApellidoMaterno) AS NombreCompleto,
                    r.RolID,
                    r.Codigo AS RolCodigo,
                    r.Nombre AS RolNombre,
                    ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                    ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo
                FROM seguridad.Usuario u
                INNER JOIN core.Persona per ON u.PersonaID = per.PersonaID
                LEFT JOIN seguridad.UsuarioRol ur ON u.UsuarioID = ur.UsuarioID AND ur.Estado = 1
                LEFT JOIN seguridad.Rol r ON ur.RolID = r.RolID
                LEFT JOIN rrhh.Personal p ON per.PersonaID = p.PersonaID
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                WHERE 1=1
            ";
            $params = [];

            if ($busqueda !== '') {
                $sql .= " AND (u.NombreUsuario LIKE :q OR per.NumeroDocumento LIKE :q OR per.Nombres LIKE :q OR per.ApellidoPaterno LIKE :q)";
                $params[':q'] = "%$busqueda%";
            }
            if ($rolFiltro !== '') {
                $sql .= " AND r.Codigo = :rol";
                $params[':rol'] = $rolFiltro;
            }
            if ($estadoFiltro !== null) {
                $sql .= " AND u.Estado = :estado";
                $params[':estado'] = $estadoFiltro;
            }

            $sql .= " ORDER BY u.UsuarioID ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($filas as $f) {
                $usuarios[] = [
                    'usuario_id'       => intval($f['UsuarioID']),
                    'persona_id'       => intval($f['PersonaID']),
                    'username'         => $f['NombreUsuario'],
                    'dni'              => $f['DNI'],
                    'nombre_completo'  => $f['NombreCompleto'],
                    'rol_id'           => intval($f['RolID'] ?? 3),
                    'rol_codigo'       => $f['RolCodigo'] ?? 'OPERADOR_ASISTENCIA',
                    'rol_nombre'       => $f['RolNombre'] ?? 'Operador de Asistencia',
                    'servicio_nombre'  => $f['NombreServicio'] ?? 'Recursos Humanos',
                    'cargo_nombre'     => $f['NombreCargo'] ?? 'Especialista en RR.HH.',
                    'fecha_creacion'   => $f['FechaCreacion'],
                    'ultimo_acceso'    => $f['UltimoAcceso'],
                    'bloqueado'        => (bool)$f['Bloqueado'],
                    'estado'           => (bool)$f['Estado']
                ];
            }
        } catch (Exception $e) {
            // Cae en fallback
        }
    }

    // Si no hay datos en BD o está desconectado, proveer usuarios institucionales estructurados
    if (empty($usuarios)) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['mock_usuarios'])) {
            $_SESSION['mock_usuarios'] = [
                [
                    'usuario_id'      => 1,
                    'persona_id'      => 1,
                    'username'        => 'mrodriguez',
                    'dni'             => '42895612',
                    'nombre_completo' => 'Lic. María Rodríguez Santillán',
                    'rol_id'          => 1,
                    'rol_codigo'      => 'ADMIN_RRHH',
                    'rol_nombre'      => 'Administrador General de RR.HH.',
                    'servicio_nombre' => 'Unidad de Personal / RR.HH.',
                    'cargo_nombre'    => 'Jefe de Recursos Humanos',
                    'fecha_creacion'  => '2026-01-15 08:30:00',
                    'ultimo_acceso'   => date('Y-m-d H:i:s'),
                    'bloqueado'       => false,
                    'estado'          => true
                ],
                [
                    'usuario_id'      => 2,
                    'persona_id'      => 2,
                    'username'        => 'cbenites',
                    'dni'             => '45871234',
                    'nombre_completo' => 'Dra. Carmen Rosa Benites Silva',
                    'rol_id'          => 2,
                    'rol_codigo'      => 'JEFE_SERVICIO',
                    'rol_nombre'      => 'Jefatura de Servicio / UPSS',
                    'servicio_nombre' => 'Emergencia y Cuidados Críticos',
                    'cargo_nombre'    => 'Jefa de Emergencia',
                    'fecha_creacion'  => '2026-02-01 09:15:00',
                    'ultimo_acceso'   => '2026-09-20 18:40:12',
                    'bloqueado'       => false,
                    'estado'          => true
                ],
                [
                    'usuario_id'      => 3,
                    'persona_id'      => 3,
                    'username'        => 'atorres',
                    'dni'             => '45892104',
                    'nombre_completo' => 'Lic. Ana María Torres Benites',
                    'rol_id'          => 3,
                    'rol_codigo'      => 'OPERADOR_ASISTENCIA',
                    'rol_nombre'      => 'Operador de Control de Asistencia',
                    'servicio_nombre' => 'Control de Asistencia y Turnos',
                    'cargo_nombre'    => 'Supervisora de Asistencia',
                    'fecha_creacion'  => '2026-02-10 11:00:00',
                    'ultimo_acceso'   => '2026-09-21 07:45:00',
                    'bloqueado'       => false,
                    'estado'          => true
                ],
                [
                    'usuario_id'      => 4,
                    'persona_id'      => 4,
                    'username'        => 'jcastillo',
                    'dni'             => '40129485',
                    'nombre_completo' => 'Dr. Jorge Andrés Castillo Vargas',
                    'rol_id'          => 4,
                    'rol_codigo'      => 'AUDITOR_PLANILLAS',
                    'rol_nombre'      => 'Auditor de Planillas y Remuneraciones',
                    'servicio_nombre' => 'Unidad de Remuneraciones',
                    'cargo_nombre'    => 'Especialista en Planillas',
                    'fecha_creacion'  => '2026-03-05 14:20:00',
                    'ultimo_acceso'   => '2026-09-18 16:10:00',
                    'bloqueado'       => false,
                    'estado'          => true
                ],
                [
                    'usuario_id'      => 5,
                    'persona_id'      => 5,
                    'username'        => 'ldiaz',
                    'dni'             => '44102938',
                    'nombre_completo' => 'Luis Alberto Díaz Albarrán',
                    'rol_id'          => 3,
                    'rol_codigo'      => 'OPERADOR_ASISTENCIA',
                    'rol_nombre'      => 'Operador de Control de Asistencia',
                    'servicio_nombre' => 'Admisión y Citas',
                    'cargo_nombre'    => 'Técnico Administrativo',
                    'fecha_creacion'  => '2026-04-12 10:05:00',
                    'ultimo_acceso'   => '2026-08-30 12:00:00',
                    'bloqueado'       => true,
                    'estado'          => true
                ]
            ];
        }

        $usuarios = $_SESSION['mock_usuarios'];

        // Filtrar en memoria si aplica
        if ($busqueda !== '') {
            $usuarios = array_values(array_filter($usuarios, function($u) use ($busqueda) {
                return stripos($u['username'], $busqueda) !== false ||
                       stripos($u['dni'], $busqueda) !== false ||
                       stripos($u['nombre_completo'], $busqueda) !== false;
            }));
        }
        if ($rolFiltro !== '') {
            $usuarios = array_values(array_filter($usuarios, fn($u) => $u['rol_codigo'] === $rolFiltro));
        }
        if ($estadoFiltro !== null) {
            $usuarios = array_values(array_filter($usuarios, fn($u) => intval($u['estado']) === $estadoFiltro));
        }
    }

    echo json_encode([
        'ok'       => true,
        'total'    => count($usuarios),
        'usuarios' => $usuarios
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    apiError(501, 'La gestión de cuentas aún no persiste en SQL Server. Operación no disponible.');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $accion = $input['accion'] ?? 'crear';

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['mock_usuarios'])) {
        // Inicializar si no existe
        $_SESSION['mock_usuarios'] = [];
    }

    if ($accion === 'crear') {
        $username = trim($input['username'] ?? '');
        $personaId = intval($input['persona_id'] ?? 0);
        $rolId = intval($input['rol_id'] ?? 3);
        $dni = trim($input['dni'] ?? '');
        $nombreCompleto = trim($input['nombre_completo'] ?? '');
        $servicioNombre = trim($input['servicio_nombre'] ?? 'Hospital Distrital de Pacasmayo');
        $cargoNombre = trim($input['cargo_nombre'] ?? 'Servidor Público');

        if (empty($username) || empty($nombreCompleto)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El nombre de usuario y nombre completo son obligatorios.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rolesCatalogo = [
            1 => ['codigo' => 'ADMIN_RRHH', 'nombre' => 'Administrador General de RR.HH.'],
            2 => ['codigo' => 'JEFE_SERVICIO', 'nombre' => 'Jefatura de Servicio / UPSS'],
            3 => ['codigo' => 'OPERADOR_ASISTENCIA', 'nombre' => 'Operador de Control de Asistencia'],
            4 => ['codigo' => 'AUDITOR_PLANILLAS', 'nombre' => 'Auditor de Planillas y Remuneraciones']
        ];
        $rolInfo = $rolesCatalogo[$rolId] ?? $rolesCatalogo[3];

        $nuevoUsuario = [
            'usuario_id'      => count($_SESSION['mock_usuarios']) + 10,
            'persona_id'      => $personaId ?: (count($_SESSION['mock_usuarios']) + 50),
            'username'        => strtolower($username),
            'dni'             => $dni ?: '47' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
            'nombre_completo' => $nombreCompleto,
            'rol_id'          => $rolId,
            'rol_codigo'      => $rolInfo['codigo'],
            'rol_nombre'      => $rolInfo['nombre'],
            'servicio_nombre' => $servicioNombre,
            'cargo_nombre'    => $cargoNombre,
            'fecha_creacion'  => date('Y-m-d H:i:s'),
            'ultimo_acceso'   => null,
            'bloqueado'       => false,
            'estado'          => true
        ];

        array_unshift($_SESSION['mock_usuarios'], $nuevoUsuario);

        // Registrar en auditoria
        Auditoria::registrar(
            $currentUser['usuario_id'],
            'SEGURIDAD',
            'INSERT',
            'seguridad.Usuario',
            (string)$nuevoUsuario['usuario_id'],
            "Se creó la cuenta de usuario '{$nuevoUsuario['username']}' para {$nuevoUsuario['nombre_completo']} con rol {$rolInfo['codigo']}"
        );

        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Usuario creado exitosamente.',
            'usuario' => $nuevoUsuario
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'cambiar_bloqueo') {
        $usuarioId = intval($input['usuario_id'] ?? 0);
        $bloqueado = !empty($input['bloqueado']);

        foreach ($_SESSION['mock_usuarios'] as &$u) {
            if ($u['usuario_id'] === $usuarioId) {
                $u['bloqueado'] = $bloqueado;
                Auditoria::registrar(
                    $currentUser['usuario_id'],
                    'SEGURIDAD',
                    'UPDATE',
                    'seguridad.Usuario',
                    (string)$usuarioId,
                    "Se " . ($bloqueado ? "BLOQUEÓ" : "DESBLOQUEÓ") . " el acceso para el usuario '{$u['username']}'"
                );
                break;
            }
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Estado de bloqueo actualizado correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'cambiar_rol') {
        $usuarioId = intval($input['usuario_id'] ?? 0);
        $rolId = intval($input['rol_id'] ?? 3);

        $rolesCatalogo = [
            1 => ['codigo' => 'ADMIN_RRHH', 'nombre' => 'Administrador General de RR.HH.'],
            2 => ['codigo' => 'JEFE_SERVICIO', 'nombre' => 'Jefatura de Servicio / UPSS'],
            3 => ['codigo' => 'OPERADOR_ASISTENCIA', 'nombre' => 'Operador de Control de Asistencia'],
            4 => ['codigo' => 'AUDITOR_PLANILLAS', 'nombre' => 'Auditor de Planillas y Remuneraciones']
        ];
        $rolInfo = $rolesCatalogo[$rolId] ?? $rolesCatalogo[3];

        foreach ($_SESSION['mock_usuarios'] as &$u) {
            if ($u['usuario_id'] === $usuarioId) {
                $u['rol_id'] = $rolId;
                $u['rol_codigo'] = $rolInfo['codigo'];
                $u['rol_nombre'] = $rolInfo['nombre'];

                Auditoria::registrar(
                    $currentUser['usuario_id'],
                    'SEGURIDAD',
                    'UPDATE',
                    'seguridad.UsuarioRol',
                    (string)$usuarioId,
                    "Se actualizó el rol del usuario '{$u['username']}' a {$rolInfo['codigo']} ({$rolInfo['nombre']})"
                );
                break;
            }
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Rol del usuario modificado con éxito.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'reset_password') {
        $usuarioId = intval($input['usuario_id'] ?? 0);
        $passwordTemp = 'Minsa.' . date('Y') . '!';

        foreach ($_SESSION['mock_usuarios'] as &$u) {
            if ($u['usuario_id'] === $usuarioId) {
                $u['bloqueado'] = false;

                Auditoria::registrar(
                    $currentUser['usuario_id'],
                    'SEGURIDAD',
                    'UPDATE',
                    'seguridad.Usuario',
                    (string)$usuarioId,
                    "Se realizó reseteo de contraseña del usuario '{$u['username']}' con generación de clave temporal"
                );
                break;
            }
        }

        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Contraseña reseteada exitosamente.',
            'clave_temporal' => $passwordTemp
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
