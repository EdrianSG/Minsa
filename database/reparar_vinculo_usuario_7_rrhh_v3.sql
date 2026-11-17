/* Reparación puntual basada en el diagnóstico de SVR-WEB / BD_HOSPITAL_V3.
   Cuenta 7, PersonalID 2302, usuario 40955497. Rol solicitado: CONSULTA (RRHH).
   No crea otra cuenta, no modifica contraseña, personal ni roles de otros sistemas.
   Ejecutar completo. Se revierte ante errores o relaciones inesperadas. */
SET XACT_ABORT ON;
SET NOCOUNT ON;
IF DB_NAME() <> N'BD_HOSPITAL_V3'
    THROW 51000, 'Seleccione BD_HOSPITAL_V3 antes de ejecutar.', 1;

DECLARE @UsuarioID int = 7, @PersonalID int = 2302;
DECLARE @NombreUsuario nvarchar(80) = N'40955497';
DECLARE @SistemaID int, @RolID int;
DECLARE @VinculoCreado bit = 0, @RolCreado bit = 0;

BEGIN TRY
    BEGIN TRANSACTION;
    IF (SELECT COUNT(*) FROM seguridad.Sistema WITH (UPDLOCK,HOLDLOCK)
        WHERE Codigo=N'RRHH' AND Activo=1) <> 1
        THROW 51001, 'Se requiere un único sistema RRHH activo.', 1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo=N'RRHH' AND Activo=1;
    IF (SELECT COUNT(*) FROM seguridad.Rol WITH (UPDLOCK,HOLDLOCK)
        WHERE SistemaID=@SistemaID AND Codigo=N'CONSULTA' AND Activo=1) <> 1
        THROW 51002, 'Se requiere un único rol CONSULTA activo de RRHH.', 1;
    SELECT @RolID=RolID FROM seguridad.Rol WHERE SistemaID=@SistemaID AND Codigo=N'CONSULTA' AND Activo=1;

    IF NOT EXISTS (SELECT 1 FROM seguridad.Usuario WITH (UPDLOCK,HOLDLOCK)
        WHERE UsuarioID=@UsuarioID AND PersonalID=@PersonalID
          AND NombreUsuario=@NombreUsuario AND Activo=1 AND Bloqueado=0)
        THROW 51003, 'La cuenta no coincide con el diagnóstico o cambió su estado. No se aplicó la reparación.', 1;

    IF EXISTS (SELECT 1 FROM seguridad.UsuarioSistema WITH (UPDLOCK,HOLDLOCK)
        WHERE UsuarioID=@UsuarioID AND SistemaID=@SistemaID)
    BEGIN
        IF (SELECT COUNT(*) FROM seguridad.UsuarioSistema WHERE UsuarioID=@UsuarioID AND SistemaID=@SistemaID) <> 1
            THROW 51004, 'Hay vínculos duplicados con RRHH. Requiere revisión.', 1;
        IF NOT EXISTS (SELECT 1 FROM seguridad.UsuarioSistema WHERE UsuarioID=@UsuarioID AND SistemaID=@SistemaID
            AND Activo=1 AND (FechaInicio IS NULL OR FechaInicio<=CAST(GETDATE() AS date))
            AND (FechaFin IS NULL OR FechaFin>=CAST(GETDATE() AS date)))
            THROW 51005, 'Existe un vínculo inactivo o fuera de vigencia. No se reactiva automáticamente.', 1;
    END
    ELSE
    BEGIN
        INSERT INTO seguridad.UsuarioSistema (UsuarioID,SistemaID,Activo,FechaInicio)
        VALUES (@UsuarioID,@SistemaID,1,CAST(GETDATE() AS date));
        SET @VinculoCreado=1;
    END;

    -- El diagnóstico no encontró roles: si aparecieron entretanto, revisar antes de modificarlos.
    IF EXISTS (SELECT 1 FROM seguridad.UsuarioRol WITH (UPDLOCK,HOLDLOCK)
        WHERE UsuarioID=@UsuarioID AND RolID<>@RolID)
        THROW 51006, 'La cuenta ahora tiene otros roles. Requiere un nuevo diagnóstico.', 1;
    IF EXISTS (SELECT 1 FROM seguridad.UsuarioRol WHERE UsuarioID=@UsuarioID AND RolID=@RolID)
    BEGIN
        IF (SELECT COUNT(*) FROM seguridad.UsuarioRol WHERE UsuarioID=@UsuarioID AND RolID=@RolID) <> 1
            THROW 51007, 'Hay asignaciones duplicadas del rol CONSULTA.', 1;
        IF NOT EXISTS (SELECT 1 FROM seguridad.UsuarioRol WHERE UsuarioID=@UsuarioID AND RolID=@RolID
            AND Activo=1 AND (FechaInicio IS NULL OR FechaInicio<=CAST(GETDATE() AS date))
            AND (FechaFin IS NULL OR FechaFin>=CAST(GETDATE() AS date)))
            THROW 51008, 'El rol existente no está vigente. No se reactiva automáticamente.', 1;
    END
    ELSE
    BEGIN
        INSERT INTO seguridad.UsuarioRol (UsuarioID,RolID,FechaInicio,Activo,FechaAsignacion)
        VALUES (@UsuarioID,@RolID,CAST(GETDATE() AS date),1,SYSDATETIME());
        SET @RolCreado=1;
    END;

    IF @VinculoCreado=1 OR @RolCreado=1
    BEGIN
        -- Actor NULL: ejecución manual; no se atribuye a un administrador inventado.
        INSERT INTO auditoria.EventoAuditoria
            (UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
        VALUES (NULL,@SistemaID,SYSDATETIME(),N'UPDATE',N'seguridad.Usuario',CAST(@UsuarioID AS varchar(100)),
            N'[SEGURIDAD] Reparación manual diagnosticada: vínculo RRHH y rol CONSULTA para cuenta existente. Sin cambio de contraseña.');
    END;
    COMMIT TRANSACTION;
    SELECT @UsuarioID AS UsuarioID,@SistemaID AS SistemaID,@RolID AS RolID,
           @VinculoCreado AS VinculoCreado,@RolCreado AS RolCreado;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
