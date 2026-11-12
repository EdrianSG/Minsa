<?php
/** Administración de cuentas y roles exclusiva del sistema RR. HH. */
require_once __DIR__.'/SystemContext.php';
class UserAdmin {
    public static function change(PDO $pdo, array $input, int $actor): array {
        $systemId = SystemContext::id($pdo);
        $action = (string)($input['accion'] ?? 'crear');
        if (!in_array($action, ['crear', 'cambiar_bloqueo', 'cambiar_rol', 'reset_password'], true)) {
            throw new InvalidArgumentException('Acción de cuenta inválida.');
        }
        $id = (int)($input['usuario_id'] ?? 0);
        $role = (int)($input['rol_id'] ?? 0);
        $password = null;
        if ($pdo->inTransaction()) throw new RuntimeException('La operación requiere una transacción propia.');
        $pdo->beginTransaction();
        try {
            // Serializa cambios administrativos para conservar al menos un administrador habilitado.
            $pdo->query('SELECT UsuarioID FROM seguridad.Usuario WITH (UPDLOCK, HOLDLOCK)')->fetchAll();
            $pdo->query('SELECT UsuarioID FROM seguridad.UsuarioRol WITH (UPDLOCK, HOLDLOCK)')->fetchAll();
            if (in_array($action, ['crear', 'cambiar_rol'], true)) {
                $s = $pdo->prepare('SELECT Codigo FROM seguridad.Rol WHERE RolID = ? AND Activo = 1 AND SistemaID = ?');
                $s->execute([$role, $systemId]);
                $code = $s->fetchColumn();
                if ($code === false || !in_array($code, ['ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN', 'JEFE_RRHH', 'SUPERVISOR', 'JEFE_SERVICIO', 'OPERADOR_ASISTENCIA', 'AUDITOR_PLANILLAS', 'CONSULTA'], true)) {
                    throw new InvalidArgumentException('Seleccione un rol activo compatible con el sistema.');
                }
            }
            if ($action === 'crear') {
                $username = trim((string)($input['username'] ?? ''));
                $personal = !empty($input['personal_id']) ? (int)$input['personal_id'] : null;
                if ($personal === null && !in_array($code, ['ADMIN','ADMIN_RRHH','RRHH_ADMIN'],true)) throw new InvalidArgumentException('Las cuentas sin trabajador requieren un rol administrador técnico.');
                if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/D', $username)) {
                    throw new InvalidArgumentException('Seleccione un trabajador y un usuario de 3 a 80 caracteres (letras, números, punto, guion).');
                }
                if ($personal !== null) {
                $s = $pdo->prepare('SELECT PersonalID FROM rrhh.Personal WHERE PersonalID = ? AND Activo = 1');
                $s->execute([$personal]);
                if ($s->fetchColumn() === false) throw new InvalidArgumentException('El trabajador no existe o está inactivo.');
                }
                $s = $pdo->prepare('SELECT UsuarioID FROM seguridad.Usuario WHERE NombreUsuario = ? OR PersonalID = ?');
                $s->execute([$username, $personal]);
                if ($s->fetchColumn() !== false) throw new InvalidArgumentException('El usuario o el trabajador ya tiene una cuenta registrada.');
                $password = bin2hex(random_bytes(10));
                $s = $pdo->prepare('SET NOCOUNT ON; INSERT INTO seguridad.Usuario (PersonalID, NombreUsuario, PasswordHash,Activo,Bloqueado,FechaCreacion) VALUES (?, ?, ?,1,0,SYSDATETIME()); SELECT CAST(SCOPE_IDENTITY() AS INT)');
                $s->execute([$personal, $username, password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)$s->fetchColumn();
                $s->closeCursor();
                $s=$pdo->prepare('INSERT INTO seguridad.UsuarioSistema (UsuarioID,SistemaID,Activo,FechaInicio) VALUES (?,?,1,CAST(GETDATE() AS DATE))');
                $s->execute([$id,$systemId]);
            } else {
                if ($id <= 0) throw new InvalidArgumentException('Cuenta inválida.');
                $s = $pdo->prepare('SELECT u.UsuarioID FROM seguridad.Usuario u JOIN seguridad.UsuarioSistema us ON us.UsuarioID=u.UsuarioID WHERE u.UsuarioID = ? AND us.SistemaID = ?');
                $s->execute([$id,$systemId]);
                if ($s->fetchColumn() === false) throw new InvalidArgumentException('La cuenta no existe.');
                if ($id === $actor && in_array($action, ['cambiar_rol', 'cambiar_bloqueo'], true)) {
                    throw new InvalidArgumentException('Use otra cuenta administradora para cambiar su propio rol o bloqueo.');
                }
            }
            if (in_array($action,['cambiar_bloqueo','reset_password'],true)) {
                $s=$pdo->prepare('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=? AND SistemaID<>?'); $s->execute([$id,$systemId]);
                if ((int)$s->fetchColumn()>0) throw new InvalidArgumentException('Cuenta compartida: bloqueo y contraseña requieren administración central autorizada.');
            }
            if (in_array($action, ['crear', 'cambiar_rol'], true)) {
                if (!in_array($code, ['ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN', 'JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'AUDITOR_PLANILLAS'], true)) {
                    $s = $pdo->prepare('SELECT ap.ServicioID FROM seguridad.Usuario u JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID = u.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1 WHERE u.UsuarioID = ?');
                    $s->execute([$id]);
                    if ($s->fetchColumn() === false) throw new InvalidArgumentException('El rol requiere un trabajador con servicio activo asignado.');
                }
                // Cambiar exclusivamente roles de este sistema; conservar los de Clínica y Laboratorio.
                $s = $pdo->prepare('UPDATE seguridad.UsuarioRol SET Activo = 0, FechaFin = CAST(GETDATE() AS DATE) WHERE UsuarioID = ? AND Activo = 1 AND RolID IN (SELECT RolID FROM seguridad.Rol WHERE SistemaID = ?)');
                $s->execute([$id,$systemId]);
                $s = $pdo->prepare('SELECT RolID FROM seguridad.UsuarioRol WHERE UsuarioID = ? AND RolID = ?');
                $s->execute([$id, $role]);
                if ($s->fetchColumn() !== false) {
                    $s = $pdo->prepare('UPDATE seguridad.UsuarioRol SET Activo = 1, FechaInicio = CAST(GETDATE() AS DATE), FechaFin = NULL, FechaAsignacion = SYSDATETIME() WHERE UsuarioID = ? AND RolID = ?');
                } else {
                    $s = $pdo->prepare('INSERT INTO seguridad.UsuarioRol (UsuarioID, RolID, FechaInicio,Activo,FechaAsignacion) VALUES (?, ?, CAST(GETDATE() AS DATE),1,SYSDATETIME())');
                }
                $s->execute([$id, $role]);
            } elseif ($action === 'cambiar_bloqueo') {
                if (!array_key_exists('bloqueado', $input) || !in_array($input['bloqueado'], [true, false, 0, 1, '0', '1'], true)) {
                    throw new InvalidArgumentException('Estado de bloqueo inválido.');
                }
                $s = $pdo->prepare('UPDATE seguridad.Usuario SET Bloqueado = ? WHERE UsuarioID = ?');
                $s->execute([(int)(bool)$input['bloqueado'], $id]);
            } elseif ($action === 'reset_password') {
                $password = bin2hex(random_bytes(10));
                $s = $pdo->prepare('UPDATE seguridad.Usuario SET PasswordHash = ? WHERE UsuarioID = ?');
                $s->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            $adminStmt=$pdo->prepare("SELECT COUNT(DISTINCT u.UsuarioID) FROM seguridad.Usuario u
                JOIN seguridad.UsuarioRol ur ON ur.UsuarioID = u.UsuarioID AND ur.Activo = 1
                JOIN seguridad.Rol r ON r.RolID = ur.RolID AND r.Activo = 1
                WHERE u.Activo = 1 AND u.Bloqueado = 0 AND r.Codigo IN ('ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN')
                AND (ur.FechaInicio IS NULL OR ur.FechaInicio <= CAST(GETDATE() AS DATE)) AND (ur.FechaFin IS NULL OR ur.FechaFin >= CAST(GETDATE() AS DATE))
                AND r.SistemaID = ? AND " . SystemContext::membershipSql());
            $adminStmt->execute([$systemId]);
            $admins=(int)$adminStmt->fetchColumn();
            if (!$admins) throw new InvalidArgumentException('Debe conservar al menos un administrador habilitado.');
            require_once __DIR__ . '/auditoria.php';
            if (!Auditoria::registrar($actor, 'SEGURIDAD', strtoupper($action), 'seguridad.Usuario', (string)$id, 'Administración de cuenta. Acción: ' . $action)) {
                throw new RuntimeException('No se pudo registrar la auditoría.');
            }
            $pdo->commit();
            $result = ['ok'=>true, 'usuario_id'=>$id, 'mensaje'=>'Cuenta actualizada en SQL Server.'];
            if ($password !== null) $result['clave_temporal'] = $password;
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
