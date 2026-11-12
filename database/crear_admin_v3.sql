/* Ejecutar completo en SQL Server / DBeaver. No crea personal ni elimina registros.
   Crea o habilita admin / admin123 exclusivamente para RRHH.
   Si admin existe, RESTABLECE su contraseña y conserva su PersonalID y otros roles.
   La clave se guarda como bcrypt compatible con password_verify de PHP.
   Incluye 8 módulos y 12 permisos derivados de index.php y api/administracion/roles.php.
   IMPORTANTE: Auth::roleAllows todavía decide permisos por código de rol en PHP;
   estos catálogos documentan las capacidades actuales, no sustituyen esa lógica.
   Ejecutar después de restaurar el backup, o generar OTRO backup después de ejecutar.
   No crea tablas, no elimina registros ni concede acceso a otros sistemas.
   Analizado contra la estructura real de V3 el 10/11/2026. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;

IF DB_NAME() <> N'BD_HOSPITAL_V3'
    THROW 51090, N'Base incorrecta: se requiere BD_HOSPITAL_V3.', 1;

DECLARE @Hash varchar(500) = '$2y$12$nIy4WjH1YjcTGD5WhVLAF.pVz4usrwIv3b7q0R2H6.7LcY645XQgC';
DECLARE @SistemaID int, @RolID int, @UsuarioID int;
-- Solo si admin NO existe y PersonalID es obligatorio: indicar un PersonalID real.
-- No usar un número arbitrario ni crear un trabajador ficticio.
DECLARE @PersonalIDNuevoAdmin int = NULL;
BEGIN TRY
    BEGIN TRANSACTION;
    DECLARE @Lock int;
    EXEC @Lock=sys.sp_getapplock @Resource=N'RRHH_CONFIGURAR_ADMIN',
        @LockMode='Exclusive', @LockOwner='Transaction', @LockTimeout=5000;
    IF @Lock < 0 THROW 51091, N'Hay otra operación administrativa en curso.', 1;

    IF (SELECT COUNT(*) FROM seguridad.Sistema WITH (UPDLOCK,HOLDLOCK) WHERE Codigo='RRHH') > 1
        THROW 51092, N'Sistema RRHH duplicado: revisar sin sobrescribir.', 1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo='RRHH';
    IF @SistemaID IS NULL
    BEGIN
        INSERT seguridad.Sistema (Codigo,Nombre,Activo) VALUES ('RRHH',N'Recursos Humanos',1);
        SET @SistemaID=CONVERT(int,SCOPE_IDENTITY());
    END
    ELSE UPDATE seguridad.Sistema SET Activo=1 WHERE SistemaID=@SistemaID;

    IF (SELECT COUNT(*) FROM seguridad.Rol WITH (UPDLOCK,HOLDLOCK)
        WHERE Codigo='RRHH_ADMIN' AND SistemaID=@SistemaID) > 1
        THROW 51093, N'Rol administrativo RRHH duplicado.', 1;
    SELECT @RolID=RolID FROM seguridad.Rol WHERE Codigo='RRHH_ADMIN' AND SistemaID=@SistemaID;
    IF @RolID IS NULL
    BEGIN
        INSERT seguridad.Rol (SistemaID,Codigo,Nombre,Activo)
        VALUES (@SistemaID,'RRHH_ADMIN',N'Administrador de Recursos Humanos',1);
        SET @RolID=CONVERT(int,SCOPE_IDENTITY());
    END
    ELSE UPDATE seguridad.Rol SET Activo=1 WHERE RolID=@RolID;

    IF (SELECT COUNT(*) FROM seguridad.Usuario WITH (UPDLOCK,HOLDLOCK) WHERE NombreUsuario='admin') > 1
        THROW 51094, N'Usuario admin duplicado: no se elige una cuenta arbitraria.', 1;
    SELECT @UsuarioID=UsuarioID FROM seguridad.Usuario WHERE NombreUsuario='admin';
    IF @UsuarioID IS NULL
    BEGIN
        IF @PersonalIDNuevoAdmin IS NULL AND COLUMNPROPERTY(
            OBJECT_ID(N'seguridad.Usuario'), N'PersonalID', 'AllowsNull') = 0
            THROW 51097, N'Admin no existe y Usuario.PersonalID es obligatorio. Indique un PersonalID real en @PersonalIDNuevoAdmin.', 1;
        IF @PersonalIDNuevoAdmin IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM rrhh.Personal WHERE PersonalID=@PersonalIDNuevoAdmin)
            THROW 51098, N'El PersonalID indicado no existe. No se crea personal ficticio.', 1;
        INSERT seguridad.Usuario (PersonalID,NombreUsuario,PasswordHash,Activo,Bloqueado)
        VALUES (@PersonalIDNuevoAdmin,'admin',@Hash,1,0);
        SET @UsuarioID=CONVERT(int,SCOPE_IDENTITY());
    END
    ELSE
        UPDATE seguridad.Usuario SET PasswordHash=@Hash,Activo=1,Bloqueado=0
        WHERE UsuarioID=@UsuarioID;

    IF (SELECT COUNT(*) FROM seguridad.UsuarioRol WITH (UPDLOCK,HOLDLOCK)
        WHERE UsuarioID=@UsuarioID AND RolID=@RolID) > 1
        THROW 51095, N'La relación usuario/rol está duplicada.', 1;
    IF EXISTS (SELECT 1 FROM seguridad.UsuarioRol WHERE UsuarioID=@UsuarioID AND RolID=@RolID)
        UPDATE seguridad.UsuarioRol SET Activo=1,FechaInicio=CAST(GETDATE() AS date),FechaFin=NULL
        WHERE UsuarioID=@UsuarioID AND RolID=@RolID;
    ELSE
        INSERT seguridad.UsuarioRol (UsuarioID,RolID,Activo,FechaInicio)
        VALUES (@UsuarioID,@RolID,1,CAST(GETDATE() AS date));

    IF (SELECT COUNT(*) FROM seguridad.UsuarioSistema WITH (UPDLOCK,HOLDLOCK)
        WHERE UsuarioID=@UsuarioID AND SistemaID=@SistemaID) > 1
        THROW 51096, N'La relación usuario/sistema está duplicada.', 1;
    IF EXISTS (SELECT 1 FROM seguridad.UsuarioSistema WHERE UsuarioID=@UsuarioID AND SistemaID=@SistemaID)
        UPDATE seguridad.UsuarioSistema SET Activo=1,FechaInicio=CAST(GETDATE() AS date),FechaFin=NULL
        WHERE UsuarioID=@UsuarioID AND SistemaID=@SistemaID;
    ELSE
        INSERT seguridad.UsuarioSistema (UsuarioID,SistemaID,Activo,FechaInicio)
        VALUES (@UsuarioID,@SistemaID,1,CAST(GETDATE() AS date));

    -- Catálogo tomado de las rutas y acciones reales del proyecto.
    -- Códigos calificados por sistema para no colisionar con Clínica/Laboratorio.
    DECLARE @Modulos TABLE (Codigo varchar(30) PRIMARY KEY, Nombre nvarchar(300), Ruta varchar(300));
    INSERT @Modulos VALUES
      ('dashboard',N'Panel principal','index.php?page=dashboard'),
      ('personal',N'Personal','index.php?page=personal'),
      ('horarios',N'Horarios y turnos','index.php?page=horarios'),
      ('programacion',N'Programación','index.php?page=programacion'),
      ('asistencia',N'Asistencia','index.php?page=asistencia'),
      ('ausencias',N'Ausencias','index.php?page=ausencias'),
      ('reportes',N'Reportes','index.php?page=reportes'),
      ('administracion',N'Administración','index.php?page=administracion');

    IF EXISTS (SELECT m.Codigo FROM seguridad.Modulo m WITH (UPDLOCK,HOLDLOCK)
        JOIN @Modulos c ON c.Codigo=m.Codigo WHERE m.SistemaID=@SistemaID
        GROUP BY m.Codigo HAVING COUNT(*)>1)
        THROW 51100, N'Existen módulos RRHH duplicados. Revisar antes de continuar.', 1;
    INSERT seguridad.Modulo (SistemaID,Codigo,Nombre,Ruta,Activo)
      SELECT @SistemaID,c.Codigo,c.Nombre,c.Ruta,1 FROM @Modulos c
      WHERE NOT EXISTS (SELECT 1 FROM seguridad.Modulo m
        WHERE m.SistemaID=@SistemaID AND m.Codigo=c.Codigo);
    UPDATE m SET Activo=1 FROM seguridad.Modulo m
      JOIN @Modulos c ON c.Codigo=m.Codigo WHERE m.SistemaID=@SistemaID;

    DECLARE @Permisos TABLE (Modulo varchar(30), Accion varchar(40), Codigo varchar(100) PRIMARY KEY);
    INSERT @Permisos SELECT Codigo,'ver','RRHH.'+Codigo+'.ver' FROM @Modulos;
    INSERT @Permisos VALUES
      ('programacion','editar','RRHH.programacion.editar'),
      ('asistencia','regularizar','RRHH.asistencia.regularizar'),
      ('ausencias','dictaminar','RRHH.ausencias.dictaminar'),
      ('reportes','exportar_excel','RRHH.reportes.exportar_excel');

    IF EXISTS (SELECT p.Codigo FROM seguridad.Permiso p WITH (UPDLOCK,HOLDLOCK)
        JOIN @Permisos c ON c.Codigo=p.Codigo GROUP BY p.Codigo HAVING COUNT(*)>1)
        THROW 51101, N'Existen códigos de permiso duplicados.', 1;
    IF EXISTS (SELECT 1 FROM seguridad.Permiso p JOIN @Permisos c ON c.Codigo=p.Codigo
        LEFT JOIN seguridad.Modulo m ON m.ModuloID=p.ModuloID
        WHERE m.ModuloID IS NULL OR m.SistemaID<>@SistemaID OR m.Codigo<>c.Modulo)
        THROW 51102, N'Un permiso coincide por código pero pertenece a otro módulo o sistema.', 1;
    INSERT seguridad.Permiso (ModuloID,Codigo,Nombre,Activo)
      SELECT m.ModuloID,c.Codigo,CONVERT(nvarchar(300),c.Modulo+': '+c.Accion),1
      FROM @Permisos c JOIN seguridad.Modulo m ON m.Codigo=c.Modulo AND m.SistemaID=@SistemaID
      WHERE NOT EXISTS (SELECT 1 FROM seguridad.Permiso p WHERE p.Codigo=c.Codigo);
    UPDATE p SET Activo=1 FROM seguridad.Permiso p JOIN @Permisos c ON c.Codigo=p.Codigo;

    -- Se asignan únicamente los 12 permisos identificados; se conservan otros vínculos.
    IF EXISTS (SELECT rp.PermisoID FROM seguridad.RolPermiso rp WITH (UPDLOCK,HOLDLOCK)
        JOIN seguridad.Permiso p ON p.PermisoID=rp.PermisoID
        JOIN @Permisos c ON c.Codigo=p.Codigo WHERE rp.RolID=@RolID
        GROUP BY rp.PermisoID HAVING COUNT(*)>1)
        THROW 51103, N'Existen relaciones rol/permiso duplicadas.', 1;
    INSERT seguridad.RolPermiso (RolID,PermisoID,Activo)
      SELECT @RolID,p.PermisoID,1 FROM seguridad.Permiso p
      JOIN @Permisos c ON c.Codigo=p.Codigo
      WHERE NOT EXISTS (SELECT 1 FROM seguridad.RolPermiso rp
        WHERE rp.RolID=@RolID AND rp.PermisoID=p.PermisoID);
    UPDATE rp SET Activo=1 FROM seguridad.RolPermiso rp
      JOIN seguridad.Permiso p ON p.PermisoID=rp.PermisoID
      JOIN @Permisos c ON c.Codigo=p.Codigo WHERE rp.RolID=@RolID;

    INSERT auditoria.EventoAuditoria
        (UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
    VALUES (@UsuarioID,@SistemaID,SYSDATETIME(),'CONFIGURAR_ADMIN','seguridad.Usuario',
        CONVERT(varchar(100),@UsuarioID),N'[SEGURIDAD] Cuenta admin habilitada y contraseña restablecida junto con catálogo de 8 módulos y 12 permisos RRHH mediante script.');

    COMMIT TRANSACTION;
    SELECT N'Cuenta administrativa habilitada para RRHH' AS Resultado,
        @UsuarioID AS UsuarioID, N'admin' AS Usuario, N'RRHH' AS Sistema;
    SELECT s.Codigo AS Sistema,r.Codigo AS Rol,m.Codigo AS Modulo,
        p.Codigo AS Permiso,rp.Activo
    FROM seguridad.RolPermiso rp
    JOIN seguridad.Rol r ON r.RolID=rp.RolID
    JOIN seguridad.Permiso p ON p.PermisoID=rp.PermisoID
    JOIN seguridad.Modulo m ON m.ModuloID=p.ModuloID
    JOIN seguridad.Sistema s ON s.SistemaID=m.SistemaID
    WHERE rp.RolID=@RolID AND m.SistemaID=@SistemaID
    ORDER BY m.Codigo,p.Codigo;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
