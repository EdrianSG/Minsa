<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';
apiGuard('dashboard', ['GET']);
require_once __DIR__ . '/../includes/DataRevision.php';
$pdo = Database::getConnection();
if ($pdo) {
    try {
        $user = Auth::getCurrentUser();
        $valid = $pdo->prepare("SELECT u.UsuarioID FROM seguridad.Usuario u
            INNER JOIN seguridad.UsuarioRol ur ON ur.UsuarioID = u.UsuarioID AND ur.Estado = 1
            INNER JOIN seguridad.Rol r ON r.RolID = ur.RolID
            WHERE u.UsuarioID = :usuario AND u.Estado = 1 AND u.Bloqueado = 0 AND r.Codigo = :rol");
        $valid->execute([':usuario'=>(int)$user['usuario_id'], ':rol'=>$user['rol']]);
        if ($valid->fetchColumn() === false) {
            unset($_SESSION['user']);
            apiError(401, 'La cuenta o sus permisos cambiaron. Inicie sesión nuevamente.');
        }
        $service = Auth::getAllowedServiceId();
        if ($service !== null) {
            $assigned = $pdo->prepare('SELECT ap.ServicioID FROM seguridad.Usuario u
                INNER JOIN rrhh.Personal p ON p.PersonaID = u.PersonaID
                INNER JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID = p.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
                WHERE u.UsuarioID = ? AND ap.ServicioID = ?');
            $assigned->execute([(int)$user['usuario_id'], $service]);
            if ($assigned->fetchColumn() === false) { unset($_SESSION['user']); apiError(401, 'Su servicio cambió. Inicie sesión nuevamente.'); }
        }
    } catch (PDOException $e) { apiError(503, 'No se pudo verificar la sesión en SQL Server.'); }
}
$revision = $pdo ? DataRevision::read($pdo) : null;
echo json_encode(['ok'=>true, 'conexion_sql'=>$pdo !== null, 'revision'=>$revision,
    'scope'=>hash('sha256', session_id() . '|' . Auth::getCurrentUser()['rol'] . '|' . (string)Auth::getAllowedServiceId())]);
