<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET', 'POST'], []);

/**
 * API Endpoint: /api/administracion/usuarios.php
 * Hospital Distrital de Pacasmayo - ETAPA 8
 * 
 * Gestión de Cuentas y Accesos de Usuarios del Sistema:
 * Mapeado a seguridad.Usuario, seguridad.UsuarioRol y rrhh.Personal
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

    $pdo = apiRequireDatabase();
    $dbConnected = ($pdo !== null);

    $usuarios = [];

    if ($dbConnected) {
        try {
            $sql = "
                SELECT 
                    u.UsuarioID,
                    u.PersonalID,
                    u.NombreUsuario,
                    u.FechaCreacion,
                    u.UltimoAcceso,
                    u.Bloqueado,
                    u.Activo,
                    p.NumeroDocumento AS DNI,
                    CONCAT(p.Nombres, ' ', p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, '')) AS NombreCompleto,
                    r.RolID,
                    r.Codigo AS RolCodigo,
                    r.Nombre AS RolNombre,
                    ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                    ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo
                FROM seguridad.Usuario u
                LEFT JOIN rrhh.Personal p ON u.PersonalID = p.PersonalID
                LEFT JOIN seguridad.UsuarioRol ur ON u.UsuarioID = ur.UsuarioID AND ur.Activo = 1
                INNER JOIN seguridad.Rol r ON ur.RolID = r.RolID AND r.SistemaID = :sistema_id
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                WHERE " . SystemContext::membershipSql() . "
            ";
            $params = [':sistema_id'=>SystemContext::id($pdo)];

            if ($busqueda !== '') {
                $sql .= " AND (u.NombreUsuario LIKE :q1 OR p.NumeroDocumento LIKE :q2 OR p.Nombres LIKE :q3 OR p.ApellidoPaterno LIKE :q4)";
                foreach (range(1,4) as $n) $params[':q'.$n] = "%$busqueda%";
            }
            if ($rolFiltro !== '') {
                $sql .= " AND r.Codigo = :rol";
                $params[':rol'] = $rolFiltro;
            }
            if ($estadoFiltro !== null) {
                $sql .= " AND u.Activo = :estado";
                $params[':estado'] = $estadoFiltro;
            }

            $sql .= " ORDER BY u.UsuarioID ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($filas as $f) {
                $usuarios[] = [
                    'usuario_id'       => intval($f['UsuarioID']),
                    'personal_id'      => $f['PersonalID'] !== null ? intval($f['PersonalID']) : null,
                    'username'         => $f['NombreUsuario'],
                    'dni'              => $f['DNI'],
                    'nombre_completo'  => $f['NombreCompleto'],
                    'rol_id'           => intval($f['RolID']),
                    'rol_codigo'       => $f['RolCodigo'] ?? 'OPERADOR_ASISTENCIA',
                    'rol_nombre'       => $f['RolNombre'] ?? 'Operador de Asistencia',
                    'servicio_nombre'  => $f['NombreServicio'] ?? 'Recursos Humanos',
                    'cargo_nombre'     => $f['NombreCargo'] ?? 'Especialista en RR.HH.',
                    'fecha_creacion'   => $f['FechaCreacion'],
                    'ultimo_acceso'    => $f['UltimoAcceso'],
                    'bloqueado'        => (bool)$f['Bloqueado'],
                    'estado'           => (bool)$f['Activo']
                ];
            }
        } catch (Exception $e) {
            error_log('Consulta de cuentas: ' . $e->getMessage());
            apiError(503, 'No se pudieron consultar las cuentas de SQL Server.');
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
    require_once __DIR__ . '/../../includes/UserAdmin.php';
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (!is_array($input)) apiError(400, 'Solicitud inválida.');
    try {
        echo json_encode(UserAdmin::change(apiRequireDatabase(), $input, (int)$currentUser['usuario_id']), JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        apiError(400, $e->getMessage());
    } catch (Throwable $e) {
        error_log('Administración de cuenta: ' . $e->getMessage());
        apiError(500, 'No se pudo actualizar la cuenta. Se revirtieron los cambios.');
    }
}
