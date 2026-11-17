/* Solo lectura. Ejecutar en la misma instancia y base configuradas en RRHH.
   No crea cuentas ni vínculos, no modifica roles y no consulta contraseñas.
   CodigoSistema debe coincidir con APP_SYSTEM_CODE (RRHH por defecto). */
DECLARE @UsuarioID int = 7;
DECLARE @PersonalID int = 2302;
DECLARE @RolID int = 3;
DECLARE @CodigoSistema nvarchar(50) = N'RRHH';

SELECT CAST(SERVERPROPERTY('ServerName') AS nvarchar(128)) AS Servidor,
       DB_NAME() AS BaseActual, @CodigoSistema AS CodigoSistemaEsperado;

SELECT SistemaID, Codigo, Activo
FROM seguridad.Sistema WHERE Codigo = @CodigoSistema;

SELECT UsuarioID, PersonalID, NombreUsuario, Activo, Bloqueado
FROM seguridad.Usuario WHERE UsuarioID = @UsuarioID OR PersonalID = @PersonalID;

SELECT us.UsuarioID, us.SistemaID, s.Codigo AS Sistema,
       us.Activo, us.FechaInicio, us.FechaFin
FROM seguridad.UsuarioSistema us
LEFT JOIN seguridad.Sistema s ON s.SistemaID = us.SistemaID
WHERE us.UsuarioID = @UsuarioID;

SELECT ur.UsuarioID, ur.RolID, r.Codigo AS Rol, r.SistemaID,
       s.Codigo AS Sistema, r.Activo AS RolActivo,
       ur.Activo AS AsignacionActiva, ur.FechaInicio, ur.FechaFin
FROM seguridad.UsuarioRol ur
LEFT JOIN seguridad.Rol r ON r.RolID = ur.RolID
LEFT JOIN seguridad.Sistema s ON s.SistemaID = r.SistemaID
WHERE ur.UsuarioID = @UsuarioID;

SELECT r.RolID, r.Codigo, r.SistemaID, s.Codigo AS Sistema, r.Activo
FROM seguridad.Rol r
LEFT JOIN seguridad.Sistema s ON s.SistemaID = r.SistemaID
WHERE r.RolID = @RolID;

-- Reproduce la condición que precede al mensaje "La cuenta no existe".
SELECT u.UsuarioID, u.PersonalID, us.SistemaID, s.Codigo AS Sistema
FROM seguridad.Usuario u
JOIN seguridad.UsuarioSistema us ON us.UsuarioID = u.UsuarioID
JOIN seguridad.Sistema s ON s.SistemaID = us.SistemaID
WHERE u.UsuarioID = @UsuarioID AND s.Codigo = @CodigoSistema AND s.Activo = 1;
