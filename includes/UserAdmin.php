<?php
/** Administración de cuentas y roles exclusiva del sistema RR. HH. */
require_once __DIR__.'/SystemContext.php';
class UserAdmin {
    public static function change(PDO $pdo, array $input, int $actor): array {
        $systemId = SystemContext::id($pdo);
        $action = (string)($input['accion'] ?? 'crear');
        if (!in_array($action, ['crear', 'habilitar', 'editar', 'cambiar_bloqueo', 'cambiar_rol', 'reset_password'], true)) {
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
            if (in_array($action, ['crear', 'habilitar', 'cambiar_rol', 'editar'], true)) {
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
                if ($s->fetchColumn() !== false) throw new InvalidArgumentException('El usuario o el trabajador ya tiene una cuenta registrada. Use Habilitar cuenta existente para asignarle acceso a RRHH.');
                $password = bin2hex(random_bytes(10));
                $s = $pdo->prepare('SET NOCOUNT ON; INSERT INTO seguridad.Usuario (PersonalID, NombreUsuario, PasswordHash,Activo,Bloqueado,FechaCreacion) VALUES (?, ?, ?,1,0,SYSDATETIME()); SELECT CAST(SCOPE_IDENTITY() AS INT)');
                $s->execute([$personal, $username, password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)$s->fetchColumn();
                $s->closeCursor();
                $s=$pdo->prepare('INSERT INTO seguridad.UsuarioSistema (UsuarioID,SistemaID,Activo,FechaInicio) VALUES (?,?,1,CAST(GETDATE() AS DATE))');
                $s->execute([$id,$systemId]);
            } elseif ($action === 'habilitar') {
                if ($id<=0) throw new InvalidArgumentException('Seleccione una cuenta existente.');
                $s=$pdo->prepare('SELECT UsuarioID,PersonalID,Activo,Bloqueado FROM seguridad.Usuario WHERE UsuarioID=?');
                $s->execute([$id]); $existing=$s->fetch(PDO::FETCH_ASSOC);
                if (!$existing || !(bool)$existing['Activo'] || (bool)$existing['Bloqueado']) throw new InvalidArgumentException('La cuenta no existe, está inactiva o bloqueada.');
                $s=$pdo->prepare('SELECT UsuarioID FROM seguridad.UsuarioSistema WITH (UPDLOCK, HOLDLOCK) WHERE UsuarioID=? AND SistemaID=?');
                $s->execute([$id,$systemId]);
                if ($s->fetchColumn()!==false) throw new InvalidArgumentException('La cuenta ya tiene un vínculo con RRHH. Recargue el listado; si está vencido requiere revisión.');
                $personal=$existing['PersonalID']!==null?(int)$existing['PersonalID']:null;
                if ($personal===null && !in_array($code,['ADMIN','ADMIN_RRHH','RRHH_ADMIN'],true)) throw new InvalidArgumentException('Una cuenta sin trabajador requiere un rol administrador técnico.');
                if ($personal!==null) {
                    $s=$pdo->prepare('SELECT PersonalID FROM rrhh.Personal WHERE PersonalID=? AND Activo=1');
                    $s->execute([$personal]);
                    if ($s->fetchColumn()===false) throw new InvalidArgumentException('El trabajador de la cuenta no existe o está inactivo.');
                }
                if (!in_array($code,['ADMIN','ADMIN_RRHH','RRHH_ADMIN','JEFE_RRHH','OPERADOR_ASISTENCIA','AUDITOR_PLANILLAS'],true)) {
                    $s=$pdo->prepare('SELECT ServicioID FROM rrhh.AsignacionPersonal WHERE PersonalID=? AND EsActual=1 AND Activo=1 AND ServicioID IS NOT NULL');
                    $s->execute([$personal]);
                    if ($s->fetchColumn()===false) throw new InvalidArgumentException('El rol requiere un trabajador con servicio activo asignado.');
                }
                $s=$pdo->prepare('INSERT INTO seguridad.UsuarioSistema (UsuarioID,SistemaID,Activo,FechaInicio) VALUES (?,?,1,CAST(GETDATE() AS DATE))');
                $s->execute([$id,$systemId]);
            } else {
                if ($id <= 0) throw new InvalidArgumentException('Cuenta inválida.');
                $s = $pdo->prepare('SELECT u.UsuarioID, u.NombreUsuario, u.PersonalID, u.Activo, u.Bloqueado FROM seguridad.Usuario u JOIN seguridad.UsuarioSistema us ON us.UsuarioID=u.UsuarioID WHERE u.UsuarioID = ? AND us.SistemaID = ?');
                $s->execute([$id,$systemId]);
                $currAccount = $s->fetch(PDO::FETCH_ASSOC);
                if ($currAccount === false) throw new InvalidArgumentException('La cuenta no está vinculada a RRHH. Use Habilitar cuenta existente.');
                if ($id === $actor && in_array($action, ['cambiar_rol', 'cambiar_bloqueo'], true)) {
                    throw new InvalidArgumentException('Use otra cuenta administradora para cambiar su propio rol o bloqueo.');
                }
            }
            if (in_array($action,['cambiar_bloqueo','reset_password'],true)) {
                $s=$pdo->prepare('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=? AND SistemaID<>?'); $s->execute([$id,$systemId]);
                if ((int)$s->fetchColumn()>0) throw new InvalidArgumentException('Cuenta compartida: bloqueo, edición y contraseña requieren administración central autorizada.');
            }
            if ($action === 'editar') {
                if ($id === $actor) {
                    if (!in_array($code, ['ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN'], true)) {
                        throw new InvalidArgumentException('Use otra cuenta administradora para cambiar su propio rol.');
                    }
                    if (isset($input['bloqueado']) && (bool)$input['bloqueado']) {
                        throw new InvalidArgumentException('No puede bloquear su propia cuenta.');
                    }
                    if (isset($input['activo']) && !(bool)$input['activo']) {
                        throw new InvalidArgumentException('No puede desactivar su propia cuenta.');
                    }
                }

                $username = trim((string)($input['username'] ?? ''));
                if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/D', $username)) {
                    throw new InvalidArgumentException('El nombre de usuario debe tener entre 3 y 80 caracteres (letras, números, punto, guion).');
                }
                $s = $pdo->prepare('SELECT UsuarioID FROM seguridad.Usuario WHERE NombreUsuario = ? AND UsuarioID <> ?');
                $s->execute([$username, $id]);
                if ($s->fetchColumn() !== false) {
                    throw new InvalidArgumentException('El nombre de usuario ya está registrado en otra cuenta.');
                }

                $personal = array_key_exists('personal_id', $input) && !empty($input['personal_id']) ? (int)$input['personal_id'] : null;
                if ($personal === null && !in_array($code, ['ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN'], true)) {
                    throw new InvalidArgumentException('Las cuentas sin trabajador requieren un rol administrador técnico.');
                }
                if ($personal !== null) {
                    $s = $pdo->prepare('SELECT PersonalID FROM rrhh.Personal WHERE PersonalID = ? AND Activo = 1');
                    $s->execute([$personal]);
                    if ($s->fetchColumn() === false) {
                        throw new InvalidArgumentException('El trabajador no existe o está inactivo.');
                    }
                    $s = $pdo->prepare('SELECT UsuarioID FROM seguridad.Usuario WHERE PersonalID = ? AND UsuarioID <> ?');
                    $s->execute([$personal, $id]);
                    if ($s->fetchColumn() !== false) {
                        throw new InvalidArgumentException('El trabajador ya está vinculado a otra cuenta de usuario.');
                    }
                }

                if (!in_array($code, ['ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN', 'JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'AUDITOR_PLANILLAS'], true)) {
                    $s = $pdo->prepare('SELECT ap.ServicioID FROM rrhh.AsignacionPersonal ap WHERE ap.PersonalID = ? AND ap.EsActual = 1 AND ap.Activo = 1 AND ap.ServicioID IS NOT NULL');
                    $s->execute([$personal]);
                    if ($s->fetchColumn() === false) {
                        throw new InvalidArgumentException('El rol requiere un trabajador con servicio activo asignado.');
                    }
                }

                $activo = array_key_exists('activo', $input) ? (int)(bool)$input['activo'] : (int)$currAccount['Activo'];
                $bloqueado = array_key_exists('bloqueado', $input) ? (int)(bool)$input['bloqueado'] : (int)$currAccount['Bloqueado'];

                $globalChanged=$username!==(string)$currAccount['NombreUsuario']
                    || $personal!==($currAccount['PersonalID']!==null?(int)$currAccount['PersonalID']:null)
                    || $activo!==(int)$currAccount['Activo'] || $bloqueado!==(int)$currAccount['Bloqueado'];
                if ($globalChanged) {
                    $s=$pdo->prepare('SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=? AND SistemaID<>?');
                    $s->execute([$id,$systemId]);
                    if ((int)$s->fetchColumn()>0) throw new InvalidArgumentException('Cuenta compartida: solo puede modificar su rol de RRHH. Los datos de acceso requieren administración central autorizada.');
                    $s = $pdo->prepare('UPDATE seguridad.Usuario SET NombreUsuario = ?, PersonalID = ?, Activo = ?, Bloqueado = ? WHERE UsuarioID = ?');
                    $s->execute([$username, $personal, $activo, $bloqueado, $id]);
                }

                // Actualizar rol exclusivamente en este sistema
                $s = $pdo->prepare('UPDATE seguridad.UsuarioRol SET Activo = 0, FechaFin = CAST(GETDATE() AS DATE) WHERE UsuarioID = ? AND Activo = 1 AND RolID IN (SELECT RolID FROM seguridad.Rol WHERE SistemaID = ?)');
                $s->execute([$id, $systemId]);
                $s = $pdo->prepare('SELECT RolID FROM seguridad.UsuarioRol WHERE UsuarioID = ? AND RolID = ?');
                $s->execute([$id, $role]);
                if ($s->fetchColumn() !== false) {
                    $s = $pdo->prepare('UPDATE seguridad.UsuarioRol SET Activo = 1, FechaInicio = CAST(GETDATE() AS DATE), FechaFin = NULL, FechaAsignacion = SYSDATETIME() WHERE UsuarioID = ? AND RolID = ?');
                } else {
                    $s = $pdo->prepare('INSERT INTO seguridad.UsuarioRol (UsuarioID, RolID, FechaInicio, Activo, FechaAsignacion) VALUES (?, ?, CAST(GETDATE() AS DATE), 1, SYSDATETIME())');
                }
                $s->execute([$id, $role]);
            } elseif (in_array($action,['crear','cambiar_rol','habilitar'],true)) {
                if (!in_array($code, ['ADMIN', 'ADMIN_RRHH', 'RRHH_ADMIN', 'JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'AUDITOR_PLANILLAS'], true)) {
                    $s = $pdo->prepare('SELECT ap.ServicioID FROM seguridad.Usuario u JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID = u.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1 WHERE u.UsuarioID = ? AND ap.ServicioID IS NOT NULL');
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
                $customPass = trim((string)($input['password'] ?? $input['clave'] ?? $input['nueva_clave'] ?? ''));
                if ($customPass !== '') {
                    if (mb_strlen($customPass, 'UTF-8') < 4) {
                        throw new InvalidArgumentException('La contraseña debe tener al menos 4 caracteres.');
                    }
                    if (mb_strlen($customPass, 'UTF-8') > 120) {
                        throw new InvalidArgumentException('La contraseña no puede exceder 120 caracteres.');
                    }
                    $password = $customPass;
                } else {
                    $password = bin2hex(random_bytes(10));
                }
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
            if (!Auditoria::registrar($actor, 'SEGURIDAD', strtoupper($action), 'seguridad.Usuario', (string)$id, 'Administración de cuenta. Acción: ' . $action, $pdo)) {
                throw new RuntimeException('No se pudo registrar la auditoría.');
            }
            $pdo->commit();
            $result = ['ok'=>true, 'usuario_id'=>$id, 'mensaje'=>$action==='habilitar'?'Cuenta habilitada en RRHH. Se conservan su contraseña y accesos de otros sistemas.':'Cuenta actualizada en SQL Server.'];
            if ($password !== null) $result['clave_temporal'] = $password;
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
