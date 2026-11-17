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

    if (($_GET['modo']??'')==='habilitables') {
        try {
            $stmt=$pdo->prepare("SELECT u.UsuarioID AS usuario_id,u.NombreUsuario AS username,u.PersonalID AS personal_id,
                p.NumeroDocumento AS dni,p.NombreCompletoOriginal AS nombre_original,
                LTRIM(RTRIM(CONCAT(p.Nombres,' ',p.ApellidoPaterno,' ',p.ApellidoMaterno))) AS nombre_completo
                FROM seguridad.Usuario u LEFT JOIN rrhh.Personal p ON p.PersonalID=u.PersonalID
                WHERE u.Activo=1 AND u.Bloqueado=0 AND (u.PersonalID IS NULL OR p.Activo=1)
                AND NOT EXISTS (SELECT 1 FROM seguridad.UsuarioSistema us WHERE us.UsuarioID=u.UsuarioID AND us.SistemaID=?)
                ORDER BY u.NombreUsuario,u.UsuarioID");
            $stmt->execute([SystemContext::id($pdo)]);
            echo json_encode(['ok'=>true,'cuentas'=>$stmt->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('Cuentas habilitables: '.$e->getMessage());
            apiError(503,'No se pudieron consultar las cuentas existentes.');
        }
        exit;
    }

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
                    ISNULL(r.RolID, 0) AS RolID,
                    ISNULL(r.Codigo, 'SIN_ROL') AS RolCodigo,
                    ISNULL(r.Nombre, 'Sin Rol Asignado') AS RolNombre,
                    ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                    ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo
                FROM seguridad.Usuario u
                LEFT JOIN rrhh.Personal p ON u.PersonalID = p.PersonalID
                OUTER APPLY (SELECT TOP 1 rx.RolID,rx.Codigo,rx.Nombre FROM seguridad.UsuarioRol ur
                    JOIN seguridad.Rol rx ON rx.RolID=ur.RolID AND rx.Activo=1 AND rx.SistemaID=:sistema_id
                    WHERE ur.UsuarioID=u.UsuarioID AND ur.Activo=1
                    AND (ur.FechaInicio IS NULL OR ur.FechaInicio<=CAST(GETDATE() AS DATE))
                    AND (ur.FechaFin IS NULL OR ur.FechaFin>=CAST(GETDATE() AS DATE))
                    ORDER BY CASE WHEN rx.Codigo IN ('ADMIN','ADMIN_RRHH','RRHH_ADMIN') THEN 0 ELSE 1 END,rx.RolID) r
                LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
                LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
                LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
                WHERE EXISTS (SELECT 1 FROM seguridad.UsuarioSistema us WHERE us.UsuarioID=u.UsuarioID
                    AND us.SistemaID=:sistema_vinculo)
            ";
            $params = [':sistema_id'=>SystemContext::id($pdo),':sistema_vinculo'=>SystemContext::id($pdo)];

            if ($busqueda !== '') {
                $sql .= " AND (u.NombreUsuario LIKE :q1 OR p.NumeroDocumento LIKE :q2 OR p.Nombres LIKE :q3 OR p.ApellidoPaterno LIKE :q4)";
                foreach (range(1,4) as $n) $params[':q'.$n] = "%$busqueda%";
            }
            if ($rolFiltro !== '') {
                if ($rolFiltro === 'SIN_ROL') {
                    $sql .= " AND r.RolID IS NULL";
                } else {
                    $sql .= " AND r.Codigo = :rol";
                    $params[':rol'] = $rolFiltro;
                }
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
